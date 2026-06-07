from __future__ import annotations

from typing import Optional, Tuple, Union

import cv2
import numpy as np


class VideoSource:
    """Wrapper tipis di atas cv2.VideoCapture untuk webcam, file video, atau RTSP."""

    def __init__(
        self,
        source: Union[int, str],
        width: int = 1280,
        height: int = 720,
        name: str = "cam",
    ):
        self.source = source
        self.width = width
        self.height = height
        self.name = name
        self._cap: Optional[cv2.VideoCapture] = None

    def open(self) -> bool:
        self._cap = cv2.VideoCapture(self.source)
        if not self._cap.isOpened():
            return False
        # Atur resolusi hanya untuk webcam (integer source)
        if isinstance(self.source, int):
            self._cap.set(cv2.CAP_PROP_FRAME_WIDTH, self.width)
            self._cap.set(cv2.CAP_PROP_FRAME_HEIGHT, self.height)
        return True

    def read(self) -> Tuple[bool, Optional[np.ndarray]]:
        if self._cap is None or not self._cap.isOpened():
            return False, None
        return self._cap.read()

    def release(self) -> None:
        if self._cap is not None:
            self._cap.release()
            self._cap = None

    @property
    def is_opened(self) -> bool:
        return self._cap is not None and self._cap.isOpened()
