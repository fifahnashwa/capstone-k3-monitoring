<?php

namespace App\Events;

use App\Models\Violation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViolationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Violation $violation) {}

    public function broadcastOn(): Channel
    {
        return new Channel('violations');
    }

    public function broadcastAs(): string
    {
        return 'violation.detected';
    }

    public function broadcastWith(): array
    {
        $v = $this->violation;
        $v->load('camera:id,name,zone_id', 'camera.zone:id,name');

        return [
            'id'             => $v->id,
            'time'           => $v->detected_at->format('H:i:s'),
            'event'          => $this->label($v),
            'location'       => $v->camera?->zone?->name ?? $v->camera?->name ?? '—',
            'camera_name'    => $v->camera?->name ?? '—',
            'violation_type' => $v->violation_type,
            'apd_label'      => $v->apd_label,
            'level'          => $v->level,
            'image_path'     => $v->image_path,
            'detected_at'    => $v->detected_at->toIso8601String(),
        ];
    }

    private function label(Violation $v): string
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
