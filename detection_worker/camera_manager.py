"""Mengelola semua CameraInstance aktif."""

import asyncio
import logging
from typing import Dict, Optional

from camera_instance import CameraInstance
from laravel_client import get_camera_config

log = logging.getLogger(__name__)

# Global registry: camera_id -> CameraInstance
_cameras: Dict[int, CameraInstance] = {}
_lock = asyncio.Lock()


async def get_all_active() -> list:
    async with _lock:
        return list(_cameras.keys())


async def get_instance(camera_id: int) -> Optional[CameraInstance]:
    async with _lock:
        return _cameras.get(camera_id)


async def start_camera(camera_id: int) -> dict:
    # Cek duplikat dengan lock sebentar saja
    async with _lock:
        if camera_id in _cameras:
            return {"success": True, "message": f"Kamera {camera_id} sudah berjalan."}

    config = await get_camera_config(camera_id)
    if not config:
        return {"success": False, "message": f"Tidak bisa mengambil config kamera {camera_id}."}

    instance = CameraInstance(config)
    # RTSP connect di executor — tidak blokir event loop, tidak tahan lock
    ok = await asyncio.get_event_loop().run_in_executor(None, instance.start)
    if not ok:
        return {"success": False, "message": f"Gagal memulai stream kamera {camera_id}."}

    async with _lock:
        _cameras[camera_id] = instance
    log.info(f"Camera {camera_id} started.")
    return {"success": True, "message": f"Kamera {camera_id} dimulai."}


async def stop_camera(camera_id: int) -> dict:
    async with _lock:
        instance = _cameras.pop(camera_id, None)
        if not instance:
            return {"success": True, "message": f"Kamera {camera_id} tidak aktif."}

        await asyncio.get_event_loop().run_in_executor(None, instance.stop)
        log.info(f"Camera {camera_id} stopped.")
        return {"success": True, "message": f"Kamera {camera_id} dihentikan."}


async def reload_camera(camera_id: int) -> dict:
    """Stop instance lama, ambil config baru dari Laravel, start instance baru."""
    log.info(f"Reloading camera {camera_id}...")
    await stop_camera(camera_id)

    config = await get_camera_config(camera_id)
    if not config:
        return {"success": False, "message": "Tidak bisa mengambil config terbaru."}

    if not config.get("is_active", True):
        return {"success": True, "message": f"Kamera {camera_id} dinonaktifkan, tidak di-restart."}

    return await start_camera(camera_id)


async def test_connection(camera_id: int) -> dict:
    config = await get_camera_config(camera_id)
    if not config:
        return {"onvif": {"ok": False, "error": "Config tidak ditemukan."}, "rtsp": {"ok": False}, "overall": False}

    instance = CameraInstance(config)
    result = await asyncio.get_event_loop().run_in_executor(None, instance.test_connection)
    return result


async def ptz_command(camera_id: int, payload: dict) -> dict:
    async with _lock:
        instance = _cameras.get(camera_id)

    if not instance:
        # Start instance temporarily for PTZ
        config = await get_camera_config(camera_id)
        if not config:
            return {"success": False, "message": "Kamera tidak aktif dan config tidak ditemukan."}
        return {"success": False, "message": "Kamera tidak aktif. Mulai detection terlebih dahulu."}

    action = payload.get("action")
    if action == "goto_preset":
        idx     = payload.get("preset_index", 0)
        presets = instance.config.get("preset_positions", [])
        ok      = instance._onvif.goto_preset(idx, presets) if instance._onvif else False
        return {"success": ok}
    elif action == "home":
        return instance.ptz_move("home")
    elif action == "save_preset":
        return instance._onvif.save_preset(payload.get("name", "Preset")) if instance._onvif else {"success": False}
    else:
        direction = payload.get("direction", "up")
        speed     = payload.get("speed")
        duration  = payload.get("duration")
        return instance.ptz_move(direction, speed, duration)


async def request_screenshot(camera_id: int) -> dict:
    async with _lock:
        instance = _cameras.get(camera_id)
    if not instance:
        return {"success": False, "message": "Kamera tidak aktif."}
    frame = instance.get_latest_frame()
    if frame is None:
        return {"success": False, "message": "Frame tidak tersedia."}

    from detection_engine import DetectionEngine
    import cv2, uuid, os
    from pathlib import Path
    from datetime import datetime
    storage = os.getenv("STORAGE_PATH", "/var/www/html/storage/app/public/violations")
    date_dir = datetime.now().strftime("%Y-%m-%d")
    save_dir = Path(storage) / date_dir
    save_dir.mkdir(parents=True, exist_ok=True)
    fname = f"manual_{uuid.uuid4().hex}.jpg"
    cv2.imwrite(str(save_dir / fname), frame)
    return {"success": True, "image_path": f"violations/{date_dir}/{fname}"}


def get_camera_status(camera_id: int) -> dict:
    instance = _cameras.get(camera_id)
    if not instance:
        return {"camera_id": camera_id, "status": "stopped"}
    return {
        "camera_id": camera_id,
        "status":    "running",
        "has_frame": instance.get_latest_frame() is not None,
    }
