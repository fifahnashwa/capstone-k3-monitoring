<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('dvr_channel');
            $table->integer('port_onvif')->default(2020)->after('ip_address');
            $table->integer('port_rtsp')->default(554)->after('port_onvif');
            $table->string('username', 100)->nullable()->after('port_rtsp');
            $table->text('password')->nullable()->after('username');
            $table->string('rtsp_path', 100)->default('/stream2')->after('password');
            $table->enum('rtsp_transport', ['tcp', 'udp'])->default('tcp')->after('rtsp_path');
            $table->integer('connection_timeout')->default(10)->after('rtsp_transport');
            $table->string('ai_model_path')->nullable()->after('connection_timeout');
            $table->integer('detection_size')->default(640)->after('ai_model_path');
            $table->decimal('confidence_threshold', 3, 2)->default(0.40)->after('detection_size');
            $table->integer('process_every_n_frame')->default(2)->after('confidence_threshold');
            $table->json('class_mapping')->nullable()->after('process_every_n_frame');
            $table->boolean('ptz_enabled')->default(false)->after('class_mapping');
            $table->decimal('ptz_speed', 2, 1)->default(0.6)->after('ptz_enabled');
            $table->decimal('ptz_movement_duration', 3, 1)->default(0.4)->after('ptz_speed');
            $table->json('preset_positions')->nullable()->after('ptz_movement_duration');
            $table->boolean('auto_screenshot')->default(true)->after('preset_positions');
            $table->integer('screenshot_cooldown')->default(30)->after('auto_screenshot');
            $table->enum('connection_status', ['online', 'offline', 'unknown'])->default('unknown')->after('screenshot_cooldown');
            $table->timestamp('last_connection_check')->nullable()->after('connection_status');
            $table->text('description')->nullable()->after('last_connection_check');
        });
    }

    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address', 'port_onvif', 'port_rtsp', 'username', 'password',
                'rtsp_path', 'rtsp_transport', 'connection_timeout', 'ai_model_path',
                'detection_size', 'confidence_threshold', 'process_every_n_frame',
                'class_mapping', 'ptz_enabled', 'ptz_speed', 'ptz_movement_duration',
                'preset_positions', 'auto_screenshot', 'screenshot_cooldown',
                'connection_status', 'last_connection_check', 'description',
            ]);
        });
    }
};
