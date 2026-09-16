<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BakongService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BakongPaymentController extends Controller
{
    protected BakongService $bakongService;

    public function __construct(BakongService $bakongService)
    {
        $this->bakongService = $bakongService;
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'md5' => ['required', 'string', 'size:32'],
        ]);

        try {
            $result = $this->bakongService->checkPaymentByMd5(
                $validated['md5']
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Bakong payment check completed.'
                    : 'The transaction has not been paid yet.',
                'data' => $result['data'],
            ], $result['http_status']);
        } catch (\Throwable $e) {
            // Never leak gateway internals or credentials to the client.
            Log::error('Bakong payment check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check Bakong payment. Please try again.',
                'data' => null,
            ], 502);
        }
    }
}
