"""
Contoh penggunaan SafeGuard-CV pipeline.

Jalankan dari folder C:\\capstone2\\capstone2:
    python example_run.py

Atau gunakan main.py langsung via CLI:
    python main.py --source 0 --zone laptop_test
    python main.py --source 1 --zone forklift_area
    python main.py --source rtsp://admin:pass@192.168.1.100/stream --zone forklift_area
    python main.py --source video_test.mp4 --zone laptop_test
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

from main import SafeGuardPipeline


# ── Contoh 1: Webcam default (index 0) ───────────────────────────────
def run_webcam():
    pipeline = SafeGuardPipeline(source=0, zone_id="laptop_test")
    pipeline.run()


# ── Contoh 2: Video file (untuk testing tanpa kamera) ────────────────
def run_video_file(video_path: str = "test_video.mp4"):
    pipeline = SafeGuardPipeline(source=video_path, zone_id="laptop_test")
    pipeline.run()


# ── Contoh 3: RTSP CCTV ──────────────────────────────────────────────
def run_rtsp(rtsp_url: str):
    pipeline = SafeGuardPipeline(source=rtsp_url, zone_id="forklift_area")
    pipeline.run()


# ── Contoh 4: Kamera USB kedua ───────────────────────────────────────
def run_usb_cam(cam_index: int = 1):
    pipeline = SafeGuardPipeline(source=cam_index, zone_id="empty_box_area")
    pipeline.run()


if __name__ == "__main__":
    # Ganti baris di bawah sesuai kebutuhan:
    run_webcam()

    # run_video_file("test_video.mp4")
    # run_rtsp("rtsp://admin:password@10.134.28.100:554/stream1")
    # run_usb_cam(1)
