<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStudent();
    }

    public function rules(): array
    {
        return [
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('role', 'teacher')->where('status', 'active');
                }),
            ],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
