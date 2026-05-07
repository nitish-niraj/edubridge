<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'subject' => ['sometimes', 'string', Rule::in(config('edubridge.subjects', []))],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_students' => ['sometimes', 'integer', 'min:2', 'max:50'],
        ];
    }
}
