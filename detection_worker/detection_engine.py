"""Engine YOLO untuk deteksi APD — refactor dari deteksi.py."""

import logging
import os
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
        self._model     = None
        self._model_path = config.get("ai_model_path")

        # Class IDs dari konfigurasi
        mapping          = config.get("class_mapping", {})
        self.person_id   = mapping.get("person_id", 6)
        self.helmet_ids  = mapping.get("helmet_ids", [0])
        self.vest_ids    = mapping.get("vest_ids", [2])
        self.boots_ids   = mapping.get("boots_ids", [3])

        self.det_size    = config.get("detection_size", 640)
        self.conf_thresh = float(config.get("confidence_threshold", 0.40))
        self.skip_frame  = int(config.get("process_every_n_frame", 2))
        self.auto_ss     = config.get("auto_screenshot", True)
        self.ss_cooldown = int(config.get("screenshot_cooldown", 30))

        # State
        self._frame_idx      = 0
        self._detected_workers = []
        self._last_ss_time   = {}   # worker_idx -> timestamp (anti-spam)

    def load_model(self) -> bool:
        if not self._model_path or not Path(self._model_path).exists():
            log.warning(f"Model tidak ditemukan: {self._model_path}")
            return False
        try:
            from ultralytics import YOLO
            self._model = YOLO(self._model_path, task="detect")
            log.info(f"Model dimuat: {self._model_path}")
            return True
        except Exception as e:
            log.error(f"Gagal memuat model: {e}")
            return False

    def process_frame(self, frame: np.ndarray) -> tuple[np.ndarray, list]:
        """
        Proses satu frame. Return (frame_annotated, violations_list).
        violations_list berisi dict per pelanggaran yang terdeteksi.
        """
        self._frame_idx += 1
        violations = []

        if self._frame_idx % self.skip_frame == 0 and self._model is not None:
            self._detected_workers, violations = self._run_detection(frame)

        # Visualisasi bounding box
        frame = self._draw_annotations(frame, self._detected_workers)
        return frame, violations

    def _run_detection(self, frame: np.ndarray) -> tuple[list, list]:
        try:
            results = self._model.predict(
                frame, imgsz=self.det_size, conf=self.conf_thresh, verbose=False
            )[0]
        except Exception as e:
            log.error(f"Prediction error: {e}")
            return self._detected_workers, []

        current_workers = []
        temp_items      = []

        for box in results.boxes:
            cls  = int(box.cls[0])
            xyxy = list(map(int, box.xyxy[0]))
            conf = float(box.conf[0])
            if cls == self.person_id:
                current_workers.append({
                    "box": xyxy, "conf": conf, "frames_lost": 0,
                    "apd": {
                        "helmet": {"ok": False, "conf": 0.0},
                        "vest":   {"ok": False, "conf": 0.0},
                        "boots":  {"ok": False, "conf": 0.0},
                    },
                })
            else:
                temp_items.append({"cls": cls, "box": xyxy, "conf": conf})

        # Asosiasi APD ke worker berdasarkan posisi horizontal
        for w in current_workers:
            wx1, wy1, wx2, wy2 = w["box"]
            for item in temp_items:
                ix1, iy1, ix2, iy2 = item["box"]
                item_cx = (ix1 + ix2) / 2
                if wx1 <= item_cx <= wx2:
                    if item["cls"] in self.helmet_ids and item["conf"] > w["apd"]["helmet"]["conf"]:
                        w["apd"]["helmet"] = {"ok": True, "conf": item["conf"]}
                    elif item["cls"] in self.vest_ids and item["conf"] > w["apd"]["vest"]["conf"]:
                        w["apd"]["vest"] = {"ok": True, "conf": item["conf"]}
                    elif item["cls"] in self.boots_ids and item["conf"] > w["apd"]["boots"]["conf"]:
                        w["apd"]["boots"] = {"ok": True, "conf": item["conf"]}

        if current_workers:
            detected = current_workers
        else:
            for w in self._detected_workers:
                w["frames_lost"] += 1
            detected = [w for w in self._detected_workers if w["frames_lost"] < 10]

        # Build violations list
        violations = []
        now = time.time()
        for i, w in enumerate(current_workers):
            missing = []
            if not w["apd"]["helmet"]["ok"]: missing.append("helmet")
            if not w["apd"]["vest"]["ok"]:   missing.append("vest")
            if not w["apd"]["boots"]["ok"]:  missing.append("boots")

            if missing:
                last_ss = self._last_ss_time.get(i, 0)
                if self.auto_ss and (now - last_ss) >= self.ss_cooldown:
                    self._last_ss_time[i] = now
                    screenshot_path = self._save_screenshot(frame, w["box"])
                    violations.append({
                        "worker_idx":    i,
                        "bbox":          w["box"],
                        "missing_apd":   missing,
                        "violation_type": "no_" + "_no_".join(missing) if len(missing) == 1 else "multiple",
                        "confidence":    w["conf"],
                        "image_path":    screenshot_path,
                        "detected_at":   datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    })

        return detected, violations

    def _draw_annotations(self, frame: np.ndarray, workers: list) -> np.ndarray:
        for i, w in enumerate(workers):
            x1, y1, x2, y2 = w["box"]
            h = y2 - y1
            cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 255, 255), 1)
            cv2.putText(frame, f"WORKER {i+1}", (x1, y1 - 8), 1, 0.9, (255, 255, 255), 1)

            apd_map = [
                ("H", w["apd"]["helmet"], 0.05, 0.22),
                ("V", w["apd"]["vest"],   0.28, 0.63),
                ("B", w["apd"]["boots"],  0.78, 0.98),
            ]
            for label, data, t, b in apd_map:
                color  = (0, 200, 0) if data["ok"] else (0, 0, 220)
                ax1    = x1 + 12
                ay1    = y1 + int(h * t)
                ax2    = x2 - 12
                ay2    = y1 + int(h * b)
                cv2.rectangle(frame, (ax1, ay1), (ax2, ay2), color, 2)
                cv2.putText(frame, label, (ax1 + 2, ay1 + 14), 1, 0.8, color, 1)

        # HUD overlay
        cv2.rectangle(frame, (0, 0), (340, 60), (30, 30, 30), -1)
        cv2.putText(frame, f"K3 Monitor | {len(workers)} WORKERS", (10, 25), 1, 1.2, (255, 255, 255), 2)
        cv2.putText(frame, datetime.now().strftime("%H:%M:%S"), (10, 50), 1, 1.0, (180, 180, 180), 1)
        return frame

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
        self._model = None
        self._detected_workers = []
        self._last_ss_time = {}
