<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Rulebook §2, §6: min 2 chars, max 120, required
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            // Rulebook §3: valid email, max 254, no spaces
            'email'   => ['required', 'email:rfc', 'max:254'],
            'subject' => ['nullable', 'string', 'max:160'],
            // Rulebook §2: short message min 10 chars
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot — must remain blank
            'company' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'     => 'Your name must be at least 2 characters.',
            'email.email'  => 'Please enter a valid email address.',
            'message.min'  => 'Your message must be at least 10 characters.',
            'message.max'  => 'Your message cannot exceed 5000 characters.',
        ];
    }

    protected function passedValidation(): void
    {
        // Honeypot field to block basic bots (Rulebook §23).
        if ((string) $this->input('company') !== '') {
            abort(422, 'Invalid submission.');
        }
    }
}
