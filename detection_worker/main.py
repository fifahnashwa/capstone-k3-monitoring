"""
Detection Worker — FastAPI
Endpoint untuk kontrol kamera APD detection.
"""

import logging
import os
from contextlib import asynccontextmanager
from typing import Optional

import cv2
import httpx
import numpy as np
from fastapi import FastAPI, HTTPException, Header, Request, Response
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

import camera_manager as mgr
from laravel_client import get_camera_config, LARAVEL_URL, _headers

# ── Logging ───────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
log = logging.getLogger(__name__)

SERVICE_KEY = os.getenv("SERVICE_KEY", "")


# ── Auth ──────────────────────────────────────────────────────────────────────

def verify_key(x_service_key: str = Header(default="")):
    if SERVICE_KEY and x_service_key != SERVICE_KEY:
        raise HTTPException(status_code=401, detail="Unauthorized")


# ── Lifespan ──────────────────────────────────────────────────────────────────

async def _auto_start_cameras():
    """Ambil daftar kamera aktif dari Laravel lalu start masing-masing."""
    try:
        async with httpx.AsyncClient(timeout=30) as client:
            r = await client.get(f"{LARAVEL_URL}/api/cameras/active", headers=_headers())
            if r.is_success:
                ids = r.json().get("camera_ids", [])
                log.info(f"Auto-starting {len(ids)} active camera(s): {ids}")
                for cam_id in ids:
                    result = await mgr.start_camera(cam_id)
                    log.info(f"  Camera {cam_id}: {result.get('message')}")
            else:
                log.warning(f"Auto-start: Laravel returned {r.status_code}")
    except Exception as e:
        log.warning(f"Auto-start cameras failed (worker will start without streams): {e}")


@asynccontextmanager
async def lifespan(app: FastAPI):
    log.info("Detection Worker starting...")
    await _auto_start_cameras()
    yield
    log.info("Detection Worker shutting down...")
    for cam_id in await mgr.get_all_active():
        await mgr.stop_camera(cam_id)


# ── App ───────────────────────────────────────────────────────────────────────

app = FastAPI(title="K3 Detection Worker", version="1.0.0", lifespan=lifespan)


# ── Models ────────────────────────────────────────────────────────────────────

class PtzPayload(BaseModel):
    direction: Optional[str] = None
    action:    Optional[str] = None
    speed:     Optional[float] = None
    duration:  Optional[float] = None
    preset_index: Optional[int] = None
    name:      Optional[str] = None


# ── Health ────────────────────────────────────────────────────────────────────

@app.get("/health")
async def health():
    return {"status": "ok", "active_cameras": await mgr.get_all_active()}


# ── Cameras List ──────────────────────────────────────────────────────────────

@app.get("/api/cameras")
async def list_cameras(x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    active = await mgr.get_all_active()
    return {"active_camera_ids": active}


# ── Start / Stop / Reload ─────────────────────────────────────────────────────

@app.post("/api/cameras/{camera_id}/start")
async def start_camera(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    result = await mgr.start_camera(camera_id)
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result["message"])
    return result


@app.post("/api/cameras/{camera_id}/stop")
async def stop_camera(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    return await mgr.stop_camera(camera_id)


@app.post("/api/cameras/{camera_id}/reload")
async def reload_camera(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    result = await mgr.reload_camera(camera_id)
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result["message"])
    return result


# ── Test Connection ───────────────────────────────────────────────────────────

@app.post("/api/cameras/{camera_id}/test-connection")
async def test_connection(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    result = await mgr.test_connection(camera_id)
    return result


# ── PTZ ───────────────────────────────────────────────────────────────────────

@app.post("/api/cameras/{camera_id}/ptz")
async def ptz_control(camera_id: int, payload: PtzPayload, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    result = await mgr.ptz_command(camera_id, payload.model_dump(exclude_none=True))
    if not result.get("success"):
        raise HTTPException(status_code=400, detail=result.get("message", "PTZ gagal"))
    return result


# ── Screenshot ────────────────────────────────────────────────────────────────

@app.post("/api/cameras/{camera_id}/screenshot")
async def screenshot(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    result = await mgr.request_screenshot(camera_id)
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result["message"])
    return result


# ── MJPEG Stream ──────────────────────────────────────────────────────────────

@app.get("/api/cameras/{camera_id}/stream")
async def stream(camera_id: int, x_service_key: str = Header(default="")):
    """MJPEG stream endpoint — bisa diakses langsung dari browser via <img>."""

    async def generate():
        import asyncio
        boundary = b"--frame\r\nContent-Type: image/jpeg\r\n\r\n"
        no_signal = _no_signal_frame()
        while True:
            try:
                instance = await mgr.get_instance(camera_id)
                frame = instance.get_latest_frame() if instance else None
                if frame is None:
                    frame = no_signal
                ok, jpg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
                if ok:
                    yield boundary + jpg.tobytes() + b"\r\n"
            except Exception:
                try:
                    _, jpg = cv2.imencode(".jpg", no_signal)
                    yield boundary + jpg.tobytes() + b"\r\n"
                except Exception:
                    pass
            await asyncio.sleep(0.04)  # ~25 fps

    return StreamingResponse(
        generate(),
        media_type="multipart/x-mixed-replace;boundary=frame",
        headers={"Cache-Control": "no-cache"},
    )


# ── Health Check (from Laravel) ───────────────────────────────────────────────

@app.get("/api/cameras/{camera_id}/health")
async def camera_health(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    return mgr.get_camera_status(camera_id)


# ── Utilities ─────────────────────────────────────────────────────────────────

def _no_signal_frame() -> np.ndarray:
    frame = np.zeros((360, 640, 3), dtype=np.uint8)
    cv2.rectangle(frame, (0, 0), (640, 360), (20, 20, 20), -1)
    cv2.putText(frame, "NO SIGNAL", (200, 170), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (80, 80, 80), 2)
    cv2.putText(frame, "Kamera tidak aktif", (195, 210), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (60, 60, 60), 1)
    return frame


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000, log_level="info")
