<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        if (strlen($key) > 128) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key must not exceed 128 characters.',
            ], 400);
        }

        return $next($request);
    }
}