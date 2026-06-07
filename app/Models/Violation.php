<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Violation extends Model
{
    protected $fillable = [
        'camera_id',
        'shift_id',
        'violation_type',
        'apd_label',
        'level',
        'confidence',
        'image_path',
        'is_outside_shift',
        'person_name',
        'validation_notes',
        'status',
        'validated_by',
        'validated_at',
        'detected_at',
    ];

    protected $casts = [
        'is_outside_shift' => 'boolean',
        'confidence'       => 'float',
        'validated_at'     => 'datetime',
        'detected_at'      => 'datetime',
    ];

    const APD_LEVELS = [
        'no_helmet' => 'major',
        'no_vest'   => 'minor',
        'no_boots'  => 'major',
    ];

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function violationNotifications(): HasMany
    {
        return $this->hasMany(ViolationNotification::class);
    }

    public function getZoneAttribute()
    {
        return $this->camera?->zone;
    }
}
