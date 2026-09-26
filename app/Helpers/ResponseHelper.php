<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    public static function success(mixed $data = [], ?string $message = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('Response successful'),
            'data' => $data,
        ], $statusCode);
    }

    public static function error(mixed $errors = null, ?string $message = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? __('An error occurred'),
            'errors' => $errors,
        ], $statusCode);
    }
}
