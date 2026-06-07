<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Camera extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'zone_id', 'name', 'dvr_channel', 'is_active', 'description',
        'ip_address', 'port_onvif', 'port_rtsp', 'username', 'password',
        'rtsp_path', 'rtsp_transport', 'connection_timeout',
        'ai_model_path', 'detection_size', 'confidence_threshold', 'process_every_n_frame',
        'class_mapping', 'ptz_enabled', 'ptz_speed', 'ptz_movement_duration',
        'preset_positions', 'auto_screenshot', 'screenshot_cooldown',
        'connection_status', 'last_connection_check',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'ptz_enabled'           => 'boolean',
        'auto_screenshot'       => 'boolean',
        'class_mapping'         => 'array',
        'preset_positions'      => 'array',
        'confidence_threshold'  => 'float',
        'ptz_speed'             => 'float',
        'ptz_movement_duration' => 'float',
        'last_connection_check' => 'datetime',
    ];

    protected $hidden = ['password'];

    // ── Password encryption ──────────────────────────────────────────────────

    public function setPasswordAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
        // If null/empty, leave existing password unchanged (handled in controller)
    }

    public function getDecryptedPassword(): ?string
    {
        $raw = $this->attributes['password'] ?? null;
        if (!$raw) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Exception) {
            return null;
        }
    }

    // ── Computed attributes ──────────────────────────────────────────────────

    public function getRtspUrlAttribute(): string
    {
        $user = $this->username ?? '';
        $pass = $this->getDecryptedPassword() ?? '';
        $ip   = $this->ip_address ?? '127.0.0.1';
        $port = $this->port_rtsp ?? 554;
        $path = $this->rtsp_path ?? '/stream2';

        if ($user && $pass) {
            return "rtsp://{$user}:{$pass}@{$ip}:{$port}{$path}";
        }

        return "rtsp://{$ip}:{$port}{$path}";
    }

    public function getDefaultClassMappingAttribute(): array
    {
        // Default sesuai model 7-kelas: boots(0) helmet(1) no_boots(2) no_helmet(3) no_vest(4) person(5) vest(6)
        return $this->class_mapping ?? [
            'person_id'     => 5,
            'helmet_ids'    => [1],
            'vest_ids'      => [6],
            'boots_ids'     => [0],
            'no_helmet_ids' => [3],
            'no_vest_ids'   => [4],
            'no_boots_ids'  => [2],
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
