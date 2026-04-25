<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSlot;
use App\Models\ClassMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Review;
use App\Models\SavedTeacher;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::query()
            ->where('role', 'student')
            ->where('status', 'active')
            ->with('studentProfile')
            ->orderBy('id')
            ->get();

        $teachers = User::query()
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->with('teacherProfile')
            ->orderBy('id')
            ->get();

        if ($students->count() < 2 || $teachers->count() < 2) {
            $this->command?->warn('DemoDataSeeder skipped: at least 2 active students and 2 active teachers are required.');

            return;
        }

        $admin = User::query()->where('role', 'admin')->orderBy('id')->first();

        $paidTeachers = $teachers
            ->filter(fn (User $teacher): bool =>
                (bool) $teacher->teacherProfile
                && ! $teacher->teacherProfile->is_free
                && (float) ($teacher->teacherProfile->hourly_rate ?? 0) > 0
            )
            ->values();

        $freeTeachers = $teachers
            ->filter(fn (User $teacher): bool => (bool) $teacher->teacherProfile && $teacher->teacherProfile->is_free)
            ->values();

        $primaryTeacher = $paidTeachers->first() ?? $teachers->first();
        $secondaryTeacher = $paidTeachers->skip(1)->first() ?? $teachers->skip(1)->first() ?? $primaryTeacher;
        $freeTeacher = $freeTeachers->first() ?? $teachers->last() ?? $primaryTeacher;

        if (! $primaryTeacher || ! $secondaryTeacher || ! $freeTeacher) {
            $this->command?->warn('DemoDataSeeder skipped: could not resolve demo teacher set.');

            return;
        }

        $this->ensureTeacherProfile($primaryTeacher, false);
        $this->ensureTeacherProfile($secondaryTeacher, false);
        $this->ensureTeacherProfile($freeTeacher, true);

        $studentA = $students->values()->get(0);
        $studentB = $students->values()->get(1);
        $studentC = $students->values()->get(2) ?? $studentB;

        $this->seedSavedTeachers($studentA, collect([$primaryTeacher, $secondaryTeacher, $freeTeacher]));
        $this->seedSavedTeachers($studentB, collect([$primaryTeacher, $freeTeacher]));

        [$pricePending, $platformFeePending, $teacherPayoutPending] = $this->calculatePricing($primaryTeacher, false);
        [$priceHeld, $platformFeeHeld, $teacherPayoutHeld] = $this->calculatePricing($secondaryTeacher, false);
        [$priceReleased, $platformFeeReleased, $teacherPayoutReleased] = $this->calculatePricing($primaryTeacher, false);
        [$priceRefunded, $platformFeeRefunded, $teacherPayoutRefunded] = $this->calculatePricing($secondaryTeacher, false);
        [$priceFree, $platformFeeFree, $teacherPayoutFree] = $this->calculatePricing($freeTeacher, true);

        $pendingBooking = $this->upsertBookingScenario([
            'notes' => '[DEMO] pending-payment booking',
            'student_id' => $studentA->id,
            'teacher_id' => $primaryTeacher->id,
            'subject' => 'Mathematics - Trigonometry',
            'session_type' => 'solo',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'start_at' => now('UTC')->addDays(2)->setTime(10, 0),
            'duration_minutes' => 60,
            'price' => $pricePending,
            'platform_fee' => $platformFeePending,
            'teacher_payout' => $teacherPayoutPending,
            'book_slot' => false,
        ]);

        $heldBooking = $this->upsertBookingScenario([
            'notes' => '[DEMO] confirmed-held booking',
            'student_id' => $studentB->id,
            'teacher_id' => $secondaryTeacher->id,
            'subject' => 'Physics - Motion and Laws',
            'session_type' => 'solo',
            'status' => 'confirmed',
            'payment_status' => 'held',
            'start_at' => now('UTC')->addDay()->setTime(16, 0),
            'duration_minutes' => 60,
            'price' => $priceHeld,
            'platform_fee' => $platformFeeHeld,
            'teacher_payout' => $teacherPayoutHeld,
            'book_slot' => true,
        ]);

        $releasedBooking = $this->upsertBookingScenario([
            'notes' => '[DEMO] completed-released booking',
            'student_id' => $studentA->id,
            'teacher_id' => $primaryTeacher->id,
            'subject' => 'Chemistry - Organic Basics',
            'session_type' => 'solo',
            'status' => 'completed',
            'payment_status' => 'released',
            'start_at' => now('UTC')->subDays(6)->setTime(14, 0),
            'duration_minutes' => 60,
            'price' => $priceReleased,
            'platform_fee' => $platformFeeReleased,
            'teacher_payout' => $teacherPayoutReleased,
            'book_slot' => true,
        ]);

        $refundedBooking = $this->upsertBookingScenario([
            'notes' => '[DEMO] cancelled-refunded booking',
            'student_id' => $studentC->id,
            'teacher_id' => $secondaryTeacher->id,
            'subject' => 'Biology - Cell Structure',
            'session_type' => 'solo',
            'status' => 'cancelled',
            'payment_status' => 'refunded',
            'start_at' => now('UTC')->subDays(2)->setTime(11, 30),
            'duration_minutes' => 60,
            'price' => $priceRefunded,
            'platform_fee' => $platformFeeRefunded,
            'teacher_payout' => $teacherPayoutRefunded,
            'book_slot' => false,
        ]);

        $freeCompletedBooking = $this->upsertBookingScenario([
            'notes' => '[DEMO] free-completed booking',
            'student_id' => $studentB->id,
            'teacher_id' => $freeTeacher->id,
            'subject' => 'Computer Science - Intro to Algorithms',
            'session_type' => 'solo',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'start_at' => now('UTC')->subDays(4)->setTime(9, 0),
            'duration_minutes' => 60,
            'price' => $priceFree,
            'platform_fee' => $platformFeeFree,
            'teacher_payout' => $teacherPayoutFree,
            'book_slot' => true,
        ]);

        $this->upsertPayment(
            $pendingBooking,
            $studentA,
            'pending',
            null,
            null,
            ['state' => 'PENDING', 'source' => 'demo-seeder']
        );

        $heldPayment = $this->upsertPayment(
            $heldBooking,
            $studentB,
            'held',
            now('UTC')->subHours(8),
            null,
            ['state' => 'COMPLETED', 'source' => 'demo-seeder']
        );

        $releasedPayment = $this->upsertPayment(
            $releasedBooking,
            $studentA,
            'released',
            now('UTC')->subDays(6)->addHour(),
            now('UTC')->subDays(5),
            ['state' => 'COMPLETED', 'source' => 'demo-seeder']
        );

        $this->upsertPayment(
            $refundedBooking,
            $studentC,
            'refunded',
            now('UTC')->subDays(3),
            null,
            ['state' => 'REFUNDED', 'source' => 'demo-seeder']
        );

        TeacherEarning::updateOrCreate(
            ['booking_id' => $heldBooking->id],
            [
                'teacher_id' => $heldBooking->teacher_id,
                'payment_id' => $heldPayment->id,
                'gross_amount' => $heldBooking->price,
                'platform_fee' => $heldBooking->platform_fee,
                'net_amount' => $heldBooking->teacher_payout,
                'status' => 'pending',
                'payout_date' => null,
            ]
        );

        TeacherEarning::updateOrCreate(
            ['booking_id' => $releasedBooking->id],
            [
                'teacher_id' => $releasedBooking->teacher_id,
                'payment_id' => $releasedPayment->id,
                'gross_amount' => $releasedBooking->price,
                'platform_fee' => $releasedBooking->platform_fee,
                'net_amount' => $releasedBooking->teacher_payout,
                'status' => 'released',
                'payout_date' => now('UTC')->subDays(5)->toDateString(),
            ]
        );

        $this->upsertBookingEvent($pendingBooking, 'booking_created', ['status' => 'pending'], $studentA);
        $this->upsertBookingEvent($heldBooking, 'payment_held', ['status' => 'held'], $studentB);
        $this->upsertBookingEvent($releasedBooking, 'payment_released', ['status' => 'released'], $admin ?? $primaryTeacher);
        $this->upsertBookingEvent($refundedBooking, 'booking_cancelled', ['status' => 'cancelled', 'refund' => true], $studentC);
        $this->upsertBookingEvent($freeCompletedBooking, 'booking_completed', ['status' => 'completed', 'free' => true], $freeTeacher);

        $releasedReview = Review::updateOrCreate(
            ['booking_id' => $releasedBooking->id],
            [
                'reviewer_id' => $studentA->id,
                'reviewee_id' => $releasedBooking->teacher_id,
                'rating' => 5,
                'comment' => 'Very clear explanations and practical examples.',
                'is_visible' => true,
                'is_flagged' => false,
                'created_at' => now('UTC')->subDays(5),
            ]
        );

        $freeReview = Review::updateOrCreate(
            ['booking_id' => $freeCompletedBooking->id],
            [
                'reviewer_id' => $studentB->id,
                'reviewee_id' => $freeCompletedBooking->teacher_id,
                'rating' => 4,
                'comment' => 'Helpful session. Would like more coding exercises next time.',
                'is_visible' => true,
                'is_flagged' => false,
                'created_at' => now('UTC')->subDays(3),
            ]
        );

        $this->refreshTeacherRatings(collect([
            $releasedBooking->teacher_id,
            $freeCompletedBooking->teacher_id,
        ]));

        $directConversation = Conversation::query()->firstOrCreate(
            [
                'title' => '[DEMO] Direct Mentorship Thread',
                'is_group' => false,
            ],
            [
                'created_by' => $studentA->id,
            ]
        );

        $directConversation->participants()->syncWithoutDetaching([
            $studentA->id => ['joined_at' => now('UTC')->subDays(7)],
            $primaryTeacher->id => ['joined_at' => now('UTC')->subDays(7)],
        ]);

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $directConversation->id,
                'sender_id' => $studentA->id,
                'body' => 'Hello teacher, could we revise trigonometry identities this week?',
                'type' => 'text',
            ],
            [
                'read_at' => now('UTC')->subDays(6),
            ]
        );

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $directConversation->id,
                'sender_id' => $primaryTeacher->id,
                'body' => 'Absolutely. Please share your weak topics and I will prepare a focused session.',
                'type' => 'text',
            ],
            [
                'read_at' => now('UTC')->subDays(6),
            ]
        );

        $groupConversation = Conversation::query()->firstOrCreate(
            [
                'title' => '[DEMO] Physics Evening Batch',
                'is_group' => true,
            ],
            [
                'created_by' => $secondaryTeacher->id,
                'subject' => 'Physics',
                'description' => 'Weekly problem-solving cohort focused on Class 11-12 fundamentals.',
                'max_students' => 35,
                'teacher_id' => $secondaryTeacher->id,
                'invite_code' => Conversation::generateInviteCode(),
            ]
        );

        $groupConversation->fill([
            'subject' => 'Physics',
            'description' => 'Weekly problem-solving cohort focused on Class 11-12 fundamentals.',
            'max_students' => 35,
            'teacher_id' => $secondaryTeacher->id,
        ]);

        if (! $groupConversation->invite_code) {
            $groupConversation->invite_code = Conversation::generateInviteCode();
        }

        $groupConversation->save();

        $groupConversation->participants()->syncWithoutDetaching([
            $secondaryTeacher->id => ['joined_at' => now('UTC')->subWeeks(2)],
            $studentA->id => ['joined_at' => now('UTC')->subWeeks(2)],
            $studentB->id => ['joined_at' => now('UTC')->subWeeks(2)->addDay()],
            $studentC->id => ['joined_at' => now('UTC')->subWeeks(2)->addDays(2)],
        ]);

        ClassMember::query()->updateOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'user_id' => $secondaryTeacher->id,
            ],
            [
                'role' => 'teacher',
                'joined_at' => now('UTC')->subWeeks(2),
                'left_at' => null,
                'is_muted' => false,
                'can_draw' => true,
            ]
        );

        ClassMember::query()->updateOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'user_id' => $studentA->id,
            ],
            [
                'role' => 'student',
                'joined_at' => now('UTC')->subWeeks(2),
                'left_at' => null,
                'is_muted' => false,
                'can_draw' => true,
            ]
        );

        ClassMember::query()->updateOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'user_id' => $studentB->id,
            ],
            [
                'role' => 'student',
                'joined_at' => now('UTC')->subWeeks(2)->addDay(),
                'left_at' => null,
                'is_muted' => false,
                'can_draw' => false,
            ]
        );

        ClassMember::query()->updateOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'user_id' => $studentC->id,
            ],
            [
                'role' => 'student',
                'joined_at' => now('UTC')->subWeeks(2)->addDays(2),
                'left_at' => null,
                'is_muted' => true,
                'can_draw' => false,
            ]
        );

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'sender_id' => $secondaryTeacher->id,
                'body' => 'Class reminder: bring your numerical notebook for tomorrow\'s revision.',
                'type' => 'announcement',
            ],
            [
                'read_at' => now('UTC')->subDay(),
            ]
        );

        $reportedMessage = Message::query()->firstOrCreate(
            [
                'conversation_id' => $groupConversation->id,
                'sender_id' => $studentC->id,
                'body' => '[DEMO] This message is intentionally used for moderation workflow testing.',
                'type' => 'text',
            ],
            [
                'read_at' => null,
            ]
        );

        Report::query()->updateOrCreate(
            [
                'reason' => '[DEMO] Review communication quality in group chat.',
                'type' => 'message',
                'reporter_id' => $studentB->id,
            ],
            [
                'reported_user_id' => $studentC->id,
                'reported_message_id' => $reportedMessage->id,
                'reported_review_id' => $freeReview->id,
                'booking_id' => $refundedBooking->id,
                'status' => 'pending',
                'admin_notes' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ]
        );

        Announcement::query()->updateOrCreate(
            ['title' => '[DEMO] Platform maintenance reminder'],
            [
                'message' => 'Scheduled maintenance is planned for Sunday 2:00 AM UTC. Sessions are unaffected.',
                'target_role' => 'all',
                'delivery_type' => 'banner',
                'is_active' => true,
                'starts_at' => now('UTC')->subDay(),
                'ends_at' => now('UTC')->addDays(7),
                'created_by' => ($admin?->id) ?? $primaryTeacher->id,
                'sent_count' => 0,
            ]
        );

        $this->command?->info('DemoDataSeeder completed: seeded realistic booking, payment, chat, review, and moderation sample data.');
    }

    private function ensureTeacherProfile(User $teacher, bool $preferFree): TeacherProfile
    {
        $profile = $teacher->teacherProfile;

        if (! $profile) {
            $profile = TeacherProfile::query()->create([
                'user_id' => $teacher->id,
                'bio' => 'Demo teacher profile',
                'experience_years' => 8,
                'previous_school' => 'Demo Public School',
                'hourly_rate' => $preferFree ? null : 250,
                'is_free' => $preferFree,
                'is_verified' => true,
                'rating_avg' => 0,
                'total_reviews' => 0,
                'subjects' => ['General Studies'],
                'languages' => ['English'],
                'gender' => 'other',
            ]);
        }

        $updates = ['is_verified' => true];

        if ($preferFree) {
            $updates['is_free'] = true;
            $updates['hourly_rate'] = null;
        } else {
            $hourlyRate = (float) ($profile->hourly_rate ?? 0);
            $updates['is_free'] = false;
            $updates['hourly_rate'] = $hourlyRate > 0 ? $hourlyRate : 250;
        }

        $profile->update($updates);
        $teacher->setRelation('teacherProfile', $profile->fresh());

        return $teacher->teacherProfile;
    }

    private function calculatePricing(User $teacher, bool $forceFree): array
    {
        if ($forceFree) {
            return [0.0, 0.0, 0.0];
        }

        $hourlyRate = (float) ($teacher->teacherProfile?->hourly_rate ?? 0);
        if ($hourlyRate <= 0) {
            $hourlyRate = 250.0;
        }

        $platformFee = round($hourlyRate * 0.12, 2);
        $teacherPayout = round($hourlyRate - $platformFee, 2);

        return [$hourlyRate, $platformFee, $teacherPayout];
    }

    private function upsertBookingScenario(array $scenario): Booking
    {
        /** @var Carbon $startAt */
        $startAt = $scenario['start_at']->copy()->seconds(0);
        $durationMinutes = (int) $scenario['duration_minutes'];
        $endAt = $startAt->copy()->addMinutes($durationMinutes);

        $slot = BookingSlot::query()->firstOrCreate(
            [
                'teacher_id' => $scenario['teacher_id'],
                'slot_date' => $startAt->toDateString(),
                'start_time' => $startAt->format('H:i:s'),
                'end_time' => $endAt->format('H:i:s'),
            ],
            [
                'duration_minutes' => $durationMinutes,
                'is_booked' => false,
            ]
        );

        if ($slot->duration_minutes !== $durationMinutes) {
            $slot->update(['duration_minutes' => $durationMinutes]);
        }

        $booking = Booking::query()->withTrashed()->where('notes', $scenario['notes'])->first();
        $previousSlotId = $booking?->slot_id;

        if (! $booking) {
            $booking = new Booking();
        }

        if ($booking->trashed()) {
            $booking->restore();
        }

        $booking->fill([
            'student_id' => $scenario['student_id'],
            'teacher_id' => $scenario['teacher_id'],
            'slot_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $scenario['status'],
            'session_type' => $scenario['session_type'],
            'subject' => $scenario['subject'],
            'notes' => $scenario['notes'],
            'price' => $scenario['price'],
            'platform_fee' => $scenario['platform_fee'],
            'teacher_payout' => $scenario['teacher_payout'],
            'payment_status' => $scenario['payment_status'],
        ]);

        $booking->save();

        if ($previousSlotId && $previousSlotId !== $slot->id) {
            BookingSlot::query()->where('id', $previousSlotId)->update([
                'is_booked' => false,
                'booking_id' => null,
            ]);
        }

        $slot->update([
            'is_booked' => (bool) $scenario['book_slot'],
            'booking_id' => $scenario['book_slot'] ? $booking->id : null,
        ]);

        return $booking->fresh();
    }

    private function upsertPayment(
        Booking $booking,
        User $payer,
        string $status,
        ?Carbon $paidAt,
        ?Carbon $releasedAt,
        array $rawResponse
    ): Payment {
        return Payment::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'payer_id' => $payer->id,
                'amount' => $booking->price,
                'amount_paise' => (int) round((float) $booking->price * 100),
                'platform_fee' => $booking->platform_fee,
                'teacher_payout' => $booking->teacher_payout,
                'gateway' => 'phonepe',
                'merchant_order_id' => 'DEMO-ORDER-' . $booking->id,
                'phonepe_order_id' => 'DEMO-PHONEPE-' . $booking->id,
                'status' => $status,
                'paid_at' => $paidAt,
                'released_at' => $releasedAt,
                'raw_response' => $rawResponse,
            ]
        );
    }

    private function upsertBookingEvent(Booking $booking, string $event, array $data, User $actor): void
    {
        BookingEvent::query()->firstOrCreate(
            [
                'booking_id' => $booking->id,
                'event' => $event,
            ],
            [
                'data' => $data,
                'created_by' => $actor->id,
                'created_at' => now('UTC'),
            ]
        );
    }

    private function refreshTeacherRatings(Collection $teacherIds): void
    {
        $teacherIds->filter()->unique()->each(function ($teacherId): void {
            $avgRating = (float) Review::query()
                ->where('reviewee_id', $teacherId)
                ->where('is_visible', true)
                ->avg('rating');

            $totalReviews = Review::query()
                ->where('reviewee_id', $teacherId)
                ->where('is_visible', true)
                ->count();

            TeacherProfile::query()
                ->where('user_id', $teacherId)
                ->update([
                    'rating_avg' => $totalReviews > 0 ? round($avgRating, 2) : 0,
                    'total_reviews' => $totalReviews,
                ]);
        });
    }

    private function seedSavedTeachers(User $student, Collection $teachers): void
    {
        $teachers
            ->filter(fn (?User $teacher): bool => (bool) $teacher && $teacher->id !== $student->id)
            ->unique('id')
            ->each(function (User $teacher) use ($student): void {
                SavedTeacher::query()->firstOrCreate([
                    'student_id' => $student->id,
                    'teacher_id' => $teacher->id,
                ]);
            });
    }
}