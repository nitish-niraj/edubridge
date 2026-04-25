<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class SocialiteController extends Controller
{
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $source = $this->normalizeSource((string) $request->query('source', 'register'));
        $request->session()->put('oauth_source', $source);

        if (! $this->hasGoogleConfiguration()) {
            return redirect()
                ->route($this->routeForSource($source))
                ->withErrors([
                    'google' => 'Google sign-in is not configured yet. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT in .env.',
                ]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $source = $this->normalizeSource((string) $request->session()->pull('oauth_source', 'register'));

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route($this->routeForSource($source))
                ->withErrors([
                    'google' => 'Google login failed. Please check OAuth credentials and try again.',
                ]);
        }

        $googleEmail = (string) $googleUser->getEmail();
        if ($googleEmail === '') {
            return redirect()
                ->route($this->routeForSource($source))
                ->withErrors(['google' => 'Google account email is required to continue.']);
        }

        $existing = User::where('email', $googleEmail)->first();

        if ($existing?->isAdmin()) {
            return redirect()
                ->route($this->routeForSource($source))
                ->withErrors(['email' => 'Admin accounts must sign in with email and password.']);
        }

        if ($existing?->isTeacher()) {
            if ($existing->status === 'suspended') {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Your account has been suspended. Contact support at support@edubridge.com.']);
            }

            $existing->forceFill([
                'email_verified_at' => $existing->email_verified_at ?? now(),
                'avatar' => $googleUser->getAvatar() ?: $existing->avatar,
            ])->save();

            auth()->login($existing);
            $request->session()->regenerate();

            $step = (int) ($existing->teacherProfile?->onboarding_step ?? 1);
            if ($step >= 1 && $step <= 5 && $step < 5) {
                return redirect()->route('teacher.profile.step', ['step' => $step]);
            }

            return redirect()->route('teacher.dashboard');
        }

        $user = User::firstOrCreate(
            ['email' => $googleEmail],
            [
                'name'              => $googleUser->getName() ?: Str::before($googleEmail, '@'),
                'password'          => bcrypt(Str::random(24)),
                'role'              => 'student',
                'status'            => 'active',
                'email_verified_at' => now(),
                'avatar'            => $googleUser->getAvatar(),
            ]
        );

        Role::findOrCreate('student', 'web');
        if (! $user->hasRole('student')) {
            $user->assignRole('student');
        }

        if (! $user->studentProfile) {
            StudentProfile::create(['user_id' => $user->id]);
        }

        auth()->login($user);
        $request->session()->regenerate();

        if (! $user->studentProfile?->onboarding_completed) {
            return redirect()->route('student.onboarding');
        }

        return redirect()->route('student.dashboard');
    }

    private function hasGoogleConfiguration(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function normalizeSource(string $source): string
    {
        return in_array($source, ['login', 'register'], true) ? $source : 'register';
    }

    private function routeForSource(string $source): string
    {
        return $source === 'login' ? 'login' : 'student.register';
    }
}
