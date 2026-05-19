<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Http\Controllers\ReportController;

// ─── Protected pages (session auth) ──────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard',    fn() => view('dashboard.index'))->name('dashboard');
    Route::get('/violations',   fn() => view('violations.index'))->name('violations.index');
    Route::get('/violations/{violation}', fn() => view('violations.show'))->name('violations.show');
    Route::get('/reports',      fn() => view('reports.index'))->name('reports.index');
    Route::get('/users',        fn() => view('users.index'))->name('users.index');
    Route::get('/zones',        fn() => view('zones.index'))->name('zones.index');
    Route::get('/shifts',       fn() => view('shifts.index'))->name('shifts.index');
    Route::get('/activity-logs', fn() => view('activity-logs.index'))->name('activity-logs.index');

    // Kamera — PENTING: /cameras/monitoring harus SEBELUM /cameras/{id}
    Route::get('/cameras', fn() => view('cameras.index'))->name('cameras.index');
    Route::get('/cameras/monitoring', fn() => view('cameras.monitoring'))->name('cameras.monitoring');
    Route::get('/cameras/{id}', function (int $id) {
        return view('cameras.show', ['cameraId' => $id]);
    })->name('cameras.show')->where('id', '[0-9]+');

    Route::get('/reports/pdf', [ReportController::class, 'exportPdf'])
        ->name('reports.pdf')
        ->middleware('role:hr,admin');
});

// ─── Auth routes ──────────────────────────────────────────────────────────────
Route::get('/', fn() => redirect('/dashboard'));
Route::get('/login', fn() => view('auth.login'))->name('login')->middleware('guest');

Route::post('/login', function () {
    $credentials = request()->validate([
        'email'    => 'required|email',
        'password' => 'required|string',
    ]);

    if (!Auth::attempt($credentials, false)) {
        return response()->json(['message' => 'Email atau password salah.'], 401);
    }

    request()->session()->regenerate();
    $user = Auth::user();

    ActivityLog::create([
        'user_id'     => $user->id,
        'action'      => 'login',
        'target_type' => null,
        'target_id'   => null,
        'description' => "User {$user->name} ({$user->role}) login ke sistem.",
        'ip_address'  => request()->ip(),
        'user_agent'  => request()->userAgent(),
    ]);

    return response()->json([
        'message' => 'Login berhasil.',
        'user'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
    ]);
})->middleware('guest');

Route::post('/logout', function () {
    $user = Auth::user();
    if ($user) {
        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'logout',
            'target_type' => null,
            'target_id'   => null,
            'description' => "User {$user->name} ({$user->role}) logout dari sistem.",
        ]);
    }
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');
