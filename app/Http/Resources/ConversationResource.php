<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $participants = $this->relationLoaded('participants') ? $this->participants : collect();
        $otherParticipant = $participants->firstWhere('id', '!=', $currentUser?->id);
        $lastMessage = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;

        $previewText = '';
        if ($lastMessage) {
            $previewText = $lastMessage->body ?? match ($lastMessage->type) {
                'image' => '[Image]',
                'file' => '[File]',
                default => '',
            };
        }

        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'is_group' => (bool) $this->is_group,
            'title' => $this->title,
            'display_name' => $this->is_group
                ? ($this->title ?: 'Group Conversation')
                : ($otherParticipant?->name ?: 'Conversation'),
            'display_avatar' => $this->is_group ? null : $otherParticipant?->avatar,
            'participants' => $participants->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->name,
                'avatar' => $participant->avatar,
                'role' => $participant->role,
            ])->values(),
            'last_message' => $lastMessage ? new MessageResource($lastMessage) : null,
            'last_message_preview' => Str::limit($previewText, 50),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
