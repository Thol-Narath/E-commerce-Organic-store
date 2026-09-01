<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminOrStaff
{
    /**
     * Allow authenticated admin or staff users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isStaff())) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
