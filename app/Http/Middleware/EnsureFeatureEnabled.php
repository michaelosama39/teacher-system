<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('feature:self_registration') reads config('teacher.features.self_registration').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config("teacher.features.{$feature}")) {
            throw ApiException::featureDisabled();
        }

        return $next($request);
    }
}
