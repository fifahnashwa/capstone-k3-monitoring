<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DetectionWorkerService
{
    private string $baseUrl;
    private string $serviceKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl    = rtrim(config('services.detection_worker.url', 'http://detection_worker:8000'), '/');
        $this->serviceKey = config('services.detection_worker.key', '');
        $this->timeout    = (int) config('services.detection_worker.timeout', 5);
    }

    public function startCamera(int $cameraId): bool
    {
        return $this->post("/api/cameras/{$cameraId}/start");
    }

    public function stopCamera(int $cameraId): bool
    {
        return $this->post("/api/cameras/{$cameraId}/stop");
    }

    public function reloadCamera(int $cameraId): bool
    {
        return $this->post("/api/cameras/{$cameraId}/reload");
    }

    public function testConnection(int $cameraId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout + 15)
                ->post("{$this->baseUrl}/api/cameras/{$cameraId}/test-connection");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => $response->json('detail', 'Worker tidak merespons.')];
        } catch (\Exception $e) {
            Log::warning("DetectionWorker testConnection error: {$e->getMessage()}");
            return ['success' => false, 'message' => 'Detection worker tidak dapat dihubungi. ' . $e->getMessage()];
        }
    }

    public function sendPtzCommand(int $cameraId, array $payload): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/cameras/{$cameraId}/ptz", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => $response->json('detail', 'Perintah PTZ gagal.')];
        } catch (\Exception $e) {
            Log::warning("DetectionWorker PTZ error: {$e->getMessage()}");
            return ['success' => false, 'message' => 'Detection worker tidak dapat dihubungi.'];
        }
    }

    public function requestScreenshot(int $cameraId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout + 10)
                ->post("{$this->baseUrl}/api/cameras/{$cameraId}/screenshot");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'Screenshot gagal diambil.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Detection worker tidak dapat dihubungi.'];
        }
    }

    public function getCameraHealth(int $cameraId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/api/cameras/{$cameraId}/health");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::debug("DetectionWorker health check failed: {$e->getMessage()}");
        }

        return ['status' => 'unknown', 'camera_id' => $cameraId];
    }

    public function getStreamUrl(int $cameraId): string
    {
        return "{$this->baseUrl}/api/cameras/{$cameraId}/stream";
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(3)
                ->get("{$this->baseUrl}/health");

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    private function post(string $path): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}{$path}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("DetectionWorker POST {$path} failed: {$e->getMessage()}");
            return false;
        }
    }

    private function headers(): array
    {
        return [
            'X-Service-Key' => $this->serviceKey,
            'Accept'        => 'application/json',
        ];
    }
}
