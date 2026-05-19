import cv2
import threading
import time
import os
import asyncio
from ultralytics import YOLO
from onvif import ONVIFCamera

# ==================== KONFIGURASI SISTEM ====================
IP_ADDRESS = '10.134.28.100'
PORT_ONVIF = 2020
USER_KAMERA = 'TC70BKelompok2A2' 
PASS_KAMERA = 'Kelompok2A2'
MODEL_PATH = r'C:\capstone\best.onnx' 

DETECTION_SIZE = 640 
CONFIDENCE_THRESHOLD = 0.40 
PROCESS_EVERY_N_FRAME = 2  # Lewati frame agar CPU enteng

PERSON_ID = 6
HELMET_IDS = [0]        
VEST_IDS = [2]          
BOOTS_IDS = [3]         

os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "rtsp_transport;tcp"
os.environ['YOLO_VERBOSE'] = 'False' 
# ============================================================

running = True
yolo_model = None
ptz_service = None
media_profile_token = None
detected_workers = []
last_key_pressed = "NONE" # Indikator debug di layar

class RTSPStreamReader:
    def __init__(self, url):
        self.cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
        self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        self.grabbed, self.frame = self.cap.read()
        self.started = False
        self.read_lock = threading.Lock()

    def start(self):
        if not self.started:
            self.started = True
            self.thread = threading.Thread(target=self.update, daemon=True)
            self.thread.start()
        return self

    def update(self):
        while self.started:
            grabbed, frame = self.cap.read()
            if grabbed:
                with self.read_lock:
                    self.grabbed, self.frame = grabbed, frame
            else: time.sleep(0.01)

    def read(self):
        with self.read_lock:
            return self.grabbed, self.frame

    def stop(self):
        self.started = False
        self.cap.release()

async def setup_systems():
    global yolo_model, ptz_service, media_profile_token
    print("\n[SYSTEM] SafeGuard-CV: Debugging Control Mode...")
    try:
        yolo_model = YOLO(MODEL_PATH, task='detect')
        # Inisialisasi ONVIF dengan timeout agar tidak gantung
        mycam = ONVIFCamera(IP_ADDRESS, PORT_ONVIF, USER_KAMERA, PASS_KAMERA)
        ptz_service = mycam.create_ptz_service()
        media_service = mycam.create_media_service()
        media_profile_token = media_service.GetProfiles()[0].token
        print("[OK] Koneksi ONVIF Berhasil.")
    except Exception as e:
        print(f"[ERROR ONVIF] Cek WiFi/Kabel LAN Kamera: {e}")

def move_camera(direction):
    global last_key_pressed
    if not ptz_service: return
    try:
        last_key_pressed = f"MOVING {direction.upper()}"
        request = ptz_service.create_type('ContinuousMove')
        request.ProfileToken = media_profile_token
        v = {'x': 0, 'y': 0}
        speed = 0.6 # Naikan speed sedikit agar terasa gerakannya
        
        if direction == 'w': v['y'] = speed
        elif direction == 's': v['y'] = -speed
        elif direction == 'a': v['x'] = -speed
        elif direction == 'd': v['x'] = speed
        
        request.Velocity = {'PanTilt': v}
        ptz_service.ContinuousMove(request)
        time.sleep(0.4) # Gerak lebih lama (0.4 detik) agar terlihat
        ptz_service.Stop({'ProfileToken': media_profile_token})
        last_key_pressed = "STOPPED"
    except Exception as e:
        last_key_pressed = "ERR ONVIF"
        print(f"PTZ Error: {e}")

async def main():
    global running, detected_workers, last_key_pressed
    await setup_systems()

    rtsp_url = f"rtsp://{USER_KAMERA}:{PASS_KAMERA}@{IP_ADDRESS}:554/stream2"
    stream = RTSPStreamReader(rtsp_url).start()

    frame_idx = 0
    while running:
        ret, frame = stream.read()
        if not ret or frame is None: continue

        frame_idx += 1
        if frame_idx % PROCESS_EVERY_N_FRAME == 0:
            results = yolo_model.predict(frame, imgsz=DETECTION_SIZE, conf=CONFIDENCE_THRESHOLD, verbose=False)[0]
            
            current_frame_workers = []
            temp_items = []

            for box in results.boxes:
                cls = int(box.cls[0])
                xyxy = list(map(int, box.xyxy[0]))
                if cls == PERSON_ID:
                    current_frame_workers.append({"box": xyxy, "conf": float(box.conf[0]), "frames_lost": 0, "apd": {"HELMET": {"ok": False, "c": 0.0}, "VEST": {"ok": False, "c": 0.0}, "SHOES": {"ok": False, "c": 0.0}}})
                else: temp_items.append({"cls": cls, "box": xyxy, "conf": float(box.conf[0])})

            for w in current_frame_workers:
                wx1, wy1, wx2, wy2 = w["box"]
                for item in temp_items:
                    ix1, iy1, ix2, iy2 = item["box"]
                    if wx1 <= (ix1+ix2)/2 <= wx2:
                        if item["cls"] in HELMET_IDS: w["apd"]["HELMET"] = {"ok": True, "c": item["conf"]}
                        elif item["cls"] in VEST_IDS: w["apd"]["VEST"] = {"ok": True, "c": item["conf"]}
                        elif item["cls"] in BOOTS_IDS: w["apd"]["SHOES"] = {"ok": True, "c": item["conf"]}
            
            if current_frame_workers: detected_workers = current_frame_workers
            else:
                for w in detected_workers: w["frames_lost"] += 1
                detected_workers = [w for w in detected_workers if w["frames_lost"] < 10]

        # --- VISUALISASI ---
        for i, worker in enumerate(detected_workers):
            x1, y1, x2, y2 = worker["box"]
            h = y2 - y1
            cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 255, 255), 1)
            cv2.putText(frame, f"WORKER {i+1}", (x1, y1-10), 1, 0.8, (255,255,255), 1)

            for name, data, t, b in [("H", worker["apd"]["HELMET"], 0.05, 0.22), ("V", worker["apd"]["VEST"], 0.28, 0.63), ("B", worker["apd"]["SHOES"], 0.78, 0.98)]:
                col = (0, 255, 0) if data["ok"] else (0, 0, 255)
                ax1, ay1, ax2, ay2 = x1+15, y1+int(h*t), x2-15, y1+int(h*b)
                cv2.rectangle(frame, (ax1, ay1), (ax2, ay2), col, 2)

        # DASHBOARD & DEBUG INFO
        cv2.rectangle(frame, (0, 0), (380, 80), (40, 40, 40), -1)
        cv2.putText(frame, f"MONITORING | {len(detected_workers)} PERS", (15, 30), 1, 1.5, (255,255,255), 2)
        cv2.putText(frame, f"CMD: {last_key_pressed}", (15, 65), 1, 1.2, (0, 255, 255), 2)
        
        cv2.imshow("SafeGuard-CV | Visual Mode", frame)
        
        # --- LOGIKA TOMBOL ---
        key = cv2.waitKey(1) & 0xFF
        if key == ord('q'): running = False; break
        elif key in [ord('w'), ord('a'), ord('s'), ord('d')]:
            # PENTING: Gunakan daemon thread agar tidak blocking
            threading.Thread(target=move_camera, args=(chr(key),), daemon=True).start()

    stream.stop()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    try: asyncio.run(main())
    except KeyboardInterrupt: pass