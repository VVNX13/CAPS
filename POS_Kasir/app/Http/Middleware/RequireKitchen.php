<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the dapur (kitchen) role only.
 */
class RequireKitchen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isKitchen()) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Halaman ini hanya untuk dapur', 403);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}

