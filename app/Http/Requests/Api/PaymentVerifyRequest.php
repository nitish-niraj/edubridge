<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PaymentVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true;
    }

    public function rules(): array
    {
        return [
            'gateway_order_id' => ['required', 'string', 'max:100'],
            'gateway_payment_id' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string', 'max:500'],
        ];
    }
}
