<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:bug,feature,general'],
            'description' => ['required', 'string', 'max:5000'],
            'page_url' => ['required', 'string', 'max:500'],
            'screenshot' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
