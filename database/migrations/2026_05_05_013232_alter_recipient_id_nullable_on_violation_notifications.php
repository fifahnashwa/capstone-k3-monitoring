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
        Schema::table('violation_notifications', function (Blueprint $table) {

            // Drop foreign key constraint lama
            $table->dropForeign(['recipient_id']);

            // Ubah kolom jadi nullable
            $table->foreignId('recipient_id')
                ->nullable()
                ->change();

            // Tambahkan kembali foreign key dengan nullOnDelete
            $table->foreign('recipient_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('violation_notifications', function (Blueprint $table) {

            $table->dropForeign(['recipient_id']);

            $table->foreignId('recipient_id')
                ->nullable(false)
                ->change();

            $table->foreign('recipient_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }
};