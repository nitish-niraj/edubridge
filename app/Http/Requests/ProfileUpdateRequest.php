<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()?->id)],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($this->user()?->isStudent()) {
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

            $rules['class_grade'] = ['sometimes', 'nullable', 'string', Rule::in($gradeOptions)];
            $rules['school_name'] = ['sometimes', 'nullable', 'string', 'max:150'];
            $rules['subjects_needed'] = ['sometimes', 'nullable', 'array'];
            $rules['subjects_needed.*'] = ['string', Rule::in($subjectOptions)];
            $rules['preferred_language'] = ['sometimes', 'nullable', 'string', Rule::in($languageOptions)];
        }

        if ($this->user()?->isTeacher()) {
            $rules['bio'] = ['sometimes', 'nullable', 'string', 'max:2000'];
            $rules['experience_years'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'];
            $rules['previous_school'] = ['sometimes', 'nullable', 'string', 'max:150'];
        }

        return $rules;
    }
}
