<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['message', 'review', 'profile', 'other'])],
            'reason' => ['required', 'string', 'max:1000'],
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reported_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'reported_review_id' => ['nullable', 'integer', 'exists:reviews,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('reported_user_id')
                && ! $this->filled('reported_message_id')
                && ! $this->filled('reported_review_id')
                && ! $this->filled('booking_id')) {
                $validator->errors()->add('report', 'At least one report target must be provided.');
            }
        });
    }
}
