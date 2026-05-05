<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Login — tersedia untuk semua role: admin, manager, hr.
     * Setiap login berhasil dicatat di activity_logs untuk audit trail.*/
    public function login(Request $request): JsonResponse
    {
        // Validasi input — kedua field wajib ada
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $user = Auth::user();

        $token = DB::transaction(function () use ($user, $request) {
            $user->tokens()->delete();
            $newToken = $user->createToken('auth_token')->plainTextToken;

            ActivityLog::create([
                'user_id'    => $user->id,
                'action'     => 'login',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'description' => "User {$user->name} ({$user->role}) login ke sistem.",
            ]);

            return $newToken;
        });

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    //Logout — invalidate token yang sedang dipakai.

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Catat aktivitas logout sebelum token dihapus
        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'logout',
            'target_type' => null,
            'target_id'   => null,
            'description' => "User {$user->name} ({$user->role}) logout dari sistem.",
        ]);

        // Hapus token yang sedang dipakai untuk request ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
