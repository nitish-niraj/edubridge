<?php

namespace App\Services;

use App\Jobs\SendCancellationNotification;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected PaymentGatewayService $paymentGateway
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
        } elseif ($isStudent) {
            $refundAmount = $hoursUntilSession > 2 ? $booking->price : 0;
        }

        DB::transaction(function () use ($booking, $refundAmount) {
            $booking->loadMissing('payment');

            $booking->update([
                'status' => 'cancelled',
                'payment_status' => $refundAmount > 0 && $booking->payment?->status === Payment::STATUS_HELD
                    ? 'refunded'
                    : $booking->payment_status,
            ]);

            BookingSlot::where('id', $booking->slot_id)
                ->update(['is_booked' => false, 'booking_id' => null]);

            if ($refundAmount > 0 && $booking->payment && $booking->payment->status === Payment::STATUS_HELD) {
                $refundResponse = $this->paymentGateway->refund($booking->payment);
                $booking->payment->transitionTo(Payment::STATUS_REFUNDED, [
                    'raw_response' => array_merge($booking->payment->raw_response ?? [], [
                        'refund' => $refundResponse,
                    ]),
                ]);
            }
        });

        dispatch(new SendCancellationNotification($booking, $refundAmount));

        return ['refund_amount' => $refundAmount, 'refunded' => $refundAmount > 0];
    }
}
