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
    Schema::create('violations', function (Blueprint $table) {
        $table->id();

        $table->foreignId('camera_id')
            ->constrained('cameras')
            ->restrictOnDelete();

        $table->foreignId('shift_id')
            ->nullable()
            ->constrained('shifts')
            ->restrictOnDelete();

        $table->enum('violation_type', ['apd', 'discipline']);
        $table->enum('apd_label', ['no_helmet', 'no_vest', 'no_boots'])->nullable();
        $table->enum('level', ['minor', 'major'])->nullable();
        $table->decimal('confidence', 5, 4)->nullable();
        $table->string('image_path', 500);
        $table->boolean('is_outside_shift')->default(false);

        $table->string('person_name')->nullable();
        $table->text('validation_notes')->nullable();

        $table->enum('status', ['pending', 'validated', 'rejected', 'reported'])
            ->default('pending');
        $table->foreignId('validated_by')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();

        $table->dateTime('validated_at')->nullable();
        $table->dateTime('detected_at');

        $table->timestamps(); // created_at = kapan event masuk DB, updated_at = update terakhir
        $table->index('status');
        $table->index('camera_id');
        $table->index('detected_at'); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
