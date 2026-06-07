<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('activity_logs', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained('users')
            ->restrictOnDelete();

        $table->string('action', 100);
        $table->string('target_type', 100)->nullable();
        $table->unsignedBigInteger('target_id')->nullable();
        $table->text('description')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
