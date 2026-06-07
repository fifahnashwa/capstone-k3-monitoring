from __future__ import annotations

import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

import cv2
import numpy as np


@dataclass
class PersonDetection:
    box: list        # [x1, y1, x2, y2]
    confidence: float
    apd: dict = field(default_factory=lambda: {
        "helmet": {"ok": False, "conf": 0.0},
        "vest":   {"ok": False, "conf": 0.0},
        "boots":  {"ok": False, "conf": 0.0},
    })
    name: str = ""   # dari face recognition


@dataclass
class FrameResult:
    persons: list[PersonDetection] = field(default_factory=list)
    inference_ms: float = 0.0

    @property
    def worker_count(self) -> int:
        return len(self.persons)


class PPEDetector:
    def __init__(
        self,
        model_path: str,
        fallback_model: str = "yolov8n.pt",
        confidence_threshold: float = 0.40,
        iou_threshold: float = 0.45,
        input_size: int = 640,
        frame_skip: int = 2,
        class_mapping: dict = None,
    ):
        self.model_path = model_path
        self.fallback_model = fallback_model
        self.conf         = confidence_threshold
        self.person_conf  = float(max(0.15, confidence_threshold * 0.6))
        self.iou          = iou_threshold
        self.input_size   = input_size
        self.frame_skip   = frame_skip

        mapping          = class_mapping or {}
        self.person_id   = mapping.get("person_id", 6)
        self.helmet_ids  = mapping.get("helmet_ids", [0])
        self.vest_ids    = mapping.get("vest_ids", [2])
        self.boots_ids   = mapping.get("boots_ids", [3])

        self._model       = None
        self._frame_idx   = 0
        self._last_result: Optional[FrameResult] = None
        self._infer_times: list[float] = []

    # ── Properties ────────────────────────────────────────────────────────────

    @property
    def model_classes(self) -> dict:
        return self._model.names if self._model else {}

    @property
    def avg_inference_ms(self) -> float:
        recent = self._infer_times[-30:]
        return sum(recent) / len(recent) if recent else 0.0

    # ── Lifecycle ─────────────────────────────────────────────────────────────

    def load(self) -> bool:
        try:
            from ultralytics import YOLO
            path = Path(self.model_path)
            if not path.exists():
                path = Path(self.fallback_model)
                if not path.exists():
                    return False
            self._model = YOLO(str(path), task="detect")
            return True
        except Exception as e:
            print(f"[PPEDetector] load error: {e}")
            return False

    # ── Inference ─────────────────────────────────────────────────────────────

    def predict(self, frame: np.ndarray) -> Optional[FrameResult]:
        self._frame_idx += 1
        if self._frame_idx % self.frame_skip != 0:
            return self._last_result

        t0 = time.time()
        try:
            results = self._model.predict(
                frame,
                imgsz=self.input_size,
                conf=self.person_conf,
                iou=self.iou,
                max_det=50,
                verbose=False,
            )[0]
        except Exception as e:
            print(f"[PPEDetector] predict error: {e}")
            return self._last_result

        ms = (time.time() - t0) * 1000
        self._infer_times.append(ms)

        persons: list[PersonDetection] = []
        apd_items: list[dict] = []

        for box in results.boxes:
            cls  = int(box.cls[0])
            xyxy = list(map(int, box.xyxy[0]))
            conf = float(box.conf[0])

            if cls == self.person_id and conf >= self.person_conf:
                persons.append(PersonDetection(box=xyxy, confidence=conf))
            elif cls != self.person_id and conf >= self.conf:
                apd_items.append({"cls": cls, "box": xyxy, "conf": conf})

        # Urutkan kiri → kanan (index stabil)
        persons.sort(key=lambda p: (p.box[0] + p.box[2]) / 2)

        # Asosiasi APD → worker terdekat
        self._assign_apd(persons, apd_items)

        self._last_result = FrameResult(persons=persons, inference_ms=ms)
        return self._last_result

    def _assign_apd(self, persons: list[PersonDetection], apd_items: list[dict]):
        for item in apd_items:
            ix1, iy1, ix2, iy2 = item["box"]
            item_cx = (ix1 + ix2) / 2
            item_cy = (iy1 + iy2) / 2

            best_p    = None
            best_dist = float("inf")

            for p in persons:
                wx1, wy1, wx2, wy2 = p.box
                ww, wh = wx2 - wx1, wy2 - wy1
                mx, my = ww * 0.2, wh * 0.2

                if not (wx1 - mx <= item_cx <= wx2 + mx and wy1 - my <= item_cy <= wy2 + my):
                    continue

                wcx = (wx1 + wx2) / 2
                wcy = (wy1 + wy2) / 2
                dist = ((item_cx - wcx) ** 2 + (item_cy - wcy) ** 2) ** 0.5 / (ww + wh + 1)
                if dist < best_dist:
                    best_dist = dist
                    best_p = p

            if best_p is None:
                continue

            cls, conf = item["cls"], item["conf"]
            if cls in self.helmet_ids and conf > best_p.apd["helmet"]["conf"]:
                best_p.apd["helmet"] = {"ok": True, "conf": conf}
            elif cls in self.vest_ids and conf > best_p.apd["vest"]["conf"]:
                best_p.apd["vest"] = {"ok": True, "conf": conf}
            elif cls in self.boots_ids and conf > best_p.apd["boots"]["conf"]:
                best_p.apd["boots"] = {"ok": True, "conf": conf}

    # ── Visualisasi ───────────────────────────────────────────────────────────

    def draw(
        self,
        frame: np.ndarray,
        result: FrameResult,
        class_colors: dict = None,
    ) -> np.ndarray:
        img = frame.copy()

        for i, p in enumerate(result.persons):
            x1, y1, x2, y2 = p.box
            h_ok  = p.apd["helmet"]["ok"]
            v_ok  = p.apd["vest"]["ok"]
            b_ok  = p.apd["boots"]["ok"]

            # Warna bounding box: hijau kalau semua lengkap, merah kalau ada yang kurang
            box_color = (0, 200, 0) if (h_ok and v_ok and b_ok) else (0, 60, 220)
            cv2.rectangle(img, (x1, y1), (x2, y2), box_color, 2)

            # Label nama worker
            name_label = f"WORKER {i+1}" + (f" : {p.name}" if p.name else "")
            lw = cv2.getTextSize(name_label, cv2.FONT_HERSHEY_SIMPLEX, 0.44, 1)[0][0] + 8
            cv2.rectangle(img, (x1, y1 - 20), (x1 + lw, y1), (0, 60, 200), -1)
            cv2.putText(img, name_label, (x1 + 4, y1 - 5),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.44, (255, 255, 255), 1, cv2.LINE_AA)

            # Panel APD (kanan bounding box)
            panel = [
                ("H", p.apd["helmet"]),
                ("V", p.apd["vest"]),
                ("B", p.apd["boots"]),
            ]
            px, py, pw, ph = x2 + 4, y1, 88, 20
            for lbl, apd in panel:
                color = (0, 200, 0) if apd["ok"] else (0, 40, 220)
                status = f"OK {apd['conf']*100:.0f}%" if apd["ok"] else "!!"
                cv2.rectangle(img, (px, py), (px + pw, py + ph), (20, 20, 20), -1)
                cv2.putText(img, f"{lbl} {status}", (px + 3, py + 14),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.42, color, 1, cv2.LINE_AA)
                py += ph + 1

            # Banner APD yang hilang di bawah box
            missing = [k.upper() for k, v in
                       {"HELMET": h_ok, "VEST": v_ok, "BOOTS": b_ok}.items() if not v]
            if missing:
                banner = "MISS: " + " | ".join(missing)
                bw = x2 - x1
                cv2.rectangle(img, (x1, y2 - 22), (x1 + bw, y2), (0, 0, 180), -1)
                cv2.putText(img, banner, (x1 + 4, y2 - 6),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.42, (255, 255, 255), 1, cv2.LINE_AA)

        return img
