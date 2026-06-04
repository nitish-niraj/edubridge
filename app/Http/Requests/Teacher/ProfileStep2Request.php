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
        $allowedSubjects = config('edubridge.subjects', [
            'Math', 'Science', 'English', 'History', 'Geography', 'Physics',
            'Chemistry', 'Biology', 'Hindi', 'Punjabi', 'Computer Science',
            'Economics', 'Commerce', 'Other',
        ]);

        $allowedLanguages = config('edubridge.languages', [
            'English', 'Hindi', 'Punjabi', 'Bengali', 'Tamil',
            'Telugu', 'Marathi', 'Gujarati',
        ]);

        $savingForLater = $this->boolean('save_for_later');
        $includesOther = is_array($this->input('subjects'))
            && in_array('Other', $this->input('subjects'), true);

        return [
            // Rulebook §13: at least 1 selection required; each must be from allowed list
            'subjects'    => [$savingForLater ? 'nullable' : 'required', 'array', 'min:1'],
            'subjects.*'  => ['string', Rule::in($allowedSubjects)],
            // Spec §3.3: "Other (allows a short free-text specification, max 50 chars)"
            'subject_other' => [
                $includesOther ? 'required' : 'nullable',
                'string',
                'max:50',
            ],
            'languages'   => [$savingForLater ? 'nullable' : 'required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::in($allowedLanguages)],
        ];
    }

    public function messages(): array
    {
        return [
            'subjects.min'        => 'Please select at least one subject you teach.',
            'subjects.required'   => 'Please select at least one subject you teach.',
            'subject_other.required' => 'Please describe your "Other" subject in up to 50 characters.',
            'subject_other.max'   => 'Your "Other" subject description cannot exceed 50 characters.',
            'languages.min'       => 'Please select at least one language you can teach in.',
            'languages.required'  => 'Please select at least one language you can teach in.',
            'subjects.*'          => 'One or more subjects are not valid.',
            'languages.*'         => 'One or more languages are not valid.',
        ];
    }
}
