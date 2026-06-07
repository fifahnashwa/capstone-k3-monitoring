from __future__ import annotations

from pathlib import Path
from typing import Any

import yaml


class ConfigNode:
    def __init__(self, data: dict):
        self._data = data

    def query(self, key: str, default: Any = None) -> Any:
        keys = key.split(".")
        val = self._data
        for k in keys:
            if not isinstance(val, dict):
                return default
            val = val.get(k)
            if val is None:
                return default
        if isinstance(val, dict):
            return ConfigNode(val)
        return val


class ConfigLoader:
    _instance: ConfigNode | None = None

    @classmethod
    def get(cls) -> ConfigNode:
        if cls._instance is None:
            config_path = Path(__file__).parent.parent.parent / "configs" / "config.yaml"
            if not config_path.exists():
                cls._instance = ConfigNode({})
            else:
                with open(config_path, encoding="utf-8") as f:
                    data = yaml.safe_load(f) or {}
                cls._instance = ConfigNode(data)
        return cls._instance
