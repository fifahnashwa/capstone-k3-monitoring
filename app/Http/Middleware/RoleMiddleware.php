<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Cek apakah role user ada di daftar role yang diizinkan
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'message'       => 'Forbidden. Anda tidak memiliki akses ke resource ini.',

                // TODO (PRODUCTION): Hapus your_role dan required_role dari response ini
                'your_role'     => $request->user()->role,
                'required_role' => $roles,
            ], 403);
        }

        return $next($request);
    }
}

