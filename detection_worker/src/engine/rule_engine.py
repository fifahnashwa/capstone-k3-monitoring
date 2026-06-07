from __future__ import annotations

import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

import yaml

from src.detection.detector import FrameResult, PersonDetection


@dataclass
class ViolationEvent:
    zone_name: str
    violation_type: str       # "apd"
    severity: str             # "MAJOR" | "MINOR"
    missing_ppe: list[str]
    confidence: float
    person_name: str = ""
    snapshot_path: str = ""


class RuleEngine:
    # Cooldown antar violation per worker (detik)
    VIOLATION_COOLDOWN = 8

    def __init__(self, zones_config_path: Optional[str] = None):
        if zones_config_path:
            path = Path(zones_config_path)
        else:
            path = Path(__file__).parent.parent.parent / "configs" / "zones.yaml"

        self.zones: dict = {}
        if path.exists():
            with open(path, encoding="utf-8") as f:
                data = yaml.safe_load(f) or {}
            for z in data.get("zones", []):
                self.zones[z["id"]] = z

        # cooldown tracker: (zone_id, worker_idx) -> last_violation_time
        self._cooldowns: dict[tuple, float] = {}

    def evaluate(self, result: FrameResult, zone_id: str) -> list[ViolationEvent]:
        zone = self.zones.get(zone_id, {})
        required: list[str] = zone.get("required_ppe", ["helmet", "vest", "boots"])
        display_name: str    = zone.get("display_name", zone_id)

        # Pemetaan APD ke severity
        major_ppe: list[str] = zone.get("major_ppe", ["helmet", "boots"])
        minor_ppe: list[str] = zone.get("minor_ppe", ["vest"])

        violations: list[ViolationEvent] = []
        now = time.time()

        for i, person in enumerate(result.persons):
            missing = [
                ppe for ppe in required
                if not person.apd.get(ppe, {}).get("ok", False)
            ]
            if not missing:
                continue

            key = (zone_id, i)
            if now - self._cooldowns.get(key, 0) < self.VIOLATION_COOLDOWN:
                continue
            self._cooldowns[key] = now

            # Severity: MAJOR jika ada APD major yang hilang
            severity = "MAJOR" if any(m in major_ppe for m in missing) else "MINOR"
            avg_conf = sum(
                person.apd.get(ppe, {}).get("conf", person.confidence)
                for ppe in required
            ) / len(required)

            violations.append(ViolationEvent(
                zone_name=display_name,
                violation_type="apd",
                severity=severity,
                missing_ppe=missing,
                confidence=avg_conf,
                person_name=person.name,
            ))

        return violations
