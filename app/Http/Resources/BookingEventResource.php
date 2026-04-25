<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingEventResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'event' => $this->event,
            'data' => $this->data,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'creator' => new UserResource($this->whenLoaded('creator')),
        ];
    }
}
