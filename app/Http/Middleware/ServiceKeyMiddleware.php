<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceKeyMiddleware
{
    /**
     * Validasi X-Service-Key header.
     * Menerima TIF_SERVICE_KEY (integrasi TIF) atau SERVICE_KEY (detection worker).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-Service-Key');

        if (!$provided) {
            return response()->json(['message' => 'Unauthorized. X-Service-Key diperlukan.'], 401);
        }

        $validKeys = array_filter([
            config('services.tif.service_key'),
            config('services.detection_worker.key'),
        ]);

        foreach ($validKeys as $key) {
            if (hash_equals($key, $provided)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized. X-Service-Key tidak valid.'], 401);
    }
}
