"""
PPEDetector — wrapper ultralytics YOLO untuk file .pt dan .onnx.
"""
from __future__ import annotations

import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

from src.utils.logger import get_logger

logger = get_logger("safeguard.detector")


@dataclass
class Detection:
    class_id: int
    class_name: str
    confidence: float
    bbox: tuple  # (x1, y1, x2, y2)


@dataclass
class FrameResult:
    detections: list = field(default_factory=list)
    inference_ms: float = 0.0
    frame_id: int = 0


class PPEDetector:
    """
    Detektor APD menggunakan YOLOv8/YOLOv11 via ultralytics.
    Mendukung model .pt (PyTorch) maupun .onnx.
    """

    def __init__(
        self,
        model_path: str = "best.pt",
        fallback_model: str = "yolov8n.pt",
        confidence_threshold: float = 0.40,
        iou_threshold: float = 0.45,
        input_size: int = 640,
        frame_skip: int = 2,
        class_mapping: dict | None = None,
    ):
        self.model_path = model_path
        self.fallback_model = fallback_model
        self.confidence_threshold = confidence_threshold
        self.iou_threshold = iou_threshold
        self.input_size = input_size
        self.frame_skip = frame_skip
        self.class_mapping = class_mapping or {}

        self._model = None
        self._model_classes: list[str] = []
        self._device = "cpu"
        self._frame_idx = 0
        self._inf_times: list[float] = []
        self._last_result: Optional[FrameResult] = None

    # ------------------------------------------------------------------
    # Properties
    # ------------------------------------------------------------------

    @property
    def model_classes(self) -> list[str]:
        return self._model_classes

    @property
    def avg_inference_ms(self) -> float:
        if not self._inf_times:
            return 0.0
        recent = self._inf_times[-30:]
        return sum(recent) / len(recent)

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def load(self) -> bool:
        """
        Muat model YOLO. Coba model_path terlebih dahulu, lalu fallback.
        Mendukung .pt (PyTorch) dan .onnx (ONNX Runtime via ultralytics).
        """
        path = self._resolve_path(self.model_path)
        if path is None:
            logger.warning(f"Model tidak ditemukan: {self.model_path} — coba fallback.")
            path = self._resolve_path(self.fallback_model)
        if path is None:
            logger.error(f"Tidak ada model yang dapat dimuat ({self.model_path}, {self.fallback_model}).")
            return False

        try:
            import torch
            from ultralytics import YOLO

            self._device = "cuda" if torch.cuda.is_available() else "cpu"
            logger.info(f"Memuat model: {path}  [device={self._device.upper()}]")

            self._model = YOLO(str(path))

            # Warm-up — agar GPU dan ONNX runtime siap sebelum frame pertama
            dummy = np.zeros((self.input_size, self.input_size, 3), dtype=np.uint8)
            self._model.predict(
                dummy,
                imgsz=self.input_size,
                conf=0.99,
                device=self._device,
                verbose=False,
            )

            # Simpan nama kelas dari model
            if hasattr(self._model, "names") and self._model.names:
                self._model_classes = [self._model.names[i] for i in sorted(self._model.names)]

            suffix = Path(str(path)).suffix.upper()
            logger.info(
                f"Model dimuat ({suffix}) | kelas={len(self._model_classes)} | "
                f"GPU={'Ya' if self._device == 'cuda' else 'Tidak'}"
            )
            if self._device == "cuda":
                logger.info(f"GPU: {torch.cuda.get_device_name(0)}")
            return True

        except Exception as exc:
            logger.error(f"Gagal memuat model: {exc}")
            return False

    def predict(self, frame: np.ndarray) -> Optional[FrameResult]:
        """
        Jalankan inferensi. Frame di-skip sesuai frame_skip agar lebih efisien.
        Mengembalikan FrameResult terakhir yang valid saat di-skip.
        """
        self._frame_idx += 1
        if self._frame_idx % self.frame_skip != 0:
            return self._last_result
        if self._model is None:
            return None

        t0 = time.perf_counter()
        try:
            results = self._model.predict(
                frame,
                imgsz=self.input_size,
                conf=self.confidence_threshold,
                iou=self.iou_threshold,
                device=self._device,
                verbose=False,
            )[0]
        except Exception as exc:
            logger.error(f"Predict error: {exc}")
            return self._last_result

        inf_ms = (time.perf_counter() - t0) * 1000
        self._inf_times.append(inf_ms)

        detections: list[Detection] = []
        for box in results.boxes:
            cls_id = int(box.cls[0])
            conf = float(box.conf[0])
            x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
            cls_name = (
                self._model_classes[cls_id]
                if cls_id < len(self._model_classes)
                else str(cls_id)
            )
            detections.append(Detection(
                class_id=cls_id,
                class_name=cls_name,
                confidence=conf,
                bbox=(x1, y1, x2, y2),
            ))

        self._last_result = FrameResult(
            detections=detections,
            inference_ms=inf_ms,
            frame_id=self._frame_idx,
        )
        return self._last_result

    def draw(
        self,
        frame: np.ndarray,
        frame_result: FrameResult,
        class_colors: dict | None = None,
    ) -> np.ndarray:
        """Gambar bounding box + label di atas frame. Warna dari class_colors (RGB)."""
        img = frame.copy()
        colors = class_colors or {}

        for det in frame_result.detections:
            x1, y1, x2, y2 = det.bbox
            rgb = colors.get(det.class_name, [200, 200, 200])
            bgr = (int(rgb[2]), int(rgb[1]), int(rgb[0]))

            cv2.rectangle(img, (x1, y1), (x2, y2), bgr, 2, cv2.LINE_AA)

            label = f"{det.class_name} {det.confidence:.0%}"
            (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 1)
            cv2.rectangle(img, (x1, y1 - th - 6), (x1 + tw + 6, y1), bgr, -1)
            cv2.putText(
                img, label, (x1 + 3, y1 - 4),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1, cv2.LINE_AA,
            )

        return img

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _resolve_path(model_path: str) -> Optional[Path]:
        """Cari file model: path absolut/relatif, atau di folder models/."""
        candidates = [
            Path(model_path),
            Path("models") / model_path,
        ]
        for p in candidates:
            if p.exists():
                return p
        return None
