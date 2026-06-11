"""Representasi satu kamera aktif: thread stream + engine deteksi + ONVIF."""

import asyncio
import base64
import logging
import os
import queue as _qmod
import threading
import time
from datetime import datetime
from typing import Optional

import httpx

import cv2
import numpy as np

from detection_engine import DetectionEngine
from laravel_client import post_violation, post_health_check
from onvif_handler import OnvifHandler
import face_recognizer

log = logging.getLogger(__name__)

os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")
os.environ.setdefault("YOLO_VERBOSE", "False")

VIOLATION_COOLDOWN = int(os.getenv("VIOLATION_COOLDOWN_SECONDS", "8"))


class RTSPStreamReader:
    """Threaded RTSP reader — selalu simpan frame terbaru."""

    def __init__(self, url: str):
        self.url       = url
        self._cap      = None
        self._frame    = None
        self._lock     = threading.Lock()
        self._running  = False
        self._thread   = None

    def start(self) -> bool:
        cap = cv2.VideoCapture(self.url, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if not cap.isOpened():
            log.error(f"Tidak bisa membuka RTSP: {self.url}")
            return False
        grabbed, frame = cap.read()
        if not grabbed:
            cap.release()
            log.error(f"Tidak bisa membaca frame awal: {self.url}")
            return False
        self._cap     = cap
        self._frame   = frame
        self._running = True
        self._thread  = threading.Thread(target=self._update, daemon=True)
        self._thread.start()
        log.info(f"Stream started: {self.url}")
        return True

    def _update(self):
        while self._running:
            if self._cap is None:
                break
            try:
                # grab() lebih cepat dari read() — flush frame lama dari buffer
                if not self._cap.grab():
                    time.sleep(0.05)
                    self._reconnect()
                    continue
                ok, frame = self._cap.retrieve()
                if ok:
                    with self._lock:
                        self._frame = frame
            except Exception as e:
                log.warning(f"Frame read error ({self.url}): {e}")
                time.sleep(1)
                self._reconnect()

    def _reconnect(self):
        try:
            if self._cap:
                self._cap.release()
            time.sleep(2)
            cap = cv2.VideoCapture(self.url, cv2.CAP_FFMPEG)
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            if cap.isOpened():
                self._cap = cap
                log.info(f"Reconnected: {self.url}")
        except Exception as e:
            log.warning(f"Reconnect failed: {e}")

    def read(self) -> Optional[np.ndarray]:
        with self._lock:
            try:
                return self._frame.copy() if self._frame is not None else None
            except Exception:
                return None

    def stop(self):
        self._running = False
        if self._cap:
            self._cap.release()
        self._cap   = None
        self._frame = None


class CameraInstance:
    """Satu kamera: RTSP reader + detection engine + ONVIF + reporting ke Laravel."""

    def __init__(self, config: dict):
        self.camera_id = config["id"]
        self.config    = config
        self._stream   = None
        self._engine   = None
        self._onvif    = None
        self._running  = False
        self._thread   = None
        self._latest_frame: Optional[np.ndarray] = None
        self._frame_lock  = threading.Lock()
        self._cooldown:      dict = {}  # employee_id → last_reported timestamp
        self._locked_names: dict = {}  # worker_idx → emp_name
        self._name_boxes:   dict = {}  # emp_name → last known bounding box [x1,y1,x2,y2]
        self._name_lost:    dict = {}  # emp_name → consecutive frames not tracked
        self._face_thread_running = False
        self._face_lock           = threading.Lock()
        self._detect_queue: _qmod.Queue = _qmod.Queue(maxsize=1)

    def start(self) -> bool:
        rtsp_url = self._build_rtsp_url()
        if not rtsp_url:
            log.error(f"Camera {self.camera_id}: ip_address kosong, skip.")
            return False
        self._stream = RTSPStreamReader(rtsp_url)
        if not self._stream.start():
            return False

        self._engine = DetectionEngine(self.config)
        self._engine.load_model()

        if self.config.get("ptz_enabled"):
            self._onvif = OnvifHandler(
                self.config["ip_address"],
                self.config.get("port_onvif", 2020),
                self.config.get("username", ""),
                self.config.get("password", ""),
            )
            self._onvif.connect()

        self._running = True
        self._thread  = threading.Thread(target=self._run_loop, daemon=True)
        self._thread.start()
        # _detect_loop tidak diperlukan — engine sudah punya background YOLO thread sendiri
        # Face recognition berjalan di thread terpisah agar tidak blokir detection
        self._face_thread_running = True
        threading.Thread(target=self._face_loop, daemon=True).start()
        log.info(f"CameraInstance {self.camera_id} started.")
        return True

    def stop(self):
        self._running = False
        self._face_thread_running = False
        if self._stream:
            self._stream.stop()
        if self._engine:
            self._engine.unload()
        if self._onvif:
            self._onvif.disconnect()
        log.info(f"CameraInstance {self.camera_id} stopped.")

    def get_latest_frame(self) -> Optional[np.ndarray]:
        with self._frame_lock:
            try:
                return self._latest_frame.copy() if self._latest_frame is not None else None
            except Exception:
                return None

    def ptz_move(self, direction: str, speed: float = None, duration: float = None) -> dict:
        if not self._onvif:
            return {"success": False, "message": "PTZ tidak diinisialisasi."}
        spd = speed    or self.config.get("ptz_speed", 0.6)
        dur = duration or self.config.get("ptz_movement_duration", 0.4)
        ok  = self._onvif.move(direction, spd, dur)
        return {"success": ok, "direction": direction}

    def test_connection(self) -> dict:
        # Test RTSP
        rtsp_ok = False
        rtsp_info = {}
        try:
            cap = cv2.VideoCapture(self._build_rtsp_url(), cv2.CAP_FFMPEG)
            if cap.isOpened():
                ok, _ = cap.read()
                rtsp_ok = ok
                if ok:
                    rtsp_info["resolution"] = f"{int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))}x{int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))}"
                    rtsp_info["fps"]        = round(cap.get(cv2.CAP_PROP_FPS), 1)
            cap.release()
        except Exception as e:
            rtsp_info["error"] = str(e)

        # Test ONVIF
        handler = OnvifHandler(
            self.config.get("ip_address", ""),
            self.config.get("port_onvif", 2020),
            self.config.get("username", ""),
            self.config.get("password", ""),
        )
        onvif_result = handler.test_connection()

        return {
            "onvif":   onvif_result.get("onvif", {}),
            "rtsp":    {"ok": rtsp_ok, **rtsp_info},
            "overall": rtsp_ok and onvif_result.get("overall", False),
        }

    # ── Private ─────────────────────────────────────────────────────────────

    def _run_loop(self):
        """Display loop: tiap frame → process_frame() → MOSSE track + annotate + violations."""
        health_tick = time.time()

        while self._running:
            try:
                frame = self._stream.read()
                if frame is None:
                    time.sleep(0.01)
                    continue

                # process_frame: kirim ke background YOLO, update MOSSE, gambar box
                annotated, violations = self._engine.process_frame(frame)

                with self._frame_lock:
                    self._latest_frame = annotated

                if violations:
                    snapshot = frame.copy()
                    with self._face_lock:
                        ident_copy = list(self._locked_names.items())
                    for v in violations:
                        threading.Thread(
                            target=lambda v=v, s=snapshot, ident=ident_copy: asyncio.run(
                                self._report_violation(v, ident, s)
                            ),
                            daemon=True,
                        ).start()

                time.sleep(0.01)  # cap ~100fps, frame selalu fresh untuk YOLO

                if time.time() - health_tick > 60:
                    health_tick = time.time()
                    cam_id = self.camera_id
                    threading.Thread(
                        target=lambda: asyncio.run(
                            post_health_check(cam_id, "online", {"rtsp_ok": True})
                        ),
                        daemon=True,
                    ).start()
            except Exception as e:
                log.error(f"CameraInstance {self.camera_id} _run_loop error: {e}")
                time.sleep(1)

    def _detect_loop(self):
        """YOLO detection loop: jalan di background thread terpisah."""
        while self._running:
            try:
                frame = self._detect_queue.get(timeout=1.0)
            except _qmod.Empty:
                continue

            if self._engine._model is None and self._engine._apd_sess is None:
                continue

            try:
                workers, violations = self._engine._run_detection(frame)
                self._engine._detected_workers = workers

                if violations:
                    snapshot = frame.copy()
                    with self._face_lock:
                        ident_copy = list(self._locked_names.items())
                    for v in violations:
                        threading.Thread(
                            target=lambda v=v, s=snapshot, ident=ident_copy: asyncio.run(
                                self._report_violation(v, ident, s)
                            ),
                            daemon=True,
                        ).start()
            except Exception as e:
                log.error(f"CameraInstance {self.camera_id} _detect_loop error: {e}")

    def _face_loop(self):
        """Thread terpisah untuk face recognition — tidak memblokir detection loop."""
        interval = float(self.config.get("face_recognition_interval", 0.3))
        while self._face_thread_running and self._running:
            try:
                if not face_recognizer.registry.has_models:
                    # Tidak ada face model — sleep lebih lama, tidak perlu processing
                    time.sleep(2.0)
                    continue
                frame = self._stream.read() if self._stream else None
                if frame is not None:
                    self._identify_and_label(frame, None)
            except Exception as e:
                log.debug(f"Face loop error: {e}")
            time.sleep(interval)

    def _patch_person_name(self, emp_name: str) -> None:
        """Retrofit person_name ke violations terakhir yang belum teridentifikasi."""
        from laravel_client import LARAVEL_URL, _headers
        try:
            httpx.patch(
                f"{LARAVEL_URL}/api/violations/patch-person-name",
                headers=_headers(),
                json={"camera_id": self.camera_id, "person_name": emp_name},
                timeout=5,
            )
            log.info(f"Patched person_name='{emp_name}' ke violations terakhir kamera {self.camera_id}")
        except Exception as e:
            log.warning(f"patch_person_name failed: {e}")

    @staticmethod
    def _box_iou(a: list, b: list) -> float:
        ix1 = max(a[0], b[0]); iy1 = max(a[1], b[1])
        ix2 = min(a[2], b[2]); iy2 = min(a[3], b[3])
        inter = max(0, ix2 - ix1) * max(0, iy2 - iy1)
        if inter == 0:
            return 0.0
        area_a = (a[2] - a[0]) * (a[3] - a[1])
        area_b = (b[2] - b[0]) * (b[3] - b[1])
        return inter / (area_a + area_b - inter + 1e-6)

    def _identify_and_label(self, frame: np.ndarray, _annotated) -> list:
        """Face recognition + box tracking — nama terkunci mengikuti orang yang bergerak."""
        # Saat skip_frame=1 MOSSE tidak aktif → pakai detected_workers sebagai fallback
        workers = list(self._engine._mosse_workers) or list(self._engine._detected_workers)

        # ── 1. Face recognition: kunci nama baru, override box tracking ─────────
        if face_recognizer.registry.has_models:
            matches = face_recognizer.registry.identify_from_frame(frame)
            for _, emp_name, sim, (fx1, fy1, fx2, fy2) in matches:
                face_cx = (fx1 + fx2) / 2
                face_cy = (fy1 + fy2) / 2
                for idx, w in enumerate(workers):
                    wx1, wy1, wx2, wy2 = w["box"]
                    if wx1 <= face_cx <= wx2 and wy1 <= face_cy <= wy2:
                        # Hapus nama lain yang sedang track worker yang sama
                        # (face recognition lebih dipercaya daripada box tracking)
                        for other in list(self._name_boxes.keys()):
                            if other != emp_name:
                                iou = self._box_iou(self._name_boxes[other], w["box"])
                                if iou > 0.25:
                                    log.info(f"Face override: hapus '{other}', ganti '{emp_name}' (iou={iou:.2f})")
                                    self._name_boxes.pop(other, None)
                                    self._name_lost.pop(other, None)

                        if emp_name not in self._name_boxes:
                            log.info(f"Locked: worker {idx+1} → {emp_name} (sim={sim:.2f})")
                            threading.Thread(
                                target=self._patch_person_name,
                                args=(emp_name,),
                                daemon=True,
                            ).start()
                        self._name_boxes[emp_name] = list(w["box"])
                        self._name_lost[emp_name]  = 0
                        break

        # ── 2. Box tracking: ikuti setiap nama yang terkunci ke worker terkini ─
        tracked = {}          # emp_name → worker_idx saat ini
        used_workers = set()  # idx yang sudah di-claim

        for emp_name, saved_box in list(self._name_boxes.items()):
            best_idx = None
            best_iou = 0.10   # threshold rendah agar tetap terlacak saat bergerak

            for idx, w in enumerate(workers):
                if idx in used_workers:
                    continue
                iou = self._box_iou(w["box"], saved_box)
                if iou > best_iou:
                    best_iou = iou
                    best_idx = idx

            if best_idx is not None:
                tracked[emp_name]              = best_idx
                used_workers.add(best_idx)
                self._name_boxes[emp_name]     = list(workers[best_idx]["box"])
                self._name_lost[emp_name]      = 0
            else:
                self._name_lost[emp_name] = self._name_lost.get(emp_name, 0) + 1

        # ── 3. Bangun locked_names dari hasil tracking ────────────────────────
        new_locked = {idx: name for name, idx in tracked.items()}

        # ── 4. Hapus nama yang sudah lama hilang (worker benar-benar keluar) ──
        MAX_LOST = 15
        for emp_name in list(self._name_lost.keys()):
            if self._name_lost.get(emp_name, 0) > MAX_LOST:
                log.info(f"Worker '{emp_name}' keluar frame, nama direset.")
                self._name_boxes.pop(emp_name, None)
                self._name_lost.pop(emp_name, None)

        with self._face_lock:
            self._locked_names = new_locked

        self._engine._worker_names = dict(new_locked)
        return list(new_locked.items())

    async def _report_violation(
        self, v: dict, identified: list = None, snapshot: Optional[np.ndarray] = None
    ):
        # Anti-double: gunakan nama terkunci dari worker_idx pelanggaran
        employee_id = None
        worker_idx  = v.get("worker_idx")
        locked_name = self._locked_names.get(worker_idx) if worker_idx is not None else None

        if locked_name:
            cooldown_key = f"{worker_idx}_{locked_name}"
            now  = time.time()
            last = self._cooldown.get(cooldown_key, 0)
            if now - last < VIOLATION_COOLDOWN:
                log.debug(f"Cooldown aktif untuk {locked_name} (worker {worker_idx+1}), skip.")
                return
            self._cooldown[cooldown_key] = now
            employee_id = locked_name

        # Encode screenshot sebagai base64 — Laravel yang akan simpan ke storage
        image_data = ""
        if snapshot is not None:
            try:
                ok, jpg = cv2.imencode(".jpg", snapshot, [cv2.IMWRITE_JPEG_QUALITY, 85])
                if ok:
                    image_data = base64.b64encode(jpg.tobytes()).decode("utf-8")
            except Exception as e:
                log.warning(f"Screenshot encode error: {e}")

        base = {
            "camera_id":      self.camera_id,
            "timestamp":      datetime.now().strftime("%Y-%m-%dT%H:%M:%S"),
            "confidence":     v["confidence"],
            "image_path":     "",
            "image_data":     image_data,
            "violation_type": v.get("violation_type", "apd"),
            "bbox_coordinates": {
                "x1": v["bbox"][0], "y1": v["bbox"][1],
                "x2": v["bbox"][2], "y2": v["bbox"][3],
            },
        }
        if employee_id:
            base["person_name"] = employee_id  # locked_name berisi nama karyawan

        if v.get("violation_type") == "person":
            # Discipline violation — Laravel yang akan cek apakah di luar shift
            await post_violation({**base, "label": "person"})
        else:
            # APD violation — kirim semua label yang hilang sekaligus
            labels = [f"no_{apd}" for apd in v["missing_apd"]]
            if labels:
                await post_violation({**base, "labels": labels})

    def _build_rtsp_url(self) -> str:
        user = self.config.get("username") or ""
        pw   = self.config.get("password") or ""
        ip   = self.config.get("ip_address") or ""
        port = self.config.get("port_rtsp", 554)
        path = self.config.get("rtsp_path", "/stream2")
        transport = self.config.get("rtsp_transport", "tcp")
        os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
            f"rtsp_transport;{transport}|"
            "max_delay;0|"
            "reorder_queue_size;0|"
            "flags;low_delay"
        )
        if not ip:
            return ""
        if user and pw:
            return f"rtsp://{user}:{pw}@{ip}:{port}{path}"
        return f"rtsp://{ip}:{port}{path}"
