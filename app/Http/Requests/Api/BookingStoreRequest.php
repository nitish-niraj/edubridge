<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller so we can return
        // specific validation/edge-case errors (e.g., booking own slot).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:booking_slots,id'],
            'subject' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'session_type' => ['nullable', Rule::in(['solo', 'group'])],
        ];
    }
}
