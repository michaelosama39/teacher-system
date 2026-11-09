<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a user still has a temporary password, only these routes are reachable.
 * Applied to the whole authenticated group, so new routes are blocked by default.
 */
class EnsurePasswordChanged
{
    /**
     * @var list<string>
     */
    public const ALLOWED_ROUTES = [
        'auth.me',
        'auth.logout',
        'auth.change-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && $user->must_change_password
            && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            throw ApiException::passwordChangeRequired();
        }

        return $next($request);
    }
}
