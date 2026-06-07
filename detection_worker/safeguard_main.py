"""
SafeGuard-CV - Main Pipeline
Orchestrates video capture -> detection -> rule evaluation -> logging/display.
Juga mengirim violations ke Laravel API jika LARAVEL_URL dikonfigurasi.
"""

from __future__ import annotations

import asyncio
import signal
import sys
import threading
import time
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

sys.path.insert(0, str(Path(__file__).parent))

from src.detection.detector import PPEDetector, FrameResult
from src.engine.rule_engine import RuleEngine, ViolationEvent
from src.engine.shift_manager import ShiftManager
from src.stream.video_source import VideoSource
from src.utils.config_loader import ConfigLoader
from src.utils.logger import get_logger
from src.utils.snapshot import SnapshotManager

logger = get_logger("safeguard.main")


class SafeGuardPipeline:
    """
    Main monitoring pipeline SafeGuard-CV.

    Usage:
        pipeline = SafeGuardPipeline(source=0, zone_id="laptop_test")
        pipeline.run()

    Atau dengan RTSP:
        pipeline = SafeGuardPipeline(source="rtsp://user:pass@ip:554/stream", zone_id="forklift_area")
        pipeline.run()
    """

    def __init__(self, source: int | str = 0, zone_id: str = "laptop_test"):
        self.source  = source
        self.zone_id = zone_id

        self._cfg     = ConfigLoader.get()
        self._running = False

        self._fps_counter = 0
        self._fps_display = 0.0
        self._fps_timer   = time.time()
        self._total_violations = 0

        self._detector:      Optional[PPEDetector]    = None
        self._rule_engine:   Optional[RuleEngine]     = None
        self._shift_manager: Optional[ShiftManager]   = None
        self._snapshot_mgr:  Optional[SnapshotManager] = None
        self._video_source:  Optional[VideoSource]    = None

        signal.signal(signal.SIGINT,  self._handle_shutdown)
        signal.signal(signal.SIGTERM, self._handle_shutdown)

    # ── Setup ─────────────────────────────────────────────────────────────────

    def setup(self) -> bool:
        logger.info("=" * 60)
        logger.info("  SafeGuard-CV  |  Initializing...")
        logger.info("=" * 60)

        model_path   = self._cfg.query("detection.model_path", "models/best.pt")
        fallback     = self._cfg.query("detection.fallback_model", "yolov8n.pt")
        class_map    = self._cfg.query("class_mapping") or {}
        if hasattr(class_map, "_data"):
            class_map = class_map._data

        self._detector = PPEDetector(
            model_path=model_path,
            fallback_model=fallback,
            confidence_threshold=self._cfg.query("detection.confidence_threshold", 0.40),
            iou_threshold=self._cfg.query("detection.iou_threshold", 0.45),
            input_size=self._cfg.query("detection.input_size", 640),
            frame_skip=self._cfg.query("detection.frame_skip", 2),
            class_mapping=class_map,
        )
        if not self._detector.load():
            logger.error("Gagal memuat model. Periksa path di configs/config.yaml")
            return False

        self._rule_engine   = RuleEngine()
        self._shift_manager = ShiftManager()
        self._snapshot_mgr  = SnapshotManager(
            snapshot_dir=self._cfg.query("storage.snapshot_dir", "snapshots/violations"),
            csv_log_path=self._cfg.query("violations.csv_log_path", "logs/violations.csv"),
            jpeg_quality=self._cfg.query("storage.jpeg_quality", 85),
        )

        zone_cfg      = self._rule_engine.zones.get(self.zone_id, {})
        camera_source = zone_cfg.get("camera_id", self.source)
        if isinstance(self.source, str):
            camera_source = self.source

        self._video_source = VideoSource(
            source=camera_source,
            width=self._cfg.query("display.window_width", 1280),
            height=self._cfg.query("display.window_height", 720),
            name=f"cam_{self.zone_id}",
        )
        if not self._video_source.open():
            logger.error(f"Gagal membuka sumber video: {camera_source}")
            return False

        logger.info(f"Zone        : {zone_cfg.get('display_name', self.zone_id)}")
        logger.info(f"Video source: {camera_source}")
        logger.info(f"Model classes: {self._detector.model_classes}")
        logger.info("=" * 60)
        logger.info("  Tekan 'q' untuk keluar | 's' untuk snapshot manual | 'i' untuk info")
        logger.info("=" * 60)
        return True

    # ── Main Loop ─────────────────────────────────────────────────────────────

    def run(self) -> None:
        if not self.setup():
            return

        self._running = True
        show_window   = self._cfg.query("display.show_window", True)
        auto_snapshot = self._cfg.query("violations.auto_snapshot", True)

        if show_window:
            cv2.namedWindow("SafeGuard-CV", cv2.WINDOW_NORMAL)

        try:
            while self._running:
                ok, frame = self._video_source.read()
                if not ok or frame is None:
                    time.sleep(0.05)
                    continue

                # Deteksi
                frame_result = self._detector.predict(frame)

                # Evaluasi aturan APD
                violations = []
                if frame_result:
                    violations = self._rule_engine.evaluate(frame_result, self.zone_id)

                # Tangani violation
                for v in violations:
                    self._total_violations += 1
                    if auto_snapshot:
                        shift_info = self._shift_manager.get_current_shift()
                        path = self._snapshot_mgr.save(
                            frame=frame,
                            zone_name=v.zone_name,
                            violation_type=v.violation_type,
                            severity=v.severity,
                            missing_ppe=v.missing_ppe,
                            confidence=v.confidence,
                            shift_name=shift_info.shift_name,
                        )
                        v.snapshot_path = path
                        logger.info(
                            f"[VIOLATION] {v.severity} | {v.zone_name} | "
                            f"MISS: {', '.join(v.missing_ppe)} | snapshot: {path}"
                        )
                    # Kirim ke Laravel di background thread
                    threading.Thread(
                        target=self._send_to_laravel,
                        args=(v, frame),
                        daemon=True,
                    ).start()

                # Tampilkan frame
                if show_window:
                    class_colors = self._cfg.query("class_colors") or {}
                    if hasattr(class_colors, "_data"):
                        class_colors = class_colors._data
                    annotated = self._detector.draw(frame, frame_result, class_colors) \
                        if frame_result else frame.copy()
                    annotated = self._build_hud(annotated, frame_result, violations)
                    cv2.imshow("SafeGuard-CV", annotated)

                # FPS counter
                self._fps_counter += 1
                elapsed = time.time() - self._fps_timer
                if elapsed >= 1.0:
                    self._fps_display = self._fps_counter / elapsed
                    self._fps_counter = 0
                    self._fps_timer   = time.time()

                # Keyboard
                if show_window:
                    key = cv2.waitKey(1) & 0xFF
                    if key == ord("q"):
                        logger.info("Keluar diminta user.")
                        break
                    elif key == ord("s"):
                        self._manual_snapshot(frame)
                    elif key == ord("i"):
                        self._print_info()

        finally:
            self._cleanup()

    # ── Display ───────────────────────────────────────────────────────────────

    def _build_hud(
        self,
        img: np.ndarray,
        frame_result: Optional[FrameResult],
        violations: list[ViolationEvent],
    ) -> np.ndarray:
        h, w = img.shape[:2]
        shift_info = self._shift_manager.get_current_shift()
        zone_cfg   = self._rule_engine.zones.get(self.zone_id, {})

        # Header bar
        worker_count = frame_result.worker_count if frame_result else 0
        cv2.rectangle(img, (0, 0), (w, 26), (20, 20, 20), -1)
        infer_ms = self._detector.avg_inference_ms
        header = (
            f"SafeGuard-CV  |  "
            f"FPS: {self._fps_display:.1f}  Inf: {infer_ms:.0f}ms  |  "
            f"{worker_count} WORKER  |  "
            f"{zone_cfg.get('display_name', self.zone_id)}  |  "
            f"{shift_info.shift_name}"
        )
        cv2.putText(img, header, (6, 18),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.48, (200, 200, 200), 1, cv2.LINE_AA)

        # Timestamp kanan atas
        from datetime import datetime
        ts = datetime.now().strftime("%Y-%m-%d  %H:%M:%S")
        tw = cv2.getTextSize(ts, cv2.FONT_HERSHEY_SIMPLEX, 0.45, 1)[0][0]
        cv2.putText(img, ts, (w - tw - 8, 18),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.45, (180, 180, 180), 1, cv2.LINE_AA)

        # Banner pelanggaran
        if violations:
            v      = violations[0]
            color  = (0, 0, 200) if v.severity == "MAJOR" else (0, 120, 220)
            alert  = f"[{v.severity}] PELANGGARAN: {', '.join(m.upper() for m in v.missing_ppe)}"
            cv2.rectangle(img, (0, h - 36), (w, h), color, -1)
            cv2.putText(img, alert, (8, h - 12),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2, cv2.LINE_AA)

        # Counter total violation kanan bawah
        ctr = f"Total Violations: {self._total_violations}"
        cw  = cv2.getTextSize(ctr, cv2.FONT_HERSHEY_SIMPLEX, 0.48, 1)[0][0]
        cv2.putText(img, ctr, (w - cw - 8, h - 12 if not violations else h - 42),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.48, (0, 220, 220), 1, cv2.LINE_AA)

        return img

    # ── Laravel Integration ───────────────────────────────────────────────────

    def _send_to_laravel(self, v: ViolationEvent, frame: np.ndarray):
        """Kirim violation ke Laravel API (jika LARAVEL_URL dikonfigurasi)."""
        try:
            import os, base64
            from laravel_client import post_violation, LARAVEL_URL
            if not LARAVEL_URL:
                return

            # Encode screenshot
            image_data = ""
            ok, jpg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
            if ok:
                image_data = base64.b64encode(jpg.tobytes()).decode("utf-8")

            from datetime import datetime
            payload = {
                "camera_id":      self._rule_engine.zones.get(self.zone_id, {}).get("camera_id", 1),
                "timestamp":      datetime.now().strftime("%Y-%m-%dT%H:%M:%S"),
                "confidence":     v.confidence,
                "image_data":     image_data,
                "image_path":     "",
                "violation_type": "apd",
                "person_name":    v.person_name or "",
            }
            for apd in v.missing_ppe:
                asyncio.run(post_violation({**payload, "label": f"no_{apd}"}))
        except Exception as e:
            logger.debug(f"send_to_laravel: {e}")

    # ── Utilities ─────────────────────────────────────────────────────────────

    def _manual_snapshot(self, frame: np.ndarray):
        shift_info = self._shift_manager.get_current_shift()
        zone_cfg   = self._rule_engine.zones.get(self.zone_id, {})
        path = self._snapshot_mgr.save(
            frame=frame,
            zone_name=zone_cfg.get("display_name", self.zone_id),
            violation_type="manual_capture",
            severity="INFO",
            missing_ppe=[],
            shift_name=shift_info.shift_name,
        )
        logger.info(f"[MANUAL SNAPSHOT] Disimpan: {path}")

    def _print_info(self):
        logger.info("--- Runtime Info ---")
        logger.info(f"FPS            : {self._fps_display:.1f}")
        logger.info(f"Avg inference  : {self._detector.avg_inference_ms:.1f} ms")
        logger.info(f"Total violations: {self._total_violations}")
        logger.info(f"Zone           : {self.zone_id}")
        logger.info(f"Shift          : {self._shift_manager.get_current_shift()}")

    def _cleanup(self):
        self._running = False
        if self._video_source:
            self._video_source.release()
        cv2.destroyAllWindows()
        logger.info(f"SafeGuard-CV dihentikan. Total pelanggaran: {self._total_violations}")

    def _handle_shutdown(self, signum, frame):
        logger.info(f"Signal {signum} diterima. Menghentikan...")
        self._running = False


# ── Entry Point ───────────────────────────────────────────────────────────────

def main():
    import argparse

    parser = argparse.ArgumentParser(description="SafeGuard-CV - K3 Monitoring System")
    parser.add_argument(
        "--source", default=0,
        help="Sumber video: 0 (webcam), 1 (USB cam), atau rtsp://... (CCTV)",
    )
    parser.add_argument(
        "--zone", default="laptop_test",
        choices=["laptop_test", "forklift_area", "empty_box_area"],
        help="ID zona monitoring (didefinisikan di configs/zones.yaml)",
    )
    args = parser.parse_args()

    source = args.source
    try:
        source = int(source)
    except (ValueError, TypeError):
        pass

    pipeline = SafeGuardPipeline(source=source, zone_id=args.zone)
    pipeline.run()


if __name__ == "__main__":
    main()
