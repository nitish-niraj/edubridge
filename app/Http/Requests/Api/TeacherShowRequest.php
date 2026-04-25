<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('role', 'teacher');
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'teacher_id' => $this->route('teacher'),
        ]);
    }
}
