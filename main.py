"""
SafeGuard-CV - Main Pipeline
Orchestrates video capture -> detection -> rule evaluation -> logging/display.
"""

from __future__ import annotations

import signal
import sys
import time
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

# Add project root to path
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
    Main monitoring pipeline for SafeGuard-CV.

    Supports:
      - Laptop webcam (source=0)
      - RTSP stream (source="rtsp://...")
      - Video file (source="path/to/video.mp4")

    Usage:
        pipeline = SafeGuardPipeline(source=0, zone_id="laptop_test")
        pipeline.run()
    """

    def __init__(
        self,
        source: int | str = 0,
        zone_id: str = "laptop_test",
    ):
        self.source = source
        self.zone_id = zone_id

        self._cfg = ConfigLoader.get()
        self._running = False

        # Statistics
        self._fps_counter = 0
        self._fps_display = 0.0
        self._fps_timer = time.time()
        self._total_violations = 0

        # Components (initialized in setup())
        self._detector: Optional[PPEDetector] = None
        self._rule_engine: Optional[RuleEngine] = None
        self._shift_manager: Optional[ShiftManager] = None
        self._snapshot_mgr: Optional[SnapshotManager] = None
        self._video_source: Optional[VideoSource] = None

        # Register graceful shutdown
        signal.signal(signal.SIGINT, self._handle_shutdown)
        signal.signal(signal.SIGTERM, self._handle_shutdown)

    def setup(self) -> bool:
        """Initialize all components. Returns True if ready."""
        logger.info("=" * 60)
        logger.info("  SafeGuard-CV  |  Initializing...")
        logger.info("=" * 60)

        # --- Detector ---
        model_path = self._cfg.query("detection.model_path", "models/safeguard_ppe.pt")
        fallback = self._cfg.query("detection.fallback_model", "yolov8n.pt")
        class_mapping = self._cfg.query("class_mapping") or {}
        if hasattr(class_mapping, '_data'):
            class_mapping = class_mapping._data

        self._detector = PPEDetector(
            model_path=model_path,
            fallback_model=fallback,
            confidence_threshold=self._cfg.query("detection.confidence_threshold", 0.50),
            iou_threshold=self._cfg.query("detection.iou_threshold", 0.45),
            input_size=self._cfg.query("detection.input_size", 640),
            frame_skip=self._cfg.query("detection.frame_skip", 2),
            class_mapping=class_mapping,
        )
        if not self._detector.load():
            logger.error("Failed to load model. Aborting.")
            return False

        # --- Rule Engine & Shift Manager ---
        self._rule_engine = RuleEngine()
        self._shift_manager = ShiftManager()

        # --- Snapshot Manager ---
        self._snapshot_mgr = SnapshotManager(
            snapshot_dir=self._cfg.query("storage.snapshot_dir", "snapshots/violations"),
            csv_log_path=self._cfg.query("violations.csv_log_path", "logs/violations.csv"),
            jpeg_quality=self._cfg.query("storage.jpeg_quality", 85),
        )

        # --- Video Source ---
        zone_cfg = self._rule_engine.zones.get(self.zone_id, {})
        camera_source = zone_cfg.get("camera_id", self.source)
        if isinstance(self.source, str):
            camera_source = self.source  # RTSP override

        self._video_source = VideoSource(
            source=camera_source,
            width=self._cfg.query("display.window_width", 1280),
            height=self._cfg.query("display.window_height", 720),
            name=f"cam_{self.zone_id}",
        )
        if not self._video_source.open():
            logger.error(f"Failed to open video source: {camera_source}")
            return False

        logger.info(f"Zone        : {zone_cfg.get('display_name', self.zone_id)}")
        logger.info(f"Video source: {camera_source}")
        logger.info(f"Model classes: {self._detector.model_classes}")
        logger.info("=" * 60)
        logger.info("  Press 'q' to quit | 's' to manual snapshot | 'i' for info")
        logger.info("=" * 60)
        return True

    def run(self) -> None:
        """Main loop."""
        if not self.setup():
            return

        self._running = True
        show_window = self._cfg.query("display.show_window", True)
        auto_snapshot = self._cfg.query("violations.auto_snapshot", True)

        if show_window:
            cv2.namedWindow("SafeGuard-CV", cv2.WINDOW_NORMAL)

        try:
            while self._running:
                ok, frame = self._video_source.read()
                if not ok or frame is None:
                    logger.warning("Empty frame received. Retrying...")
                    time.sleep(0.05)
                    continue

                # --- Inference ---
                frame_result = self._detector.predict(frame)

                # --- Rule Evaluation ---
                violations = []
                if frame_result:
                    violations = self._rule_engine.evaluate(frame_result, self.zone_id)

                # --- Handle Violations ---
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

                # --- Display ---
                if show_window:
                    annotated = self._build_display(frame, frame_result, violations)
                    cv2.imshow("SafeGuard-CV", annotated)

                # --- Update FPS ---
                self._fps_counter += 1
                elapsed = time.time() - self._fps_timer
                if elapsed >= 1.0:
                    self._fps_display = self._fps_counter / elapsed
                    self._fps_counter = 0
                    self._fps_timer = time.time()

                # --- Keyboard Controls ---
                if show_window:
                    key = cv2.waitKey(1) & 0xFF
                    if key == ord("q"):
                        logger.info("Quit requested by user.")
                        break
                    elif key == ord("s"):
                        self._manual_snapshot(frame)
                    elif key == ord("i"):
                        self._print_info()

        finally:
            self._cleanup()

    def _build_display(
        self,
        frame: np.ndarray,
        frame_result: Optional[FrameResult],
        violations: list,
    ) -> np.ndarray:
        """Build annotated display frame with detection boxes and HUD."""
        class_colors = self._cfg.query("class_colors") or {}
        if hasattr(class_colors, '_data'):
            class_colors = class_colors._data

        # Draw detection boxes
        if frame_result:
            img = self._detector.draw(frame, frame_result, class_colors)
        else:
            img = frame.copy()

        h, w = img.shape[:2]
        shift_info = self._shift_manager.get_current_shift()
        zone_cfg = self._rule_engine.zones.get(self.zone_id, {})

        # --- Top HUD ---
        hud_lines = []
        if self._cfg.query("display.show_fps", True):
            infer_ms = self._detector.avg_inference_ms
            hud_lines.append(f"FPS: {self._fps_display:.1f}  |  Infer: {infer_ms:.0f}ms")
        if self._cfg.query("display.show_zone_name", True):
            hud_lines.append(f"Zone: {zone_cfg.get('display_name', self.zone_id)}")
        if self._cfg.query("display.show_shift_status", True):
            status = shift_info.shift_name if shift_info.is_working_hours else "OUT OF SHIFT"
            hud_lines.append(f"Shift: {status}")
        if self._cfg.query("display.show_timestamp", True):
            from datetime import datetime
            hud_lines.append(datetime.now().strftime("%Y-%m-%d %H:%M:%S"))

        for i, line in enumerate(hud_lines):
            y = 22 + i * 22
            cv2.putText(img, line, (8, y), cv2.FONT_HERSHEY_SIMPLEX,
                        0.55, (0, 0, 0), 3, cv2.LINE_AA)
            cv2.putText(img, line, (8, y), cv2.FONT_HERSHEY_SIMPLEX,
                        0.55, (255, 255, 255), 1, cv2.LINE_AA)

        # --- Violation Alert Banner ---
        if violations:
            v = violations[0]
            color = (0, 0, 255) if v.severity == "MAJOR" else (0, 165, 255)
            cv2.rectangle(img, (0, h - 50), (w, h), color, -1)
            alert = (
                f"[{v.severity}] PELANGGARAN: {', '.join(v.missing_ppe).upper() or v.violation_type.upper()}"
            )
            cv2.putText(img, alert, (8, h - 18),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.65, (255, 255, 255), 2, cv2.LINE_AA)

        # --- Total violations counter ---
        cv2.putText(
            img, f"Total Violations: {self._total_violations}",
            (w - 220, 22), cv2.FONT_HERSHEY_SIMPLEX,
            0.55, (0, 0, 0), 3, cv2.LINE_AA,
        )
        cv2.putText(
            img, f"Total Violations: {self._total_violations}",
            (w - 220, 22), cv2.FONT_HERSHEY_SIMPLEX,
            0.55, (0, 255, 255), 1, cv2.LINE_AA,
        )

        return img

    def _manual_snapshot(self, frame: np.ndarray) -> None:
        """Save a manual snapshot (key 's')."""
        shift_info = self._shift_manager.get_current_shift()
        zone_cfg = self._rule_engine.zones.get(self.zone_id, {})
        path = self._snapshot_mgr.save(
            frame=frame,
            zone_name=zone_cfg.get("display_name", self.zone_id),
            violation_type="manual_capture",
            severity="INFO",
            missing_ppe=[],
            shift_name=shift_info.shift_name,
        )
        logger.info(f"[MANUAL SNAPSHOT] Saved: {path}")

    def _print_info(self) -> None:
        """Print runtime info (key 'i')."""
        logger.info(f"--- Runtime Info ---")
        logger.info(f"FPS: {self._fps_display:.1f}")
        logger.info(f"Avg inference: {self._detector.avg_inference_ms:.1f}ms")
        logger.info(f"Total violations: {self._total_violations}")
        logger.info(f"Model classes: {self._detector.model_classes}")
        logger.info(f"Zone: {self.zone_id}")
        shift = self._shift_manager.get_current_shift()
        logger.info(f"Shift: {shift}")

    def _cleanup(self) -> None:
        self._running = False
        if self._video_source:
            self._video_source.release()
        cv2.destroyAllWindows()
        logger.info(
            f"SafeGuard-CV stopped. Total violations recorded: {self._total_violations}"
        )

    def _handle_shutdown(self, signum, frame) -> None:
        logger.info(f"Received signal {signum}. Shutting down...")
        self._running = False


# ============================================================
# Entry Point
# ============================================================

def main():
    import argparse

    parser = argparse.ArgumentParser(
        description="SafeGuard-CV - K3 Monitoring System"
    )
    parser.add_argument(
        "--source",
        default=0,
        help="Video source: 0 (webcam), 1 (USB cam), atau rtsp://... (CCTV)",
    )
    parser.add_argument(
        "--zone",
        default="laptop_test",
        choices=["laptop_test", "forklift_area", "empty_box_area"],
        help="Monitoring zone ID (didefinisikan di configs/zones.yaml)",
    )
    args = parser.parse_args()

    # Konversi source ke int jika angka
    source = args.source
    try:
        source = int(source)
    except (ValueError, TypeError):
        pass  # Tetap string untuk RTSP

    pipeline = SafeGuardPipeline(source=source, zone_id=args.zone)
    pipeline.run()


if __name__ == "__main__":
    main()
