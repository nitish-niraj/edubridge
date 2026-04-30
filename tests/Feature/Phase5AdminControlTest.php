<?php

namespace Tests\Feature;

use App\Mail\DisputeResolvedMail;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\PhonePeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase5AdminControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        TeacherProfile::factory()->create([
            'user_id' => $this->teacher->id,
            'is_verified' => true,
            'rating_avg' => 0,
            'total_reviews' => 0,
        ]);
    }

    public function test_dispute_queue_includes_no_show_bookings(): void
    {
        Sanctum::actingAs($this->admin);
        $booking = $this->createBooking('no_show');

        $response = $this->getJson('/api/admin/disputes?type=no_show');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->contains('id', $booking->id));
    }

    public function test_admin_full_refund_is_idempotent_and_audited(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);
        $booking = $this->createBooking('cancelled', 'held');
        $payment = $this->createPayment($booking, Payment::STATUS_HELD);

        $this->mockPhonePeRefund();

        $this->postJson("/api/admin/disputes/{$booking->id}/full-refund", [
            'note' => 'Student safety concern.',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame('refunded', $booking->fresh()->payment_status);
        $this->assertArrayHasKey('admin_refund', $payment->raw_response);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'dispute.full_refund',
            'entity_type' => 'Booking',
            'entity_id' => $booking->id,
        ]);

        Mail::assertSent(DisputeResolvedMail::class);

        $this->postJson("/api/admin/disputes/{$booking->id}/full-refund")
            ->assertStatus(422);
    }

    public function test_partial_refund_rejects_amount_above_paid_amount(): void
    {
        Sanctum::actingAs($this->admin);
        $booking = $this->createBooking('cancelled', 'held');
        $this->createPayment($booking, Payment::STATUS_HELD, 500);

        $this->postJson("/api/admin/disputes/{$booking->id}/partial-refund", [
            'amount' => 600,
        ])->assertStatus(422);
    }

    public function test_suspended_user_with_existing_session_is_blocked_from_api_access(): void
    {
        $this->student->update(['status' => 'suspended']);
        Sanctum::actingAs($this->student);

        $this->getJson('/api/bookings')->assertStatus(403);
    }

    public function test_review_visibility_toggle_recalculates_rating_and_audits(): void
    {
        Sanctum::actingAs($this->admin);
        $booking = $this->createBooking('completed');

        $review = Review::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $this->student->id,
            'reviewee_id' => $this->teacher->id,
            'rating' => 5,
            'comment' => 'Great class.',
            'is_visible' => true,
        ]);

        app(\App\Services\ReviewRatingService::class)->recalculateForTeacher($this->teacher->id);
        $this->assertSame(1, (int) $this->teacher->teacherProfile()->first()->total_reviews);

        $this->patchJson("/api/admin/reviews/{$review->id}/visibility")->assertOk();

        $this->assertFalse((bool) $review->fresh()->is_visible);
        $profile = $this->teacher->teacherProfile()->first();
        $this->assertSame(0, (int) $profile->total_reviews);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review.hidden',
            'entity_type' => 'Review',
            'entity_id' => $review->id,
        ]);
    }

    private function createBooking(string $status = 'confirmed', string $paymentStatus = 'unpaid'): Booking
    {
        $slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'is_booked' => true,
        ]);

        return Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $slot->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => $status,
            'subject' => 'Math',
            'price' => 500,
            'platform_fee' => 60,
            'teacher_payout' => 440,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function createPayment(Booking $booking, string $status, float $amount = 500): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'payer_id' => $this->student->id,
            'amount' => $amount,
            'amount_paise' => (int) ($amount * 100),
            'platform_fee' => round($amount * 0.12, 2),
            'teacher_payout' => round($amount * 0.88, 2),
            'gateway' => 'phonepe',
            'gateway_order_id' => 'order_' . $booking->id,
            'gateway_payment_id' => 'pay_' . $booking->id,
            'status' => $status,
            'paid_at' => now(),
            'raw_response' => ['created_by' => 'test'],
        ]);
    }

    private function mockPhonePeRefund(): void
    {
        $mock = Mockery::mock(PhonePeService::class);
        $mock->shouldReceive('initiateRefund')
            ->once()
            ->andReturn(['state' => 'COMPLETED', 'refund_id' => 'refund_test']);

        $this->app->instance(PhonePeService::class, $mock);
    }
}
