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
     * - Teacher cancels ≥1 hour before start → full refund to student.
     * - Teacher cancels <1 hour before start → no refund to student (teacher pays penalty).
     * - Student cancels ≥2 hours before start → full refund.
     * - Student cancels <2 hours before start → no refund.
     */
    public function cancelBooking(Booking $booking, User $cancelledBy): array
    {
        $minutesUntilSession = now()->diffInMinutes($booking->start_at, false);
        $isTeacher  = $cancelledBy->id === $booking->teacher_id;
        $isStudent  = $cancelledBy->id === $booking->student_id;
        $refundAmount = 0;
        $refunded = false;

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            abort(422, 'Booking cannot be cancelled from its current state.');
        }

        if ($isTeacher) {
            $refundAmount = $minutesUntilSession > 60 ? (float) $booking->price : 0;
        } elseif ($isStudent) {
            $refundAmount = $minutesUntilSession > 120 ? (float) $booking->price : 0;
        }

        try {
            DB::transaction(function () use ($booking, $refundAmount, &$refunded) {
                $booking->loadMissing('payment');

                $booking->transitionTo(Booking::STATUS_CANCELLED, [
                    'payment_status' => $refundAmount > 0 && $booking->payment?->status === Payment::STATUS_HELD
                        ? 'refunded'
                        : $booking->payment_status,
                ]);

                BookingSlot::where('id', $booking->slot_id)
                    ->update(['is_booked' => false, 'booking_id' => null]);

                if ($refundAmount > 0 && $booking->payment && $booking->payment->status === Payment::STATUS_HELD) {
                    try {
                        $refundResponse = $this->paymentGateway->refund($booking->payment);
                        $booking->payment->transitionTo(Payment::STATUS_REFUNDED, [
                            'raw_response' => array_merge($booking->payment->raw_response ?? [], [
                                'refund' => $refundResponse,
                            ]),
                        ]);
                        $refunded = true;
                    } catch (\Exception $e) {
                        \Log::error('Refund failed during cancellation', [
                            'booking_id' => $booking->id,
                            'payment_id' => $booking->payment->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with cancellation even if refund fails
                    }
                }
            });

            dispatch(new SendCancellationNotification($booking, $refundAmount));

            return ['refund_amount' => $refundAmount, 'refunded' => $refunded];
        } catch (\Exception $e) {
            \Log::error('Booking cancellation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
