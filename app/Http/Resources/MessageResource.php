<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'type' => $this->type,
            'file_url' => $this->file_url,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar' => $this->sender?->avatar,
            ],
        ];
    }
}
