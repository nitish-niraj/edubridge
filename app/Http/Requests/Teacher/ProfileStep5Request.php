<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class ProfileStep5Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existingTypes = $this->user()
            ?->teacherProfile
            ?->documents()
            ->pluck('type')
            ->all() ?? [];

        $degreeRule = in_array('degree', $existingTypes, true) ? 'nullable' : 'required';
        $serviceRecordRule = in_array('service_record', $existingTypes, true) ? 'nullable' : 'required';
        $idProofRule = in_array('id_proof', $existingTypes, true) ? 'nullable' : 'required';

        return [
            'avatar'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'degree'         => [$degreeRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'service_record' => [$serviceRecordRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'id_proof'       => [$idProofRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'degree.required' => 'Degree certificate is required before submitting for verification.',
            'service_record.required' => 'Service record or experience letter is required before submitting for verification.',
            'id_proof.required' => 'ID proof is required before submitting for verification.',
            'avatar.max' => 'Profile photo must be 2 MB or smaller.',
            'degree.mimes' => 'Degree file must be JPG, PNG, WebP, or PDF.',
            'service_record.mimes' => 'Service record must be JPG, PNG, WebP, or PDF.',
            'id_proof.mimes' => 'ID proof must be JPG, PNG, WebP, or PDF.',
        ];
    }
}
