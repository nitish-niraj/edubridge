<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrawPermissionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $studentId,
        public bool $canDraw
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->studentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DrawPermissionChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'student_id' => $this->studentId,
            'can_draw' => $this->canDraw,
        ];
    }
}
