<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_face_models', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('employee_name');
            $table->string('model_path')->nullable();
            $table->integer('embeddings_count')->default(0);
            $table->enum('status', ['pending', 'trained', 'failed'])->default('pending');
            $table->string('error_message')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_face_models');
    }
};
