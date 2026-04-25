<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class ProfileStep3Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_free'     => ['required', 'boolean'],
            // Rulebook §10: if charging, rate must be ≥ 1 and ≤ 50000 (no ₹0 sessions)
            'hourly_rate' => ['required_if:is_free,false', 'nullable', 'numeric', 'min:1', 'max:50000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate.required_if' => 'Please enter your hourly rate since you chose to charge students.',
            'hourly_rate.min'         => 'Hourly rate must be at least ₹1.',
            'hourly_rate.max'         => 'Hourly rate cannot exceed ₹50,000.',
        ];
    }
}

