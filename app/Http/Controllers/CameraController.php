<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCameraRequest;
use App\Http\Requests\UpdateCameraRequest;
use App\Models\ActivityLog;
use App\Models\Camera;
use App\Models\Violation;
use App\Services\CameraService;
use App\Services\DetectionWorkerService;
use App\Services\OnvifService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CameraController extends Controller
{
    public function __construct(
        private CameraService $cameraService,
        private DetectionWorkerService $workerService,
        private OnvifService $onvifService,
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // API — CRUD (digunakan oleh frontend via fetch)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/cameras — Daftar kamera dengan filter opsional.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Camera::with('zone:id,name')
            ->orderBy('zone_id')
            ->orderBy('name');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%")
                    ->orWhereHas('zone', fn($z) => $z->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('connection_status')) {
            $query->where('connection_status', $request->connection_status);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $cameras = $query->paginate($perPage);

        return response()->json([
            'data' => $cameras->through(fn($c) => $this->formatCamera($c))->items(),
            'meta' => [
                'total'        => $cameras->total(),
                'page'         => $cameras->currentPage(),
                'per_page'     => $cameras->perPage(),
                'last_page'    => $cameras->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/cameras — Tambah kamera baru.
     */
    public function store(StoreCameraRequest $request): JsonResponse
    {
        $camera = $this->cameraService->store($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Kamera berhasil ditambahkan.',
            'data'    => $this->formatCamera($camera),
        ], 201);
    }

    /**
     * GET /api/cameras/{camera} — Detail satu kamera.
     */
    public function show(Camera $camera): JsonResponse
    {
        $camera->load('zone:id,name');

        return response()->json([
            'data' => $this->formatCamera($camera, detailed: true),
        ]);
    }

    /**
     * PUT /api/cameras/{camera} — Update data kamera.
     */
    public function update(UpdateCameraRequest $request, Camera $camera): JsonResponse
    {
        $camera = $this->cameraService->update($camera, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Kamera berhasil diupdate.',
            'data'    => $this->formatCamera($camera, detailed: true),
        ]);
    }

    /**
     * DELETE /api/cameras/{camera} — Soft delete kamera.
     */
    public function destroy(Request $request, Camera $camera): JsonResponse
    {
        $this->cameraService->delete($camera, $request->user()->id);

        return response()->json(['message' => 'Kamera berhasil dihapus.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // API — Koneksi & Kontrol
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/cameras/active — IDs kamera aktif (service key only, untuk detection worker).
     */
    public function getActiveCameraIds(): JsonResponse
    {
        $ids = Camera::where('is_active', true)->pluck('id');
        return response()->json(['camera_ids' => $ids]);
    }

    /**
     * POST /api/cameras/{camera}/test-connection
     */
    public function testConnection(Request $request, Camera $camera): JsonResponse
    {
        $result = $this->onvifService->testConnection($camera);

        // Update status berdasarkan hasil test
        $status = ($result['overall'] ?? false) ? 'online' : 'offline';
        $this->cameraService->updateConnectionStatus($camera, $status);

        return response()->json([
            'message' => $result['overall'] ? 'Koneksi berhasil.' : 'Koneksi gagal.',
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/cameras/{camera}/toggle-status
     */
    public function toggleStatus(Request $request, Camera $camera): JsonResponse
    {
        $camera = $this->cameraService->toggleStatus($camera, $request->user()->id);

        return response()->json([
            'message'   => 'Status kamera diperbarui.',
            'is_active' => $camera->is_active,
        ]);
    }

    /**
     * POST /api/cameras/{camera}/ptz — Kontrol PTZ.
     */
    public function ptzControl(Request $request, Camera $camera): JsonResponse
    {
        if (!$camera->ptz_enabled) {
            return response()->json(['message' => 'PTZ tidak diaktifkan untuk kamera ini.'], 422);
        }

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down', 'left', 'right', 'home'])],
            'speed'     => 'sometimes|numeric|min:0.1|max:1.0',
            'duration'  => 'sometimes|numeric|min:0.1|max:5.0',
        ]);

        $result = $this->onvifService->movePtz(
            $camera,
            $validated['direction'],
            $validated['speed'] ?? $camera->ptz_speed,
            $validated['duration'] ?? $camera->ptz_movement_duration,
        );

        return response()->json($result);
    }

    /**
     * POST /api/cameras/{camera}/ptz/preset/{presetIndex} — Go to preset.
     */
    public function gotoPreset(Request $request, Camera $camera, int $presetIndex): JsonResponse
    {
        if (!$camera->ptz_enabled) {
            return response()->json(['message' => 'PTZ tidak diaktifkan.'], 422);
        }

        $presets = $camera->preset_positions ?? [];
        if (!isset($presets[$presetIndex])) {
            return response()->json(['message' => 'Preset tidak ditemukan.'], 404);
        }

        $result = $this->onvifService->gotoPreset($camera, $presetIndex);

        return response()->json($result);
    }

    /**
     * POST /api/cameras/{camera}/ptz/save-preset — Simpan posisi PTZ sekarang.
     */
    public function savePreset(Request $request, Camera $camera): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $result = $this->onvifService->savePreset($camera, $validated['name']);

        return response()->json($result);
    }

    /**
     * POST /api/cameras/{camera}/screenshot — Screenshot manual.
     */
    public function screenshot(Request $request, Camera $camera): JsonResponse
    {
        $result = $this->workerService->requestScreenshot($camera->id);

        return response()->json($result);
    }

    /**
     * GET /api/cameras/{camera}/stream — Proxy MJPEG stream ke detection worker.
     * Redirect ke URL stream di detection worker (hindari double buffering di PHP).
     */
    public function streamProxy(Camera $camera): \Illuminate\Http\RedirectResponse
    {
        $streamUrl = $this->workerService->getStreamUrl($camera->id);

        return redirect()->away($streamUrl);
    }

    /**
     * GET /api/models — Daftar model yang tersedia di detection worker.
     * Mengembalikan error=string jika worker tidak dapat dihubungi.
     */
    public function listModels(): JsonResponse
    {
        $models = $this->workerService->listModels();

        if ($models === null) {
            return response()->json([
                'data'  => [],
                'error' => 'Detection worker tidak dapat dihubungi. Pastikan worker sudah berjalan (port 8001).',
            ]);
        }

        return response()->json(['data' => $models]);
    }

    /**
     * POST /api/cameras/upload-model — Upload model .pt, .onnx, atau .zip ke detection worker.
     */
    public function uploadModel(Request $request): JsonResponse
    {
        $file = $request->file('model');

        if (!$file) {
            return response()->json(['message' => 'File model wajib diupload.'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['pt', 'onnx', 'zip'])) {
            return response()->json([
                'message' => 'Format tidak didukung. Gunakan .pt, .onnx, atau .zip yang berisi .pt.',
            ], 422);
        }

        $result = $this->workerService->uploadModel($file);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Upload ke detection worker gagal.',
            ], 422);
        }

        return response()->json([
            'message'    => 'Model berhasil diupload ke detection worker.',
            'model_name' => $result['model_name'] ?? '',
            'model_path' => $result['model_path'] ?? '',
            'size_mb'    => $result['size_mb'] ?? 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // API — Dashboard Data
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/dashboard/stats — Statistik untuk monitoring dashboard.
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $cameraId = $request->camera_id;
        $zoneId   = $request->zone_id;

        // Active workers — ambil real-time dari detection worker
        $workerService = app(\App\Services\DetectionWorkerService::class);
        if ($cameraId) {
            $activeWorkers = $workerService->getWorkerCount((int) $cameraId);
        } else {
            // Jika tidak ada camera_id, jumlahkan semua kamera aktif
            $activeCamIds = \App\Models\Camera::where('is_active', true)->pluck('id');
            $activeWorkers = $activeCamIds->sum(fn($id) => $workerService->getWorkerCount($id));
        }

        // Violations in last hour
        $violationsLastHour = Violation::where('detected_at', '>=', now()->subHour())
            ->when($cameraId, fn($q) => $q->where('camera_id', $cameraId))
            ->when($zoneId, fn($q) => $q->whereHas('camera', fn($c) => $c->where('zone_id', $zoneId)))
            ->count();

        $locationStatus = match (true) {
            $violationsLastHour > 15 => 'HIGH',
            $violationsLastHour >= 5 => 'MEDIUM',
            default                  => 'LOW',
        };

        // Today's violations
        $todayViolations = Violation::whereDate('detected_at', today())
            ->when($cameraId, fn($q) => $q->where('camera_id', $cameraId))
            ->when($zoneId, fn($q) => $q->whereHas('camera', fn($c) => $c->where('zone_id', $zoneId)))
            ->count();

        // Compliance rate (workers with full APD / total workers detected, last hour)
        $totalDetected = Violation::where('detected_at', '>=', now()->subHour())
            ->where('violation_type', 'apd')
            ->when($cameraId, fn($q) => $q->where('camera_id', $cameraId))
            ->select('detected_at', 'camera_id')
            ->distinct()
            ->count();

        $withViolation = Violation::where('detected_at', '>=', now()->subHour())
            ->where('violation_type', 'apd')
            ->when($cameraId, fn($q) => $q->where('camera_id', $cameraId))
            ->count();

        $complianceRate = $totalDetected > 0
            ? max(0, round((1 - $withViolation / max($totalDetected, 1)) * 100))
            : 100;

        return response()->json([
            'active_workers'     => $activeWorkers,
            'location_status'    => $locationStatus,
            'today_violations'   => $todayViolations,
            'compliance_rate'    => $complianceRate,
            'violations_last_hour' => $violationsLastHour,
        ]);
    }

    /**
     * GET /api/dashboard/activity-log — Log aktivitas (pelanggaran terbaru).
     */
    public function dashboardActivityLog(Request $request): JsonResponse
    {
        $query = Violation::with([
            'camera:id,name,zone_id',
            'camera.zone:id,name',
        ])
            ->orderBy('id', 'desc')
            ->limit(20);

        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->camera_id);
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('camera', fn($q) => $q->where('zone_id', $request->zone_id));
        }

        $violations = $query->get();

        return response()->json([
            'data' => $violations->map(fn($v) => [
                'id'             => $v->id,
                'time'           => $v->detected_at->format('H:i:s'),
                'event'          => $this->violationLabel($v),
                'location'       => $v->camera?->zone?->name ?? $v->camera?->name ?? '—',
                'camera_name'    => $v->camera?->name ?? '—',
                'violation_type' => $v->violation_type,
                'apd_label'      => $v->apd_label,
                'level'          => $v->level,
                'image_path'     => $v->image_path,
                'person_name'    => $v->person_name,
                'status'         => $v->status,
                'detected_at'    => $v->detected_at,
            ]),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // API — Service Key (untuk Detection Worker)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/cameras/{camera}/config — Konfigurasi kamera untuk detection worker.
     */
    public function getCameraConfig(Camera $camera): JsonResponse
    {
        return response()->json(
            $this->cameraService->getConfigForWorker($camera)
        );
    }

    /**
     * POST /api/cameras/{camera}/health-check — Update status dari worker.
     */
    public function healthCheck(Request $request, Camera $camera): JsonResponse
    {
        $validated = $request->validate([
            'status'       => ['required', Rule::in(['online', 'offline'])],
            'onvif_ok'     => 'boolean',
            'rtsp_ok'      => 'boolean',
            'fps'          => 'nullable|numeric',
            'resolution'   => 'nullable|string',
        ]);

        $camera->update([
            'connection_status'    => $validated['status'],
            'last_connection_check' => now(),
        ]);

        return response()->json(['message' => 'Health check diterima.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function formatCamera(Camera $camera, bool $detailed = false): array
    {
        $base = [
            'id'               => $camera->id,
            'zone_id'          => $camera->zone_id,
            'zone_name'        => $camera->zone?->name,
            'name'             => $camera->name,
            'dvr_channel'      => $camera->dvr_channel,
            'ip_address'       => $camera->ip_address,
            'is_active'        => $camera->is_active,
            'connection_status' => $camera->connection_status,
            'last_connection_check' => $camera->last_connection_check?->diffForHumans(),
            'created_at'       => $camera->created_at,
        ];

        if (!$detailed) {
            return $base;
        }

        return array_merge($base, [
            'port_onvif'            => $camera->port_onvif,
            'port_rtsp'             => $camera->port_rtsp,
            'username'              => $camera->username,
            'has_password'          => !empty($camera->attributes['password']),
            'rtsp_path'             => $camera->rtsp_path,
            'rtsp_transport'        => $camera->rtsp_transport,
            'connection_timeout'    => $camera->connection_timeout,
            'ai_model_path'         => $camera->ai_model_path,
            'detection_size'        => $camera->detection_size,
            'confidence_threshold'  => $camera->confidence_threshold,
            'process_every_n_frame' => $camera->process_every_n_frame,
            'class_mapping'         => $camera->default_class_mapping,
            'ptz_enabled'           => $camera->ptz_enabled,
            'ptz_speed'             => $camera->ptz_speed,
            'ptz_movement_duration' => $camera->ptz_movement_duration,
            'preset_positions'      => $camera->preset_positions ?? [],
            'auto_screenshot'       => $camera->auto_screenshot,
            'screenshot_cooldown'   => $camera->screenshot_cooldown,
            'description'           => $camera->description,
        ]);
    }

    private function violationLabel(Violation $v): string
    {
        if ($v->violation_type === 'discipline') {
            return 'Aktivitas di Luar Shift';
        }

        return match ($v->apd_label) {
            'no_helmet' => 'Tidak Pakai Helm',
            'no_vest'   => 'Tidak Pakai Rompi',
            'no_boots'  => 'Tidak Pakai Sepatu Safety',
            default     => ucfirst(str_replace('_', ' ', $v->apd_label ?? 'Pelanggaran APD')),
        };
    }
}
