<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
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
            'subjects'    => ['Art'],
            'languages'   => ['English'],
            'rating_avg'  => 0,
            'total_reviews' => 0,
        ]);

        $slot = BookingSlot::create([
            'teacher_id'      => $this->teacher->id,
            'slot_date'       => now()->subDay()->toDateString(),
            'start_time'      => '10:00',
            'end_time'        => '11:00',
            'duration_minutes' => 60,
            'is_booked'       => true,
        ]);

        $this->booking = Booking::create([
            'student_id'     => $this->student->id,
            'teacher_id'     => $this->teacher->id,
            'slot_id'        => $slot->id,
            'start_at'       => now()->subDay()->setTime(10, 0),
            'end_at'         => now()->subDay()->setTime(11, 0),
            'status'         => 'completed',
            'price'          => 0,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_submitting_review_updates_teacher_rating(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/reviews', [
                'booking_id' => $this->booking->id,
                'rating'     => 5,
                'comment'    => 'Amazing teacher!',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->booking->id,
            'rating'     => 5,
        ]);

        $profile = TeacherProfile::where('user_id', $this->teacher->id)->first();
        $this->assertEquals(5.00, (float) $profile->rating_avg);
        $this->assertEquals(1, $profile->total_reviews);
    }

    public function test_duplicate_review_returns_422(): void
    {
        Review::create([
            'booking_id'  => $this->booking->id,
            'reviewer_id' => $this->student->id,
            'reviewee_id' => $this->teacher->id,
            'rating'      => 4,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson('/api/reviews', [
                'booking_id' => $this->booking->id,
                'rating'     => 5,
            ]);

        $response->assertStatus(422);
    }

    public function test_rating_outside_range_returns_422(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/reviews', [
                'booking_id' => $this->booking->id,
                'rating'     => 6,
            ]);

        $response->assertStatus(422);
    }
}
