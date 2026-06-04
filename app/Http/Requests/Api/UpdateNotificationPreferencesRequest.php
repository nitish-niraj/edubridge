<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'new_message_email' => ['sometimes', 'boolean'],
            'new_message_sms' => ['sometimes', 'boolean'],
            'booking_confirmed_email' => ['sometimes', 'boolean'],
            'booking_confirmed_sms' => ['sometimes', 'boolean'],
            'session_reminder_email' => ['sometimes', 'boolean'],
            'session_reminder_sms' => ['sometimes', 'boolean'],
            'booking_cancelled_email' => ['sometimes', 'boolean'],
            'review_received_email' => ['sometimes', 'boolean'],
            'earnings_released_email' => ['sometimes', 'boolean'],
            'group_session_started_email' => ['sometimes', 'boolean'],
            'high_contrast' => ['sometimes', 'boolean'],
        ];
    }
}
