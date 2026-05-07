<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', Rule::in(config('edubridge.subjects', []))],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_students' => ['nullable', 'integer', 'min:2', 'max:50'],
        ];
    }
}
