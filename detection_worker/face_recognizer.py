"""
Face recognition module using insightface.
Handles training (embedding extraction), deletion, and identification.
"""

import base64
import logging
import os
import pickle
import time
from pathlib import Path
from typing import Optional

import numpy as np

log = logging.getLogger(__name__)

FACE_MODELS_DIR = Path(os.getenv("FACE_MODELS_PATH", "./face_models"))
SIMILARITY_THRESHOLD = float(os.getenv("FACE_SIMILARITY_THRESHOLD", "0.45"))

_insight_app = None


def _get_insight_app():
    global _insight_app
    if _insight_app is None:
        import torch
        import insightface
        cuda_ok = torch.cuda.is_available()
        providers = (
            ["CUDAExecutionProvider", "CPUExecutionProvider"]
            if cuda_ok else
            ["CPUExecutionProvider"]
        )
        ctx_id = 0 if cuda_ok else -1
        _insight_app = insightface.app.FaceAnalysis(
            name="buffalo_l",
            providers=providers,
        )
        _insight_app.prepare(ctx_id=ctx_id, det_size=(320, 320))
        device_label = f"GPU ({torch.cuda.get_device_name(0)})" if cuda_ok else "CPU"
        log.info(f"InsightFace loaded (buffalo_l, {device_label}, det_size=320)")
    return _insight_app


def _extract_embedding(image_b64: str) -> Optional[np.ndarray]:
    import cv2
    try:
        img_bytes = base64.b64decode(image_b64)
        arr = np.frombuffer(img_bytes, dtype=np.uint8)
        img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    except Exception as e:
        log.warning(f"Failed to decode base64 image: {e}")
        return None
    if img is None:
        log.warning("imdecode returned None")
        return None
    # Resize jika terlalu besar agar tidak OOM
    h, w = img.shape[:2]
    if max(h, w) > 1920:
        scale = 1920 / max(h, w)
        img = cv2.resize(img, (int(w * scale), int(h * scale)))
    img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    try:
        faces = _get_insight_app().get(img_rgb)
        log.info(f"Detected {len(faces)} face(s) in image")
    except Exception as e:
        log.warning(f"InsightFace error: {e}")
        return None
    if not faces:
        log.warning("No face detected in image")
        return None
    # pick largest face
    face = max(faces, key=lambda f: (f.bbox[2] - f.bbox[0]) * (f.bbox[3] - f.bbox[1]))
    return face.normed_embedding


def _pkl_path(employee_id: str) -> Path:
    return FACE_MODELS_DIR / f"{employee_id}.pkl"


# ── Public API ────────────────────────────────────────────────────────────────

def train(employee_id: str, employee_name: str, images_b64: list) -> dict:
    """Extract embeddings from base64-encoded photos and save .pkl."""
    FACE_MODELS_DIR.mkdir(parents=True, exist_ok=True)
    embeddings = []
    for i, b64 in enumerate(images_b64):
        emb = _extract_embedding(b64)
        if emb is not None:
            embeddings.append(emb)
        else:
            log.warning(f"No face detected in image #{i + 1}")

    if not embeddings:
        return {"success": False, "message": "Tidak ada wajah terdeteksi di foto yang diunggah."}

    data = {
        "employee_id":   employee_id,
        "employee_name": employee_name,
        "embeddings":    embeddings,
        "trained_at":    time.time(),
    }
    pkl = _pkl_path(employee_id)
    with open(pkl, "wb") as f:
        pickle.dump(data, f)

    registry.load()
    log.info(f"Trained {employee_name}: {len(embeddings)} embeddings → {pkl}")
    return {
        "success":          True,
        "embeddings_count": len(embeddings),
        "model_path":       str(pkl),
    }


def delete(employee_id: str) -> bool:
    """Delete .pkl model and reload registry."""
    pkl = _pkl_path(employee_id)
    if pkl.exists():
        pkl.unlink()
        registry.load()
        log.info(f"Deleted face model: {pkl}")
        return True
    return False


# ── Registry ──────────────────────────────────────────────────────────────────

class FaceRegistry:
    """In-memory store of all known face embeddings loaded from .pkl files."""

    def __init__(self):
        self._records: list = []

    def load(self):
        FACE_MODELS_DIR.mkdir(parents=True, exist_ok=True)
        self._records = []
        for pkl in FACE_MODELS_DIR.glob("*.pkl"):
            try:
                with open(pkl, "rb") as f:
                    rec = pickle.load(f)
                self._records.append(rec)
            except Exception as e:
                log.warning(f"Could not load {pkl}: {e}")
        log.info(f"FaceRegistry: loaded {len(self._records)} model(s)")

    def identify(self, embedding: np.ndarray) -> Optional[tuple]:
        """Return (employee_id, employee_name, similarity) or None."""
        best_sim = -1.0
        best_rec = None
        for rec in self._records:
            for emb in rec["embeddings"]:
                sim = float(np.dot(embedding, emb))
                if sim > best_sim:
                    best_sim = sim
                    best_rec = rec
        if best_rec and best_sim >= SIMILARITY_THRESHOLD:
            return best_rec["employee_id"], best_rec["employee_name"], best_sim
        return None

    def identify_from_frame(self, frame_bgr) -> list:
        """Detect + identify all faces in a BGR frame.
        Returns list of (emp_id, emp_name, sim, bbox) for each matched face.
        bbox = (x1, y1, x2, y2) in original frame coordinates.
        """
        if not self._records:
            return []
        try:
            import cv2
            orig_h, orig_w = frame_bgr.shape[:2]
            img_rgb = cv2.cvtColor(frame_bgr, cv2.COLOR_BGR2RGB)
            scale = 1.0
            if max(orig_h, orig_w) > 640:
                scale = 640 / max(orig_h, orig_w)
                img_rgb = cv2.resize(img_rgb, (int(orig_w * scale), int(orig_h * scale)))
            faces = _get_insight_app().get(img_rgb)
            if not faces:
                return []
            results = []
            for face in faces:
                match = self.identify(face.normed_embedding)
                if match:
                    emp_id, emp_name, sim = match
                    # Kembalikan bbox ke koordinat frame asli
                    x1, y1, x2, y2 = face.bbox
                    bbox = (
                        int(x1 / scale), int(y1 / scale),
                        int(x2 / scale), int(y2 / scale),
                    )
                    results.append((emp_id, emp_name, sim, bbox))
            return results
        except Exception as e:
            log.debug(f"Face identification skipped: {e}")
            return []

    @property
    def has_models(self) -> bool:
        return len(self._records) > 0


registry = FaceRegistry()
