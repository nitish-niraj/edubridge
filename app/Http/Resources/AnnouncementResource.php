<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'target_role' => $this->target_role,
            'delivery_type' => $this->delivery_type,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'created_by' => $this->created_by,
            'sent_count' => (int) $this->sent_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator' => new UserResource($this->whenLoaded('creator')),
        ];
    }
}
