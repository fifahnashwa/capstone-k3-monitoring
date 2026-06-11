<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ViolationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ViolationNotificationController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\WorkerFaceModelController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC
// ─────────────────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────────────────────────────────────
// SERVICE KEY — TIF pipeline + Detection Worker
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('service.key')->group(function () {
    Route::post('/violations', [ViolationController::class, 'store']);
    Route::patch('/violations/patch-person-name', [ViolationController::class, 'patchPersonName']);

    // Detection worker endpoints
    Route::get('/cameras/active',             [CameraController::class, 'getActiveCameraIds']);
    Route::get('/cameras/{camera}/config',    [CameraController::class, 'getCameraConfig']);
    Route::post('/cameras/{camera}/health-check', [CameraController::class, 'healthCheck']);
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTHENTICATED — Session (Laravel Web Guard)
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // ── ADMIN ONLY ────────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        // Worker Face Models
        Route::get('/worker-face-models',                    [WorkerFaceModelController::class, 'index']);
        Route::post('/worker-face-models/train',             [WorkerFaceModelController::class, 'train']);
        Route::delete('/worker-face-models/{workerFaceModel}', [WorkerFaceModelController::class, 'destroy']);

        // Pengguna
        Route::get('/users',            [UserController::class, 'index']);
        Route::post('/users',           [UserController::class, 'store']);
        Route::put('/users/{user}',     [UserController::class, 'update']);
        Route::delete('/users/{user}',  [UserController::class, 'destroy']);

        // Zona
        Route::post('/zones',                          [ZoneController::class, 'store']);
        Route::put('/zones/{zone}',                    [ZoneController::class, 'update']);
        Route::delete('/zones/{zone}',                 [ZoneController::class, 'destroy']);
        Route::post('/zones/{zone}/rules',             [ZoneController::class, 'storeRule']);
        Route::delete('/zones/{zone}/rules/{rule}',    [ZoneController::class, 'destroyRule']);

        // Kamera — CRUD + konfigurasi
        Route::post('/cameras',                        [CameraController::class, 'store']);
        Route::put('/cameras/{camera}',                [CameraController::class, 'update']);
        Route::delete('/cameras/{camera}',             [CameraController::class, 'destroy']);
        Route::post('/cameras/{camera}/test-connection', [CameraController::class, 'testConnection']);
        Route::post('/cameras/{camera}/toggle-status', [CameraController::class, 'toggleStatus']);
        Route::post('/cameras/upload-model',           [CameraController::class, 'uploadModel']);
        Route::post('/cameras/{camera}/ptz/save-preset', [CameraController::class, 'savePreset']);

        // Shift
        Route::post('/shifts',          [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}',   [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

        // Activity logs
        Route::get('/activity-logs',    [ActivityLogController::class, 'index']);
    });

    // ── ADMIN + MANAGER + HR ──────────────────────────────────────────────────
    Route::middleware('role:admin,manager,hr')->group(function () {
        // Config read
        Route::get('/zones',                 [ZoneController::class, 'index']);
        Route::get('/cameras',               [CameraController::class, 'index']);
        Route::get('/cameras/{camera}',      [CameraController::class, 'show']);
        Route::get('/shifts',                [ShiftController::class, 'index']);

        // Stream & screenshot (semua role bisa lihat monitoring)
        Route::get('/cameras/{camera}/stream',     [CameraController::class, 'streamProxy']);
        Route::post('/cameras/{camera}/screenshot', [CameraController::class, 'screenshot']);

        // PTZ (manager & admin) — diatur di RoleMiddleware tambahan di controller jika perlu
        Route::post('/cameras/{camera}/ptz',              [CameraController::class, 'ptzControl']);
        Route::post('/cameras/{camera}/ptz/preset/{presetIndex}', [CameraController::class, 'gotoPreset'])
            ->where('presetIndex', '[0-9]+');

        // Violations
        Route::get('/violations',           [ViolationController::class, 'index']);
        Route::get('/violations/{violation}', [ViolationController::class, 'show']);

        // Dashboard
        Route::get('/dashboard/summary',     [DashboardController::class, 'summary']);
        Route::get('/dashboard/stats',       [CameraController::class, 'dashboardStats']);
        Route::get('/dashboard/activity-log', [CameraController::class, 'dashboardActivityLog']);

        // Notifikasi
        Route::get('/notifications',         [ViolationNotificationController::class, 'index']);
    });

    // ── MANAGER + ADMIN ───────────────────────────────────────────────────────
    Route::middleware('role:manager,admin')->group(function () {
        Route::put('/violations/{violation}/validate', [ViolationController::class, 'validateViolation']);
        Route::delete('/violations/{violation}',       [ViolationController::class, 'destroy']);
    });

    // ── HR ONLY ───────────────────────────────────────────────────────────────
    Route::middleware('role:hr')->group(function () {
        Route::post('/reports', [ReportController::class, 'generate']);
    });
});
