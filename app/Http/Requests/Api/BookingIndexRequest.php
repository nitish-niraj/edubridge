<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true || $this->user()?->isTeacher() === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])],
        ];
    }
}
