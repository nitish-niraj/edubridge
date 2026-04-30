<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingPhase3BTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected TeacherProfile $teacherProfile;
    protected BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);

        $this->teacherProfile = TeacherProfile::factory()->create([
            'user_id' => $this->teacher->id,
            'is_verified' => true,
            'is_free' => false,
            'hourly_rate' => 50.00,
        ]);

        $this->slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'is_booked' => false,
        ]);
    }

    public function test_successful_paid_booking_returns_requires_payment()
    {
        $response = $this->actingAs($this->student)->postJson('/api/bookings', [
            'slot_id' => $this->slot->id,
            'session_type' => 'solo',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'requires_payment' => true,
                'amount' => 50,
            ]);

        $this->assertDatabaseHas('bookings', [
            'student_id' => $this->student->id,
            'slot_id' => $this->slot->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'price' => 50.00,
            'platform_fee' => 6.00, // 12%
            'teacher_payout' => 44.00, // 88%
        ]);

        // Slot is NOT marked as booked yet for paid
        $this->assertDatabaseHas('booking_slots', [
            'id' => $this->slot->id,
            'is_booked' => 0,
        ]);
    }

    public function test_free_session_works_and_confirms_instantly()
    {
        $this->teacherProfile->update(['is_free' => true]);

        $response = $this->actingAs($this->student)->postJson('/api/bookings', [
            'slot_id' => $this->slot->id,
            'session_type' => 'solo',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'requires_payment' => false,
                'amount' => 0,
            ]);

        $this->assertDatabaseHas('bookings', [
            'student_id' => $this->student->id,
            'slot_id' => $this->slot->id,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'price' => 0,
        ]);

        // Slot IS marked as booked for free
        $this->assertDatabaseHas('booking_slots', [
            'id' => $this->slot->id,
            'is_booked' => 1,
        ]);
    }

    public function test_double_booking_fails()
    {
        $this->slot->update(['is_booked' => true]);

        $response = $this->actingAs($this->student)->postJson('/api/bookings', [
            'slot_id' => $this->slot->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'This slot is already booked.']);
    }

    public function test_student_cancellation_rules_enforced()
    {
        // Setup a confirmed booking 3 hours from now
        $booking = Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $this->slot->id,
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
            'status' => 'confirmed',
            'price' => 50,
        ]);

        // Cancel > 2 hours -> success
        $response = $this->actingAs($this->student)->patchJson("/api/bookings/{$booking->id}/cancel");
        $response->assertStatus(200);
        $this->assertEquals('cancelled', $booking->fresh()->status);

        // Setup a confirmed booking 1 hour from now
        $booking2 = Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $this->slot->id,
            'start_at' => now()->addHours(1),
            'end_at' => now()->addHours(2),
            'status' => 'confirmed',
            'price' => 50,
        ]);

        // Cancel < 2 hours -> fails
        $response2 = $this->actingAs($this->student)->patchJson("/api/bookings/{$booking2->id}/cancel");
        $response2->assertStatus(403)
            ->assertJsonFragment(['message' => 'Cannot cancel within 2 hours of session start.']);
    }

    public function test_no_show_detection_works()
    {
        // Confirmed booking started 20 mins ago without video session
        $booking = Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $this->slot->id,
            'start_at' => now()->subMinutes(20),
            'end_at' => now()->addMinutes(40),
            'status' => 'confirmed',
            'price' => 50,
        ]);

        $this->artisan('bookings:mark-no-show');

        $this->assertEquals('no_show', $booking->fresh()->status);
    }
}
