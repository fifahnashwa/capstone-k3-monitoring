"""Representasi satu kamera aktif: thread stream + engine deteksi + ONVIF."""

import asyncio
import logging
import os
import threading
import time
from typing import Optional

import cv2
import numpy as np

from detection_engine import DetectionEngine
from laravel_client import post_violation, post_health_check
from onvif_handler import OnvifHandler

log = logging.getLogger(__name__)

os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")
os.environ.setdefault("YOLO_VERBOSE", "False")


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

    # Resize target untuk hemat memory (720x1280 → 360x640 = 75% lebih kecil)
    STREAM_W, STREAM_H = 640, 360

    def _update(self):
        while self._running:
            if self._cap is None:
                break
            try:
                grabbed, frame = self._cap.read()
                if grabbed:
                    try:
                        frame = cv2.resize(frame, (self.STREAM_W, self.STREAM_H))
                    except Exception:
                        pass
                    with self._lock:
                        self._frame = frame
                else:
                    time.sleep(0.05)
                    self._reconnect()
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
        self._frame_lock = threading.Lock()

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
        log.info(f"CameraInstance {self.camera_id} started.")
        return True

    def stop(self):
        self._running = False
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
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        health_tick = time.time()

        while self._running:
            try:
                frame = self._stream.read()
                if frame is None:
                    time.sleep(0.01)
                    continue

                annotated, violations = self._engine.process_frame(frame)

                with self._frame_lock:
                    self._latest_frame = annotated

                for v in violations:
                    loop.run_until_complete(self._report_violation(v))

                if time.time() - health_tick > 60:
                    health_tick = time.time()
                    loop.run_until_complete(
                        post_health_check(self.camera_id, "online", {"rtsp_ok": True})
                    )
            except Exception as e:
                log.error(f"CameraInstance {self.camera_id} _run_loop error: {e}")
                time.sleep(1)

        loop.close()

    async def _report_violation(self, v: dict):
        payload = {
            "camera_id":     self.camera_id,
            "timestamp":     v["detected_at"].replace(" ", "T"),
            "label":         "no_" + v["missing_apd"][0] if len(v["missing_apd"]) == 1 else "no_helmet",
            "confidence":    v["confidence"],
            "image_path":    v.get("image_path", ""),
            "missing_apd":   v["missing_apd"],
            "violation_type": v.get("violation_type", "apd"),
            "bbox_coordinates": {
                "x1": v["bbox"][0], "y1": v["bbox"][1],
                "x2": v["bbox"][2], "y2": v["bbox"][3],
            },
        }
        await post_violation(payload)

    def _build_rtsp_url(self) -> str:
        user = self.config.get("username") or ""
        pw   = self.config.get("password") or ""
        ip   = self.config.get("ip_address") or ""
        port = self.config.get("port_rtsp", 554)
        path = self.config.get("rtsp_path", "/stream2")
        transport = self.config.get("rtsp_transport", "tcp")
        os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = f"rtsp_transport;{transport}"
        if not ip:
            return ""
        if user and pw:
            return f"rtsp://{user}:{pw}@{ip}:{port}{path}"
        return f"rtsp://{ip}:{port}{path}"
