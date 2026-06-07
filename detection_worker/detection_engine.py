"""Engine YOLO untuk deteksi APD — refactor dari deteksi.py."""

import logging
import os
import queue
import threading
import time
import uuid
from datetime import datetime
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

log = logging.getLogger(__name__)

# Screenshot storage path (shared volume dengan Laravel)
STORAGE_BASE = os.getenv("STORAGE_PATH", "/var/www/html/storage/app/public/violations")


class DetectionEngine:
    def __init__(self, config: dict):
        self.config     = config
        self._model     = None        # APD model (best_2.pt)
        self._person_model = None     # Person model (best.onnx)
        self._model_path = config.get("ai_model_path")

        # Person model: dari config kamera, lalu env, lalu tidak ada
        _env_person = os.getenv("PERSON_MODEL_PATH", "")
        self._person_model_path = config.get("person_model_path") or (_env_person if _env_person else None)
        self._person_model_cls  = int(os.getenv("PERSON_MODEL_CLASS_ID",
                                       config.get("person_model_class_id", 6)))

        # Class IDs dari konfigurasi — default sesuai model 7-kelas:
        # boots(0) helmet(1) no_boots(2) no_helmet(3) no_vest(4) person(5) vest(6)
        mapping               = config.get("class_mapping", {})
        self.person_id        = mapping.get("person_id", 5)
        self.helmet_ids       = mapping.get("helmet_ids", [1])
        self.vest_ids         = mapping.get("vest_ids", [6])
        self.boots_ids        = mapping.get("boots_ids", [0])
        self.no_helmet_ids    = mapping.get("no_helmet_ids", [3])
        self.no_vest_ids      = mapping.get("no_vest_ids", [4])
        self.no_boots_ids     = mapping.get("no_boots_ids", [2])

        self.det_size         = config.get("detection_size", 640)
        self.conf_thresh      = float(config.get("confidence_threshold", 0.25))
        self.boots_conf       = float(config.get("boots_confidence_threshold", 0.15))
        self.person_conf      = float(config.get("person_confidence_threshold", 0.20))
        self.skip_frame       = int(config.get("process_every_n_frame", 2))
        self.apd_lock_secs    = float(config.get("apd_lock_seconds", 3))
        self.auto_ss     = config.get("auto_screenshot", True)
        self.ss_cooldown = int(config.get("screenshot_cooldown", 30))

        # State
        self._device           = "cpu"
        self._frame_idx        = 0
        self._detected_workers = []
        self._last_ss_time     = {}
        self._worker_names     = {}

        # Background YOLO thread
        self._detect_queue   = queue.Queue(maxsize=1)
        self._pending_viols  = []
        self._viol_lock      = threading.Lock()
        self._detect_thread  = None
        self._detect_running = False

    def load_model(self) -> bool:
        if not self._model_path or not Path(self._model_path).exists():
            log.warning(f"Model tidak ditemukan: {self._model_path}")
            return False
        try:
            import torch
            from ultralytics import YOLO
            self._device = "cuda" if torch.cuda.is_available() else "cpu"

            # Load APD model
            self._model = YOLO(self._model_path, task="detect")
            dummy = np.zeros((320, 320, 3), dtype=np.uint8)
            self._model.predict(dummy, imgsz=320, conf=0.9, device=self._device, verbose=False)
            log.info(f"APD model dimuat: {self._model_path} | device={self._device.upper()}")

            # Load person model (opsional)
            if self._person_model_path and Path(self._person_model_path).exists():
                self._person_model = YOLO(self._person_model_path, task="detect")
                dummy640 = np.zeros((640, 640, 3), dtype=np.uint8)
                self._person_model.predict(dummy640, imgsz=640, conf=0.9,
                                           device=self._device, verbose=False)
                log.info(f"Person model dimuat: {self._person_model_path} (class={self._person_model_cls})")
            else:
                log.info("Person model tidak ditemukan — menggunakan APD model untuk deteksi orang")

            if self._device == "cuda":
                log.info(f"GPU: {torch.cuda.get_device_name(0)}")

            self._detect_running = True
            self._detect_thread  = threading.Thread(
                target=self._detect_loop, daemon=True,
                name=f"yolo-cam-{id(self)}"
            )
            self._detect_thread.start()
            return True
        except Exception as e:
            log.error(f"Gagal memuat model: {e}")
            return False

    def _detect_loop(self):
        """YOLO berjalan di thread terpisah — tidak memblokir frame loop."""
        while self._detect_running:
            try:
                frame = self._detect_queue.get(timeout=0.5)
            except queue.Empty:
                continue
            if self._model is None:
                continue
            workers, viols = self._run_detection(frame)
            self._detected_workers = workers
            if viols:
                with self._viol_lock:
                    self._pending_viols.extend(viols)

    def process_frame(self, frame: np.ndarray) -> tuple[np.ndarray, list]:
        """
        Non-blocking: kirim frame ke background YOLO thread, kembalikan
        anotasi dengan hasil deteksi terakhir yang tersedia.
        """
        self._frame_idx += 1

        # Kirim frame ke background thread (lewati jika thread sedang sibuk)
        if self._model is not None and self._frame_idx % self.skip_frame == 0:
            try:
                self._detect_queue.put_nowait(frame.copy())
            except queue.Full:
                pass

        # Ambil violations yang sudah dikumpulkan thread background
        with self._viol_lock:
            violations = list(self._pending_viols)
            self._pending_viols.clear()

        # Gambar anotasi dengan deteksi terakhir (cepat, tidak blokir)
        annotated = self._draw_annotations(frame, self._detected_workers)
        return annotated, violations

    @staticmethod
    def _make_apd_dict():
        return {
            "helmet": {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
            "vest":   {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
            "boots":  {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
        }

    def _run_detection(self, frame: np.ndarray) -> tuple[list, list]:
        boots_related = set(self.boots_ids) | set(self.no_boots_ids)
        current_workers = []
        temp_items = []

        if self._person_model is not None:
            # ── Dual-model: person dari best.onnx, APD dari best_2.pt ──────────
            try:
                p_res = self._person_model.predict(
                    frame, imgsz=640, conf=self.person_conf, iou=0.50,
                    classes=[self._person_model_cls],
                    max_det=20, device=self._device, verbose=False
                )[0]
            except Exception as e:
                log.error(f"Person model prediction error: {e}")
                return self._detected_workers, []

            current_workers = [
                {"box": list(map(int, b.xyxy[0])), "conf": float(b.conf[0]),
                 "frames_lost": 0, "apd": self._make_apd_dict()}
                for b in p_res.boxes if float(b.conf[0]) >= self.person_conf
            ]
            log.debug(f"[PERSON] {len(p_res.boxes)} raw → {len(current_workers)} workers")

            try:
                apd_res = self._model.predict(
                    frame, imgsz=self.det_size, conf=self.conf_thresh,
                    iou=0.50, max_det=50, device=self._device, verbose=False
                )[0]
            except Exception as e:
                log.error(f"APD model prediction error: {e}")
                apd_res = None

            if apd_res is not None:
                for box in apd_res.boxes:
                    cls = int(box.cls[0])
                    if cls == self.person_id:
                        continue  # Abaikan person dari APD model
                    conf = float(box.conf[0])
                    xyxy = list(map(int, box.xyxy[0]))
                    min_conf = self.boots_conf if cls in boots_related else self.conf_thresh
                    if conf >= min_conf:
                        temp_items.append({"cls": cls, "box": xyxy, "conf": conf})

        else:
            # ── Single-model fallback ─────────────────────────────────────────
            try:
                results = self._model.predict(
                    frame, imgsz=self.det_size, conf=self.person_conf,
                    iou=0.70, max_det=50, device=self._device, verbose=False
                )[0]
            except Exception as e:
                log.error(f"Prediction error: {e}")
                return self._detected_workers, []

            for box in results.boxes:
                cls  = int(box.cls[0])
                xyxy = list(map(int, box.xyxy[0]))
                conf = float(box.conf[0])
                if cls == self.person_id and conf >= self.person_conf:
                    current_workers.append({
                        "box": xyxy, "conf": conf, "frames_lost": 0,
                        "apd": self._make_apd_dict(),
                    })
                elif cls != self.person_id:
                    min_conf = self.boots_conf if cls in boots_related else self.conf_thresh
                    if conf >= min_conf:
                        temp_items.append({"cls": cls, "box": xyxy, "conf": conf})

        # Deduplication: buang box yang hampir identik (IoU > 0.70)
        if len(current_workers) > 1:
            current_workers.sort(key=lambda w: w["conf"], reverse=True)
            deduped = []
            for w in current_workers:
                if not any(self._iou_boxes(w["box"], kept["box"]) > 0.70 for kept in deduped):
                    deduped.append(w)
            current_workers = deduped

        # Asosiasi APD ke worker — pilih worker dengan jarak center terdekat
        for item in temp_items:
            ix1, iy1, ix2, iy2 = item["box"]
            item_cx = (ix1 + ix2) / 2
            item_cy = (iy1 + iy2) / 2
            is_boots = item["cls"] in boots_related

            best_w    = None
            best_dist = float('inf')

            for w in current_workers:
                wx1, wy1, wx2, wy2 = w["box"]
                wcx = (wx1 + wx2) / 2
                wcy = (wy1 + wy2) / 2
                ww  = wx2 - wx1
                wh  = wy2 - wy1

                margin_x = ww * 0.35
                # Helm sering di atas bounding box (model person = torso) — perluas jauh ke atas
                margin_y_top = wh * 0.6
                # Boots sering di bawah bounding box
                margin_y_bot = wh * 0.6 if is_boots else wh * 0.25

                if not (wx1 - margin_x  <= item_cx <= wx2 + margin_x and
                        wy1 - margin_y_top <= item_cy <= wy2 + margin_y_bot):
                    continue

                dist      = ((item_cx - wcx) ** 2 + (item_cy - wcy) ** 2) ** 0.5
                dist_norm = dist / (ww + wh + 1)
                if dist_norm < best_dist:
                    best_dist = dist_norm
                    best_w    = w

            if best_w is not None:
                cls, conf, ibox = item["cls"], item["conf"], item["box"]
                # Positive: APD dipakai → selalu override negatif; update positif jika conf lebih tinggi
                if cls in self.helmet_ids:
                    apd = best_w["apd"]["helmet"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["helmet"] = {"ok": True, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": False}
                elif cls in self.vest_ids:
                    apd = best_w["apd"]["vest"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["vest"]   = {"ok": True, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": False}
                elif cls in self.boots_ids:
                    apd = best_w["apd"]["boots"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["boots"]  = {"ok": True, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": False}
                # Negative: hanya berlaku jika tidak ada deteksi positif di frame ini
                elif cls in self.no_helmet_ids and not best_w["apd"]["helmet"]["ok"]:
                    best_w["apd"]["helmet"] = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}
                elif cls in self.no_vest_ids and not best_w["apd"]["vest"]["ok"]:
                    best_w["apd"]["vest"]   = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}
                elif cls in self.no_boots_ids and not best_w["apd"]["boots"]["ok"]:
                    best_w["apd"]["boots"]  = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}

        # ── APD Lock: pertahankan status OK selama apd_lock_secs detik ──────────
        now = time.time()
        for w in current_workers:
            # Cari worker sebelumnya yang paling cocok berdasarkan IoU
            best_prev, best_iou = None, 0.25
            for prev in self._detected_workers:
                iou = self._iou_boxes(w["box"], prev["box"])
                if iou > best_iou:
                    best_iou, best_prev = iou, prev

            for apd_key in ("helmet", "vest", "boots"):
                apd = w["apd"][apd_key]
                if apd["ok"]:
                    # Terdeteksi OK sekarang → perbarui timestamp lock
                    apd["last_ok"] = now
                elif best_prev and not apd.get("explicit_no", False):
                    # Tidak terdeteksi dan tidak ada negatif eksplisit → cek grace period
                    prev_apd     = best_prev["apd"][apd_key]
                    prev_last_ok = prev_apd.get("last_ok", 0.0)
                    if now - prev_last_ok < self.apd_lock_secs:
                        # Masih dalam grace period → pertahankan OK
                        apd["ok"]      = True
                        apd["conf"]    = prev_apd.get("conf", 0.0)
                        apd["last_ok"] = prev_last_ok
                        apd["box"]     = prev_apd.get("box")

        if current_workers:
            # Pertahankan urutan stabil via IoU matching ke frame sebelumnya
            current_workers = self._match_to_previous(current_workers)
            detected = current_workers
        else:
            # Tidak ada yang terdeteksi — pertahankan worker terakhir sesaat (anti-flicker)
            for w in self._detected_workers:
                w["frames_lost"] = w.get("frames_lost", 0) + 1
            detected = [w for w in self._detected_workers if w["frames_lost"] < 6]

        # Build violations list
        violations = []
        now = time.time()
        for i, w in enumerate(current_workers):
            missing = []
            if not w["apd"]["helmet"]["ok"]: missing.append("helmet")
            if not w["apd"]["vest"]["ok"]:   missing.append("vest")
            if not w["apd"]["boots"]["ok"]:  missing.append("boots")

            if missing:
                last_viol = self._last_ss_time.get(i, 0)
                if (now - last_viol) >= self.ss_cooldown:
                    self._last_ss_time[i] = now
                    # Screenshot opsional — violation tetap di-generate meski auto_ss=False
                    screenshot_path = None
                    if self.auto_ss:
                        screenshot_path = self._save_screenshot(frame, w["box"])
                    violations.append({
                        "worker_idx":    i,
                        "bbox":          w["box"],
                        "missing_apd":   missing,
                        "violation_type": "no_" + "_no_".join(missing) if len(missing) == 1 else "multiple",
                        "confidence":    max(w["conf"], 0.50),
                        "image_path":    screenshot_path or "",
                        "detected_at":   datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    })

        return detected, violations

    @staticmethod
    def _iou_boxes(a: list, b: list) -> float:
        ix1 = max(a[0], b[0]); iy1 = max(a[1], b[1])
        ix2 = min(a[2], b[2]); iy2 = min(a[3], b[3])
        inter = max(0, ix2 - ix1) * max(0, iy2 - iy1)
        if inter == 0:
            return 0.0
        union = (a[2]-a[0])*(a[3]-a[1]) + (b[2]-b[0])*(b[3]-b[1]) - inter
        return inter / (union + 1e-6)

    def _draw_annotations(self, frame: np.ndarray, workers: list) -> np.ndarray:
        fh, fw = frame.shape[:2]

        # Satu pass overlay untuk semua worker (hemat frame.copy())
        if workers:
            ov = frame.copy()
            for w in workers:
                x1, y1, x2, y2 = w["box"]
                apd    = w["apd"]
                all_ok = apd["helmet"]["ok"] and apd["vest"]["ok"] and apd["boots"]["ok"]
                cv2.rectangle(ov, (x1, y1), (x2, y2),
                              (0, 40, 0) if all_ok else (0, 0, 60), -1)
            cv2.addWeighted(ov, 0.10, frame, 0.90, 0, frame)

        for i, w in enumerate(workers):
            x1, y1, x2, y2 = [max(0, min(v, fw if j % 2 == 0 else fh))
                               for j, v in enumerate(w["box"])]
            apd = w["apd"]
            bw, bh = x2 - x1, y2 - y1

            all_ok  = apd["helmet"]["ok"] and apd["vest"]["ok"] and apd["boots"]["ok"]
            missing = [k for k, v in apd.items() if not v["ok"]]
            box_clr = (0, 210, 80) if all_ok else (30, 60, 255)

            # ── Corner markers (L-shape) ─────────────────────────────────────
            c = min(bw, bh) // 6
            pts = [(x1,y1,1,0), (x1,y1,0,1), (x2,y1,-1,0), (x2,y1,0,1),
                   (x1,y2,1,0), (x1,y2,0,-1), (x2,y2,-1,0), (x2,y2,0,-1)]
            for px,py,dx,dy in pts:
                cv2.line(frame, (px, py), (px+dx*c, py+dy*c), box_clr, 2, cv2.LINE_AA)

            # ── Worker ID badge (top-left corner, with name if recognised) ──
            name  = self._worker_names.get(i, "")
            badge = f"WORKER {i+1}" + (f" : {name}" if name else "")
            (bw2, bh2), _ = cv2.getTextSize(badge, cv2.FONT_HERSHEY_SIMPLEX, 0.48, 1)
            cv2.rectangle(frame, (x1, y1 - bh2 - 8), (x1 + bw2 + 10, y1), box_clr, -1)
            cv2.putText(frame, badge, (x1 + 5, y1 - 5),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.48, (255, 255, 255), 1, cv2.LINE_AA)

            # ── APD status panel (right of box, or inside if no room) ────────
            PW, ROW = 108, 23
            px = x2 + 6 if x2 + 6 + PW <= fw else max(0, x1 - PW - 6)
            py = y1
            ph = len(apd) * ROW + 10

            # panel background (solid, tanpa copy untuk performa)
            cv2.rectangle(frame, (px, py), (px + PW, py + ph), (18, 18, 18), -1)
            cv2.rectangle(frame, (px, py), (px + PW, py + ph), box_clr, 1)

            apd_rows = [("H", "Helmet", apd["helmet"]),
                        ("V", "Vest",   apd["vest"]),
                        ("B", "Boots",  apd["boots"])]
            for j, (code, name, data) in enumerate(apd_rows):
                ry  = py + 5 + j * ROW
                clr = (0, 210, 80) if data["ok"] else (50, 80, 255)
                sym = "✓" if data["ok"] else "✗"   # ✓ / ✗ fallback:
                sym = "OK" if data["ok"] else "!!"

                cv2.rectangle(frame, (px+3, ry+3), (px+7, ry+ROW-5), clr, -1)
                cv2.putText(frame, f"{code}  {sym}", (px+11, ry+15),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.42, clr, 1, cv2.LINE_AA)
                if data["conf"] > 0:
                    cv2.putText(frame, f"{data['conf']:.0%}", (px+68, ry+15),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.38, (130,130,130), 1, cv2.LINE_AA)

            # ── Missing APD banner (bottom of box) ───────────────────────────
            if missing:
                miss_str = "MISS: " + " | ".join(m.upper() for m in missing)
                (tw, th), _ = cv2.getTextSize(miss_str, cv2.FONT_HERSHEY_SIMPLEX, 0.44, 1)
                by1, by2 = y2 + 2, y2 + th + 10
                if by2 < fh:
                    cv2.rectangle(frame, (x1, by1), (x1 + tw + 10, by2), (20, 20, 200), -1)
                    cv2.putText(frame, miss_str, (x1 + 5, by2 - 4),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.44, (255,255,255), 1, cv2.LINE_AA)

        # ── Top HUD bar ──────────────────────────────────────────────────────
        cv2.rectangle(frame, (0, 0), (fw, 48), (12, 12, 12), -1)

        # status dot
        dot_clr = (0, 210, 80) if workers else (80, 80, 80)
        cv2.circle(frame, (14, 24), 5, dot_clr, -1, cv2.LINE_AA)

        cv2.putText(frame, "K3 MONITOR", (26, 20),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.52, (220,220,220), 1, cv2.LINE_AA)
        count_str = f"{len(workers)} WORKER{'S' if len(workers) != 1 else ''}"
        cv2.putText(frame, count_str, (26, 38),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.40, (120,120,120), 1, cv2.LINE_AA)

        ts = datetime.now().strftime("%H:%M:%S")
        (tw, _), _ = cv2.getTextSize(ts, cv2.FONT_HERSHEY_SIMPLEX, 0.50, 1)
        cv2.putText(frame, ts, (fw - tw - 10, 30),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.50, (160,160,160), 1, cv2.LINE_AA)

        return frame

    def _iou(self, a: list, b: list) -> float:
        ix1, iy1 = max(a[0], b[0]), max(a[1], b[1])
        ix2, iy2 = min(a[2], b[2]), min(a[3], b[3])
        inter = max(0, ix2 - ix1) * max(0, iy2 - iy1)
        if inter == 0:
            return 0.0
        area_a = (a[2] - a[0]) * (a[3] - a[1])
        area_b = (b[2] - b[0]) * (b[3] - b[1])
        return inter / (area_a + area_b - inter)

    def _match_to_previous(self, current: list) -> list:
        """Pertahankan urutan index worker dari frame sebelumnya via IoU.
        Mencegah Worker 1 dan Worker 2 bertukar tempat antar frame."""
        prev = self._detected_workers
        if not prev:
            return current

        n_c, n_p = len(current), len(prev)
        matched  = {}   # prev_idx -> curr worker
        used_c   = set()

        for pi in range(n_p):
            best_iou, best_ci = 0.3, -1
            for ci in range(n_c):
                if ci in used_c:
                    continue
                iou = self._iou(current[ci]["box"], prev[pi]["box"])
                if iou > best_iou:
                    best_iou, best_ci = iou, ci
            if best_ci >= 0:
                matched[pi] = current[best_ci]
                used_c.add(best_ci)

        # Susun ulang: slot dari prev dulu, sisanya append
        result = []
        for pi in range(max(n_c, n_p)):
            if pi in matched:
                result.append(matched[pi])
        for ci in range(n_c):
            if ci not in used_c:
                result.append(current[ci])
        return result[:n_c]

    def _save_screenshot(self, frame: np.ndarray, bbox: list) -> Optional[str]:
        try:
            date_dir = datetime.now().strftime("%Y-%m-%d")
            save_dir = Path(STORAGE_BASE) / date_dir
            save_dir.mkdir(parents=True, exist_ok=True)
            filename = f"{uuid.uuid4().hex}.jpg"
            filepath = save_dir / filename
            cv2.imwrite(str(filepath), frame)
            return f"violations/{date_dir}/{filename}"
        except Exception as e:
            log.error(f"Screenshot gagal: {e}")
            return None

    def get_latest_frame(self) -> Optional[np.ndarray]:
        return None  # Diisi oleh CameraInstance

    def unload(self):
        self._detect_running = False
        if self._detect_thread and self._detect_thread.is_alive():
            self._detect_thread.join(timeout=2)
        self._model = None
        self._person_model = None
        self._detected_workers = []
        self._last_ss_time = {}
