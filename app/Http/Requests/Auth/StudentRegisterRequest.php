<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Section 2.2: minimum 8 characters + uppercase + number + symbol.
        // mixedCase() enforces uppercase and lowercase together.
        $passwordRules = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (app()->environment('production')) {
            $passwordRules->uncompromised();
        }

        $gradeOptions = [
            'Class 1','Class 2','Class 3','Class 4','Class 5','Class 6',
            'Class 7','Class 8','Class 9','Class 10','Class 11','Class 12',
            'Undergraduate','Postgraduate',
        ];

        return [
            // Rulebook §6: min 2 chars, max 100, no numbers
            'name'        => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\s\-\'\.]+$/u'],
            // Rulebook §3: valid email, unique across the entire users table (incl. soft-deleted), max 254
            'email'       => [
                'required',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $existing = User::withTrashed()->where('email', (string) $value)->first();
                    if ($existing?->status === 'suspended') {
                        $fail('This email cannot be used to register.');
                    }
                },
            ],
            // Rulebook §5: phone digits/+ only, 7-15 digits, unique across the entire users table (incl. soft-deleted)
            'phone'       => [
                'required',
                'string',
                'regex:/^\+?[0-9]{7,15}$/',
                Rule::unique('users', 'phone')->whereNull('deleted_at'),
            ],
            'password'    => ['required', 'confirmed', $passwordRules],
            // Rulebook §11: must be a real option from the list
            'class_grade' => ['nullable', 'string', Rule::in($gradeOptions)],
            'school_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'       => 'Full name must be at least 2 characters.',
            'name.regex'     => 'Full name should only contain letters, spaces, hyphens, and apostrophes.',
            'email.email'    => 'Please enter a valid email address.',
            'email.unique'   => 'This email is already registered. Sign in with Google.',
            'phone.regex'    => 'Please enter a valid phone number (digits only, may start with +).',
            'phone.unique'   => 'This phone number is already registered.',
            'class_grade'    => 'Please select a valid class/grade from the list.',
        ];
    }
}

