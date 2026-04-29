<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['string', 'max:100'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'price' => ['nullable', Rule::in(['any', 'free', 'under_200', '200_500', '500_plus'])],
            'min_rating' => ['nullable', 'numeric', 'between:1,5'],
            'availability_days' => ['nullable', 'array'],
            'availability_days.*' => [
                'string',
                Rule::in([
                    'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun',
                    'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
                    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
                ]),
            ],
            'gender' => ['nullable', Rule::in(['any', 'male', 'female', 'other'])],
            'sort' => ['nullable', Rule::in(['relevance', 'rating_desc', 'price_asc', 'price_desc', 'experienced', 'newest'])],
        ];
    }
}
