<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TeacherRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Rulebook §4: min 12 chars, uppercase, lowercase, number, symbol
        $passwordRules = Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (app()->environment('production')) {
            $passwordRules->uncompromised();
        }

        return [
            // Rulebook §6: min 2 chars, max 100, letters/spaces/hyphens only
            'name'     => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\s\-\'\.]+$/u'],
            // Rulebook §3: valid email, unique, max 254
            'email'    => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            // Rulebook §5: phone digits/+ only, 7-15 digits
            'phone'    => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/', 'unique:users,phone'],
            // Rulebook §12: must be from allowed set
            'gender'   => ['required', 'in:male,female,other'],
            'password' => ['required', 'confirmed', $passwordRules],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'     => 'Full name must be at least 2 characters.',
            'name.regex'   => 'Full name should only contain letters, spaces, hyphens, and apostrophes.',
            'email.email'  => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'phone.regex'  => 'Please enter a valid phone number (digits only, may start with +).',
            'phone.unique' => 'This phone number is already registered.',
            'gender'       => 'Please select a valid gender option.',
        ];
    }
}

