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
        return [
            // Rulebook §2: description min 50, max 2000
            'bio'              => ['nullable', 'string', 'min:50', 'max:2000'],
            // Rulebook §10: number, 0–60
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'previous_school'  => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'bio.min'              => 'Your bio must be at least 50 characters so students can learn about you.',
            'bio.max'              => 'Your bio cannot exceed 2000 characters.',
            'experience_years'     => 'Years of experience is required (0–60).',
        ];
    }
}
