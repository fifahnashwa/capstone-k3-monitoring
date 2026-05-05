<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Camera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CameraController extends Controller
{
    /**
     * GET /api/cameras — Daftar kamera aktif (tidak include soft-deleted).
     * Akses: Admin, Manager, HR.
     */
    public function index(): JsonResponse
    {
        // Eager load zone supaya response include zone_name tanpa N+1
        $cameras = Camera::with('zone:id,name')
            ->orderBy('zone_id')
            ->orderBy('name')
            ->get();

        $data = $cameras->map(fn(Camera $camera) => [
            'id'          => $camera->id,
            'zone_id'     => $camera->zone_id,
            'zone_name'   => $camera->zone?->name,
            'name'        => $camera->name,
            'dvr_channel' => $camera->dvr_channel,
            'is_active'   => $camera->is_active,
            'created_at'  => $camera->created_at,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/cameras — Tambah kamera baru.
     * Akses: Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id'     => 'required|exists:zones,id,deleted_at,NULL',
            'name'        => 'required|string|max:255',
            'dvr_channel' => [
                'required',
                'string',
                'max:10',
                Rule::unique('cameras', 'dvr_channel')->whereNull('deleted_at'),
            ],
            'is_active'   => 'boolean',
        ]);

        $camera = Camera::create($validated);
        $camera->load('zone:id,name');

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'create_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Admin menambah kamera: {$camera->name} ({$camera->dvr_channel}) di zona {$camera->zone->name}.",
        ]);

        return response()->json([
            'message' => 'Kamera berhasil ditambahkan.',
            'data'    => [
                'id'          => $camera->id,
                'zone_id'     => $camera->zone_id,
                'zone_name'   => $camera->zone->name,
                'name'        => $camera->name,
                'dvr_channel' => $camera->dvr_channel,
                'is_active'   => $camera->is_active,
                'created_at'  => $camera->created_at,
            ],
        ], 201);
    }

    /**
     * PUT /api/cameras/{camera} — Update data kamera.
     * Akses: Admin only.
     */
    public function update(Request $request, Camera $camera): JsonResponse
    {
        $validated = $request->validate([
            // FIX: Sama seperti store — exclude soft-deleted zones
            'zone_id'     => 'sometimes|exists:zones,id,deleted_at,NULL',
            'name'        => 'sometimes|string|max:255',
            'dvr_channel' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('cameras', 'dvr_channel')
                    ->ignore($camera->id)
                    ->whereNull('deleted_at'),
            ],
            'is_active'   => 'sometimes|boolean',
        ]);

        $camera->update($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'update_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Admin mengupdate kamera: {$camera->name}.",
        ]);

        $camera->load('zone:id,name');
        return response()->json([
            'message' => 'Kamera berhasil diupdate.',
            'data'    => [
                'id'          => $camera->id,
                'zone_id'     => $camera->zone_id,
                'zone_name'   => $camera->zone?->name,
                'name'        => $camera->name,
                'dvr_channel' => $camera->dvr_channel,
                'is_active'   => $camera->is_active,
                'created_at'  => $camera->created_at,
            ],
        ]);
    }

    /**
     * DELETE /api/cameras/{camera} — Soft delete kamera.
     * Akses: Admin only.
     */
    public function destroy(Request $request, Camera $camera): JsonResponse
    {
        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'delete_camera',
            'target_type' => 'cameras',
            'target_id'   => $camera->id,
            'description' => "Admin menghapus kamera: {$camera->name} ({$camera->dvr_channel}).",
        ]);

        $camera->delete(); // soft delete

        return response()->json([
            'message' => 'Kamera berhasil dihapus.',
        ]);
    }
}
