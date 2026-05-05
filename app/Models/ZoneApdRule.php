<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneApdRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'apd_label',
    ];

    protected $casts = [
        'zone_id'    => 'integer',
        'created_at' => 'datetime',
    ];
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
