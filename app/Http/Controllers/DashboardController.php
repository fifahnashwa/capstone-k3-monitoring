<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary — Agregasi KPI siap pakai untuk dashboard SI.
     * Akses: Admin, Manager, HR.
     * Filter opsional: date_from, date_to, zone_id, shift_id.
     * Kalau tidak ada filter tanggal, default ke 30 hari terakhir.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to'   => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
            'zone_id'   => 'sometimes|integer',
            'shift_id'  => 'sometimes|integer',
        ]);

        // Default rentang: 30 hari terakhir kalau tidak ada filter tanggal
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo   = $request->input('date_to', now()->format('Y-m-d'));

        // Base query — semua filter diterapkan di sini
        // Hanya violations berstatus 'validated' dan 'reported' yang dihitung.
        // 'pending' dan 'rejected' tidak masuk KPI — pending belum dikonfirmasi,
        $baseQuery = Violation::whereBetween('detected_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay(),
        ])
            ->whereIn('status', ['validated', 'reported']);
        // Filter opsional per zona via relasi camera
        if ($request->filled('zone_id')) {
            $baseQuery->whereHas(
                'camera',
                fn($q) =>
                $q->where('zone_id', $request->zone_id)
            );
        }

        // Filter opsional per shift
        if ($request->filled('shift_id')) {
            $baseQuery->where('shift_id', $request->shift_id);
        }

        // ── TOTAL ─────────────────────────────────────────────────────────────
        $total = (clone $baseQuery)->count();

        // ── BY TYPE ───────────────────────────────────────────────────────────
        $byType = (clone $baseQuery)
            ->selectRaw('violation_type, COUNT(*) as total')
            ->groupBy('violation_type')
            ->pluck('total', 'violation_type');

        // ── BY LEVEL ──────────────────────────────────────────────────────────
        // level bisa null untuk violation disiplin — filter hanya apd
        $byLevel = (clone $baseQuery)
            ->whereNotNull('level')
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        // ── BY LABEL ──────────────────────────────────────────────────────────
        // apd_label bisa null untuk violation disiplin — filter hanya apd
        $byLabel = (clone $baseQuery)
            ->whereNotNull('apd_label')
            ->selectRaw('apd_label, COUNT(*) as total')
            ->groupBy('apd_label')
            ->pluck('total', 'apd_label');

        // ── BY ZONE ───────────────────────────────────────────────────────────
        // Join ke cameras dan zones untuk group by zona.
        $byZone = (clone $baseQuery)
            ->leftJoin('cameras', function ($join) {
                $join->on('violations.camera_id', '=', 'cameras.id')
                    ->whereNull('cameras.deleted_at');
            })
            ->leftJoin('zones', function ($join) {
                $join->on('cameras.zone_id', '=', 'zones.id')
                    ->whereNull('zones.deleted_at');
            })
            ->selectRaw('
    COALESCE(zones.id, 0) as zone_id,
    COALESCE(zones.name, "Zona Tidak Diketahui") as zone_name,
    COUNT(*) as total')
            ->groupBy('zone_id', 'zone_name')
            ->get();

        // ── BY SHIFT ──────────────────────────────────────────────────────────
        // Violation disiplin punya shift_id null — ditampilkan terpisah
        // dengan label 'Di luar shift', tidak digabung ke shift manapun.
        $byShiftRaw = (clone $baseQuery)
            ->leftJoin('shifts', 'violations.shift_id', '=', 'shifts.id')
            ->selectRaw('shifts.id as shift_id, shifts.name as shift_name, COUNT(*) as total')
            ->groupBy('shifts.id', 'shifts.name')
            ->get();

        $byShift = $byShiftRaw->map(fn($row) => [
            'shift_id' => $row->shift_id,
            'shift'    => $row->shift_name ?? 'Di luar shift',
            'total'    => $row->total,
        ]);

        // ── DAILY TREND ───────────────────────────────────────────────────────
        // Trend harian untuk chart di dashboard SI
        $dailyTrend = (clone $baseQuery)
            ->selectRaw('DATE(detected_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date'  => $row->date,
                'total' => $row->total,
            ]);

        return response()->json([
            'period' => [
                'from' => $dateFrom,
                'to'   => $dateTo,
            ],
            'total_violations' => $total,
            'by_type'          => [
                'apd'        => $byType['apd'] ?? 0,
                'discipline' => $byType['discipline'] ?? 0,
            ],
            'by_level'         => [
                'major' => $byLevel['major'] ?? 0,
                'minor' => $byLevel['minor'] ?? 0,
            ],
            'by_label'         => [
                'no_helmet' => $byLabel['no_helmet'] ?? 0,
                'no_vest'   => $byLabel['no_vest'] ?? 0,
                'no_boots'  => $byLabel['no_boots'] ?? 0,
            ],
            'by_zone'          => $byZone,
            'by_shift'         => $byShift,
            'daily_trend'      => $dailyTrend,
        ]);
    }
}
