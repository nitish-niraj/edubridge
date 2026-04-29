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

        $verification = Verification::where('user_id', $user->id)
            ->where('type', 'email')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $verification || ! Hash::check($request->otp, $verification->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.']);
        }

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

        $latestOtp = Verification::where('user_id', $user->id)
            ->where('type', 'email')
            ->latest()
            ->first();

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
}
