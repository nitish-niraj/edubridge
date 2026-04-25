<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnableAdminTwoFactorRequest;
use App\Http\Requests\Admin\VerifyAdminTwoFactorRequest;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class AdminTwoFactorController extends Controller
{
    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        if (! $user->two_factor_enabled) {
            $request->session()->put('admin_2fa_passed', true);

            return redirect()->intended(route('admin.dashboard'));
        }

        if ($request->session()->get('admin_2fa_passed') === true) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return Inertia::render('Admin/TwoFactorChallenge', [
            'email' => $user->email,
        ]);
    }

    public function verify(VerifyAdminTwoFactorRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return redirect()->intended(route('admin.dashboard'));
        }

        $isValid = Google2FA::verifyKey($user->two_factor_secret, $request->validated('code'));
        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => 'Invalid verification code.',
            ]);
        }

        $request->session()->put('admin_2fa_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $secret = $this->ensureSecret($user);
        $qrSvg = $user->two_factor_enabled ? null : $this->generateQrSvg($user->email, $secret);

        return Inertia::render('Admin/SettingsAccount', [
            'two_factor' => [
                'enabled' => (bool) $user->two_factor_enabled,
                'secret_preview' => $this->maskSecret($secret),
                'manual_key' => $user->two_factor_enabled ? null : $secret,
                'qr_svg' => $qrSvg,
            ],
        ]);
    }

    public function enable(EnableAdminTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $secret = $this->ensureSecret($user);
        $isValid = Google2FA::verifyKey($secret, $request->validated('code'));

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid. Try again with a fresh code from your authenticator app.',
            ]);
        }

        $user->forceFill([
            'two_factor_enabled' => true,
        ])->save();

        $request->session()->put('admin_2fa_passed', true);

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
            'two_factor_enabled' => true,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        $request->session()->forget('admin_2fa_passed');

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
            'two_factor_enabled' => false,
        ]);
    }

    private function ensureSecret($user): string
    {
        if (! $user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => Google2FA::generateSecretKey(),
            ])->save();
            $user->refresh();
        }

        return (string) $user->two_factor_secret;
    }

    private function generateQrSvg(string $email, string $secret): string
    {
        $issuer = config('app.name', 'EduBridge');
        $otpauth = sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer . ':' . $email),
            rawurlencode($secret),
            rawurlencode($issuer)
        );

        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($otpauth);
    }

    private function maskSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return $secret;
        }

        return substr($secret, 0, 4) . '****' . substr($secret, -4);
    }
}
