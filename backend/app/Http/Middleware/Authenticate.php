<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * This is a pure API application, so authentication failures are always
     * handled as JSON (401 envelope) by the exception handler instead of being
     * redirected to a web "login" route.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
