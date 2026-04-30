<?php

namespace App\Http\Resources;

use App\Models\ClassMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversation = $this->relationLoaded('conversation') ? $this->conversation : $this->conversation()->first();

        $isTeacher = $conversation?->is_group && (int) $conversation->teacher_id === (int) $this->sender_id;
        $isMuted = false;

        if ($conversation?->is_group) {
            $isMuted = ClassMember::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $this->sender_id)
                ->where('is_muted', true)
                ->exists();
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'type' => $this->type,
            'file_url' => $this->file_url,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'is_teacher' => $isTeacher,
            'is_muted' => $isMuted,
            'muted_label' => $isMuted && $request->user()?->id === $conversation?->teacher_id ? '[MUTED]' : null,
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar' => $this->sender?->avatar,
                'role_label' => $isTeacher ? 'Teacher' : 'Student',
            ],
        ];
    }
}
