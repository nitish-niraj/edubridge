<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'redirect' => $request->query('redirect', ''),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Block suspended users immediately after authentication
        if ($user->status === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended. Contact support at support@edubridge.com.',
            ]);
        }

        if (($user->isStudent() || $user->isTeacher()) && (! $user->email_verified_at || $user->status === 'pending')) {
            if ($user->isStudent()) {
                StudentAuthController::sendOtp($user, 'email');
            } else {
                TeacherAuthController::sendOtp($user, 'email');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('verify.otp.form', ['user_id' => $user->id])
                ->with('status', 'We sent a fresh OTP to your email. Please verify to continue.');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_ip' => $request->ip()])->save();
        $safeRedirect = $this->sanitizeRedirectPath((string) $request->input('redirect', ''));

        if ($safeRedirect !== null && $this->isRedirectAllowedForRole($user, $safeRedirect)) {
            $request->session()->put('url.intended', $safeRedirect);
        }

        if (! $user->isAdmin()) {
            $request->session()->forget('admin_2fa_passed');
        }

        if ($user->isAdmin() && $user->two_factor_enabled) {
            $request->session()->forget('admin_2fa_passed');

            return redirect()->route('admin.2fa.challenge');
        }

        if ($user->isAdmin()) {
            $request->session()->put('admin_2fa_passed', true);
        }

        return redirect()->intended($this->defaultRedirectForRole($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function defaultRedirectForRole(User $user): string
    {
        return match($user->role) {
            'student' => route('student.dashboard'),
            'teacher' => route('teacher.dashboard'),
            'admin' => route('admin.dashboard'),
            default => RouteServiceProvider::HOME,
        };
    }

    private function sanitizeRedirectPath(string $rawRedirect): ?string
    {
        $redirect = trim($rawRedirect);
        if ($redirect === '') {
            return null;
        }

        if (str_starts_with($redirect, '//')) {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirect) === 1) {
            return null;
        }

        if (! str_starts_with($redirect, '/')) {
            return null;
        }

        $parts = parse_url($redirect);
        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        if ($path === '') {
            $path = '/';
        }

        return $path . $query;
    }

    private function isRedirectAllowedForRole(User $user, string $redirect): bool
    {
        $path = parse_url($redirect, PHP_URL_PATH) ?: '/';
        $blockedPaths = ['/login', '/logout', '/register', '/verify-otp', '/admin/2fa'];

        foreach ($blockedPaths as $blockedPath) {
            if ($path === $blockedPath || str_starts_with($path, $blockedPath . '/')) {
                return false;
            }
        }

        if ($user->isAdmin()) {
            return str_starts_with($path, '/admin/');
        }

        if ($user->isTeacher()) {
            return ! str_starts_with($path, '/admin/');
        }

        if ($user->isStudent()) {
            return ! str_starts_with($path, '/admin/') && ! str_starts_with($path, '/teacher/');
        }

        return false;
    }
}
