<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'bio'              => $this->bio,
            'experience_years' => $this->experience_years,
            'previous_school'  => $this->previous_school,
            'hourly_rate'      => $this->hourly_rate,
            'is_free'          => $this->is_free,
            'is_verified'      => $this->is_verified,
            'rating_avg'       => $this->rating_avg,
            'total_reviews'    => $this->total_reviews,
            'subjects'         => $this->subjects,
            'languages'        => $this->languages,
            'gender'           => $this->gender,
            'availability'     => $this->availability,
            'user'             => new UserResource($this->whenLoaded('user')),
        ];
    }
}
