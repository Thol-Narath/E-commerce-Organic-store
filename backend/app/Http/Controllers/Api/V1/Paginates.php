<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

/**
 * Shared pagination helpers for API controllers.
 */
trait Paginates
{
    /**
     * Resolve a safe per-page value, capped to avoid oversized responses.
     */
    protected function perPage(Request $request, int $default = 10): int
    {
        return max(1, min((int) $request->input('per_page', $default), 50));
    }

    /**
     * Build the standard pagination metadata block.
     */
    protected function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
