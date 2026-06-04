<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->ensureIsNotLockedOut();

        // Check if user exists
        $user = User::where('email', $this->input('email'))->first();
        
        if (! $user) {
            RateLimiter::hit($this->throttleKey());
            $this->recordFailedLoginAttempt();

            throw ValidationException::withMessages([
                'email' => 'No account found with this email. Please register first.',
                'user_not_found' => true,
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->recordFailedLoginAttempt();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->clearLoginAttempts();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Ensure the login request is not locked out due to too many failed attempts.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureIsNotLockedOut(): void
    {
        try {
            $lockKey = 'login_lock:' . $this->loginAttemptKey();
            
            if (Redis::exists($lockKey)) {
                $remainingSeconds = (int) Redis::ttl($lockKey);
                $remainingMinutes = ceil($remainingSeconds / 60);

                throw ValidationException::withMessages([
                    'email' => "Too many failed login attempts. Your account is locked for {$remainingMinutes} minutes. Please try again later.",
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            // If Redis is unavailable, allow the request to proceed
            return;
        }
    }

    /**
     * Clear login attempts on successful authentication.
     */
    private function clearLoginAttempts(): void
    {
        try {
            $redisKey = 'login_attempts:' . $this->loginAttemptKey();
            $lockKey = 'login_lock:' . $this->loginAttemptKey();
            
            Redis::del($redisKey);
            Redis::del($lockKey);
        } catch (\Throwable) {
            return;
        }
    }

    private function recordFailedLoginAttempt(): void
    {
        try {
            $redisKey = 'login_attempts:' . $this->loginAttemptKey();
            $lockKey = 'login_lock:' . $this->loginAttemptKey();

            $attempts = (int) Redis::incr($redisKey);
            Redis::expire($redisKey, 1800); // 30 minutes

            if ($attempts >= 10) {
                Redis::setex($lockKey, 1800, '1'); // 30 minutes = 1800 seconds

                $user = User::query()->where('email', $this->string('email'))->first();
                if ($user) {
                    $user->forceFill([
                        'status' => 'suspended',
                        'last_login_ip' => $this->ip(),
                    ])->save();

                    AuditLog::create([
                        'admin_id' => null,
                        'action' => 'auth.lockout_suspended',
                        'entity_type' => 'User',
                        'entity_id' => $user->id,
                        'details' => [
                            'reason' => 'Too many failed login attempts',
                            'attempts' => $attempts,
                            'email' => (string) $this->string('email'),
                            'ip_address' => $this->ip(),
                            'auto_lockout' => true,
                        ],
                        'ip_address' => $this->ip(),
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function loginAttemptKey(): string
    {
        return Str::lower((string) $this->string('email')) . '|' . $this->ip();
    }
}
