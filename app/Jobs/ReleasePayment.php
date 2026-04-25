<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\TeacherEarning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ReleasePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $bookingId
    ) {}

    public function handle(): void
    {
        $booking = Booking::with('payment', 'teacher')->find($this->bookingId);

        if (! $booking || $booking->payment_status !== 'held') {
            return;
        }

        DB::transaction(function () use ($booking) {
            $booking->payment->update([
                'status'      => 'released',
                'released_at' => now(),
            ]);

            $booking->update(['payment_status' => 'released']);

            TeacherEarning::create([
                'teacher_id'   => $booking->teacher_id,
                'payment_id'   => $booking->payment->id,
                'booking_id'   => $booking->id,
                'gross_amount' => $booking->price,
                'platform_fee' => $booking->platform_fee,
                'net_amount'   => $booking->teacher_payout,
                'status'       => 'released',
                'payout_date'  => now()->toDateString(),
            ]);
        });

        // TODO: Notify teacher about earnings credit
    }
}
