<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Camera;
use App\Models\Shift;
use App\Models\Violation;
use App\Models\ViolationNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ViolationController extends Controller
{
    //POST /api/violations — Terima event deteksi dari TIF.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => [
                'required',
                'integer',
                'exists:cameras,id,deleted_at,NULL,is_active,1',
            ],
            'timestamp'  => 'required|date_format:Y-m-d\TH:i:s',
            'label'      => ['required', Rule::in(['no_helmet', 'no_vest', 'no_boots', 'person'])],
            'confidence' => 'required|numeric|min:0|max:1',
            'image_path' => 'required|string|max:500',
        ]);

        if ($validated['confidence'] < 0.7) {
            return response()->json([
                'message' => 'Event diabaikan.',
                'reason'  => 'Confidence di bawah threshold 0.7.',
            ]);
        }

        $camera = Camera::with('zone.apdRules')->find($validated['camera_id']);

        $detectedAt = Carbon::parse($validated['timestamp']);

        $time = $detectedAt->format('H:i:s');

        $activeShift = Shift::where(function ($query) use ($time) {
            $query
                // shift normal
                ->where(function ($q) use ($time) {
                    $q->whereColumn('start_time', '<', 'end_time')
                        ->whereRaw('? >= start_time AND ? < end_time', [$time, $time]);
                })
                // shift overnight
                ->orWhere(function ($q) use ($time) {
                    $q->whereColumn('start_time', '>', 'end_time')
                        ->whereRaw('? >= start_time OR ? < end_time', [$time, $time]);
                });
        })->first();

        $isOutsideShift = $activeShift === null;

        /**
         * =========================
         * CASE: PERSON (discipline)
         * =========================
         */
        if ($validated['label'] === 'person') {

            if (!$isOutsideShift) {
                return response()->json([
                    'message' => 'Event diabaikan.',
                    'reason'  => 'Deteksi person dalam jam shift aktif.',
                ]);
            }

            $existing = Violation::where('camera_id', $camera->id)
                ->where('detected_at', $detectedAt)
                ->where('violation_type', 'discipline')
                ->whereNull('apd_label')
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Event sudah diproses sebelumnya.',
                    'data'    => [
                        'violation_id' => $existing->id,
                        'status'       => $existing->status,
                    ],
                ]);
            }

            $violation = Violation::create([
                'camera_id'        => $camera->id,
                'shift_id'         => null,
                'violation_type'   => 'discipline',
                'apd_label'        => null,
                'level'            => null,
                'confidence'       => $validated['confidence'],
                'image_path'       => $validated['image_path'],
                'is_outside_shift' => true,
                'status'           => 'pending',
                'detected_at'      => $detectedAt,
            ]);

            $this->sendManagerAlert($violation, $camera);

            return response()->json([
                'message' => 'Event pelanggaran berhasil dicatat.',
                'data'    => [
                    'violation_id'   => $violation->id,
                    'violation_type' => 'discipline',
                    'status'         => 'pending',
                    'shift_id'       => null,
                    'level'          => null,
                ],
            ], 201);
        }

        /**
         * =========================
         * CASE: APD
         * =========================
         */
        $zoneRules = $camera->zone?->apdRules?->pluck('apd_label')->toArray() ?? [];

        if (!in_array($validated['label'], $zoneRules)) {
            return response()->json([
                'message' => 'Event diabaikan.',
                'reason'  => 'Label tidak termasuk aturan APD zona kamera ini.',
            ]);
        }

        $existing = Violation::where('camera_id', $camera->id)
            ->where('detected_at', $detectedAt)
            ->where('violation_type', 'apd')
            ->where('apd_label', $validated['label'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Event sudah diproses sebelumnya.',
                'data'    => [
                    'violation_id' => $existing->id,
                    'status'       => $existing->status,
                ],
            ]);
        }

        $level = Violation::APD_LEVELS[$validated['label']];

        $violation = Violation::create([
            'camera_id'        => $camera->id,
            'shift_id'         => $activeShift?->id,
            'violation_type'   => 'apd',
            'apd_label'        => $validated['label'],
            'level'            => $level,
            'confidence'       => $validated['confidence'],
            'image_path'       => $validated['image_path'],
            'is_outside_shift' => $isOutsideShift,
            'status'           => 'pending',
            'detected_at'      => $detectedAt,
        ]);

        $this->sendManagerAlert($violation, $camera);

        return response()->json([
            'message' => 'Event pelanggaran berhasil dicatat.',
            'data'    => [
                'violation_id'   => $violation->id,
                'violation_type' => 'apd',
                'status'         => 'pending',
                'shift_id'       => $activeShift?->id,
                'level'          => $level,
            ],
        ], 201);
    }

    /**
     * GET /api/violations — Daftar violations dengan filter opsional.
     * Akses: Admin (read-only), Manager, HR.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'         => ['sometimes', Rule::in(['pending', 'validated', 'rejected', 'reported'])],
            'violation_type' => ['sometimes', Rule::in(['apd', 'discipline'])],
            'camera_id'      => 'sometimes|integer',
            'zone_id'        => 'sometimes|integer',
            'shift_id'       => 'sometimes|integer',
            'level'          => ['sometimes', Rule::in(['minor', 'major'])],
            'date_from'      => 'sometimes|date_format:Y-m-d',
            'date_to'        => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
            'page'           => 'sometimes|integer|min:1',
            'per_page'       => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Violation::with([
            'camera:id,name,dvr_channel,zone_id',
            'camera.zone:id,name',
            'shift:id,name',
            'validator:id,name',
        ])->orderBy('detected_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->violation_type);
        }
        if ($request->filled('camera_id')) {
            $query->where('camera_id', $request->camera_id);
        }
        if ($request->filled('zone_id')) {
            $query->whereHas(
                'camera',
                fn($q) => $q->where('zone_id', $request->zone_id)
            );
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }
        if ($request->filled('date_from')) {
            $query->where('detected_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('detected_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $perPage    = $request->input('per_page', 20);
        $violations = $query->paginate($perPage);

        return response()->json([
            'data' => $violations->through(fn($v) => $this->formatViolation($v))->items(),
            'meta' => [
                'total'    => $violations->total(),
                'page'     => $violations->currentPage(),
                'per_page' => $violations->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/violations/{violation} — Detail satu violation.
     * Akses: Admin (read-only), Manager, HR.
     */
    public function show(Violation $violation): JsonResponse
    {
        $violation->load([
            'camera:id,name,dvr_channel,zone_id',
            'camera.zone:id,name',
            'shift:id,name',
            'validator:id,name',
        ]);

        return response()->json([
            'data' => $this->formatViolation($violation),
        ]);
    }

    /**
     * PUT /api/violations/{violation}/validate — Validasi atau reject violation.
     * Akses: Manager only.
     */
    public function validateViolation(Request $request, Violation $violation): JsonResponse
    {
        if ($violation->status !== 'pending') {
            return response()->json([
                'message' => "Violation ini sudah berstatus '{$violation->status}' dan tidak bisa divalidasi ulang.",
            ], 422);
        }

        $validated = $request->validate([
            'is_valid'         => 'required|boolean',
            'person_name'      => 'nullable|string|max:255',
            'validation_notes' => 'nullable|string',
        ]);

        $newStatus = $validated['is_valid'] ? 'validated' : 'rejected';

        $violation->update([
            'status'       => $newStatus,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),

            'person_name' => array_key_exists('person_name', $validated)
                ? $validated['person_name']
                : $violation->person_name,

            'validation_notes' => array_key_exists('validation_notes', $validated)
                ? $validated['validation_notes']
                : $violation->validation_notes,
        ]);
        
        $violation->refresh();

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => $validated['is_valid'] ? 'validate_violation' : 'reject_violation',
            'target_type' => 'violations',
            'target_id'   => $violation->id,
            'description' => $validated['is_valid']
                ? "Manager memvalidasi pelanggaran #{$violation->id} sebagai valid."
                : "Manager menolak pelanggaran #{$violation->id} sebagai false positive.",
        ]);

        if ($validated['is_valid']) {
            $this->sendHrNotification($violation);

            return response()->json([
                'message' => 'Pelanggaran berhasil divalidasi. Notifikasi telah dikirim ke HR.',
                'data'    => [
                    'id'           => $violation->id,
                    'status'       => $violation->status,
                    'validated_by' => $violation->validated_by,
                    'validated_at' => $violation->validated_at,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Pelanggaran ditolak sebagai false positive.',
            'data'    => [
                'id'           => $violation->id,
                'status'       => $violation->status,
                'validated_by' => $violation->validated_by,
                'validated_at' => $violation->validated_at,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function formatViolation(Violation $violation): array
    {
        return [
            'id'               => $violation->id,
            'camera'           => [
                'id'          => $violation->camera?->id,
                'name'        => $violation->camera?->name,
                'dvr_channel' => $violation->camera?->dvr_channel,
            ],
            'zone'             => [
                'id'   => $violation->camera?->zone?->id,
                'name' => $violation->camera?->zone?->name,
            ],
            'shift'            => $violation->shift ? [
                'id'   => $violation->shift->id,
                'name' => $violation->shift->name,
            ] : null,
            'violation_type'   => $violation->violation_type,
            'apd_label'        => $violation->apd_label,
            'level'            => $violation->level,
            'confidence'       => $violation->confidence,
            'image_path'       => $violation->image_path,
            'is_outside_shift' => $violation->is_outside_shift,
            'person_name'      => $violation->person_name,
            'validation_notes' => $violation->validation_notes,
            'status'           => $violation->status,
            'validated_by'     => $violation->validator ? [
                'id'   => $violation->validator->id,
                'name' => $violation->validator->name,
            ] : null,
            'validated_at'     => $violation->validated_at,
            'detected_at'      => $violation->detected_at,
            'created_at'       => $violation->created_at,
        ];
    }

    /**
     * Kirim alert Telegram ke Manager saat event baru masuk (status pending).
     */
    private function sendManagerAlert(Violation $violation, Camera $camera): void
    {

        $chatId   = config('services.telegram.manager_chat_id');
        $botToken = config('services.telegram.token');

        if (empty($botToken) || empty($chatId)) {
            Log::error('Telegram configuration tidak lengkap', [
                'violation_id' => $violation->id,
                'token_loaded' => !empty(config('services.telegram.token')),
                'chat_id_loaded' => !empty(config('services.telegram.manager_chat_id')),
            ]);
            return;
        }

        $zoneN    = $camera->zone?->name ?? 'Unknown';
        $label    = $violation->apd_label ?? 'Orang di luar shift';
        $type     = $violation->violation_type === 'apd' ? 'APD' : 'Disiplin';
        $level    = $violation->level ? strtoupper($violation->level) : '-';
        $time     = $violation->detected_at->format('d/m/Y H:i:s');

        $text = "<b>ALERT PELANGGARAN K3</b>\n\n"
            . "Tipe: {$type}\n"
            . "Label: {$label}\n"
            . "Level: {$level}\n"
            . "Zona: {$zoneN}\n"
            . "Kamera: {$camera->name} ({$camera->dvr_channel})\n"
            . "Waktu: {$time}\n"
            . "ID: #{$violation->id}\n\n"
            . "<i>Silakan validasi di sistem.</i>";

        $notif = ViolationNotification::create([
            'violation_id' => $violation->id,
            'recipient_id' => null,
            'channel'      => 'telegram',
            'type'         => 'alert_manager',
            'status'       => 'failed',
            'sent_at'      => null,
        ]);

        try {
            $response = Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ]
            );

            $data = $response->json();

            $sent = $response->successful()
                && isset($data['ok'])
                && $data['ok'] === true;

            if (!$sent) {
                Log::warning('Telegram API gagal atau response tidak sesuai', [
                    'violation_id'  => $violation->id,
                    'http_status'   => $response->status(),
                    'response_body' => $data,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram sendManagerAlert exception', [
                'violation_id' => $violation->id,
                'error'        => $e->getMessage(),
            ]);

            $sent = false;
        }

        $notif->update(
            $sent
                ? ['status' => 'sent', 'sent_at' => now()]
                : ['status' => 'failed']
        );
    }

    /**
     * Kirim notifikasi Telegram ke HR setelah Manager validate (status validated).
     */
    private function sendHrNotification(Violation $violation): void
    {
        $botToken = config('services.telegram.token');
        $chatId   = config('services.telegram.hr_chat_id');

        if (empty($botToken) || empty($chatId)) {
            Log::error('Telegram configuration tidak lengkap untuk HR notification', [
                'violation_id' => $violation->id,
                'token_loaded' => !empty(config('services.telegram.token')),
                'chat_id_loaded' => !empty(config('services.telegram.hr_chat_id')),
            ]);
            return;
        }

        $notif = ViolationNotification::create([
            'violation_id' => $violation->id,
            'recipient_id' => null,
            'channel'      => 'telegram',
            'type'         => 'notify_hr',
            'status'       => 'failed',
            'sent_at'      => null,
        ]);

        $label    = $violation->apd_label ?? 'Orang di luar shift';
        $type     = $violation->violation_type === 'apd' ? 'APD' : 'Disiplin';
        $level    = $violation->level ? strtoupper($violation->level) : '-';
        $person   = $violation->person_name ?? 'Tidak diidentifikasi';
        $time     = $violation->detected_at->format('d/m/Y H:i:s');

        $text = "<b>PELANGGARAN TERVALIDASI</b>\n\n"
            . "Tipe: {$type}\n"
            . "Label: {$label}\n"
            . "Level: {$level}\n"
            . "Pelanggar: {$person}\n"
            . "Waktu: {$time}\n"
            . "ID: #{$violation->id}\n\n"
            . "<i>Pelanggaran ini sudah dikonfirmasi Manager dan siap masuk laporan.</i>";

        try {
            $response = Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ]
            );

            $data = $response->json();

            $sent = $response->successful()
                && isset($data['ok'])
                && $data['ok'] === true;

            if (!$sent) {
                Log::warning('Telegram API gagal atau response tidak sesuai (HR)', [
                    'violation_id'  => $violation->id,
                    'http_status'   => $response->status(),
                    'response_body' => $data,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram sendHrNotification exception', [
                'violation_id' => $violation->id,
                'error'        => $e->getMessage(),
            ]);

            $sent = false;
        }

        $notif->update(
            $sent
                ? ['status' => 'sent', 'sent_at' => now()]
                : ['status' => 'failed']
        );
    }
}