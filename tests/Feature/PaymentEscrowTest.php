<?php

namespace Tests\Feature;

use App\Jobs\ReleasePayment;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\TeacherEarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected BookingSlot $slot;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'test_secret',
            'services.razorpay.webhook_secret' => 'test_secret',
        ]);

        $this->student = User::factory()->create(['role' => 'student']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);

        $this->slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration_minutes' => 60,
            'is_booked' => false,
        ]);

        $this->booking = Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $this->slot->id,
            'start_at' => now()->addDays(3)->setTime(10, 0),
            'end_at' => now()->addDays(3)->setTime(11, 0),
            'status' => 'pending',
            'price' => 500,
            'platform_fee' => 60,
            'teacher_payout' => 440,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_initiate_payment_creates_pending_gateway_order(): void
    {
        $response = $this->actingAs($this->student)->postJson('/api/payments/initiate', [
            'booking_id' => $this->booking->id,
            'gateway' => 'razorpay',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['payment_id', 'gateway_order_id', 'checkout']);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'payer_id' => $this->student->id,
            'amount' => 500,
            'platform_fee' => 60,
            'teacher_payout' => 440,
            'gateway' => 'razorpay',
            'status' => 'pending',
        ]);
    }

    public function test_valid_payment_verification_holds_money_and_confirms_booking(): void
    {
        Queue::fake();
        $payment = $this->createPendingPayment();
        $paymentId = 'pay_valid_123';
        $signature = hash_hmac('sha256', $payment->gateway_order_id . '|' . $paymentId, 'test_secret');

        $response = $this->actingAs($this->student)->postJson('/api/payments/verify', [
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => $paymentId,
            'signature' => $signature,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'gateway_payment_id' => $paymentId,
            'status' => 'held',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
            'payment_status' => 'held',
        ]);
        $this->assertDatabaseHas('booking_slots', [
            'id' => $this->slot->id,
            'is_booked' => true,
            'booking_id' => $this->booking->id,
        ]);
    }

    public function test_invalid_payment_signature_fails(): void
    {
        $payment = $this->createPendingPayment();

        $response = $this->actingAs($this->student)->postJson('/api/payments/verify', [
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => 'pay_invalid',
            'signature' => 'bad-signature',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => 'pending']);
    }

    public function test_webhook_is_idempotent(): void
    {
        Queue::fake();
        $payment = $this->createPendingPayment();
        $payload = json_encode([
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => 'pay_webhook_123',
            'state' => 'COMPLETED',
        ]);
        $signature = hash_hmac('sha256', $payload, 'test_secret');

        $this->postJson('/api/webhooks/payment?gateway=razorpay', json_decode($payload, true), [
            'X-Webhook-Signature' => $signature,
        ])->assertOk();

        $this->postJson('/api/webhooks/payment?gateway=razorpay', json_decode($payload, true), [
            'X-Webhook-Signature' => $signature,
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'held']);
        $this->assertSame(1, Payment::where('id', $payment->id)->where('status', 'held')->count());
    }

    public function test_release_payment_creates_teacher_earning_once(): void
    {
        $payment = $this->createHeldPayment();
        Queue::fake();
        $this->booking->update(['status' => 'completed', 'payment_status' => 'held']);

        (new ReleasePayment($this->booking->id))->handle();
        (new ReleasePayment($this->booking->id))->handle();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'released']);
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'payment_status' => 'released']);
        $this->assertSame(1, TeacherEarning::where('payment_id', $payment->id)->count());
    }

    public function test_refund_scenarios(): void
    {
        $earlyPayment = $this->createHeldPayment();

        $earlyResponse = $this->actingAs($this->student)
            ->patchJson('/api/bookings/' . $this->booking->id . '/cancel');

        $earlyResponse->assertOk()->assertJson(['refunded' => true]);
        $this->assertDatabaseHas('payments', ['id' => $earlyPayment->id, 'status' => 'refunded']);

        [$lateBooking, $latePayment] = $this->makeHeldBooking(now()->addHour());
        $lateResponse = $this->actingAs($lateBooking->student)
            ->patchJson('/api/bookings/' . $lateBooking->id . '/cancel');

        $lateResponse->assertOk()->assertJson(['refunded' => false]);
        $this->assertDatabaseHas('payments', ['id' => $latePayment->id, 'status' => 'held']);

        [$teacherBooking, $teacherPayment] = $this->makeHeldBooking(now()->addHour());
        $teacherResponse = $this->actingAs($teacherBooking->teacher)
            ->patchJson('/api/bookings/' . $teacherBooking->id . '/cancel');

        $teacherResponse->assertOk()->assertJson(['refunded' => true]);
        $this->assertDatabaseHas('payments', ['id' => $teacherPayment->id, 'status' => 'refunded']);
    }

    private function createPendingPayment(?Booking $booking = null): Payment
    {
        $booking ??= $this->booking;

        return Payment::create([
            'booking_id' => $booking->id,
            'payer_id' => $booking->student_id,
            'amount' => $booking->price,
            'amount_paise' => 50000,
            'platform_fee' => $booking->platform_fee,
            'teacher_payout' => $booking->teacher_payout,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_' . $booking->id,
            'status' => 'pending',
        ]);
    }

    private function createHeldPayment(?Booking $booking = null): Payment
    {
        $payment = $this->createPendingPayment($booking);
        $payment->transitionTo(Payment::STATUS_HELD, [
            'gateway_payment_id' => 'pay_' . $payment->booking_id,
            'paid_at' => now(),
        ]);

        ($booking ?? $this->booking)->update([
            'status' => 'confirmed',
            'payment_status' => 'held',
        ]);

        return $payment;
    }

    private function makeHeldBooking($startAt): array
    {
        $slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'duration_minutes' => 60,
            'is_booked' => true,
        ]);

        $booking = Booking::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHour(),
            'status' => 'confirmed',
            'price' => 500,
            'platform_fee' => 60,
            'teacher_payout' => 440,
            'payment_status' => 'held',
        ]);

        $slot->update(['booking_id' => $booking->id]);
        $booking->setRelation('student', $this->student);
        $booking->setRelation('teacher', $this->teacher);

        return [$booking, $this->createHeldPayment($booking)];
    }
}
