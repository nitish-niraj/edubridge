<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        if (! $user->two_factor_enabled) {
            return $next($request);
        }

        if ($request->session()->get('admin_2fa_passed') === true) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor verification required.',
            ], 403);
        }

        return redirect()->route('admin.2fa.challenge');
    }
}
