<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\PhonePeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function createBooking(User $student, User $teacher, float $hoursFromNow = 24): array
    {
        $slot = BookingSlot::create([
            'teacher_id'      => $teacher->id,
            'slot_date'       => now()->addHours($hoursFromNow)->toDateString(),
            'start_time'      => now()->addHours($hoursFromNow)->format('H:i'),
            'end_time'        => now()->addHours($hoursFromNow + 1)->format('H:i'),
            'duration_minutes' => 60,
            'is_booked'       => true,
        ]);

        $booking = Booking::create([
            'student_id'     => $student->id,
            'teacher_id'     => $teacher->id,
            'slot_id'        => $slot->id,
            'start_at'       => now()->addHours($hoursFromNow),
            'end_at'         => now()->addHours($hoursFromNow + 1),
            'status'         => 'confirmed',
            'price'          => 500,
            'platform_fee'   => 60,
            'teacher_payout' => 440,
            'payment_status' => 'held',
        ]);

        $payment = Payment::create([
            'booking_id'        => $booking->id,
            'payer_id'          => $student->id,
            'amount'            => 500,
            'amount_paise'      => 50000,
            'platform_fee'      => 60,
            'teacher_payout'    => 440,
            'merchant_order_id' => 'EDUB-' . $booking->id . '-' . time(),
            'status'            => 'held',
            'paid_at'           => now(),
        ]);

        $slot->update(['booking_id' => $booking->id]);

        return [$booking, $slot, $payment];
    }

    public function test_student_early_cancel_gets_refund(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        TeacherProfile::create(['user_id' => $teacher->id, 'bio' => 'T', 'is_free' => false, 'hourly_rate' => 500, 'is_verified' => true, 'subjects' => ['M'], 'languages' => ['E']]);

        [$booking] = $this->createBooking($student, $teacher, 24);

        // Mock PhonePe refund
        $mock = Mockery::mock(PhonePeService::class);
        $mock->shouldReceive('initiateRefund')->once()->andReturn(['state' => 'COMPLETED', 'order_id' => 'REF-1']);
        $this->app->instance(PhonePeService::class, $mock);

        $response = $this->actingAs($student)->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('refunded', true);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled', 'payment_status' => 'refunded']);
    }

    public function test_student_late_cancel_no_refund(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        TeacherProfile::create(['user_id' => $teacher->id, 'bio' => 'T', 'is_free' => false, 'hourly_rate' => 500, 'is_verified' => true, 'subjects' => ['M'], 'languages' => ['E']]);

        [$booking] = $this->createBooking($student, $teacher, 1); // 1 hour from now

        $response = $this->actingAs($student)->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('refunded', false);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_teacher_cancel_always_refunds(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        TeacherProfile::create(['user_id' => $teacher->id, 'bio' => 'T', 'is_free' => false, 'hourly_rate' => 500, 'is_verified' => true, 'subjects' => ['M'], 'languages' => ['E']]);

        [$booking] = $this->createBooking($student, $teacher, 1); // Even short notice

        $mock = Mockery::mock(PhonePeService::class);
        $mock->shouldReceive('initiateRefund')->once()->andReturn(['state' => 'COMPLETED', 'order_id' => 'REF-2']);
        $this->app->instance(PhonePeService::class, $mock);

        $response = $this->actingAs($teacher)->patchJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('refunded', true);
    }
}
