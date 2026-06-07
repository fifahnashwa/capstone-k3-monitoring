"""HTTP client untuk komunikasi dengan Laravel API."""

import httpx
import logging
import os
from pathlib import Path
from typing import Optional

# Load .env lokal jika ada (untuk run di luar Docker)
_env_file = Path(__file__).parent / ".env"
if _env_file.exists():
    for line in _env_file.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            os.environ.setdefault(k.strip(), v.strip())

log = logging.getLogger(__name__)

LARAVEL_URL  = os.getenv("LARAVEL_URL", "http://app:80").rstrip("/")
SERVICE_KEY  = os.getenv("SERVICE_KEY", "")
TIMEOUT      = float(os.getenv("LARAVEL_TIMEOUT", "10"))


def _headers() -> dict:
    return {"X-Service-Key": SERVICE_KEY, "Accept": "application/json"}


async def get_camera_config(camera_id: int) -> Optional[dict]:
    url = f"{LARAVEL_URL}/api/cameras/{camera_id}/config"
    async with httpx.AsyncClient(timeout=TIMEOUT) as client:
        try:
            r = await client.get(url, headers=_headers())
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.error(f"get_camera_config({camera_id}) failed: {e}")
            return None


async def post_violation(payload: dict) -> bool:
    url = f"{LARAVEL_URL}/api/violations"
    async with httpx.AsyncClient(timeout=TIMEOUT) as client:
        try:
            r = await client.post(url, headers=_headers(), json=payload)
            if r.status_code not in (200, 201):
                log.warning(f"post_violation returned {r.status_code}: {r.text[:200]}")
            return r.status_code in (200, 201)
        except Exception as e:
            log.error(f"post_violation failed: {e}")
            return False


async def post_health_check(camera_id: int, status: str, extra: dict = None) -> bool:
    url = f"{LARAVEL_URL}/api/cameras/{camera_id}/health-check"
    payload = {"status": status, **(extra or {})}
    async with httpx.AsyncClient(timeout=5) as client:
        try:
            r = await client.post(url, headers=_headers(), json=payload)
            return r.is_success
        except Exception as e:
            log.debug(f"post_health_check({camera_id}) failed: {e}")
            return False
