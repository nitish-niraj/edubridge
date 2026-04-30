<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account has been suspended.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()?->invalidate();
            $request->session()?->regenerateToken();

            abort(403, 'Your account has been suspended.');
        }

        return $next($request);
    }
}
