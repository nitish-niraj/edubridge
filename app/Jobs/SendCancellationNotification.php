<?php

namespace App\Jobs;

use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCancellationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Booking $booking,
        public float   $refundAmount = 0
    ) {}

    public function handle(): void
    {
        $this->booking->load('student', 'teacher');

        // Email to student
        Mail::to($this->booking->student->email)
            ->send(new BookingCancelledMail($this->booking, $this->refundAmount, 'student'));

        // Email to teacher
        Mail::to($this->booking->teacher->email)
            ->send(new BookingCancelledMail($this->booking, $this->refundAmount, 'teacher'));
    }
}
