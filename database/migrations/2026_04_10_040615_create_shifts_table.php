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
    Schema::create('shifts', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100);
        $table->time('start_time');
        $table->time('end_time');

        $table->timestamps();

        // Soft delete karena violations historis menyimpan shift_id.
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
