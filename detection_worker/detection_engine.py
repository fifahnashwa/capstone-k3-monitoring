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
        try:
            from dotenv import load_dotenv
            load_dotenv(override=True)
        except ImportError:
            pass

        self.config     = config
        self._model      = None   # APD model (.pt) via ultralytics
        self._onnx_sess  = None   # Person model (best.onnx) via onnxruntime
        self._apd_sess   = None   # APD model (.onnx) via onnxruntime langsung
        self._model_path = config.get("ai_model_path")

        # Person model path: dari config kamera, lalu env var
        _env_person = os.getenv("PERSON_MODEL_PATH", "")
        self._person_model_path = config.get("person_model_path") or (_env_person or None)
        self._person_model_cls  = int(os.getenv("PERSON_MODEL_CLASS_ID",
                                       config.get("person_model_class_id", 5)))

        # Class IDs dari konfigurasi
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
        self.conf_thresh      = float(config.get("confidence_threshold")       or 0.25)
        self.boots_conf       = float(config.get("boots_confidence_threshold")  or 0.48)
        self.person_conf      = float(config.get("person_confidence_threshold") or 0.10)
        # skip_frame=1: proses setiap frame agar orang baru langsung terdeteksi
        self.skip_frame       = int(config.get("process_every_n_frame", 1))
        self.apd_lock_secs    = float(config.get("apd_lock_seconds", 1.0))
        self.auto_ss     = config.get("auto_screenshot", True)
        self.ss_cooldown = int(config.get("screenshot_cooldown", 30))

        # State
        self._device           = "cpu"
        self._apd_nms_fmt      = False   # True = output (N,6) NMS; False = (4+nc,anchors) raw
        self._apd_infer_size   = self.det_size  # ukuran input yg digunakan model ONNX
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

        # MOSSE tracker ringan
        self._yolo_ver        = 0
        self._last_yolo_ver   = -1
        self._mosse_trackers  = []
        self._mosse_workers   = []


    def load_model(self) -> bool:
        if not self._model_path or not Path(self._model_path).exists():
            log.warning(f"Model tidak ditemukan: {self._model_path}")
            return False

        is_onnx = self._model_path.lower().endswith(".onnx")

        if is_onnx:
            # ── APD model via onnxruntime (.onnx) — tanpa torch/ultralytics ──────
            try:
                import onnxruntime as ort
                providers = ["CUDAExecutionProvider", "CPUExecutionProvider"]
                sess = ort.InferenceSession(self._model_path, providers=providers)

                # Deteksi ukuran input wajib dari metadata model
                inp_shape = sess.get_inputs()[0].shape  # e.g. [1,3,640,640] atau [batch,3,H,W]
                h = inp_shape[2]; w = inp_shape[3]
                # Jika dimensi fixed integer → gunakan itu; jika dynamic string → pakai det_size
                self._apd_infer_size = h if isinstance(h, int) else self.det_size

                # Warmup dengan ukuran yang benar
                _dummy = np.zeros((1, 3, self._apd_infer_size, self._apd_infer_size), dtype=np.float32)
                _out   = sess.run(None, {"images": _dummy})[0]

                # Auto-deteksi format output:
                #   NMS format : (batch, N, 6)    → last dim = 6 = [x1,y1,x2,y2,score,cls_id]
                #   raw format : (batch, 4+nc, anchors)
                self._apd_nms_fmt = (_out.ndim == 3 and _out.shape[2] == 6)

                self._apd_sess = sess   # set SETELAH warmup berhasil
                used_prov = sess.get_providers()[0]
                self._device = "cuda" if "CUDA" in used_prov else "cpu"
                fmt_str = f"NMS({_out.shape[1]}×6)" if self._apd_nms_fmt \
                          else f"raw({_out.shape[1]}×{_out.shape[2]})"
                log.info(f"APD ONNX dimuat: {self._model_path} | "
                         f"infer_size={self._apd_infer_size} | fmt={fmt_str} | provider={used_prov}")
            except Exception as e:
                log.error(f"Gagal memuat APD ONNX model: {e}")
                self._apd_sess = None
                return False
        else:
            # ── APD model via ultralytics (.pt) ───────────────────────────────
            try:
                import torch
                from ultralytics import YOLO
                self._device = "cuda" if torch.cuda.is_available() else "cpu"
                self._model = YOLO(self._model_path, task="detect")
                dummy = np.zeros((640, 640, 3), dtype=np.uint8)
                self._model.predict(dummy, imgsz=640, conf=0.9, device=self._device, verbose=False)
                log.info(f"APD model dimuat: {self._model_path} | device={self._device.upper()}")
                if self._device == "cuda":
                    log.info(f"GPU: {torch.cuda.get_device_name(0)}")
            except Exception as e:
                log.error(f"Gagal memuat APD model: {e}")
                return False

            # ── Person ONNX model (opsional, hanya untuk mode .pt) ────────────
            if self._person_model_path and Path(self._person_model_path).exists():
                try:
                    import onnxruntime as ort
                    self._onnx_sess = ort.InferenceSession(
                        self._person_model_path,
                        providers=["CPUExecutionProvider"],
                    )
                    _dummy = np.zeros((1, 3, 640, 640), dtype=np.float32)
                    self._onnx_sess.run(None, {"images": _dummy})
                    log.info(f"Person ONNX dimuat: {self._person_model_path} "
                             f"(class={self._person_model_cls})")
                except Exception as e:
                    log.warning(f"Person ONNX gagal — fallback ke APD model saja: {e}")
                    self._onnx_sess = None
            else:
                log.info("Person ONNX tidak dikonfigurasi — deteksi orang dari APD model")

        # ── Start background detection thread ────────────────────────────────
        self._detect_running = True
        self._detect_thread  = threading.Thread(
            target=self._detect_loop, daemon=True,
            name=f"yolo-cam-{id(self)}"
        )
        self._detect_thread.start()
        return True

    def _detect_loop(self):
        """ONNX/YOLO berjalan di thread terpisah — tidak memblokir frame loop."""
        while self._detect_running:
            try:
                frame = self._detect_queue.get(timeout=0.5)
            except queue.Empty:
                continue
            if self._model is None and self._apd_sess is None:
                continue
            workers, viols = self._run_detection(frame)
            self._detected_workers = workers
            self._yolo_ver += 1
            if viols:
                with self._viol_lock:
                    self._pending_viols.extend(viols)

    def process_frame(self, frame: np.ndarray) -> tuple[np.ndarray, list]:
        """Non-blocking: kirim frame ke background detection, update MOSSE tracker tiap frame."""
        self._frame_idx += 1

        if (self._model is not None or self._apd_sess is not None) \
                and self._frame_idx % self.skip_frame == 0:
            # Selalu ganti frame lama di queue dengan frame terbaru agar orang baru
            # langsung diproses YOLO tanpa menunggu frame lama selesai.
            frame_copy = frame.copy()
            if self._detect_queue.full():
                try:
                    self._detect_queue.get_nowait()
                except queue.Empty:
                    pass
            try:
                self._detect_queue.put_nowait(frame_copy)
            except queue.Full:
                pass

        with self._viol_lock:
            violations = list(self._pending_viols)
            self._pending_viols.clear()

        if self.skip_frame == 1:
            # YOLO jalan tiap frame — MOSSE tidak diperlukan, justru bikin stuttering
            annotated = self._draw_annotations(frame, self._detected_workers)
        else:
            # YOLO jalan setiap N frame — MOSSE tracking antar frame
            if self._yolo_ver != self._last_yolo_ver:
                self._last_yolo_ver = self._yolo_ver
                self._mosse_init(frame, self._detected_workers)
            else:
                self._mosse_update(frame)
            annotated = self._draw_annotations(frame, self._mosse_workers)

        return annotated, violations

    def _mosse_init(self, frame: np.ndarray, workers: list) -> None:
        """Incremental: reuse tracker untuk worker lama, buat baru untuk orang baru."""
        fh, fw = frame.shape[:2]

        # Cocokkan worker baru ke tracker lama via IoU
        used_old: set = set()
        matched: dict = {}  # new_idx -> old_idx
        for ni, nw in enumerate(workers):
            best_iou, best_oi = 0.25, -1
            for oi, ow in enumerate(self._mosse_workers):
                if oi in used_old:
                    continue
                iou = self._iou_boxes(nw["box"], ow["box"])
                if iou > best_iou:
                    best_iou, best_oi = iou, oi
            if best_oi >= 0:
                matched[ni] = best_oi
                used_old.add(best_oi)

        new_trackers: list = []
        new_workers:  list = []

        for ni, nw in enumerate(workers):
            wc = {k: (dict(v) if isinstance(v, dict) else v) for k, v in nw.items()}
            if ni in matched:
                # Pertahankan tracker lama — tidak reinit
                new_trackers.append(self._mosse_trackers[matched[ni]])
            else:
                # Orang baru — buat MOSSE tracker sekarang juga
                x1 = max(0, nw["box"][0]); y1 = max(0, nw["box"][1])
                x2 = min(fw - 1, nw["box"][2]); y2 = min(fh - 1, nw["box"][3])
                if x2 - x1 < 16 or y2 - y1 < 16:
                    new_trackers.append(None)
                else:
                    tr = None
                    for fn in (lambda: cv2.legacy.TrackerMOSSE_create(),
                               lambda: cv2.TrackerMOSSE_create()):
                        try:
                            tr = fn(); break
                        except AttributeError:
                            pass
                    if tr is not None:
                        try:
                            tr.init(frame, (x1, y1, x2 - x1, y2 - y1))
                            new_trackers.append([tr, 0])
                        except Exception:
                            new_trackers.append(None)
                    else:
                        new_trackers.append(None)
            new_workers.append(wc)

        self._mosse_trackers = new_trackers
        self._mosse_workers  = new_workers

    def _mosse_update(self, frame: np.ndarray) -> None:
        new_tr, new_w = [], []
        for entry, w in zip(self._mosse_trackers, self._mosse_workers):
            if entry is None:
                new_tr.append(None); new_w.append(w); continue
            tr, lost = entry
            try:
                ok, (tx, ty, tw_, th) = tr.update(frame)
            except Exception:
                ok = False
            if ok:
                wc = {k: (dict(v) if isinstance(v, dict) else v) for k, v in w.items()}
                wc["box"] = [int(tx), int(ty), int(tx+tw_), int(ty+th)]
                new_tr.append([tr, 0]); new_w.append(wc)
            else:
                if lost + 1 < 5:   # hapus box setelah MOSSE gagal 5 frame berturut-turut
                    new_tr.append([tr, lost+1]); new_w.append(w)
        self._mosse_trackers = new_tr
        self._mosse_workers  = new_w

    @staticmethod
    def _make_apd_dict():
        return {
            "helmet": {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
            "vest":   {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
            "boots":  {"ok": False, "conf": 0.0, "last_ok": 0.0, "box": None, "explicit_no": False},
        }

    @staticmethod
    def _letterbox_img(img: np.ndarray, new_size: int):
        """Letterbox resize. Returns (resized_img, ratio, (pad_left, pad_top))."""
        h, w = img.shape[:2]
        r = min(new_size / h, new_size / w)
        nw, nh = int(round(w * r)), int(round(h * r))
        img = cv2.resize(img, (nw, nh), interpolation=cv2.INTER_LINEAR)
        pl = (new_size - nw) / 2;  pt = (new_size - nh) / 2
        top    = int(round(pt - 0.1));  bottom = int(round(pt + 0.1))
        left   = int(round(pl - 0.1));  right  = int(round(pl + 0.1))
        img = cv2.copyMakeBorder(img, top, bottom, left, right,
                                 cv2.BORDER_CONSTANT, value=(114, 114, 114))
        return img, r, (left, top)

    @staticmethod
    def _nms(boxes: np.ndarray, scores: np.ndarray, iou_thr: float = 0.45) -> list:
        """Vectorized NMS. boxes: (N,4) x1y1x2y2. Returns list of kept indices."""
        if len(boxes) == 0:
            return []
        x1, y1, x2, y2 = boxes[:, 0], boxes[:, 1], boxes[:, 2], boxes[:, 3]
        areas  = np.maximum(0, x2 - x1) * np.maximum(0, y2 - y1)
        order  = scores.argsort()[::-1]
        keep   = []
        while order.size > 0:
            i = order[0]; keep.append(int(i))
            if order.size == 1:
                break
            rest = order[1:]
            xx1  = np.maximum(x1[i], x1[rest]); yy1 = np.maximum(y1[i], y1[rest])
            xx2  = np.minimum(x2[i], x2[rest]); yy2 = np.minimum(y2[i], y2[rest])
            inter = np.maximum(0, xx2 - xx1) * np.maximum(0, yy2 - yy1)
            iou   = inter / (areas[i] + areas[rest] - inter + 1e-6)
            order = rest[iou <= iou_thr]
        return keep

    def _infer_all_onnx(self, frame: np.ndarray) -> tuple[list, list]:
        """Inference APD ONNX: satu model untuk persons + semua item APD.
        Handle dua format:
          raw  (1, 4+nc, anchors): YOLO standard tanpa NMS
          NMS  (1, N, 6)         : [x1,y1,x2,y2,score,class_id] sudah di-NMS
        Returns: (persons: [(box, conf)], apd_items: [{"cls","box","conf"}])
        """
        fh, fw    = frame.shape[:2]
        sz        = self._apd_infer_size   # ukuran yang cocok dengan model
        boots_rel = set(self.boots_ids) | set(self.no_boots_ids)

        img, ratio, (pl, pt) = self._letterbox_img(frame, sz)
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB).astype(np.float32) / 255.0
        inp     = np.ascontiguousarray(img_rgb.transpose(2, 0, 1)[np.newaxis])

        try:
            raw = self._apd_sess.run(None, {"images": inp})[0]
        except Exception as e:
            log.error(f"APD ONNX inference error: {e}"); return [], []

        if self._apd_nms_fmt:
            # ── Format NMS: (1, N, 6) = [x1, y1, x2, y2, score, class_id] ──────
            pred      = raw[0]                          # (N, 6)
            scores    = pred[:, 4]
            class_ids = pred[:, 5].round().astype(int)
            x1 = np.clip((pred[:, 0] - pl) / ratio, 0, fw)
            y1 = np.clip((pred[:, 1] - pt) / ratio, 0, fh)
            x2 = np.clip((pred[:, 2] - pl) / ratio, 0, fw)
            y2 = np.clip((pred[:, 3] - pt) / ratio, 0, fh)
            boxes_xyxy = np.stack([x1, y1, x2, y2], axis=1)
            confs      = scores
            min_thr    = min(self.person_conf, self.boots_conf)
            mask       = confs >= min_thr
            boxes_xyxy = boxes_xyxy[mask]; confs = confs[mask]; class_ids = class_ids[mask]
        else:
            # ── Format raw: (1, 4+nc, anchors) → transpose → (anchors, 4+nc) ────
            pred       = raw[0].T                          # (anchors, 4+nc)
            bboxes     = pred[:, :4]                       # cx, cy, w, h
            cls_scores = pred[:, 4:]                       # (anchors, nc)
            class_ids  = np.argmax(cls_scores, axis=1)
            confs      = cls_scores[np.arange(len(cls_scores)), class_ids]
            min_thr    = min(self.person_conf, self.boots_conf)
            mask       = confs >= min_thr
            if not np.any(mask):
                return [], []
            bboxes = bboxes[mask]; confs = confs[mask]; class_ids = class_ids[mask]
            # cx,cy,w,h → x1,y1,x2,y2, undo letterbox
            x1 = np.clip((bboxes[:, 0] - bboxes[:, 2] / 2 - pl) / ratio, 0, fw)
            y1 = np.clip((bboxes[:, 1] - bboxes[:, 3] / 2 - pt) / ratio, 0, fh)
            x2 = np.clip((bboxes[:, 0] + bboxes[:, 2] / 2 - pl) / ratio, 0, fw)
            y2 = np.clip((bboxes[:, 1] + bboxes[:, 3] / 2 - pt) / ratio, 0, fh)
            boxes_xyxy = np.stack([x1, y1, x2, y2], axis=1)
            keep       = self._nms(boxes_xyxy, confs, iou_thr=0.45)
            boxes_xyxy = boxes_xyxy[keep]; confs = confs[keep]; class_ids = class_ids[keep]

        persons   = []
        apd_items = []
        for i in range(len(class_ids)):
            cls  = int(class_ids[i]); conf = float(confs[i])
            box  = [int(boxes_xyxy[i, 0]), int(boxes_xyxy[i, 1]),
                    int(boxes_xyxy[i, 2]), int(boxes_xyxy[i, 3])]
            if cls == self.person_id:
                if conf >= self.person_conf:
                    persons.append((box, conf))
            else:
                thr = self.boots_conf if cls in boots_rel else self.conf_thresh
                if conf >= thr:
                    apd_items.append({"cls": cls, "box": box, "conf": conf})

        return persons, apd_items

    def _infer_persons_onnx(self, frame: np.ndarray) -> list:
        """Deteksi orang via onnxruntime (person model terpisah). Output: [(box, conf)]."""
        fh, fw = frame.shape[:2]
        img = cv2.resize(frame, (640, 640))
        img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB).astype(np.float32) / 255.0
        inp = np.ascontiguousarray(img.transpose(2, 0, 1)[np.newaxis])
        try:
            outputs = self._onnx_sess.run(None, {"images": inp})[0]  # (1, 300, 6)
        except Exception as e:
            log.error(f"ONNX inference error: {e}")
            return []
        dets = outputs[0]
        sx, sy = fw / 640.0, fh / 640.0
        persons = []
        for d in dets:
            score = float(d[4]); cls_id = int(round(float(d[5])))
            if score < self.person_conf or cls_id != self._person_model_cls:
                continue
            x1 = max(0, int(d[0] * sx)); y1 = max(0, int(d[1] * sy))
            x2 = min(fw, int(d[2] * sx)); y2 = min(fh, int(d[3] * sy))
            if x2 > x1 and y2 > y1:
                persons.append(([x1, y1, x2, y2], score))
        return persons

    def _run_detection(self, frame: np.ndarray) -> tuple[list, list]:
        boots_related = set(self.boots_ids) | set(self.no_boots_ids)
        current_workers = []
        temp_items = []

        if self._apd_sess is not None:
            # ── Full ONNX: satu model untuk person + semua APD ───────────────
            persons, temp_items = self._infer_all_onnx(frame)
            current_workers = [
                {"box": box, "conf": conf, "frames_lost": 0, "apd": self._make_apd_dict()}
                for box, conf in persons
            ]
            log.debug(f"[APD-ONNX] {len(current_workers)} person(s), {len(temp_items)} item(s)")

        elif self._onnx_sess is not None:
            # ── Dual-model: person dari best.onnx + APD dari ultralytics ─────
            person_boxes = self._infer_persons_onnx(frame)
            current_workers = [
                {"box": box, "conf": conf, "frames_lost": 0, "apd": self._make_apd_dict()}
                for box, conf in person_boxes
            ]
            try:
                apd_res = self._model.predict(
                    frame, imgsz=self.det_size, conf=self.conf_thresh,
                    iou=0.50, max_det=50, device=self._device, verbose=False
                )[0]
            except Exception as e:
                log.error(f"APD model prediction error: {e}"); apd_res = None
            if apd_res is not None:
                for box in apd_res.boxes:
                    cls = int(box.cls[0])
                    if cls == self.person_id: continue
                    conf = float(box.conf[0]); xyxy = list(map(int, box.xyxy[0]))
                    min_conf = self.boots_conf if cls in boots_related else self.conf_thresh
                    if conf >= min_conf:
                        temp_items.append({"cls": cls, "box": xyxy, "conf": conf})

        else:
            # ── Single ultralytics model (.pt) ────────────────────────────────
            try:
                results = self._model.predict(
                    frame, imgsz=self.det_size, conf=self.person_conf,
                    iou=0.45, max_det=50, device=self._device, verbose=False
                )[0]
            except Exception as e:
                log.error(f"Prediction error: {e}")
                return self._detected_workers, []
            for box in results.boxes:
                cls  = int(box.cls[0]); xyxy = list(map(int, box.xyxy[0])); conf = float(box.conf[0])
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

        # Asosiasi APD ke worker — jarak center terdekat
        for item in temp_items:
            ix1, iy1, ix2, iy2 = item["box"]
            item_cx = (ix1 + ix2) / 2; item_cy = (iy1 + iy2) / 2
            is_boots = item["cls"] in boots_related
            best_w = None; best_dist = float('inf')
            for w in current_workers:
                wx1, wy1, wx2, wy2 = w["box"]
                wcx = (wx1 + wx2) / 2; wcy = (wy1 + wy2) / 2
                ww = wx2 - wx1; wh = wy2 - wy1
                margin_x = ww * 0.35
                margin_y_top = wh * 0.6
                margin_y_bot = wh * 0.6 if is_boots else wh * 0.25
                if not (wx1 - margin_x <= item_cx <= wx2 + margin_x and
                        wy1 - margin_y_top <= item_cy <= wy2 + margin_y_bot):
                    continue
                dist = ((item_cx - wcx) ** 2 + (item_cy - wcy) ** 2) ** 0.5
                dist_norm = dist / (ww + wh + 1)
                if dist_norm < best_dist:
                    best_dist = dist_norm; best_w = w
            if best_w is not None:
                cls, conf, ibox = item["cls"], item["conf"], item["box"]
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
                elif cls in self.no_helmet_ids:
                    apd = best_w["apd"]["helmet"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["helmet"] = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}
                elif cls in self.no_vest_ids:
                    apd = best_w["apd"]["vest"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["vest"]   = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}
                elif cls in self.no_boots_ids:
                    apd = best_w["apd"]["boots"]
                    if not apd["ok"] or conf > apd["conf"]:
                        best_w["apd"]["boots"]  = {"ok": False, "conf": conf, "box": ibox, "last_ok": 0.0, "explicit_no": True}

        # APD Lock: pertahankan status OK selama apd_lock_secs
        now = time.time()
        for w in current_workers:
            best_prev, best_iou = None, 0.25
            for prev in self._detected_workers:
                iou = self._iou_boxes(w["box"], prev["box"])
                if iou > best_iou:
                    best_iou, best_prev = iou, prev
            for apd_key in ("helmet", "vest", "boots"):
                apd = w["apd"][apd_key]
                if apd["ok"]:
                    apd["last_ok"] = now
                elif best_prev and not apd.get("explicit_no", False):
                    prev_apd     = best_prev["apd"][apd_key]
                    prev_last_ok = prev_apd.get("last_ok", 0.0)
                    if now - prev_last_ok < self.apd_lock_secs:
                        apd["ok"] = True; apd["conf"] = prev_apd.get("conf", 0.0)
                        apd["last_ok"] = prev_last_ok; apd["box"] = prev_apd.get("box")

        if current_workers:
            current_workers = self._match_to_previous(current_workers)
            for w in current_workers:
                w["frames_lost"] = 0
            detected = current_workers
        else:
            # Pertahankan worker lama sampai 10 frame saat YOLO tidak mendeteksi
            for w in self._detected_workers:
                w["frames_lost"] = w.get("frames_lost", 0) + 1
            detected = [w for w in self._detected_workers if w["frames_lost"] < 3]

        # Build violations list
        violations = []
        now = time.time()
        for i, w in enumerate(current_workers):
            # ── APD violations ────────────────────────────────────────────────
            missing = []
            if not w["apd"]["helmet"]["ok"]: missing.append("helmet")
            if not w["apd"]["vest"]["ok"]:   missing.append("vest")
            if not w["apd"]["boots"]["ok"]:  missing.append("boots")
            if missing:
                last_viol = self._last_ss_time.get(i, 0)
                if (now - last_viol) >= self.ss_cooldown:
                    self._last_ss_time[i] = now
                    screenshot_path = None
                    if self.auto_ss:
                        screenshot_path = self._save_screenshot(frame, w["box"])
                    violations.append({
                        "worker_idx":    i,
                        "bbox":          w["box"],
                        "missing_apd":   missing,
                        "violation_type": "apd",
                        "confidence":    max(w["conf"], 0.50),
                        "image_path":    screenshot_path or "",
                        "detected_at":   datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    })

            # ── Discipline violation (person terdeteksi — Laravel cek shift) ──
            disc_key = f"disc_{i}"
            last_disc = self._last_ss_time.get(disc_key, 0)
            if (now - last_disc) >= 300:   # cooldown 5 menit
                self._last_ss_time[disc_key] = now
                screenshot_path = None
                if self.auto_ss:
                    screenshot_path = self._save_screenshot(frame, w["box"])
                violations.append({
                    "worker_idx":    i,
                    "bbox":          w["box"],
                    "missing_apd":   [],
                    "violation_type": "person",
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
        for i, w in enumerate(workers):
            x1, y1, x2, y2 = w["box"]
            h = y2 - y1
            apd = w["apd"]

            # Bounding box putih tipis
            cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 255, 255), 1)

            # Label worker
            name = self._worker_names.get(i, "")
            label = f"WORKER {i+1}" + (f" : {name}" if name else "")
            cv2.putText(frame, label, (x1, y1 - 8), 1, 0.9, (255, 255, 255), 1)

            # Panel APD di dalam bounding box
            apd_map = [
                ("H", apd["helmet"], 0.05, 0.22),
                ("V", apd["vest"],   0.28, 0.63),
                ("B", apd["boots"],  0.78, 0.98),
            ]
            for code, data, t, b in apd_map:
                color = (0, 200, 0) if data["ok"] else (0, 0, 220)
                ax1 = x1 + 12
                ay1 = y1 + int(h * t)
                ax2 = x2 - 12
                ay2 = y1 + int(h * b)
                cv2.rectangle(frame, (ax1, ay1), (ax2, ay2), color, 2)
                conf_str = f"{data['conf']:.2f}" if data["conf"] > 0 else "--"
                cv2.putText(frame, code, (ax1 + 4, ay1 + 20), 1, 1.3, color, 2)
                cv2.putText(frame, conf_str, (ax1 + 4, ay1 + 38), 1, 1.0, color, 1)

        # HUD overlay kanan atas
        fw = frame.shape[1]
        hud_w = 340
        hx = fw - hud_w
        cv2.rectangle(frame, (hx, 0), (fw, 60), (30, 30, 30), -1)
        cv2.putText(frame, f"K3 Monitor | {len(workers)} WORKERS",
                    (hx + 10, 25), 1, 1.2, (255, 255, 255), 2)
        cv2.putText(frame, datetime.now().strftime("%H:%M:%S"),
                    (hx + 10, 50), 1, 1.0, (180, 180, 180), 1)
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
        prev = self._detected_workers
        if not prev:
            return current
        n_c, n_p = len(current), len(prev)
        matched  = {}; used_c = set()
        for pi in range(n_p):
            best_iou, best_ci = 0.3, -1
            for ci in range(n_c):
                if ci in used_c: continue
                iou = self._iou(current[ci]["box"], prev[pi]["box"])
                if iou > best_iou:
                    best_iou, best_ci = iou, ci
            if best_ci >= 0:
                matched[pi] = current[best_ci]; used_c.add(best_ci)
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
        self._model     = None
        self._onnx_sess = None
        self._apd_sess  = None
        self._detected_workers = []
        self._mosse_trackers   = []
        self._mosse_workers    = []
        self._last_ss_time = {}
