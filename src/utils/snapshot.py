from __future__ import annotations

import csv
import uuid
from datetime import datetime
from pathlib import Path

import cv2
import numpy as np


class SnapshotManager:
    def __init__(
        self,
        snapshot_dir: str = "snapshots/violations",
        csv_log_path: str = "logs/violations.csv",
        jpeg_quality: int = 85,
    ):
        self.snapshot_dir = Path(snapshot_dir)
        self.csv_log_path = Path(csv_log_path)
        self.jpeg_quality = jpeg_quality

        self.snapshot_dir.mkdir(parents=True, exist_ok=True)
        self.csv_log_path.parent.mkdir(parents=True, exist_ok=True)

        if not self.csv_log_path.exists():
            with open(self.csv_log_path, "w", newline="", encoding="utf-8") as f:
                csv.writer(f).writerow([
                    "timestamp", "zone_name", "violation_type", "severity",
                    "missing_ppe", "confidence", "shift_name", "snapshot_path",
                ])

    def save(
        self,
        frame: np.ndarray,
        zone_name: str,
        violation_type: str,
        severity: str,
        missing_ppe: list,
        confidence: float = 0.0,
        shift_name: str = "",
    ) -> str:
        ts = datetime.now()
        date_dir = ts.strftime("%Y-%m-%d")
        save_dir = self.snapshot_dir / date_dir
        save_dir.mkdir(parents=True, exist_ok=True)

        filename = f"{ts.strftime('%H%M%S')}_{uuid.uuid4().hex[:8]}.jpg"
        filepath = save_dir / filename
        cv2.imwrite(str(filepath), frame, [cv2.IMWRITE_JPEG_QUALITY, self.jpeg_quality])

        with open(self.csv_log_path, "a", newline="", encoding="utf-8") as f:
            csv.writer(f).writerow([
                ts.strftime("%Y-%m-%d %H:%M:%S"),
                zone_name,
                violation_type,
                severity,
                "|".join(missing_ppe),
                f"{confidence:.3f}",
                shift_name,
                str(filepath),
            ])

        return str(filepath)
