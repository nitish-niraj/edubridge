<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class ProfileStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Spec §3.1: "All optional to save but teacher cannot proceed if bio is blank."
        // When the teacher clicks "Save for Later" they may submit a blank bio and stay
        // on step 1. When they click "Save & Continue" the bio must be at least 50 chars.
        $savingForLater = $this->boolean('save_for_later');

        return [
            'bio' => [
                $savingForLater ? 'nullable' : 'required',
                'string',
                'min:50',
                'max:2000',
            ],
            // Spec §3.1: experience_years is integer, optional, 0–50 when provided.
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'previous_school'  => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'bio.required'         => 'Please write a bio of at least 50 characters before continuing to the next step.',
            'bio.min'              => 'Your bio must be at least 50 characters so students can learn about you.',
            'bio.max'              => 'Your bio cannot exceed 2000 characters.',
            'experience_years.min' => 'Years of experience cannot be negative.',
            'experience_years.max' => 'Years of experience must be between 0 and 50.',
            'experience_years.integer' => 'Years of experience must be a whole number.',
        ];
    }
}
