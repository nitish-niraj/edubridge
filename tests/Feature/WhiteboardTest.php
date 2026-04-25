<?php

namespace Tests\Feature;

use App\Events\WhiteboardUpdate;
use App\Events\DrawPermissionGranted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WhiteboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_whiteboard_update_event_has_elements_key(): void
    {
        Event::fake([WhiteboardUpdate::class]);

        $elements = [
            ['type' => 'rectangle', 'x' => 10, 'y' => 20, 'width' => 100, 'height' => 50],
            ['type' => 'text', 'x' => 50, 'y' => 30, 'text' => 'Hello'],
        ];

        WhiteboardUpdate::dispatch(1, $elements, 42);

        Event::assertDispatched(WhiteboardUpdate::class, function ($event) use ($elements) {
            return $event->conversationId === 1
                && $event->senderId === 42
                && $event->elements === $elements;
        });
    }

    public function test_draw_permission_granted_event_has_correct_structure(): void
    {
        Event::fake([DrawPermissionGranted::class]);

        DrawPermissionGranted::dispatch(5, 10, true);

        Event::assertDispatched(DrawPermissionGranted::class, function ($event) {
            return $event->conversationId === 5
                && $event->studentId === 10
                && $event->granted === true;
        });
    }
}
