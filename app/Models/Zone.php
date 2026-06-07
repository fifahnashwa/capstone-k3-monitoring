<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function apdRules(): HasMany
    {
        return $this->hasMany(ZoneApdRule::class);
    }

    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class);
    }
}

