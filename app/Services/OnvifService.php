<?php

namespace App\Services;

use App\Models\Camera;
use Illuminate\Support\Facades\Log;

class OnvifService
{
    private DetectionWorkerService $workerService;

    public function __construct(DetectionWorkerService $workerService)
    {
        $this->workerService = $workerService;
    }

    /**
     * Test ONVIF + RTSP connectivity via the detection worker.
     * Returns structured result with latency and stream info.
     */
    public function testConnection(Camera $camera): array
    {
        $result = $this->workerService->testConnection($camera->id);

        if (!$result['success']) {
            // Worker unavailable — try basic HTTP/socket check as fallback
            return $this->fallbackConnectionTest($camera, $result['message']);
        }

        return $result['data'];
    }

    /**
     * Send PTZ command via detection worker.
     */
    public function movePtz(Camera $camera, string $direction, float $speed, float $duration): array
    {
        $payload = [
            'direction' => $direction,
            'speed'     => $speed,
            'duration'  => $duration,
        ];

        return $this->workerService->sendPtzCommand($camera->id, $payload);
    }

    /**
     * Go to a saved PTZ preset by index.
     */
    public function gotoPreset(Camera $camera, int $presetIndex): array
    {
        return $this->workerService->sendPtzCommand($camera->id, [
            'action'       => 'goto_preset',
            'preset_index' => $presetIndex,
        ]);
    }

    /**
     * Save current PTZ position as a named preset.
     */
    public function savePreset(Camera $camera, string $name): array
    {
        return $this->workerService->sendPtzCommand($camera->id, [
            'action' => 'save_preset',
            'name'   => $name,
        ]);
    }

    /**
     * Go to home position.
     */
    public function gotoHome(Camera $camera): array
    {
        return $this->workerService->sendPtzCommand($camera->id, ['action' => 'home']);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function fallbackConnectionTest(Camera $camera, string $workerError): array
    {
        if (!$camera->ip_address) {
            return [
                'onvif'   => ['ok' => false, 'error' => 'IP address tidak dikonfigurasi.'],
                'rtsp'    => ['ok' => false, 'error' => 'IP address tidak dikonfigurasi.'],
                'overall' => false,
            ];
        }

        $onvifOk = $this->checkPort($camera->ip_address, $camera->port_onvif ?? 2020, 3);
        $rtspOk  = $this->checkPort($camera->ip_address, $camera->port_rtsp ?? 554, 3);

        return [
            'onvif' => [
                'ok'    => $onvifOk,
                'error' => $onvifOk ? null : "Port {$camera->port_onvif} tidak bisa dijangkau.",
            ],
            'rtsp' => [
                'ok'    => $rtspOk,
                'error' => $rtspOk ? null : "Port {$camera->port_rtsp} tidak bisa dijangkau.",
            ],
            'overall'      => $onvifOk && $rtspOk,
            'worker_error' => $workerError,
            'note'         => 'Fallback check: hanya memverifikasi port terbuka, bukan stream aktual.',
        ];
    }

    private function checkPort(string $host, int $port, int $timeout): bool
    {
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($fp) {
                fclose($fp);
                return true;
            }
        } catch (\Exception $e) {
            Log::debug("Port check {$host}:{$port} failed: {$e->getMessage()}");
        }

        return false;
    }
}
