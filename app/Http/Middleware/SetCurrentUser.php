<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentUser
{
    /**
     * Resolve the current user from the X-User-Id header.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $userId = $request->header('X-User-Id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'X-User-Id header is required.',
            ], 400);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        /*
         * Set the resolved user as the current request user.
         */
        $request->setUserResolver(
            fn () => $user
        );

        return $next($request);
    }
}