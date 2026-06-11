"""
Test deteksi langsung — tanpa dashboard/Laravel/HTTP.
Jalankan: python test_detection.py
Tekan Q untuk keluar.
"""

import os, sys, time
os.environ["YOLO_VERBOSE"] = "False"
os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

import cv2
from detection_engine import DetectionEngine

# ── Konfigurasi ─────────────────────────────────────────────────────────────
# Ganti RTSP_URL jika perlu (atau pakai webcam: 0)
RTSP_URL = "rtsp://TC70AKelompok2A2:Kelompok2A2@172.21.252.20:554/stream2"
# RTSP_URL = 0   # ← uncomment untuk webcam lokal

MODEL_PATH = r"C:\capstone2\capstone2\detection_worker\models\best_2.pt"
# MODEL_PATH = r"C:\capstone2\capstone2\detection_worker\models\best.onnx"

CONFIG = {
    "id": 99,
    "ai_model_path": MODEL_PATH,
    "detection_size": 640,
    "confidence_threshold": 0.25,
    "boots_confidence_threshold": 0.15,
    "person_confidence_threshold": 0.10,
    "process_every_n_frame": 1,
    "auto_screenshot": False,
    "screenshot_cooldown": 999,
    "apd_lock_seconds": 1.0,
    "class_mapping": {
        "person_id": 5,
        "helmet_ids": [1], "vest_ids": [6], "boots_ids": [0],
        "no_helmet_ids": [3], "no_vest_ids": [4], "no_boots_ids": [2],
    },
}
# ─────────────────────────────────────────────────────────────────────────────

print("Memuat model...")
engine = DetectionEngine(CONFIG)
ok = engine.load_model()
if not ok:
    print("GAGAL memuat model. Cek MODEL_PATH.")
    sys.exit(1)
print("Model dimuat. Membuka stream...")

cap = cv2.VideoCapture(RTSP_URL, cv2.CAP_FFMPEG)
cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
if not cap.isOpened():
    print(f"GAGAL membuka stream: {RTSP_URL}")
    sys.exit(1)
print("Stream terbuka. Tekan Q untuk keluar.\n")

fps_t = time.time()
frame_count = 0

while True:
    ret, frame = cap.read()
    if not ret:
        print("Frame kosong, mencoba lagi...")
        time.sleep(0.1)
        continue

    annotated, violations = engine.process_frame(frame)

    # Tampilkan info FPS di pojok kanan atas
    frame_count += 1
    if time.time() - fps_t >= 1.0:
        fps = frame_count / (time.time() - fps_t)
        fps_t = time.time(); frame_count = 0
        cv2.putText(annotated, f"FPS:{fps:.1f}", (annotated.shape[1]-90, 20),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0,255,255), 1)

    for v in violations:
        print(f"[VIOLATION] worker={v['worker_idx']+1} missing={v['missing_apd']} conf={v['confidence']:.2f}")

    cv2.imshow("K3 Detection Test (tekan Q keluar)", annotated)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
engine.unload()
print("Selesai.")
