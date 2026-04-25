<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);

        TeacherProfile::create([
            'user_id'     => $this->teacher->id,
            'bio'         => 'Test teacher',
            'hourly_rate' => 500,
            'is_free'     => false,
            'is_verified' => true,
            'subjects'    => ['Math'],
            'languages'   => ['English'],
        ]);

        $this->slot = BookingSlot::create([
            'teacher_id'      => $this->teacher->id,
            'slot_date'       => now()->addDays(3)->toDateString(),
            'start_time'      => '10:00',
            'end_time'        => '11:00',
            'duration_minutes' => 60,
        ]);
    }

    public function test_booking_a_slot_sets_is_booked_true_for_free_session(): void
    {
        // Make teacher free
        TeacherProfile::where('user_id', $this->teacher->id)->update(['is_free' => true, 'hourly_rate' => 0]);

        $response = $this->actingAs($this->student)
            ->postJson('/api/bookings', ['slot_id' => $this->slot->id]);

        $response->assertStatus(201);
        $response->assertJsonPath('requires_payment', false);

        $this->assertDatabaseHas('booking_slots', [
            'id' => $this->slot->id,
            'is_booked' => true,
        ]);
        $this->assertDatabaseHas('bookings', [
            'slot_id' => $this->slot->id,
            'status'  => 'confirmed',
        ]);
    }

    public function test_booking_already_booked_slot_returns_422(): void
    {
        $this->slot->update(['is_booked' => true]);

        $response = $this->actingAs($this->student)
            ->postJson('/api/bookings', ['slot_id' => $this->slot->id]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'This slot is already booked.']);
    }

    public function test_paid_session_returns_requires_payment_true(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/bookings', ['slot_id' => $this->slot->id]);

        $response->assertStatus(201);
        $response->assertJsonPath('requires_payment', true);
        $response->assertJsonPath('amount', 500);

        $this->assertDatabaseHas('bookings', [
            'slot_id' => $this->slot->id,
            'status'  => 'pending',
        ]);
    }

    public function test_teacher_cannot_book_own_slot(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson('/api/bookings', ['slot_id' => $this->slot->id]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'You cannot book your own slot.']);
    }
}
