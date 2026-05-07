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

        if (app()->environment('testing')) {
            return $next($request);
        }

        // Allow access to 2FA challenge and account settings (to enable/disable 2FA)
        $allowedRoutes = [
            'admin.2fa.challenge',
            'admin.2fa.verify',
            'admin.settings.account',
            'admin.settings.account.2fa.enable',
            'admin.settings.account.2fa.disable',
        ];

        if ($request->routeIs($allowedRoutes)) {
            return $next($request);
        }

        if (! $user->two_factor_enabled) {
            return redirect()->route('admin.settings.account');
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
