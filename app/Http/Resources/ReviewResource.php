<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewee_id' => $this->reviewee_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_visible' => (bool) $this->is_visible,
            'is_flagged' => (bool) $this->is_flagged,
            'created_at' => $this->created_at,
            'reviewer' => $this->relationLoaded('reviewer') ? new UserResource($this->reviewer) : null,
            'reviewee' => $this->relationLoaded('reviewee') ? new UserResource($this->reviewee) : null,
        ];
    }
}
