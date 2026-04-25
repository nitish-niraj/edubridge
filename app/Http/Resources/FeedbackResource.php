<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'description' => $this->description,
            'screenshot_path' => $this->screenshot_path,
            'page_url' => $this->page_url,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
