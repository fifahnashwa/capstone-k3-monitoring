<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * GET /api/shifts — Daftar shift aktif.
     * Akses: Admin, Manager, HR.
     */
    public function index(): JsonResponse
    {
        $shifts = Shift::orderBy('start_time')->get();

        return response()->json(['data' => $shifts]);
    }

    /**
     * POST /api/shifts — Buat shift baru.
     * Akses: Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i:s',
            'end_time'   => [
                'required',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value === $request->input('start_time')) {
                        $fail('Waktu mulai dan selesai tidak boleh sama.');
                    }
                    // end < start = overnight shift → diizinkan
                }
            ]
        ]);

        $shift = Shift::create($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'create_shift',
            'target_type' => 'shifts',
            'target_id'   => $shift->id,
            'description' => "Admin membuat shift: {$shift->name} ({$shift->start_time} - {$shift->end_time}).",
        ]);

        return response()->json([
            'message' => 'Shift berhasil dibuat.',
            'data'    => $shift,
        ], 201);
    }

    /**
     * PUT /api/shifts/{shift} — Update jam shift.
     * Akses: Admin only.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => [
                'sometimes',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) use ($request, $shift) {
                    $start = $request->input('start_time', $shift->start_time);

                    // hanya tolak kalau sama persis
                    if ($value <= $start) {
                        $fail('Waktu selesai harus lebih besar dari waktu mulai.');
                    }
                },
            ],
        ]);

        $shift->update($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'update_shift',
            'target_type' => 'shifts',
            'target_id'   => $shift->id,
            'description' => "Admin mengupdate shift: {$shift->name}.",
        ]);

        return response()->json([
            'message' => 'Shift berhasil diupdate.',
            'data'    => $shift,
        ]);
    }

    /**
     * DELETE /api/shifts/{shift} — Soft delete shift.
     * Akses: Admin only. */
    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'delete_shift',
            'target_type' => 'shifts',
            'target_id'   => $shift->id,
            'description' => "Admin menghapus shift: {$shift->name}.",
        ]);

        $shift->delete(); // soft delete

        return response()->json([
            'message' => 'Shift berhasil dihapus.',
        ]);
    }
}
