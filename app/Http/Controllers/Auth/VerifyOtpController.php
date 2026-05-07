<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ShowOtpFormRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

// Used by resend() to regenerate OTPs
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Auth\TeacherAuthController;

class VerifyOtpController extends Controller
{
    public function showForm(ShowOtpFormRequest $request): Response
    {
        $user = User::findOrFail((int) $request->validated('user_id'));

        return Inertia::render('Auth/VerifyOtp', [
            'user_id' => $user->id,
            'role'    => $user->role,
            'status'  => session('status'),
        ]);
    }

    public function verify(VerifyOtpRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->user_id);

        // Check if account is locked due to too many failed OTP attempts
        $ipKeyPart = sha1((string) $request->ip());
        $lockKey = 'otp_lock:' . $user->id . ':' . $ipKeyPart;
        $attemptsKey = 'otp_attempts:' . $user->id . ':' . $ipKeyPart;

        if ($this->keyExists($lockKey)) {
            $remainingSeconds = max(1, $this->keyTtl($lockKey));
            $remainingMinutes = ceil($remainingSeconds / 60);

            return back()->withErrors([
                'otp' => "Too many failed attempts. Your account is locked for {$remainingMinutes} minutes. Please try again later.",
            ]);
        }

        $verification = Verification::where('user_id', $user->id)
            ->where('type', 'email')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $verification) {
            $latestExpired = Verification::where('user_id', $user->id)
                ->where('type', 'email')
                ->whereNull('used_at')
                ->latest()
                ->first();

            if ($latestExpired && $latestExpired->expires_at && $latestExpired->expires_at->gt(now()->subMinutes(30))) {
                try {
                    if ($user->isStudent()) {
                        StudentAuthController::sendOtp($user, 'email');
                    } else {
                        TeacherAuthController::sendOtp($user, 'email');
                    }
                } catch (\Throwable $e) {
                    Log::warning('Auto OTP resend after expiry failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);
                }

                return back()->withErrors([
                    'otp' => 'OTP expired. A new OTP has been sent to your email.',
                ]);
            }
        }

        if (! $verification || ! Hash::check($request->otp, $verification->otp)) {
            // Increment failed attempts counter
            $attempts = $this->incrementAttempts($attemptsKey, 1800);

            if ($attempts >= 10) {
                // Lock account for 30 minutes
                $this->setKeyWithTtl($lockKey, 1800, '1');
                $this->deleteKey($attemptsKey);

                return back()->withErrors([
                    'otp' => 'Too many failed attempts. Your account has been locked for 30 minutes.',
                ]);
            }

            $remainingAttempts = 10 - $attempts;
            return back()->withErrors([
                'otp' => "Invalid or expired OTP. You have {$remainingAttempts} attempts remaining.",
            ]);
        }

        // Clear failed attempts on successful verification
        $this->deleteKey($attemptsKey);
        $this->deleteKey($lockKey);

        $verification->update(['used_at' => now()]);
        $user->update([
            'email_verified_at' => now(),
            'status'            => 'active',
        ]);

        auth()->login($user);

        if ($user->isStudent()) {
            return redirect()->route('student.onboarding');
        }

        return redirect()->route('teacher.profile.step', ['step' => 1]);
    }

    public function resend(ResendOtpRequest $request): RedirectResponse
    {
        $user = User::findOrFail((int) $request->validated('user_id'));

        $throttleKey = 'otp_resend_ts:' . $user->id;
        if ($this->keyExists($throttleKey)) {
            $seconds = max(1, $this->keyTtl($throttleKey));

            return back()->withErrors([
                'otp' => "Please wait {$seconds} seconds before requesting a new OTP.",
            ]);
        }

        $this->setKeyWithTtl($throttleKey, 60, (string) now()->timestamp);

        $latestOtp = Verification::where('user_id', $user->id)
            ->where('type', 'email')
            ->latest()
            ->first();

        // Keep the DB check as an extra guard in case Redis is unavailable.
        if ($latestOtp && $latestOtp->created_at->gt(now()->subSeconds(60))) {
            $seconds = max(1, 60 - (int) $latestOtp->created_at->diffInSeconds(now()));

            return back()->withErrors([
                'otp' => "Please wait {$seconds} seconds before requesting a new OTP.",
            ]);
        }

        try {
            if ($user->isStudent()) {
                StudentAuthController::sendOtp($user, 'email');
            } else {
                TeacherAuthController::sendOtp($user, 'email');
            }
        } catch (\Throwable $e) {
            Log::warning('OTP resend failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'otp' => 'We could not resend the OTP right now. Please try again in a moment.',
            ]);
        }

        return back()->with('status', 'OTP resent successfully.');
    }

    private function keyExists(string $key): bool
    {
        try {
            return (bool) Redis::exists($key);
        } catch (\Throwable) {
            return Cache::has($key);
        }
    }

    private function keyTtl(string $key): int
    {
        try {
            return (int) Redis::ttl($key);
        } catch (\Throwable) {
            return 60;
        }
    }

    private function setKeyWithTtl(string $key, int $seconds, string $value): void
    {
        try {
            Redis::setex($key, $seconds, $value);
        } catch (\Throwable) {
            Cache::put($key, $value, now()->addSeconds($seconds));
        }
    }

    private function deleteKey(string $key): void
    {
        try {
            Redis::del($key);
        } catch (\Throwable) {
            Cache::forget($key);
        }
    }

    private function incrementAttempts(string $key, int $seconds): int
    {
        try {
            $attempts = (int) Redis::incr($key);
            Redis::expire($key, $seconds);

            return $attempts;
        } catch (\Throwable) {
            $attempts = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $attempts, now()->addSeconds($seconds));

            return $attempts;
        }
    }
}
