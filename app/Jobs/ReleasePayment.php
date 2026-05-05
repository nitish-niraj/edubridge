<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TeacherEarning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleasePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public int $bookingId
    ) {}

    public function handle(): void
    {
        $booking = Booking::with('payment', 'teacher')->find($this->bookingId);

        if (! $booking || $booking->payment_status !== 'held' || $booking->payment?->status !== Payment::STATUS_HELD) {
            return;
        }

        DB::transaction(function () use ($booking) {
            $payment = $booking->payment()->lockForUpdate()->first();

            if (! $payment || $payment->status !== Payment::STATUS_HELD) {
                return;
            }

            $payment->transitionTo(Payment::STATUS_RELEASED, [
                'released_at' => now(),
            ]);

            $booking->update(['payment_status' => 'released']);

            TeacherEarning::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'teacher_id'   => $booking->teacher_id,
                    'booking_id'   => $booking->id,
                    'gross_amount' => $booking->price,
                    'platform_fee' => $booking->platform_fee,
                    'net_amount'   => $booking->teacher_payout,
                    'status'       => 'released',
                    'payout_date'  => now()->toDateString(),
                ]
            );
        });

        // TODO: Notify teacher about earnings credit
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ReleasePayment job failed.', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
