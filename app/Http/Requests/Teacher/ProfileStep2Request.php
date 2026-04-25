<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileStep2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedSubjects = [
            'Math','Science','English','History','Geography','Physics',
            'Chemistry','Biology','Hindi','Punjabi','Computer Science',
            'Economics','Commerce','Other',
        ];

        $allowedLanguages = [
            'English','Hindi','Punjabi','Bengali','Tamil',
            'Telugu','Marathi','Gujarati',
        ];

        return [
            // Rulebook §13: at least 1 selection required; each must be from allowed list
            'subjects'    => ['required', 'array', 'min:1'],
            'subjects.*'  => ['string', Rule::in($allowedSubjects)],
            'languages'   => ['required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::in($allowedLanguages)],
        ];
    }

    public function messages(): array
    {
        return [
            'subjects.min'  => 'Please select at least one subject you teach.',
            'languages.min' => 'Please select at least one language you can teach in.',
            'subjects.*'    => 'One or more subjects are not valid.',
            'languages.*'   => 'One or more languages are not valid.',
        ];
    }
}

