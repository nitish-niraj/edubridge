<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VideoSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);

        TeacherProfile::create([
            'user_id'     => $this->teacher->id,
            'bio'         => 'Test',
            'hourly_rate' => 0,
            'is_free'     => true,
            'is_verified' => true,
            'subjects'    => ['Science'],
            'languages'   => ['English'],
        ]);

        $slot = BookingSlot::create([
            'teacher_id'      => $this->teacher->id,
            'slot_date'       => now()->addMinutes(15)->toDateString(),
            'start_time'      => now()->addMinutes(15)->format('H:i'),
            'end_time'        => now()->addMinutes(75)->format('H:i'),
            'duration_minutes' => 60,
            'is_booked'       => true,
        ]);

        $this->booking = Booking::create([
            'student_id'     => $this->student->id,
            'teacher_id'     => $this->teacher->id,
            'slot_id'        => $slot->id,
            'start_at'       => now()->addMinutes(15),
            'end_at'         => now()->addMinutes(75),
            'status'         => 'confirmed',
            'price'          => 0,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_token_endpoint_returns_token_and_room_name(): void
    {
        $mock = Mockery::mock(TwilioService::class);
        $mock->shouldReceive('generateVideoToken')->once()->andReturn('jwt-token-here');
        $this->app->instance(TwilioService::class, $mock);

        $response = $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$this->booking->id}/token");

        $response->assertOk();
        $response->assertJsonStructure(['token', 'room_name', 'identity']);
    }

    public function test_non_participant_gets_403(): void
    {
        $stranger = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($stranger)
            ->postJson("/api/video-sessions/{$this->booking->id}/token");

        $response->assertStatus(403);
    }

    public function test_too_early_returns_starts_in_minutes(): void
    {
        // Update booking to be 2 hours from now
        $this->booking->update([
            'start_at' => now()->addHours(2),
            'end_at'   => now()->addHours(3),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/video-sessions/{$this->booking->id}/token");

        $response->assertOk();
        $response->assertJsonPath('too_early', true);
        $response->assertJsonStructure(['starts_in_minutes']);
    }
}
