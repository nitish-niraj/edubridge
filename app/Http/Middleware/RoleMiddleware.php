<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $hasSpatieRole = method_exists($user, 'hasRole') ? (bool) $user->hasRole($role) : false;
        $hasRoleColumn = isset($user->role) && is_string($user->role) && $user->role === $role;

        if (! $hasSpatieRole && ! $hasRoleColumn) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $redirectRoute = match (true) {
                (method_exists($user, 'hasRole') && $user->hasRole('student')) || ($user->role ?? null) === 'student' => 'student.dashboard',
                (method_exists($user, 'hasRole') && $user->hasRole('teacher')) || ($user->role ?? null) === 'teacher' => 'teacher.dashboard',
                (method_exists($user, 'hasRole') && $user->hasRole('admin')) || ($user->role ?? null) === 'admin' => 'admin.dashboard',
                default => 'login',
            };

            return redirect()
                ->route($redirectRoute)
                ->with('error', 'You do not have access to that portal.');
        }

        return $next($request);
    }
}
