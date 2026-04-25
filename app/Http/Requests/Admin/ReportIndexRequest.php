<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['all', 'pending', 'reviewed', 'dismissed', 'action_taken'])],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }
}
