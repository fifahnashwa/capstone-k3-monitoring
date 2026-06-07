"""
RuleEngine — evaluasi pelanggaran APD berdasarkan zona dan konfigurasi kelas.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import TYPE_CHECKING, Optional

import yaml

if TYPE_CHECKING:
    from src.detection.detector import FrameResult

_ROOT = Path(__file__).parent.parent.parent  # C:\capstone2\capstone2

_ZONE_PATHS = [
    Path("configs/zones.yaml"),                              # CWD/configs/ (jika ada)
    _ROOT / "configs/zones.yaml",                           # project root/configs/
    _ROOT / "detection_worker/configs/zones.yaml",          # reuse file detection_worker
]


@dataclass
class ViolationEvent:
    zone_name: str
    violation_type: str
    severity: str          # "MAJOR" | "MINOR"
    missing_ppe: list      # ["helmet", "boots", ...]
    confidence: float
    snapshot_path: str = ""


class RuleEngine:
    def __init__(self):
        self._zones: dict = {}
        self._load_zones()
        self._load_class_mapping()

    # ------------------------------------------------------------------
    # Setup
    # ------------------------------------------------------------------

    def _load_zones(self) -> None:
        for path in _ZONE_PATHS:
            if path.exists():
                with open(path, encoding="utf-8") as f:
                    data = yaml.safe_load(f) or {}
                for z in data.get("zones", []):
                    self._zones[z["id"]] = z
                return

    def _load_class_mapping(self) -> None:
        from src.utils.config_loader import ConfigLoader
        cfg = ConfigLoader.get()
        mapping: dict = cfg.query("class_mapping") or {}

        self.person_id: int = mapping.get("person_id", 5)
        self.helmet_ids: set = set(mapping.get("helmet_ids", [1]))
        self.vest_ids: set = set(mapping.get("vest_ids", [6]))
        self.boots_ids: set = set(mapping.get("boots_ids", [0]))
        self.no_helmet_ids: set = set(mapping.get("no_helmet_ids", [3]))
        self.no_vest_ids: set = set(mapping.get("no_vest_ids", [4]))
        self.no_boots_ids: set = set(mapping.get("no_boots_ids", [2]))

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    @property
    def zones(self) -> dict:
        return self._zones

    def evaluate(
        self,
        frame_result: Optional["FrameResult"],
        zone_id: str,
    ) -> list[ViolationEvent]:
        """
        Evaluasi frame_result terhadap aturan zona.
        Kembalikan daftar ViolationEvent (kosong jika tidak ada pelanggaran).
        """
        if frame_result is None or not frame_result.detections:
            return []

        zone = self._zones.get(zone_id)
        if not zone:
            return []

        required_ppe: set = set(zone.get("required_ppe", []))
        major_ppe: set = set(zone.get("major_ppe", []))

        persons = [d for d in frame_result.detections if d.class_id == self.person_id]
        items = [d for d in frame_result.detections if d.class_id != self.person_id]

        violations: list[ViolationEvent] = []
        for person in persons:
            present = self._check_ppe(person.bbox, items)
            missing = [ppe for ppe in required_ppe if not present.get(ppe, False)]

            if missing:
                severity = "MAJOR" if any(m in major_ppe for m in missing) else "MINOR"
                violations.append(ViolationEvent(
                    zone_name=zone.get("display_name", zone_id),
                    violation_type="missing_ppe",
                    severity=severity,
                    missing_ppe=missing,
                    confidence=person.confidence,
                ))

        return violations

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------

    def _check_ppe(self, person_bbox: tuple, items: list) -> dict[str, bool]:
        """
        Periksa APD yang hadir di sekitar person_bbox.
        Kembalikan dict {"helmet": bool, "vest": bool, "boots": bool}.
        """
        present = {"helmet": False, "vest": False, "boots": False}

        for item in items:
            if not self._is_associated(item.bbox, person_bbox):
                continue
            cid = item.class_id
            if cid in self.helmet_ids:
                present["helmet"] = True
            elif cid in self.vest_ids:
                present["vest"] = True
            elif cid in self.boots_ids:
                present["boots"] = True
            # Deteksi negatif eksplisit (no_helmet/no_vest/no_boots) tidak mengubah
            # nilai default False — sudah missing secara default.

        return present

    @staticmethod
    def _is_associated(item_bbox: tuple, person_bbox: tuple) -> bool:
        """
        Cek apakah center item berada di dalam (atau dekat) bounding box person.
        Margin bawah lebih longgar untuk mendeteksi boots di bawah kaki.
        """
        ix1, iy1, ix2, iy2 = item_bbox
        px1, py1, px2, py2 = person_bbox

        item_cx = (ix1 + ix2) / 2
        item_cy = (iy1 + iy2) / 2

        pw = max(px2 - px1, 1)
        ph = max(py2 - py1, 1)

        return (
            px1 - pw * 0.25 <= item_cx <= px2 + pw * 0.25
            and py1 - ph * 0.15 <= item_cy <= py2 + ph * 0.45
        )
