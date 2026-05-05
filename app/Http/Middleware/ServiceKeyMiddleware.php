<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-Service-Key');
        $expectedKey = config('services.tif.service_key');

        if (!$providedKey || !hash_equals($expectedKey, $providedKey)) {
            return response()->json(['message' => 'Unauthorized. X-Service-Key tidak valid.'], 401);
        }

        return $next($request);
    }
}
