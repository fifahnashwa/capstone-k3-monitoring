from __future__ import annotations

import csv
import time
from datetime import datetime
from pathlib import Path
from typing import Optional

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
                writer = csv.writer(f)
                writer.writerow([
                    "timestamp", "zone", "violation_type", "severity",
                    "missing_ppe", "confidence", "shift", "snapshot_path",
                ])

    def save(
        self,
        frame: np.ndarray,
        zone_name: str,
        violation_type: str,
        severity: str,
        missing_ppe: list[str],
        confidence: float = 0.0,
        shift_name: str = "",
        snapshot_path: Optional[str] = None,
    ) -> str:
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        slug = zone_name.lower().replace(" ", "_")
        filename = f"{slug}_{violation_type}_{ts}_{int(time.time()*1000) % 1000:03d}.jpg"
        path = self.snapshot_dir / filename

        ok, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, self.jpeg_quality])
        if ok:
            path.write_bytes(buf.tobytes())

        with open(self.csv_log_path, "a", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow([
                datetime.now().isoformat(),
                zone_name,
                violation_type,
                severity,
                "|".join(missing_ppe),
                f"{confidence:.3f}",
                shift_name,
                str(path),
            ])

        return str(path)
