<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'user_id'               => $this->user_id,
            'class_grade'           => $this->class_grade,
            'school_name'           => $this->school_name,
            'subjects_needed'       => $this->subjects_needed,
            'preferred_language'    => $this->preferred_language,
            'onboarding_completed'  => $this->onboarding_completed,
            'user'                  => new UserResource($this->whenLoaded('user')),
        ];
    }
}
