<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $gradeOptions = [
            'Class 1',
            'Class 2',
            'Class 3',
            'Class 4',
            'Class 5',
            'Class 6',
            'Class 7',
            'Class 8',
            'Class 9',
            'Class 10',
            'Class 11',
            'Class 12',
            'Undergraduate',
            'Postgraduate',
        ];

        $subjectOptions = [
            'Math',
            'Science',
            'English',
            'History',
            'Geography',
            'Physics',
            'Chemistry',
            'Biology',
            'Hindi',
            'Punjabi',
            'Computer Science',
            'Economics',
            'Commerce',
            'Other',
        ];

        $languageOptions = [
            'English',
            'Hindi',
            'Punjabi',
            'Bengali',
            'Tamil',
            'Telugu',
            'Marathi',
            'Gujarati',
        ];

        return [
            'name'               => ['required', 'string', 'max:255'],
            'class_grade'        => ['nullable', 'string', Rule::in($gradeOptions)],
            'school_name'        => ['nullable', 'string', 'max:150'],
            'subjects_needed'    => ['nullable', 'array'],
            'subjects_needed.*'  => ['string', Rule::in($subjectOptions)],
            'preferred_language' => ['nullable', 'string', Rule::in($languageOptions)],
            'avatar'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
