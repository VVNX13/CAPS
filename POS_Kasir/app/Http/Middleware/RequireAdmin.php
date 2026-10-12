<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the admin role.
 *
 * Used for the shop-wide configuration screens: dynamic QRIS, the QR stand
 * printer, the gateway credentials and the payment webhook secret. A food or
 * drink owner has no reason to read or change any of those, and leaving them
 * open meant an owner account could rewrite the webhook secret or the QRIS-ify
 * API key that the whole payment flow depends on.
 */
class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // "auth" already ran, so this is a belt-and-braces check.
        if ($user === null || ! $user->isAdmin()) {
            return ApiResponse::error('Halaman ini hanya untuk admin', 403);
        }

        return $next($request);
    }
}
