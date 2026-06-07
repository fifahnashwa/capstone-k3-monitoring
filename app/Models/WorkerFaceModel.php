<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerFaceModel extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_name',
        'model_path',
        'embeddings_count',
        'status',
        'error_message',
        'trained_at',
    ];

    protected $casts = [
        'trained_at' => 'datetime',
    ];
}
