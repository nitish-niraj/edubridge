<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['text', 'image', 'file'])],
            'body' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->input('type') === 'text'),
            ],
            'attachment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), ['image', 'file'], true)),
            ],
        ];
    }
}
