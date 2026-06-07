<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users — Daftar semua user.
     * Akses: Admin only.
     */
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $users]);
    }

    /**
     * POST /api/users — Buat user baru.
     * Akses: Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => 'required|string|min:8',
            // Role hanya boleh salah satu dari tiga ini
            'role'     => ['required', Rule::in(['admin', 'manager', 'hr'])],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'], // akan di-hash otomatis via cast 'hashed'
            'role'     => $validated['role'],
        ]);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'create_user',
            'target_type' => 'users',
            'target_id'   => $user->id,
            'description' => "Admin membuat user baru: {$user->name} ({$user->role}).",
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'data'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    /**
     * PUT /api/users/{user} — Update data user lain.
     * Akses: Admin only.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Cegah Admin mengubah dirinya sendiri lewat endpoint ini
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Tidak bisa mengubah akun sendiri lewat endpoint ini.',
            ], 422);
        }

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                // Email harus unik, tapi abaikan user yang sedang diupdate
                Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at'),
            ],
            'role'  => ['sometimes', Rule::in(['admin', 'manager', 'hr'])],
        ]);

        $user->update($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'update_user',
            'target_type' => 'users',
            'target_id'   => $user->id,
            'description' => "Admin mengupdate data user: {$user->name} ({$user->role}).",
        ]);

        return response()->json([
            'message' => 'User berhasil diupdate.',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * DELETE /api/users/{user} — Soft delete user.
     * Akses: Admin only.
     * Admin tidak bisa menghapus dirinya sendiri.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Cegah Admin menghapus dirinya sendiri
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Tidak bisa menghapus akun sendiri.',
            ], 422);
        }

        // Catat sebelum delete supaya nama user masih bisa diambil
        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'delete_user',
            'target_type' => 'users',
            'target_id'   => $user->id,
            'description' => "Admin menghapus user: {$user->name} ({$user->role}).",
        ]);

        // Soft delete — row tetap ada di DB dengan deleted_at diisi
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }
}
