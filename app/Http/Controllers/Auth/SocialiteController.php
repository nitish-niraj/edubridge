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

        // Block admin accounts from using Google OAuth
        if ($existing?->isAdmin()) {
            return redirect()
                ->route($this->routeForSource($source))
                ->withErrors(['email' => 'Admin accounts must sign in with email and password.']);
        }

        // Block teacher accounts from using Google OAuth (spec requirement)
        if ($existing?->isTeacher()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This email is registered as a teacher account. Teacher accounts cannot use Google sign-in.']);
        }

        // If no existing user, this is a new registration - only allow students
        if (! $existing) {
            // Create new student account via Google OAuth
            $user = User::create([
                'name'              => $googleUser->getName() ?: Str::before($googleEmail, '@'),
                'password'          => bcrypt(Str::random(24)),
                'oauth_provider'    => 'google',
                'oauth_provider_id' => (string) $googleUser->getId(),
                'role'              => 'student',
                'status'            => 'active',
                'email_verified_at' => now(),
                'avatar'            => $googleUser->getAvatar(),
                'email'             => $googleEmail,
            ]);

            Role::findOrCreate('student', 'web');
            $user->assignRole('student');

            StudentProfile::create(['user_id' => $user->id]);

            auth()->login($user);
            $request->session()->regenerate();

            return redirect()->route('student.onboarding');
        }

        // Existing student account - allow login
        if ($existing->status === 'suspended') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account has been suspended. Contact support at support@edubridge.com.']);
        }

        $existing->forceFill([
            'email_verified_at' => $existing->email_verified_at ?? now(),
            'avatar' => $googleUser->getAvatar() ?: $existing->avatar,
            'oauth_provider' => $existing->oauth_provider ?: 'google',
            'oauth_provider_id' => $existing->oauth_provider_id ?: (string) $googleUser->getId(),
        ])->save();

        Role::findOrCreate('student', 'web');
        if (! $existing->hasRole('student')) {
            $existing->assignRole('student');
        }

        if (! $existing->studentProfile) {
            StudentProfile::create(['user_id' => $existing->id]);
        }

        auth()->login($existing);
        $request->session()->regenerate();

        if (! $existing->studentProfile?->onboarding_completed) {
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
