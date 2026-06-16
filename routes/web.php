<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Http\Controllers\ReportController;

// ─── Semua role (cukup login) ─────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => view('dashboard.index'))->name('dashboard');
});

// ─── Admin & Manager ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/violations',             fn() => view('violations.index'))->name('violations.index');
    Route::get('/violations/{violation}', fn() => view('violations.show'))->name('violations.show');
});

// ─── HR saja ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:hr'])->group(function () {
    Route::get('/reports', fn() => view('reports.index'))->name('reports.index');
});

// ─── HR & Admin — Export PDF ──────────────────────────────────────────────────
Route::middleware(['auth', 'role:hr,admin'])->group(function () {
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');
});

// ─── Admin saja ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Kamera — monitoring HARUS sebelum {id}
    Route::get('/cameras',            fn() => view('cameras.index'))->name('cameras.index');
    Route::get('/cameras/monitoring', fn() => view('cameras.monitoring'))->name('cameras.monitoring');
    Route::get('/cameras/{id}',       fn(int $id) => view('cameras.show', ['cameraId' => $id]))
        ->name('cameras.show')->where('id', '[0-9]+');

    Route::get('/operasional',   fn() => view('operasional.index'))->name('operasional.index');
    Route::get('/users',         fn() => view('users.index'))->name('users.index');
    Route::get('/zones',         fn() => view('zones.index'))->name('zones.index');
    Route::get('/shifts',        fn() => view('shifts.index'))->name('shifts.index');
    Route::get('/activity-logs', fn() => view('activity-logs.index'))->name('activity-logs.index');
    Route::get('/workers/faces', fn() => view('workers.faces'))->name('workers.faces');
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