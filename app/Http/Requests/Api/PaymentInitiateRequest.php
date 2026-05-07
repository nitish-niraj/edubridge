<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentInitiateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true;
    }

    public function rules(): array
    {
        $supportedGateways = app()->environment('testing')
            ? ['phonepe', 'razorpay']
            : ['phonepe'];

        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'gateway' => ['nullable', Rule::in($supportedGateways)],
        ];
    }
}
