<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAvailabilityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() === true;
    }

    public function rules(): array
    {
        $isRecurring = $this->boolean('is_recurring');

        return [
            'day_of_week' => [
                Rule::requiredIf($isRecurring),
                'nullable',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_recurring' => ['required', 'boolean'],
            'specific_date' => [
                Rule::requiredIf(! $isRecurring),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }
}

