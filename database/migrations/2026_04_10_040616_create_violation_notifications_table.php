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
    Schema::create('violation_notifications', function (Blueprint $table) {
        $table->id();

        $table->foreignId('violation_id')
            ->constrained('violations')
            ->restrictOnDelete();

        $table->foreignId('recipient_id')
            ->constrained('users')
            ->restrictOnDelete();
        
        $table->enum('channel', ['telegram']);
        $table->enum('type', ['alert_manager', 'notify_hr']);
        $table->enum('status', ['sent', 'failed']);
        $table->dateTime('sent_at')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violation_notifications');
    }
};
