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
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC — Tidak butuh auth apapun
// ─────────────────────────────────────────────────────────────────────────────

// Login tersedia untuk semua role: admin, manager, hr
Route::post('/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────────────────────────────────────
// TIF ONLY — Validasi via X-Service-Key header, bukan Bearer token
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('service.key')->group(function () {
    Route::post('/violations', [ViolationController::class, 'store']);
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTHENTICATED — Semua user yang login via Bearer token (Sanctum)
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Logout — semua role bisa logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── ADMIN ONLY ────────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        // Manajemen user: Admin bisa lihat, buat, update, hapus user lain.
        // Admin tidak bisa hapus diri sendiri — dicek di controller.
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Manajemen zona: CRUD zona.
        // Zona tidak bisa dihapus kalau masih punya kamera aktif — dicek di controller.
        Route::post('/zones', [ZoneController::class, 'store']);
        Route::put('/zones/{zone}', [ZoneController::class, 'update']);
        Route::delete('/zones/{zone}', [ZoneController::class, 'destroy']);

        // Manajemen aturan APD per zona: append-only (tidak ada PUT).
        // Kalau aturan berubah: DELETE rule lama → POST rule baru.
        Route::post('/zones/{zone}/rules', [ZoneController::class, 'storeRule']);
        Route::delete('/zones/{zone}/rules/{rule}', [ZoneController::class, 'destroyRule']);

        // Manajemen kamera: CRUD kamera.
        Route::post('/cameras', [CameraController::class, 'store']);
        Route::put('/cameras/{camera}', [CameraController::class, 'update']);
        Route::delete('/cameras/{camera}', [CameraController::class, 'destroy']);

        // Manajemen shift: CRUD shift.
        // Jam shift disimpan di DB supaya Admin bisa update tanpa deploy ulang.
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

        // Activity logs: hanya Admin yang bisa lihat seluruh audit trail sistem.
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });

    // ── ADMIN + MANAGER + HR (READ) ───────────────────────────────────────────
    Route::middleware('role:admin,manager,hr')->group(function () {
        // GET konfigurasi — read-only untuk Manager dan HR
        Route::get('/zones', [ZoneController::class, 'index']);
        Route::get('/cameras', [CameraController::class, 'index']);
        Route::get('/shifts', [ShiftController::class, 'index']);

        // Violations — semua role bisa lihat list dan detail,
        // tapi hanya Manager yang bisa validasi (endpoint validate di bawah)
        Route::get('/violations', [ViolationController::class, 'index']);
        Route::get('/violations/{violation}', [ViolationController::class, 'show']);

        // Dashboard KPI — agregasi siap pakai untuk SI,
        // tidak perlu loop pagination violations untuk hitung summary
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Notifikasi — setiap user hanya bisa lihat notifikasi milik sendiri,
        // filtering by auth()->id() dilakukan di controller
        Route::get('/notifications', [ViolationNotificationController::class, 'index']);
    });

    // ── MANAGER ONLY ──────────────────────────────────────────────────────────
    Route::middleware('role:manager')->group(function () {
        Route::put('/violations/{violation}/validate', [ViolationController::class, 'validateViolation']);
    });

    // ── HR ONLY ───────────────────────────────────────────────────────────────
    Route::middleware('role:hr')->group(function () {
        Route::post('/reports', [ReportController::class, 'generate']);
    });
});
