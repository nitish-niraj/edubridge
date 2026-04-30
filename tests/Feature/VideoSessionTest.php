<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\VideoSession;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class VideoSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-30 10:00:00'));

        $this->student = User::factory()->create(['role' => 'student']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);

        TeacherProfile::factory()->create([
            'user_id' => $this->teacher->id,
            'is_free' => true,
            'is_verified' => true,
            'hourly_rate' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_token_endpoint_returns_token_for_valid_participant(): void
    {
        $booking = $this->createBooking(startAt: now()->addMinutes(15));
        $this->mockTwilioToken(
            roomName: "edubridge-{$booking->id}",
            identity: "student-{$this->student->id}",
            token: 'jwt-token-here',
        );

        $response = $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$booking->id}/token");

        $response->assertOk()
            ->assertJson([
                'token' => 'jwt-token-here',
                'room_name' => "edubridge-{$booking->id}",
                'identity' => "student-{$this->student->id}",
            ]);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'room_name' => "edubridge-{$booking->id}",
        ]);
    }

    public function test_token_endpoint_blocks_non_participants(): void
    {
        $booking = $this->createBooking(startAt: now()->addMinutes(15));
        $stranger = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($stranger)
            ->postJson("/api/video-sessions/{$booking->id}/token");

        $response->assertForbidden();

        $this->assertDatabaseMissing('video_sessions', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_token_endpoint_enforces_join_window(): void
    {
        $tooEarly = $this->createBooking(startAt: now()->addMinutes(16));
        $onEarlyBoundary = $this->createBooking(startAt: now()->addMinutes(15));
        $onLateBoundary = $this->createBooking(
            startAt: now()->subMinutes(30),
            endAt: now()->addMinutes(30),
        );
        $tooLate = $this->createBooking(
            startAt: now()->subMinutes(31),
            endAt: now()->addMinutes(29),
        );

        $mock = Mockery::mock(TwilioService::class);
        $mock->shouldReceive('generateVideoToken')
            ->once()
            ->with("edubridge-{$onEarlyBoundary->id}", "student-{$this->student->id}")
            ->andReturn('early-boundary-token');
        $mock->shouldReceive('generateVideoToken')
            ->once()
            ->with("edubridge-{$onLateBoundary->id}", "student-{$this->student->id}")
            ->andReturn('late-boundary-token');
        $this->app->instance(TwilioService::class, $mock);

        $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$tooEarly->id}/token")
            ->assertUnprocessable();

        $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$onEarlyBoundary->id}/token")
            ->assertOk()
            ->assertJsonPath('token', 'early-boundary-token');

        $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$onLateBoundary->id}/token")
            ->assertOk()
            ->assertJsonPath('token', 'late-boundary-token');

        $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$tooLate->id}/token")
            ->assertStatus(410);
    }

    public function test_token_endpoint_blocks_unconfirmed_booking(): void
    {
        $booking = $this->createBooking(
            startAt: now()->addMinutes(15),
            status: 'pending',
        );

        $response = $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$booking->id}/token");

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Booking is not confirmed.']);
    }

    public function test_start_updates_started_at_idempotently(): void
    {
        $booking = $this->createBooking(startAt: now()->addMinutes(15));
        $this->createVideoSession($booking);

        $firstStart = now();
        Carbon::setTestNow($firstStart);

        $this->actingAs($this->student)
            ->patchJson("/api/video-sessions/{$booking->id}/start")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Session started.']);

        $this->assertEquals($firstStart->toDateTimeString(), $booking->videoSession->fresh()->started_at->toDateTimeString());

        Carbon::setTestNow($firstStart->copy()->addMinutes(10));

        $this->actingAs($this->teacher)
            ->patchJson("/api/video-sessions/{$booking->id}/start")
            ->assertOk();

        $this->assertEquals($firstStart->toDateTimeString(), $booking->videoSession->fresh()->started_at->toDateTimeString());
    }

    public function test_only_teacher_can_end_session(): void
    {
        $booking = $this->createBooking(startAt: now()->subMinutes(10), endAt: now()->addMinutes(50));
        $this->createVideoSession($booking, startedAt: now()->subMinutes(5));

        $response = $this->actingAs($this->student)
            ->patchJson("/api/video-sessions/{$booking->id}/end");

        $response->assertForbidden()
            ->assertJsonFragment(['message' => 'Only the teacher can end the session.']);

        $this->assertNull($booking->videoSession->fresh()->ended_at);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_end_calculates_duration_and_completes_booking_for_five_minutes_or_more(): void
    {
        $booking = $this->createBooking(startAt: now()->subMinutes(10), endAt: now()->addMinutes(50));
        $this->createVideoSession($booking, startedAt: now()->subMinutes(5));

        $response = $this->actingAs($this->teacher)
            ->patchJson("/api/video-sessions/{$booking->id}/end");

        $response->assertOk()
            ->assertJson([
                'duration_minutes' => 5,
                'booking_status' => 'completed',
            ]);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'duration_minutes' => 5,
        ]);
        $this->assertEquals('completed', $booking->fresh()->status);
    }

    public function test_end_marks_booking_no_show_for_duration_under_five_minutes(): void
    {
        $booking = $this->createBooking(startAt: now()->subMinutes(10), endAt: now()->addMinutes(50));
        $this->createVideoSession($booking, startedAt: now()->subMinutes(4));

        $response = $this->actingAs($this->teacher)
            ->patchJson("/api/video-sessions/{$booking->id}/end");

        $response->assertOk()
            ->assertJson([
                'duration_minutes' => 4,
                'booking_status' => 'no_show',
            ]);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'duration_minutes' => 4,
        ]);
        $this->assertEquals('no_show', $booking->fresh()->status);
    }

    private function createBooking(
        Carbon $startAt,
        ?Carbon $endAt = null,
        string $status = 'confirmed',
    ): Booking {
        $endAt ??= $startAt->copy()->addHour();

        $slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'duration_minutes' => $startAt->diffInMinutes($endAt),
            'is_booked' => true,
        ]);

        return Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $status,
            'price' => 0,
            'payment_status' => 'unpaid',
        ]);
    }

    private function createVideoSession(Booking $booking, ?Carbon $startedAt = null): VideoSession
    {
        return VideoSession::create([
            'booking_id' => $booking->id,
            'room_name' => "edubridge-{$booking->id}",
            'room_type' => 'peer-to-peer',
            'started_at' => $startedAt,
        ]);
    }

    private function mockTwilioToken(string $roomName, string $identity, string $token): void
    {
        $mock = Mockery::mock(TwilioService::class);
        $mock->shouldReceive('generateVideoToken')
            ->once()
            ->with($roomName, $identity)
            ->andReturn($token);

        $this->app->instance(TwilioService::class, $mock);
    }
}
