<?php

namespace App\Services;

use App\Jobs\SendCancellationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected PhonePeService $phonePeService
    ) {}

    /**
     * Cancel a booking and handle refunds per cancellation policy.
     *
     * Policy:
     * - Teacher cancels → full refund always
     * - Student cancels >2 hours before → full refund
     * - Student cancels <2 hours before → no refund
     */
    public function cancelBooking(Booking $booking, User $cancelledBy): array
    {
        $hoursUntilSession = now()->diffInHours($booking->start_at, false);
        $isTeacher  = $cancelledBy->id === $booking->teacher_id;
        $isStudent  = $cancelledBy->id === $booking->student_id;
        $refundAmount = 0;

        if ($isTeacher) {
            $refundAmount = $booking->price; // Teacher always full refund
        } elseif ($isStudent && $hoursUntilSession > 2) {
            $refundAmount = $booking->price; // Student early cancel — full refund
        }
        // Student late cancel → no refund

        DB::transaction(function () use ($booking, $refundAmount) {
            $booking->update(['status' => 'cancelled']);
            BookingSlot::where('id', $booking->slot_id)
                ->update(['is_booked' => false, 'booking_id' => null]);

            if ($refundAmount > 0 && $booking->payment && $booking->payment->status === 'held') {
                $refundOrderId = 'REFUND-' . $booking->id . '-' . time();
                $this->phonePeService->initiateRefund(
                    $refundOrderId,
                    $booking->payment->merchant_order_id,
                    (int) ($refundAmount * 100)
                );
                $booking->payment->update(['status' => 'refunded']);
                $booking->update(['payment_status' => 'refunded']);
            }
        });

        dispatch(new SendCancellationNotification($booking, $refundAmount));

        return ['refund_amount' => $refundAmount, 'refunded' => $refundAmount > 0];
    }
}
