<?php

namespace Tests\Feature;

use App\Jobs\SendReviewReminder;
use App\Jobs\SendSessionReminders;
use App\Mail\ReviewReminderMail;
use App\Mail\SessionCompletedMail;
use App\Mail\SessionReminderMail;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReviewRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase3ETest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-30 10:00:00'));
        Cache::flush();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);

        TeacherProfile::factory()->create([
            'user_id' => $this->teacher->id,
            'is_free' => true,
            'is_verified' => true,
            'rating_avg' => 0,
            'total_reviews' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hidden_reviews_are_excluded_from_rating_recalculation(): void
    {
        $visibleBooking = $this->createBooking($this->student, 'completed', now()->subDay());
        $hiddenStudent = User::factory()->create(['role' => 'student']);
        $hiddenBooking = $this->createBooking($hiddenStudent, 'completed', now()->subDays(2));

        Review::create([
            'booking_id' => $visibleBooking->id,
            'reviewer_id' => $this->student->id,
            'reviewee_id' => $this->teacher->id,
            'rating' => 5,
            'is_visible' => true,
        ]);
        Review::create([
            'booking_id' => $hiddenBooking->id,
            'reviewer_id' => $hiddenStudent->id,
            'reviewee_id' => $this->teacher->id,
            'rating' => 1,
            'is_visible' => false,
        ]);

        app(ReviewRatingService::class)->recalculateForTeacher($this->teacher->id);

        $profile = $this->teacher->teacherProfile()->first();
        $this->assertSame(5.0, (float) $profile->rating_avg);
        $this->assertSame(1, $profile->total_reviews);
    }

    public function test_completion_sends_notification_and_queues_review_reminder(): void
    {
        Mail::fake();
        Queue::fake();

        $booking = $this->createBooking($this->student, 'confirmed', now()->subHour());

        $booking->update(['status' => 'completed']);

        Mail::assertSent(SessionCompletedMail::class, 2);
        Queue::assertPushed(SendReviewReminder::class, function (SendReviewReminder $job) use ($booking): bool {
            return $job->bookingId === $booking->id;
        });
    }

    public function test_session_reminder_job_sends_time_based_reminders_once(): void
    {
        Mail::fake();

        $this->createBooking($this->student, 'confirmed', now()->addHour());

        $job = new SendSessionReminders();
        $job->handle(app(NotificationService::class));
        $job->handle(app(NotificationService::class));

        Mail::assertSent(SessionReminderMail::class, 2);
    }

    public function test_review_reminder_job_skips_already_reviewed_booking(): void
    {
        Mail::fake();

        $booking = $this->createBooking($this->student, 'completed', now()->subHour());
        Review::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $this->student->id,
            'reviewee_id' => $this->teacher->id,
            'rating' => 4,
        ]);

        (new SendReviewReminder($booking->id))->handle(app(NotificationService::class));

        Mail::assertNotSent(ReviewReminderMail::class);
    }

    public function test_teacher_booking_api_returns_released_earnings_summary(): void
    {
        $releasedBooking = $this->createBooking($this->student, 'completed', now()->subDays(3), price: 500);
        $pendingBooking = $this->createBooking(User::factory()->create(['role' => 'student']), 'completed', now()->subDays(4), price: 300);

        $releasedPayment = $this->createPayment($releasedBooking, 'released');
        $pendingPayment = $this->createPayment($pendingBooking, 'held');

        TeacherEarning::create([
            'teacher_id' => $this->teacher->id,
            'payment_id' => $releasedPayment->id,
            'booking_id' => $releasedBooking->id,
            'gross_amount' => 500,
            'platform_fee' => 60,
            'net_amount' => 440,
            'status' => 'released',
            'payout_date' => now()->toDateString(),
        ]);
        TeacherEarning::create([
            'teacher_id' => $this->teacher->id,
            'payment_id' => $pendingPayment->id,
            'booking_id' => $pendingBooking->id,
            'gross_amount' => 300,
            'platform_fee' => 36,
            'net_amount' => 264,
            'status' => 'pending',
        ]);

        $this->actingAs($this->teacher)
            ->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('earnings_summary.this_month', 440.0)
            ->assertJsonPath('earnings_summary.total', 440.0)
            ->assertJsonPath('earnings_summary.pending', 264.0);
    }

    private function createBooking(User $student, string $status, Carbon $startAt, float $price = 0): Booking
    {
        $slot = BookingSlot::create([
            'teacher_id' => $this->teacher->id,
            'slot_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'duration_minutes' => 60,
            'is_booked' => true,
        ]);

        return Booking::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'slot_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHour(),
            'status' => $status,
            'subject' => 'Science',
            'price' => $price,
            'platform_fee' => round($price * 0.12, 2),
            'teacher_payout' => round($price * 0.88, 2),
            'payment_status' => $price > 0 ? 'held' : 'unpaid',
        ]);
    }

    private function createPayment(Booking $booking, string $status): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'payer_id' => $booking->student_id,
            'amount' => $booking->price,
            'amount_paise' => (int) ($booking->price * 100),
            'platform_fee' => $booking->platform_fee,
            'teacher_payout' => $booking->teacher_payout,
            'gateway' => 'test',
            'gateway_order_id' => 'order_' . $booking->id,
            'status' => $status,
            'paid_at' => now(),
        ]);
    }
}
