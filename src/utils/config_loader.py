from __future__ import annotations

from pathlib import Path
from typing import Any, Optional

import yaml

_ROOT = Path(__file__).parent.parent.parent  # C:\capstone2\capstone2

# Urutan pencarian: CWD → project root → detection_worker (fallback)
_SEARCH_PATHS = [
    Path("configs/config.yaml"),
    _ROOT / "configs/config.yaml",
    _ROOT / "detection_worker/configs/config.yaml",
]


class ConfigLoader:
    _instance: Optional["ConfigLoader"] = None

    def __init__(self, data: dict):
        self._data = data

    @classmethod
    def get(cls) -> "ConfigLoader":
        if cls._instance is None:
            data: dict = {}
            for path in _SEARCH_PATHS:
                if path.exists():
                    with open(path, encoding="utf-8") as f:
                        data = yaml.safe_load(f) or {}
                    break
            cls._instance = cls(data)
        return cls._instance

    def query(self, key: str, default: Any = None) -> Any:
        """Akses nested key dengan notasi titik, misal 'detection.model_path'."""
        parts = key.split(".")
        val: Any = self._data
        for part in parts:
            if not isinstance(val, dict):
                return default
            val = val.get(part)
            if val is None:
                return default
        return val if val is not None else default
