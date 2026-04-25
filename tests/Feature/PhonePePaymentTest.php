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

class PhonePePaymentTest extends TestCase
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
            'hourly_rate' => 500,
            'is_free'     => false,
            'is_verified' => true,
            'subjects'    => ['Math'],
            'languages'   => ['English'],
        ]);

        $slot = BookingSlot::create([
            'teacher_id'      => $this->teacher->id,
            'slot_date'       => now()->addDays(3)->toDateString(),
            'start_time'      => '10:00',
            'end_time'        => '11:00',
            'duration_minutes' => 60,
        ]);

        $this->booking = Booking::create([
            'student_id'     => $this->student->id,
            'teacher_id'     => $this->teacher->id,
            'slot_id'        => $slot->id,
            'start_at'       => now()->addDays(3)->setTime(10, 0),
            'end_at'         => now()->addDays(3)->setTime(11, 0),
            'status'         => 'pending',
            'price'          => 500,
            'platform_fee'   => 60,
            'teacher_payout' => 440,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_initiate_payment_returns_redirect_url(): void
    {
        $mock = Mockery::mock(PhonePeService::class);
        $mock->shouldReceive('initiatePayment')->once()->andReturn([
            'state'            => 'PENDING',
            'redirect_url'     => 'https://mercury-t2.phonepe.com/transact/test',
            'phonepe_order_id' => 'PP-123',
            'expire_at'        => time() + 3600,
        ]);
        $this->app->instance(PhonePeService::class, $mock);

        $response = $this->actingAs($this->student)
            ->postJson('/api/payments/initiate', ['booking_id' => $this->booking->id]);

        $response->assertOk();
        $response->assertJsonStructure(['redirect_url', 'merchant_order_id']);
    }

    public function test_wrong_student_cannot_initiate_payment(): void
    {
        $otherStudent = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($otherStudent)
            ->postJson('/api/payments/initiate', ['booking_id' => $this->booking->id]);

        $response->assertStatus(403);
    }
}
