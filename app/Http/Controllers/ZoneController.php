<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Violation;
use App\Models\Zone;
use App\Models\ZoneApdRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends Controller
{
    /**
     * GET /api/zones — Daftar zona aktif beserta aturan APD-nya.
     * Akses: Admin, Manager, HR.
     */
    public function index(): JsonResponse
    {
        $zones = Zone::with('apdRules')
            ->orderBy('created_at')
            ->get();

        $data = $zones->map(function (Zone $zone) {
            return [
                'id'          => $zone->id,
                'name'        => $zone->name,
                'description' => $zone->description,
                'rules'       => $zone->apdRules->map(fn($rule) => [
                    'id'        => $rule->id,
                    'apd_label' => $rule->apd_label,
                    'level'     => Violation::APD_LEVELS[$rule->apd_label],
                ]),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/zones — Buat zona baru.
     * Akses: Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('zones', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string',
        ]);

        $zone = Zone::create($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'create_zone',
            'target_type' => 'zones',
            'target_id'   => $zone->id,
            'description' => "Admin membuat zona baru: {$zone->name}.",
        ]);

        return response()->json([
            'message' => 'Zona berhasil dibuat.',
            'data'    => $zone,
        ], 201);
    }

    /**
     * PUT /api/zones/{zone} — Update nama atau deskripsi zona.
     * Akses: Admin only.
     */
    public function update(Request $request, Zone $zone): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('zones', 'name')
                    ->ignore($zone->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string',
        ]);

        $zone->update($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'update_zone',
            'target_type' => 'zones',
            'target_id'   => $zone->id,
            'description' => "Admin mengupdate zona: {$zone->name}.",
        ]);

        return response()->json([
            'message' => 'Zona berhasil diupdate.',
            'data'    => $zone,
        ]);
    }

    /**
     * DELETE /api/zones/{zone} — Soft delete zona.
     * Akses: Admin only.
     */
    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        // Cek apakah zona masih punya kamera aktif
        $existingCameraCount = $zone->cameras()->count();

        if ($existingCameraCount > 0) {
            return response()->json([
                'message' => "Zona masih memiliki {$existingCameraCount} kamera. " .
                    "Hapus semua kamera terlebih dahulu sebelum menghapus zona.",
            ], 422);
        }

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'delete_zone',
            'target_type' => 'zones',
            'target_id'   => $zone->id,
            'description' => "Admin menghapus zona: {$zone->name}.",
        ]);

        $zone->delete(); // soft delete

        return response()->json([
            'message' => 'Zona berhasil dihapus.',
        ]);
    }

    /**
     * POST /api/zones/{zone}/rules — Tambah aturan APD ke zona.
     * Akses: Admin only.
     */
    public function storeRule(Request $request, Zone $zone): JsonResponse
    {
        $validated = $request->validate([
            'apd_label' => [
                'required',
                Rule::in(['no_helmet', 'no_vest', 'no_boots']),
                Rule::unique('zone_apd_rules')->where(
                    fn($query) =>
                    $query->where('zone_id', $zone->id)
                ),
            ],
        ]);

        $rule = ZoneApdRule::create([
            'zone_id'   => $zone->id,
            'apd_label' => $validated['apd_label'],
        ]);

        $level = Violation::APD_LEVELS[$validated['apd_label']];

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'create_zone_rule',
            'target_type' => 'zone_apd_rules',
            'target_id'   => $rule->id,
            'description' => "Admin menambah aturan APD '{$validated['apd_label']}' ke zona {$zone->name}.",
        ]);

        return response()->json([
            'message' => 'Aturan APD berhasil ditambahkan.',
            'data'    => [
                'id'        => $rule->id,
                'zone_id'   => $zone->id,
                'apd_label' => $rule->apd_label,
                'level'     => $level,
            ],
        ], 201);
    }

    /**
     * DELETE /api/zones/{zone}/rules/{rule} — Hapus aturan APD dari zona.
     * Akses: Admin only.
     */
    public function destroyRule(Request $request, Zone $zone, ZoneApdRule $rule): JsonResponse
    {
        // Pastikan rule ini memang milik zona yang dimaksud.
        // Mencegah Admin menghapus rule zona lain via URL manipulation.
        if ($rule->zone_id !== $zone->id) {
            return response()->json([
                'message' => 'Aturan APD ini tidak ditemukan di zona tersebut.',
            ], 404);
        }

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'delete_zone_rule',
            'target_type' => 'zone_apd_rules',
            'target_id'   => $rule->id,
            'description' => "Admin menghapus aturan APD '{$rule->apd_label}' dari zona {$zone->name}.",
        ]);

        $rule->delete(); // hard delete — intentional, tabel append-only

        return response()->json([
            'message' => 'Aturan APD berhasil dihapus.',
        ]);
    }
}
