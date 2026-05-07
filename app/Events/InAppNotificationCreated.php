<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InAppNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('App.Models.User.' . $this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'audience' => $this->notification->audience,
            'data' => $this->notification->data ?? [],
            'is_critical' => (bool) $this->notification->is_critical,
            'created_at' => optional($this->notification->created_at)->toIso8601String(),
        ];
    }
}
