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
        $user = $this->user();
        $hasAvatar     = ! empty($user?->avatar);
        $existingTypes = $user?->teacherProfile
            ?->documents()
            ->pluck('type')
            ->all() ?? [];

        // Spec §3.1 Step 5: "Photo is required." A profile without a photo is incomplete,
        // so we make it required on first submission and optional afterwards (so teachers
        // can re-upload documents without re-uploading the photo).
        $avatarRule        = $hasAvatar ? 'nullable' : 'required';
        $degreeRule        = in_array('degree', $existingTypes, true) ? 'nullable' : 'required';
        $serviceRecordRule = in_array('service_record', $existingTypes, true) ? 'nullable' : 'required';
        $idProofRule       = in_array('id_proof', $existingTypes, true) ? 'nullable' : 'required';

        return [
            // Spec §3.1: Profile photo max 2MB, JPEG/PNG/WebP, resized to 300x300.
            'avatar'         => [$avatarRule, 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Spec §3.1: Documents max 10MB, PDF or image.
            'degree'         => [$degreeRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'service_record' => [$serviceRecordRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'id_proof'       => [$idProofRule, 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required'         => 'A profile photo is required to complete your profile.',
            'avatar.image'            => 'Profile photo must be an image (JPG, PNG, or WebP).',
            'avatar.mimes'            => 'Profile photo must be JPG, PNG, or WebP.',
            'avatar.max'              => 'Profile photo must be 2 MB or smaller.',
            'degree.required'         => 'Degree certificate is required before submitting for verification.',
            'service_record.required' => 'Service record or experience letter is required before submitting for verification.',
            'id_proof.required'       => 'ID proof is required before submitting for verification.',
            'degree.mimes'            => 'Degree file must be JPG, PNG, WebP, or PDF.',
            'service_record.mimes'    => 'Service record must be JPG, PNG, WebP, or PDF.',
            'id_proof.mimes'          => 'ID proof must be JPG, PNG, WebP, or PDF.',
            '*.max'                   => 'Each document must be 10 MB or smaller.',
        ];
    }
}
