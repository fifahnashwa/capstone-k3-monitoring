from __future__ import annotations

import os
import threading
import time
from typing import Optional

import cv2
import numpy as np

os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp|max_delay;0|flags;low_delay")


class VideoSource:
    def __init__(
        self,
        source: int | str = 0,
        width: int = 1280,
        height: int = 720,
        name: str = "cam",
    ):
        self.source = source
        self.width = width
        self.height = height
        self.name = name

        self._cap: Optional[cv2.VideoCapture] = None
        self._frame: Optional[np.ndarray] = None
        self._lock = threading.Lock()
        self._running = False

    def open(self) -> bool:
        cap = cv2.VideoCapture(self.source, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if self.width:
            cap.set(cv2.CAP_PROP_FRAME_WIDTH, self.width)
        if self.height:
            cap.set(cv2.CAP_PROP_FRAME_HEIGHT, self.height)

        if not cap.isOpened():
            return False
        ok, frame = cap.read()
        if not ok:
            cap.release()
            return False

        self._cap = cap
        self._frame = frame
        self._running = True
        threading.Thread(target=self._update, daemon=True, name=f"vs_{self.name}").start()
        return True

    def _update(self):
        while self._running:
            if self._cap is None:
                break
            try:
                if not self._cap.grab():
                    time.sleep(0.05)
                    self._reconnect()
                    continue
                ok, frame = self._cap.retrieve()
                if ok:
                    with self._lock:
                        self._frame = frame
            except Exception:
                time.sleep(1)
                self._reconnect()

    def _reconnect(self):
        try:
            if self._cap:
                self._cap.release()
            time.sleep(2)
            cap = cv2.VideoCapture(self.source, cv2.CAP_FFMPEG)
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            if cap.isOpened():
                self._cap = cap
        except Exception:
            pass

    def read(self) -> tuple[bool, Optional[np.ndarray]]:
        with self._lock:
            if self._frame is not None:
                return True, self._frame.copy()
        return False, None

    def release(self):
        self._running = False
        if self._cap:
            self._cap.release()
        self._cap = None
