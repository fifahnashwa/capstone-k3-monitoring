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
    Schema::create('zone_apd_rules', function (Blueprint $table) {
        $table->id();

        $table->foreignId('zone_id')
            ->constrained('zones')
            ->restrictOnDelete();

        $table->enum('apd_label', ['no_helmet', 'no_vest', 'no_boots']);
        $table->timestamp('created_at')->useCurrent();
        $table->unique(['zone_id', 'apd_label']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_apd_rules');
    }
};
