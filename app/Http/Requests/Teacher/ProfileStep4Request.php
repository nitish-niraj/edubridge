<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class ProfileStep4Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $rules = [
            'availability' => ['required', 'array'],
        ];

        foreach ($days as $day) {
            $rules["availability.{$day}.enabled"] = ['required', 'boolean'];
            $rules["availability.{$day}.start"] = [
                'required_if:availability.' . $day . '.enabled,true',
                'nullable',
                'date_format:H:i',
            ];
            $rules["availability.{$day}.end"] = [
                'required_if:availability.' . $day . '.enabled,true',
                'nullable',
                'date_format:H:i',
                'after:availability.' . $day . '.start',
            ];
        }

        return $rules;
    }
}
