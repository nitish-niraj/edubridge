<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpMailSender
{
    public function send(User $user, string $otp): void
    {
        $mailer = (string) config('mail.default', 'smtp');

        try {
            Mail::mailer($mailer)->to($user->email)->send(new OtpMail($user, $otp));

            return;
        } catch (\Throwable $smtpException) {
            if ($this->sendViaBrevoApi($user, $otp)) {
                Log::warning('OTP email sent via Brevo API fallback', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'mailer' => $mailer,
                    'smtp_error' => $smtpException->getMessage(),
                ]);

                return;
            }

            throw $smtpException;
        }
    }

    private function sendViaBrevoApi(User $user, string $otp): bool
    {
        $apiKey = trim((string) config('services.brevo.api_key', ''));
        if ($apiKey === '') {
            return false;
        }

        $fromAddress = trim((string) config('mail.from.address', ''));
        if ($fromAddress === '') {
            return false;
        }

        $fromName = trim((string) config('mail.from.name', config('app.name', 'EduBridge')));
        $endpoint = trim((string) config('services.brevo.smtp_api_url', 'https://api.brevo.com/v3/smtp/email'));

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->timeout(15)->post($endpoint, [
            'sender' => [
                'email' => $fromAddress,
                'name' => $fromName,
            ],
            'to' => [[
                'email' => $user->email,
                'name' => $user->name,
            ]],
            'subject' => 'Your EduBridge Verification Code',
            'htmlContent' => view('emails.otp', [
                'user' => $user,
                'otp' => $otp,
            ])->render(),
        ]);

        if ($response->successful()) {
            return true;
        }

        Log::warning('Brevo API OTP dispatch failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return false;
    }
}