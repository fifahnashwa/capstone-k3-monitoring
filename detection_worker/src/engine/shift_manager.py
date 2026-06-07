from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, time
from pathlib import Path
from typing import Optional

import yaml


@dataclass
class ShiftInfo:
    shift_name: str
    is_working_hours: bool
    start_time: Optional[time] = None
    end_time: Optional[time] = None


class ShiftManager:
    def __init__(self, shifts_config_path: Optional[str] = None):
        if shifts_config_path:
            path = Path(shifts_config_path)
        else:
            path = Path(__file__).parent.parent.parent / "configs" / "shifts.yaml"

        self._shifts: list[dict] = []
        if path.exists():
            with open(path, encoding="utf-8") as f:
                data = yaml.safe_load(f) or {}
            self._shifts = data.get("shifts", [])

    def get_current_shift(self) -> ShiftInfo:
        now = datetime.now().time()

        for shift in self._shifts:
            name  = shift.get("name", "Shift")
            start = self._parse_time(shift.get("start", "00:00"))
            end   = self._parse_time(shift.get("end", "00:00"))

            if self._in_range(now, start, end):
                return ShiftInfo(
                    shift_name=name,
                    is_working_hours=True,
                    start_time=start,
                    end_time=end,
                )

        return ShiftInfo(shift_name="Di luar shift", is_working_hours=False)

    @staticmethod
    def _parse_time(t: str) -> time:
        try:
            h, m = map(int, str(t).split(":"))
            return time(h, m)
        except Exception:
            return time(0, 0)

    @staticmethod
    def _in_range(now: time, start: time, end: time) -> bool:
        if start < end:
            return start <= now < end
        # Overnight shift
        return now >= start or now < end
