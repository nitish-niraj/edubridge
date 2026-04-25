<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TeacherRegisterRequest;
use App\Services\OtpMailSender;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TeacherAuthController extends Controller
{
    public function showRegisterForm(): Response
    {
        return Inertia::render('Auth/TeacherRegister');
    }

    public function register(TeacherRegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'role'     => 'teacher',
            'status'   => 'pending',
        ]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

        TeacherProfile::create([
            'user_id' => $user->id,
            'gender'  => $request->gender,
        ]);

        $status = null;

        try {
            $this->sendOtp($user, 'email');
        } catch (\Throwable $e) {
            Log::warning('Teacher OTP dispatch failed during registration', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            $status = 'Account created, but we could not send the OTP email right now. Please use Resend OTP.';
        }

        return redirect()->route('verify.otp.form', ['user_id' => $user->id])->with('status', $status);
    }

    public static function sendOtp(User $user, string $type): void
    {
        // Invalidate any existing unused OTPs
        Verification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp    = (string) random_int(100000, 999999);
        $hashed = Hash::make($otp);

        Verification::create([
            'user_id'    => $user->id,
            'otp'        => $hashed,
            'type'       => $type,
            'expires_at' => now()->addMinutes(15),
        ]);

        app(OtpMailSender::class)->send($user, $otp);
    }
}
