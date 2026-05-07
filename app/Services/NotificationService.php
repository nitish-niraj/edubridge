<?php

namespace App\Services;

use App\Events\InAppNotificationCreated;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\NewMessageMail;
use App\Mail\NoShowNotificationMail;
use App\Mail\ReviewReminderMail;
use App\Mail\SessionCompletedMail;
use App\Mail\SessionReminderMail;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendBookingConfirmed(Booking $booking): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $this->mailIfEnabled($booking->student, 'booking_confirmed_email', new BookingConfirmedMail($booking, 'student'));
        $this->mailIfEnabled($booking->teacher, 'booking_confirmed_email', new BookingConfirmedMail($booking, 'teacher'));
        $this->createInApp(
            user: $booking->student,
            audience: 'student',
            type: 'booking_confirmed',
            title: 'Booking Confirmed',
            message: 'Your session with ' . ($booking->teacher?->name ?? 'your teacher') . ' is confirmed.',
            data: ['booking_id' => $booking->id, 'start_at' => optional($booking->start_at)->toIso8601String()]
        );
        $this->createInApp(
            user: $booking->teacher,
            audience: 'teacher',
            type: 'booking_confirmed',
            title: 'New Confirmed Session',
            message: 'Session with ' . ($booking->student?->name ?? 'a student') . ' is confirmed.',
            data: ['booking_id' => $booking->id, 'teacher_payout' => (float) ($booking->teacher_payout ?? 0)]
        );
    }

    public function sendBookingCancelled(Booking $booking, float $refundAmount = 0): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $this->mailIfEnabled($booking->student, 'booking_cancelled_email', new BookingCancelledMail($booking, $refundAmount, 'student'));
        $this->mailIfEnabled($booking->teacher, 'booking_cancelled_email', new BookingCancelledMail($booking, $refundAmount, 'teacher'));
        $this->createInApp(
            user: $booking->student,
            audience: 'student',
            type: 'booking_cancelled',
            title: 'Booking Cancelled',
            message: 'A booking was cancelled. Refund: INR ' . number_format($refundAmount, 2),
            data: ['booking_id' => $booking->id, 'refund_amount' => $refundAmount]
        );
        $this->createInApp(
            user: $booking->teacher,
            audience: 'teacher',
            type: 'booking_cancelled',
            title: 'Booking Cancelled',
            message: 'A booking with ' . ($booking->student?->name ?? 'a student') . ' was cancelled.',
            data: ['booking_id' => $booking->id, 'refund_amount' => $refundAmount]
        );
    }

    public function sendSessionReminder(Booking $booking, int $minutesBefore): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $cacheKey = "notifications:session-reminder:{$booking->id}:{$minutesBefore}";
        if (! Cache::add($cacheKey, now()->timestamp, now()->addDays(2))) {
            return;
        }

        if ($minutesBefore > 15) {
            $this->mailIfEnabled($booking->student, 'session_reminder_email', new SessionReminderMail($booking, 'student', $minutesBefore));
            $this->mailIfEnabled($booking->teacher, 'session_reminder_email', new SessionReminderMail($booking, 'teacher', $minutesBefore));
        }

        if ($minutesBefore === 15) {
            $this->createInApp(
                user: $booking->student,
                audience: 'student',
                type: 'session_starting_soon',
                title: 'Session starts in 15 minutes',
                message: 'Your session is about to start. Join now when ready.',
                data: ['booking_id' => $booking->id, 'join_url' => '/session/' . $booking->id]
            );
            $this->createInApp(
                user: $booking->teacher,
                audience: 'teacher',
                type: 'session_starting_soon',
                title: 'Session starts in 15 minutes',
                message: 'Session with ' . ($booking->student?->name ?? 'student') . ' starts soon.',
                data: ['booking_id' => $booking->id, 'join_url' => '/session/' . $booking->id]
            );
        }
    }

    public function sendSessionCompleted(Booking $booking): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $cacheKey = "notifications:session-completed:{$booking->id}";
        if (! Cache::add($cacheKey, now()->timestamp, now()->addDays(7))) {
            return;
        }

        $this->mailIfEnabled($booking->student, 'session_reminder_email', new SessionCompletedMail($booking, 'student'));
        $this->mailIfEnabled($booking->teacher, 'session_reminder_email', new SessionCompletedMail($booking, 'teacher'));
        $this->createInApp(
            user: $booking->student,
            audience: 'student',
            type: 'session_completed',
            title: 'Session Completed',
            message: 'Session completed. Please leave your review.',
            data: ['booking_id' => $booking->id, 'review_url' => '/reviews/' . $booking->id]
        );
    }

    public function sendReviewReminder(Booking $booking): void
    {
        $booking->loadMissing('student.notificationPreferences', 'review');

        if ($booking->status !== 'completed' || $booking->review) {
            return;
        }

        $cacheKey = "notifications:review-reminder:{$booking->id}";
        if (! Cache::add($cacheKey, now()->timestamp, now()->addDays(7))) {
            return;
        }

        $this->mailIfEnabled($booking->student, 'review_received_email', new ReviewReminderMail($booking));
    }

    public function sendNoShowDetected(Booking $booking): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $cacheKey = "notifications:no-show:{$booking->id}";
        if (! Cache::add($cacheKey, now()->timestamp, now()->addDays(7))) {
            return;
        }

        $this->mailIfEnabled($booking->student, 'session_reminder_email', new NoShowNotificationMail($booking, 'student'));
        $this->mailIfEnabled($booking->teacher, 'session_reminder_email', new NoShowNotificationMail($booking, 'teacher'));
    }

    public function sendNewMessage(User $recipient, string $senderName, ?string $senderAvatar, string $preview, int $conversationId): void
    {
        $this->mailIfEnabled($recipient, 'new_message_email', new NewMessageMail(
            senderName: $senderName,
            senderAvatar: $senderAvatar,
            preview: $preview,
            conversationId: $conversationId
        ));
        $this->createInApp(
            user: $recipient,
            audience: $recipient->isTeacher() ? 'teacher' : ($recipient->isAdmin() ? 'admin' : 'student'),
            type: 'new_message',
            title: 'New Message',
            message: $senderName . ': ' . mb_substr($preview, 0, 50),
            data: ['conversation_id' => $conversationId, 'sender_avatar' => $senderAvatar]
        );
    }

    public function sendReviewReceived(Review $review): void
    {
        $review->loadMissing('reviewer', 'reviewee.notificationPreferences');

        if ($review->reviewee) {
            $snippet = trim((string) $review->comment);
            $snippet = mb_substr($snippet, 0, 80);
            $message = ($review->reviewer?->name ?? 'A student') . ' left a ' . number_format((float) $review->rating, 1) . '-star review'
                . ($snippet !== '' ? (': ' . $snippet) : '.');

            $this->createInApp(
                user: $review->reviewee,
                audience: 'teacher',
                type: 'review_received',
                title: 'New Review Received',
                message: $message,
                data: ['review_id' => $review->id, 'booking_id' => $review->booking_id]
            );
        }
    }

    public function sendEarningsReleased(Booking $booking): void
    {
        $booking->loadMissing('teacher.notificationPreferences');

        $this->createInApp(
            user: $booking->teacher,
            audience: 'teacher',
            type: 'earnings_released',
            title: 'Earnings Released',
            message: 'INR ' . number_format((float) $booking->teacher_payout, 2) . ' has been added to your earnings.',
            data: [
                'booking_id' => $booking->id,
                'gross_amount' => (float) $booking->price,
                'net_amount' => (float) $booking->teacher_payout,
            ],
            isCritical: true
        );
    }

    public function sendGroupSessionStarted(User $recipient, string $teacherName, int $conversationId): void
    {
        $this->createInApp(
            user: $recipient,
            audience: $recipient->isTeacher() ? 'teacher' : 'student',
            type: 'group_session_started',
            title: 'Group Session Started',
            message: $teacherName . ' started a group session. Join now.',
            data: ['conversation_id' => $conversationId],
            isCritical: true
        );
    }

    private function mailIfEnabled(?User $user, string $preference, object $mailable): void
    {
        if (! $user?->email || ! $this->preferenceEnabled($user, $preference)) {
            return;
        }

        Mail::to($user->email)->queue($mailable);
    }

    private function preferenceEnabled(User $user, string $preference): bool
    {
        $preferences = $user->notificationPreferences;

        if (! $preferences || ! array_key_exists($preference, $preferences->getAttributes())) {
            return true;
        }

        return (bool) $preferences->{$preference};
    }

    private function createInApp(
        ?User $user,
        string $audience,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $isCritical = false
    ): void {
        if (! $user) {
            return;
        }

        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'audience' => $audience,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_critical' => $isCritical,
        ]);

        broadcast(new InAppNotificationCreated($notification))->toOthers();
    }
}
