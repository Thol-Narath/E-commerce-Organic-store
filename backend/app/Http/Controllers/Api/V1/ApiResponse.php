<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a consistent success envelope.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a consistent error envelope.
     */
    protected function error(string $message = 'Something went wrong', mixed $data = null, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
