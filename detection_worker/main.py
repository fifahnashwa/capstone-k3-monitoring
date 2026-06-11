"""
Detection Worker — FastAPI
Endpoint untuk kontrol kamera APD detection.
"""

import asyncio
import io
import logging
import os
import zipfile
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Optional

import cv2
import httpx
import numpy as np
from fastapi import FastAPI, File, HTTPException, Header, Request, Response, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

# Direktori penyimpanan model — selalu relatif terhadap lokasi file ini
MODELS_DIR = Path(__file__).parent / "models"

import camera_manager as mgr
import face_recognizer
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
    """Ambil daftar kamera aktif dari Laravel lalu start semua secara paralel, load face models."""
    try:
        async with httpx.AsyncClient(timeout=30) as client:
            r = await client.get(f"{LARAVEL_URL}/api/cameras/active", headers=_headers())
            if not r.is_success:
                log.warning(f"Auto-start: Laravel returned {r.status_code}")
                return
            ids = r.json().get("camera_ids", [])
            log.info(f"Auto-starting {len(ids)} active camera(s) in parallel: {ids}")

            async def _start_one(cam_id):
                result = await mgr.start_camera(cam_id)
                log.info(f"  Camera {cam_id}: {result.get('message')}")

            await asyncio.gather(*[_start_one(cid) for cid in ids], return_exceptions=True)
    except Exception as e:
        log.warning(f"Auto-start cameras failed (worker will start without streams): {e}")


@asynccontextmanager
async def lifespan(app: FastAPI):
    log.info("Detection Worker starting...")
    loop = asyncio.get_event_loop()
    await loop.run_in_executor(None, face_recognizer.registry.load)
    # Kamera di-start di background — server langsung siap tanpa menunggu RTSP + YOLO warmup
    asyncio.create_task(_auto_start_cameras())
    yield
    log.info("Detection Worker shutting down...")
    for cam_id in await mgr.get_all_active():
        await mgr.stop_camera(cam_id)


# ── App ───────────────────────────────────────────────────────────────────────

app = FastAPI(title="K3 Detection Worker", version="1.0.0", lifespan=lifespan)

#yang ditambah, buat pengembangan gkpph katanya
from fastapi.middleware.cors import CORSMiddleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# ── Models ────────────────────────────────────────────────────────────────────

class PtzPayload(BaseModel):
    direction: Optional[str] = None
    action:    Optional[str] = None
    speed:     Optional[float] = None
    duration:  Optional[float] = None
    preset_index: Optional[int] = None
    name:      Optional[str] = None


class TrainFaceRequest(BaseModel):
    employee_id:   str
    employee_name: str
    images_b64:    list[str]


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
        loop = asyncio.get_event_loop()
        boundary = b"--frame\r\nContent-Type: image/jpeg\r\n\r\n"
        no_signal = _no_signal_frame()

        while True:
            try:
                instance = await mgr.get_instance(camera_id)

                def _encode_frame():
                    frame = instance.get_latest_frame() if instance else None
                    src = frame if frame is not None else no_signal
                    ok, jpg = cv2.imencode(".jpg", src, [cv2.IMWRITE_JPEG_QUALITY, 88])
                    return jpg.tobytes() if ok else None

                jpg_bytes = await loop.run_in_executor(None, _encode_frame)
                if jpg_bytes:
                    yield boundary + jpg_bytes + b"\r\n"
            except Exception:
                try:
                    _, jpg = cv2.imencode(".jpg", no_signal, [cv2.IMWRITE_JPEG_QUALITY, 88])
                    yield boundary + jpg.tobytes() + b"\r\n"
                except Exception:
                    pass
            await asyncio.sleep(0.02)  # ~50 fps

    return StreamingResponse(
        generate(),
        media_type="multipart/x-mixed-replace;boundary=frame",
        headers={
            "Cache-Control":    "no-cache, no-store, must-revalidate",
            "X-Accel-Buffering": "no",   # matikan nginx proxy buffering
            "Pragma":           "no-cache",
        },
    )


# ── Health Check (from Laravel) ───────────────────────────────────────────────

