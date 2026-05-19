<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Camera;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CameraService
{
    public function __construct(
        private DetectionWorkerService $workerService,
    ) {}

    public function store(array $data, int $userId): Camera
    {
        $camera = Camera::create($this->prepareData($data));
        $camera->load('zone:id,name');

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => 'create_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Menambah kamera: {$camera->name} ({$camera->dvr_channel}) di zona {$camera->zone?->name}.",
        ]);

        return $camera;
    }

    public function update(Camera $camera, array $data, int $userId): Camera
    {
        $oldData = $camera->only(['name', 'ip_address', 'is_active', 'connection_status']);

        // Password: only update if user explicitly sent a new password
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $camera->update($this->prepareData($data));
        $camera->load('zone:id,name');

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => 'update_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Mengupdate kamera: {$camera->name}.",
        ]);

        // Notify detection worker to reload config
        $this->workerService->reloadCamera($camera->id);

        return $camera;
    }

    public function delete(Camera $camera, int $userId): void
    {
        $this->workerService->stopCamera($camera->id);

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => 'delete_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Menghapus kamera: {$camera->name} ({$camera->dvr_channel}).",
        ]);

        $camera->delete();
    }

    public function toggleStatus(Camera $camera, int $userId): Camera
    {
        $wasActive = $camera->is_active;
        $camera->update(['is_active' => !$wasActive]);

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => 'toggle_camera_status',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Mengubah status kamera {$camera->name} menjadi " . ($camera->is_active ? 'Aktif' : 'Nonaktif') . ".",
        ]);

        if ($camera->is_active) {
            $this->workerService->startCamera($camera->id);
        } else {
            $this->workerService->stopCamera($camera->id);
        }

        return $camera;
    }

    public function uploadModel(UploadedFile $file): string
    {
        $filename = 'models/' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            . '_' . now()->format('YmdHis') . '.onnx';

        Storage::disk('local')->putFileAs('', $file, $filename);

        return Storage::disk('local')->path($filename);
    }

    public function getConfigForWorker(Camera $camera): array
    {
        return [
            'id'                    => $camera->id,
            'name'                  => $camera->name,
            'ip_address'            => $camera->ip_address,
            'port_onvif'            => $camera->port_onvif,
            'port_rtsp'             => $camera->port_rtsp,
            'username'              => $camera->username,
            'password'              => $camera->getDecryptedPassword(),
            'rtsp_path'             => $camera->rtsp_path,
            'rtsp_transport'        => $camera->rtsp_transport,
            'connection_timeout'    => $camera->connection_timeout,
            'ai_model_path'         => $camera->ai_model_path,
            'detection_size'        => $camera->detection_size,
            'confidence_threshold'  => (float) $camera->confidence_threshold,
            'process_every_n_frame' => $camera->process_every_n_frame,
            'class_mapping'         => $camera->default_class_mapping,
            'ptz_enabled'           => $camera->ptz_enabled,
            'ptz_speed'             => (float) $camera->ptz_speed,
            'ptz_movement_duration' => (float) $camera->ptz_movement_duration,
            'preset_positions'      => $camera->preset_positions ?? [],
            'auto_screenshot'       => $camera->auto_screenshot,
            'screenshot_cooldown'   => $camera->screenshot_cooldown,
        ];
    }

    public function updateConnectionStatus(Camera $camera, string $status): void
    {
        $camera->update([
            'connection_status'    => $status,
            'last_connection_check' => now(),
        ]);
    }

    private function prepareData(array $data): array
    {
        // Ensure class_mapping is valid JSON if passed as string
        if (isset($data['class_mapping']) && is_string($data['class_mapping'])) {
            $decoded = json_decode($data['class_mapping'], true);
            $data['class_mapping'] = $decoded ?? null;
        }

        if (isset($data['preset_positions']) && is_string($data['preset_positions'])) {
            $decoded = json_decode($data['preset_positions'], true);
            $data['preset_positions'] = $decoded ?? null;
        }

        return $data;
    }
}
