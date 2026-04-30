<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_students' => ['nullable', 'integer', 'min:2', 'max:50'],
        ];
    }
}
