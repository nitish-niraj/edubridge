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
            // Spec §3.1: if charging, rate must be ≥ ₹50 and ≤ ₹2000
            'hourly_rate' => ['required_if:is_free,false', 'nullable', 'numeric', 'min:50', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate.required_if' => 'Please enter your hourly rate since you chose to charge students.',
            'hourly_rate.min'         => 'Hourly rate must be at least ₹50.',
            'hourly_rate.max'         => 'Hourly rate cannot exceed ₹2,000.',
        ];
    }
}

