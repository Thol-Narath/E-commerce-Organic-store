<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Block inactive/banned accounts from protected functionality.
     * The check is server-side against the authenticated database record.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            abort(403, 'Your account is inactive.');
        }

        return $next($request);
    }
}
