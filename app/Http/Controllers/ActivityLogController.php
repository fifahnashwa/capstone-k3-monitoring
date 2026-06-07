<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * GET /api/activity-logs — Daftar semua activity log.
     * Akses: Admin only.*/
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'   => 'sometimes|exists:users,id',
            'action'    => 'sometimes|string|max:100',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to'   => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $query = ActivityLog::with('user:id,name,role')
            ->orderBy('created_at', 'desc');

        // Filter opsional per user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter opsional per jenis aksi
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter rentang tanggal berdasarkan created_at
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Pagination — default 50 per halaman karena log bisa banyak
        $logs = $query->paginate(50);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'total'    => $logs->total(),
                'page'     => $logs->currentPage(),
                'per_page' => $logs->perPage(),
            ],
        ]);
    }
}
