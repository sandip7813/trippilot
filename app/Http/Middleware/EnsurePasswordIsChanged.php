<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Routes a user who still has a one-time password may reach.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'logout',
    ];

    /**
     * Force users logged in with a one-time password to choose a new one
     * before they can use the rest of the application.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
