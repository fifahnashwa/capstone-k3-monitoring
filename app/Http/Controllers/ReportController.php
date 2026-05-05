<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * POST /api/reports — Generate laporan pelanggaran (HR only)
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $dateFrom = $validated['date_from'];
        $dateTo   = $validated['date_to'];

        $violations = DB::transaction(function () use ($request, $dateFrom, $dateTo) {

            $query = Violation::with([
                'camera:id,name,dvr_channel,zone_id',
                'camera.zone:id,name',
                'shift:id,name',
                'validator:id,name',
            ])
                ->whereBetween('detected_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay(),
                ])
                ->where('status', 'validated')
                ->lockForUpdate(); 

            $violations = $query->get();

            if ($violations->isEmpty()) {
                return collect(); 
            }

            // Update status → reported
            Violation::whereIn('id', $violations->pluck('id'))
                ->update([
                    'status' => 'reported',
                    'updated_at' => now(),
                ]);

            // Log activity
            ActivityLog::create([
                'user_id'     => $request->user()->id,
                'action'      => 'generate_report',
                'target_type' => 'violations',
                'target_id'   => null,
                'description' => "HR generate laporan periode {$dateFrom} s/d {$dateTo}. Total: {$violations->count()} pelanggaran.",
            ]);

            return $violations;
        });

        // ================= HANDLE EMPTY =================
        if ($violations->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada pelanggaran tervalidasi dalam periode ini.',
                'period'  => ['from' => $dateFrom, 'to' => $dateTo],
                'summary' => [
                    'total_violations' => 0,
                    'by_type'          => ['apd' => 0, 'discipline' => 0],
                    'by_level'         => ['major' => 0, 'minor' => 0],
                    'by_label'         => [
                        'no_helmet' => 0,
                        'no_vest'   => 0,
                        'no_boots'  => 0
                    ],
                    'by_zone'  => [],
                    'by_shift' => [],
                ],
                'violations' => [],
            ]);
        }

        // ================= SUMMARY =================
        $byType = $violations->groupBy('violation_type')->map->count();

        $byLevel = $violations->whereNotNull('level')
            ->groupBy('level')->map->count();

        $byLabel = $violations->whereNotNull('apd_label')
            ->groupBy('apd_label')->map->count();

        $byZone = $violations->groupBy(fn($v) => $v->camera?->zone?->name ?? 'Unknown')
            ->map(fn($group, $zoneName) => [
                'zone'  => $zoneName,
                'total' => $group->count(),
            ])->values();

        $byShift = $violations->groupBy(fn($v) => $v->shift?->name ?? 'Di luar shift')
            ->map(fn($group, $shiftName) => [
                'shift' => $shiftName,
                'total' => $group->count(),
            ])->values();

        // ================= DETAIL =================
        $detail = $violations->map(fn(Violation $v) => [
            'id'              => $v->id,
            'tanggal'         => $v->detected_at->format('Y-m-d'),
            'waktu'           => $v->detected_at->format('H:i:s'),
            'shift'           => $v->shift?->name ?? 'Di luar shift',
            'zona'            => $v->camera?->zone?->name ?? 'Unknown',
            'kamera'          => $v->camera?->dvr_channel ?? '-',
            'jenis'           => $v->violation_type === 'apd' ? 'APD' : 'Disiplin',
            'label'           => $v->apd_label ?? 'Orang di luar shift',
            'level'           => $v->level ? strtoupper($v->level) : '-',
            'nama_pelanggar'  => $v->person_name ?? 'Tidak diidentifikasi',
            'divalidasi_oleh' => $v->validator?->name ?? '-',
            'catatan'         => $v->validation_notes ?? '-',
        ]);

        // ================= RESPONSE =================
        return response()->json([
            'period'  => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_violations' => $violations->count(),
                'by_type' => [
                    'apd'        => $byType['apd'] ?? 0,
                    'discipline' => $byType['discipline'] ?? 0,
                ],
                'by_level' => [
                    'major' => $byLevel['major'] ?? 0,
                    'minor' => $byLevel['minor'] ?? 0,
                ],
                'by_label' => [
                    'no_helmet' => $byLabel['no_helmet'] ?? 0,
                    'no_vest'   => $byLabel['no_vest'] ?? 0,
                    'no_boots'  => $byLabel['no_boots'] ?? 0,
                ],
                'by_zone'  => $byZone,
                'by_shift' => $byShift,
            ],
            'violations' => $detail,
        ]);
    }
}