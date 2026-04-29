<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_conversation_creates_conversation_participants_and_message_records(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/conversations', [
            'teacher_id' => $teacher->id,
            'message' => 'Hello teacher!',
        ]);

        $response->assertStatus(201);

        $conversationId = $response->json('data.id');
        $this->assertNotNull($conversationId);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'created_by' => $student->id,
            'is_group' => false,
        ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $teacher->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $student->id,
            'body' => 'Hello teacher!',
            'type' => 'text',
        ]);
    }

    public function test_sending_message_fires_message_sent_event(): void
    {
        Event::fake([MessageSent::class]);

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $conversation = Conversation::query()->create([
            'created_by' => $student->id,
            'is_group' => false,
        ]);
        $conversation->participants()->attach($student->id, ['joined_at' => now()]);
        $conversation->participants()->attach($teacher->id, ['joined_at' => now()]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'type' => 'text',
            'body' => 'Follow up message',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_read_endpoint_updates_read_at(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $conversation = Conversation::query()->create([
            'created_by' => $student->id,
            'is_group' => false,
        ]);
        $conversation->participants()->attach($student->id, ['joined_at' => now()]);
        $conversation->participants()->attach($teacher->id, ['joined_at' => now()]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $teacher->id,
            'body' => 'Unread message',
            'type' => 'text',
            'read_at' => null,
        ]);

        Sanctum::actingAs($student);

        $response = $this->patchJson("/api/conversations/{$conversation->id}/read");
        $response->assertOk();

        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    public function test_non_participant_gets_403_on_messages_endpoint(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $intruder = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $conversation = Conversation::query()->create([
            'created_by' => $student->id,
            'is_group' => false,
        ]);
        $conversation->participants()->attach($student->id, ['joined_at' => now()]);
        $conversation->participants()->attach($teacher->id, ['joined_at' => now()]);

        Sanctum::actingAs($intruder);

        $response = $this->getJson("/api/conversations/{$conversation->id}/messages");
        $response->assertStatus(403);
    }

    public function test_duplicate_conversation_prevention_returns_existing_conversation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        Sanctum::actingAs($student);

        // First call creates the conversation
        $response1 = $this->postJson('/api/conversations', [
            'teacher_id' => $teacher->id,
            'message' => 'Hello teacher!',
        ]);

        $response1->assertSuccessful();
        $conversationId1 = $response1->json('data.id');

        // Second call with same teacher should return the same conversation
        $response2 = $this->postJson('/api/conversations', [
            'teacher_id' => $teacher->id,
            'message' => 'Follow up message',
        ]);

        $response2->assertSuccessful();
        $conversationId2 = $response2->json('data.id');

        $this->assertEquals($conversationId1, $conversationId2);

        // Ensure we only have one conversation for this pair
        $this->assertDatabaseCount('conversations', 1);
        
        // But we should have 2 messages
        $this->assertDatabaseCount('messages', 2);
    }
}
