from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, time
from pathlib import Path
from typing import Optional

import yaml

_ROOT = Path(__file__).parent.parent.parent  # C:\capstone2\capstone2

_SEARCH_PATHS = [
    Path("configs/shifts.yaml"),                             # CWD/configs/ (jika ada)
    _ROOT / "configs/shifts.yaml",                          # project root/configs/
    _ROOT / "detection_worker/configs/shifts.yaml",         # reuse file detection_worker
]


@dataclass
class ShiftInfo:
    shift_name: str
    is_working_hours: bool
    start_time: Optional[str] = None
    end_time: Optional[str] = None

    def __str__(self) -> str:
        if self.is_working_hours:
            return f"{self.shift_name} ({self.start_time}-{self.end_time})"
        return f"{self.shift_name} (di luar jam kerja)"


class ShiftManager:
    def __init__(self):
        self._shifts: list = []
        self._load()

    def _load(self) -> None:
        for path in _SEARCH_PATHS:
            if path.exists():
                with open(path, encoding="utf-8") as f:
                    self._shifts = yaml.safe_load(f).get("shifts", [])
                return

    def get_current_shift(self) -> ShiftInfo:
        now = datetime.now().time()
        for s in self._shifts:
            start = _parse_time(s["start"])
            end = _parse_time(s["end"])
            if _in_range(now, start, end):
                return ShiftInfo(
                    shift_name=s["name"],
                    is_working_hours=True,
                    start_time=s["start"],
                    end_time=s["end"],
                )
        return ShiftInfo(shift_name="Di Luar Shift", is_working_hours=False)


def _parse_time(t: str) -> time:
    h, m = map(int, t.split(":"))
    return time(h, m)


def _in_range(now: time, start: time, end: time) -> bool:
    """Mendukung shift lintas tengah malam (mis. 23:00-07:00)."""
    if start <= end:
        return start <= now < end
    return now >= start or now < end
