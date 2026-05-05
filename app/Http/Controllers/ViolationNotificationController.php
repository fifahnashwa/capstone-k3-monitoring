<?php

namespace App\Http\Controllers;

use App\Models\ViolationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViolationNotificationController extends Controller
{
    /**
     * GET /api/notifications — Log notifikasi milik user yang sedang login.
     * Akses: Admin, Manager, HR.
     * Setiap user hanya bisa melihat notifikasi yang dikirimkan ke dirinya sendiri.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ViolationNotification::with(
            'violation:id,violation_type,apd_label,level,status,detected_at'
        )->orderBy('created_at', 'desc');

        if ($user->role === 'manager') {
            $query->where('type', 'alert_manager');
        } elseif ($user->role === 'hr') {
            $query->where('type', 'notify_hr');
        }
        // admin: lihat semua

        $notifications = $query->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'total'    => $notifications->total(),
                'page'     => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }
}
