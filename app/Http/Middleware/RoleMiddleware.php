<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        if (! $request->user()->hasRole($role)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $redirectRoute = match (true) {
                $request->user()->hasRole('student') => 'student.dashboard',
                $request->user()->hasRole('teacher') => 'teacher.dashboard',
                $request->user()->hasRole('admin') => 'admin.dashboard',
                default => 'login',
            };

            return redirect()
                ->route($redirectRoute)
                ->with('error', 'You do not have access to that portal.');
        }

        return $next($request);
    }
}
