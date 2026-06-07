"""Wrapper ONVIF untuk PTZ dan connection test."""

import logging
import time
from typing import Optional

log = logging.getLogger(__name__)


class OnvifHandler:
    def __init__(self, ip: str, port: int, username: str, password: str):
        self.ip       = ip
        self.port     = port
        self.username = username
        self.password = password
        self._cam            = None
        self._ptz            = None
        self._profile_token  = None

    def connect(self) -> bool:
        try:
            from onvif import ONVIFCamera
            self._cam   = ONVIFCamera(self.ip, self.port, self.username, self.password)
            media       = self._cam.create_media_service()
            profiles    = media.GetProfiles()
            self._profile_token = profiles[0].token if profiles else None
            self._ptz   = self._cam.create_ptz_service()
            log.info(f"ONVIF connected: {self.ip}:{self.port}")
            return True
        except Exception as e:
            log.error(f"ONVIF connect failed ({self.ip}:{self.port}): {e}")
            return False

    def is_connected(self) -> bool:
        return self._cam is not None and self._profile_token is not None

    def move(self, direction: str, speed: float = 0.6, duration: float = 0.4) -> bool:
        if not self.is_connected() or not self._ptz:
            return False
        try:
            req = self._ptz.create_type("ContinuousMove")
            req.ProfileToken = self._profile_token
            v = {"x": 0.0, "y": 0.0}
            if direction == "up":      v["y"] =  speed
            elif direction == "down":  v["y"] = -speed
            elif direction == "left":  v["x"] = -speed
            elif direction == "right": v["x"] =  speed
            elif direction == "home":
                self._ptz.GotoHomePosition({"ProfileToken": self._profile_token, "Speed": speed})
                return True
            req.Velocity = {"PanTilt": v}
            self._ptz.ContinuousMove(req)
            time.sleep(duration)
            self._ptz.Stop({"ProfileToken": self._profile_token})
            return True
        except Exception as e:
            log.error(f"PTZ move {direction} failed: {e}")
            return False

    def goto_preset(self, preset_index: int, presets: list) -> bool:
        if not self.is_connected() or preset_index >= len(presets):
            return False
        preset = presets[preset_index]
        log.info(f"Goto preset: {preset.get('name', preset_index)}")
        return True

    def save_preset(self, name: str) -> dict:
        return {"success": True, "name": name, "note": "Preset saved (stub)"}

    def test_connection(self) -> dict:
        import socket, time
        result = {"onvif": {"ok": False}, "rtsp": {"ok": False}, "overall": False}
        # Test ONVIF port
        t0 = time.monotonic()
        try:
            s = socket.create_connection((self.ip, self.port), timeout=5)
            s.close()
            result["onvif"] = {"ok": True, "latency_ms": round((time.monotonic() - t0) * 1000)}
        except Exception as e:
            result["onvif"] = {"ok": False, "error": str(e)}

        # Verify ONVIF auth
        if result["onvif"]["ok"]:
            connected = self.connect()
            if not connected:
                result["onvif"] = {"ok": False, "error": "Auth gagal atau ONVIF tidak didukung."}

        result["overall"] = result["onvif"]["ok"]
        return result

    def disconnect(self):
        self._cam = None
        self._ptz = None
        self._profile_token = None