@app.get("/api/cameras/{camera_id}/health")
async def camera_health(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    return mgr.get_camera_status(camera_id)


# ── Live Worker Count ─────────────────────────────────────────────────────────

@app.get("/api/cameras/{camera_id}/workers")
async def get_worker_count(camera_id: int, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    instance = await mgr.get_instance(camera_id)
    count = len(instance._engine._detected_workers) if instance else 0
    return {"camera_id": camera_id, "worker_count": count}


# ── Worker Face Models ────────────────────────────────────────────────────────

@app.post("/api/worker-faces/train")
async def train_face_model(req: TrainFaceRequest, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    loop = asyncio.get_event_loop()
    result = await loop.run_in_executor(
        None,
        lambda: face_recognizer.train(req.employee_id, req.employee_name, req.images_b64),
    )
    if not result.get("success"):
        raise HTTPException(status_code=422, detail=result.get("message", "Training gagal."))
    return result


@app.delete("/api/worker-faces/{employee_id}")
async def delete_face_model(employee_id: str, x_service_key: str = Header(default="")):
    verify_key(x_service_key)
    deleted = face_recognizer.delete(employee_id)
    return {"success": deleted, "employee_id": employee_id}


# ── Model Management ──────────────────────────────────────────────────────────

@app.get("/api/models")
async def list_models(x_service_key: str = Header(default="")):
    """
    Daftar file model (.pt / .onnx) di direktori models/.
    Juga otomatis mengekstrak file .zip berisi .pt yang ada di direktori
    detection_worker/ (satu level di atas models/) ke dalam models/.
    """
    verify_key(x_service_key)
    MODELS_DIR.mkdir(parents=True, exist_ok=True)

    # Auto-ekstrak .zip berisi .pt dari direktori parent (detection_worker/)
    _auto_extract_zips(MODELS_DIR.parent)
    _auto_extract_zips(MODELS_DIR)

    models = []
    for ext in (".pt", ".onnx"):
        for p in MODELS_DIR.glob(f"*{ext}"):
            if p.name.startswith("_"):
                continue
            models.append({
                "name":     p.name,
                "path":     str(p.resolve()),
                "size_mb":  round(p.stat().st_size / 1024 / 1024, 2),
                "modified": p.stat().st_mtime,
            })

    models.sort(key=lambda m: m["modified"], reverse=True)
    return {"models": models}


def _is_pytorch_zip(zf: zipfile.ZipFile) -> bool:
    """Cek apakah zip adalah file PyTorch model (format internal .pt adalah zip)."""
    names = zf.namelist()
    return any("data.pkl" in n or n.endswith("/data.pkl") for n in names)


def _pt_name_from_zip(zip_path: Path) -> str:
    """Ambil nama .pt dari nama zip. 'best_2.pt.zip' → 'best_2.pt', 'model.zip' → 'model.pt'."""
    stem = zip_path.stem  # 'best_2.pt' atau 'model'
    if stem.lower().endswith(".pt"):
        return stem
    return stem + ".pt"


def _auto_extract_zips(scan_dir: Path) -> None:
    """
    Ekstrak file .zip ke MODELS_DIR. Dua kasus didukung:
    1. Zip berisi file .pt di dalamnya → ekstrak file tersebut.
    2. Zip ADALAH file PyTorch model (format .pt internal = zip) → simpan langsung sebagai .pt.
    """
    for zp in scan_dir.glob("*.zip"):
        try:
            with zipfile.ZipFile(zp) as zf:
                # Kasus 1: ada entry .pt di dalam zip
                pt_files = [n for n in zf.namelist()
                            if n.lower().endswith(".pt") and not n.startswith("__")]
                if pt_files:
                    for pt_entry in pt_files:
                        dest = MODELS_DIR / Path(pt_entry).name
                        if not dest.exists():
                            dest.write_bytes(zf.read(pt_entry))
                            log.info(f"Auto-ekstrak: {zp.name} → {dest.name}")
                    continue

                # Kasus 2: zip itu sendiri adalah model PyTorch
                if _is_pytorch_zip(zf):
                    dest = MODELS_DIR / _pt_name_from_zip(zp)
                    if not dest.exists():
                        dest.write_bytes(zp.read_bytes())
                        log.info(f"Auto-ekstrak (PyTorch zip): {zp.name} → {dest.name}")
        except Exception as exc:
            log.debug(f"Skip zip {zp.name}: {exc}")


@app.post("/api/models/upload")
async def upload_model(
    file: UploadFile = File(...),
    x_service_key: str = Header(default=""),
):
    """
    Upload file model .pt, .onnx, atau .zip yang berisi .pt.
    File disimpan ke direktori models/ di detection worker.
    """
    verify_key(x_service_key)
    MODELS_DIR.mkdir(parents=True, exist_ok=True)

    original_name = file.filename or "model.bin"
    ext = Path(original_name).suffix.lower()

    if ext not in (".pt", ".onnx", ".zip"):
        raise HTTPException(
            status_code=400,
            detail="Format tidak didukung. Gunakan .pt, .onnx, atau .zip yang berisi .pt.",
        )

    content = await file.read()

    if ext == ".zip":
        try:
            with zipfile.ZipFile(io.BytesIO(content)) as zf:
                # Kasus 1: ada entry .pt di dalam zip
                pt_files = [
                    n for n in zf.namelist()
                    if n.lower().endswith(".pt") and not n.startswith("__")
                ]
                if pt_files:
                    pt_entry = pt_files[0]
                    final_name = Path(pt_entry).name
                    final_path = MODELS_DIR / final_name
                    final_path.write_bytes(zf.read(pt_entry))
                elif _is_pytorch_zip(zf):
                    # Kasus 2: zip itu sendiri adalah file PyTorch model
                    final_name = _pt_name_from_zip(Path(original_name))
                    final_path = MODELS_DIR / final_name
                    final_path.write_bytes(content)
                else:
                    raise HTTPException(400, "Zip tidak mengandung file .pt yang valid.")
        except zipfile.BadZipFile:
            raise HTTPException(400, "File zip tidak valid atau rusak.")
    else:
        final_name = original_name
        final_path = MODELS_DIR / final_name
        final_path.write_bytes(content)

    log.info(f"Model diupload: {final_name} ({len(content)/1024/1024:.1f} MB)")
    return {
        "success":    True,
        "model_name": final_name,
        "model_path": str(final_path.resolve()),
        "size_mb":    round(final_path.stat().st_size / 1024 / 1024, 2),
    }


# ── Utilities ─────────────────────────────────────────────────────────────────

def _no_signal_frame() -> np.ndarray:
    frame = np.zeros((360, 640, 3), dtype=np.uint8)
    cv2.rectangle(frame, (0, 0), (640, 360), (20, 20, 20), -1)
    cv2.putText(frame, "NO SIGNAL", (200, 170), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (80, 80, 80), 2)
    cv2.putText(frame, "Kamera tidak aktif", (195, 210), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (60, 60, 60), 1)
    return frame


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001, log_level="info")
