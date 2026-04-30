<?php

namespace App\Services;

use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\NewMessageMail;
use App\Mail\ReviewReminderMail;
use App\Mail\SessionCompletedMail;
use App\Mail\SessionReminderMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class NotificationService
{
    public function sendBookingConfirmed(Booking $booking): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $this->mailIfEnabled($booking->student, 'booking_confirmed_email', new BookingConfirmedMail($booking, 'student'));
        $this->mailIfEnabled($booking->teacher, 'booking_confirmed_email', new BookingConfirmedMail($booking, 'teacher'));

        $message = 'EduBridge: Your session on ' . $booking->start_at->format('M j, g:i A') . ' is confirmed.';
        $this->smsIfEnabled($booking->student, 'booking_confirmed_sms', $message);
        $this->smsIfEnabled($booking->teacher, 'booking_confirmed_sms', $message);
    }

    public function sendBookingCancelled(Booking $booking, float $refundAmount = 0): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $this->mailIfEnabled($booking->student, 'booking_cancelled_email', new BookingCancelledMail($booking, $refundAmount, 'student'));
        $this->mailIfEnabled($booking->teacher, 'booking_cancelled_email', new BookingCancelledMail($booking, $refundAmount, 'teacher'));
    }

    public function sendSessionReminder(Booking $booking, int $minutesBefore): void
    {
        $booking->loadMissing('student.notificationPreferences', 'teacher.notificationPreferences');

        $cacheKey = "notifications:session-reminder:{$booking->id}:{$minutesBefore}";
        if (! Cache::add($cacheKey, now()->timestamp, now()->addDays(2))) {
            return;
        }

        $this->mailIfEnabled($booking->student, 'session_reminder_email', new SessionReminderMail($booking, 'student', $minutesBefore));
        $this->mailIfEnabled($booking->teacher, 'session_reminder_email', new SessionReminderMail($booking, 'teacher', $minutesBefore));

        if ($minutesBefore === 15) {
            $message = 'EduBridge: Your session starts in 15 minutes. Join from your sessions page.';
            $this->smsIfEnabled($booking->student, 'session_reminder_sms', $message);
            $this->smsIfEnabled($booking->teacher, 'session_reminder_sms', $message);
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

    public function sendNewMessage(User $recipient, string $senderName, ?string $senderAvatar, string $preview, int $conversationId): void
    {
        $this->mailIfEnabled($recipient, 'new_message_email', new NewMessageMail(
            senderName: $senderName,
            senderAvatar: $senderAvatar,
            preview: $preview,
            conversationId: $conversationId
        ));
    }

    private function mailIfEnabled(?User $user, string $preference, object $mailable): void
    {
        if (! $user?->email || ! $this->preferenceEnabled($user, $preference)) {
            return;
        }

        Mail::to($user->email)->send($mailable);
    }

    private function smsIfEnabled(?User $user, string $preference, string $message): void
    {
        if (! $user?->phone || ! $this->preferenceEnabled($user, $preference)) {
            return;
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.sms_from');

        if (! $sid || ! $token || ! $from) {
            return;
        }

        (new Client($sid, $token))->messages->create($user->phone, [
            'from' => $from,
            'body' => $message,
        ]);
    }

    private function preferenceEnabled(User $user, string $preference): bool
    {
        $preferences = $user->notificationPreferences;

        if (! $preferences || ! array_key_exists($preference, $preferences->getAttributes())) {
            return true;
        }

        return (bool) $preferences->{$preference};
    }
}
