<?php

namespace App\Jobs;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Booking $booking
    ) {}

    public function handle(): void
    {
        $this->booking->load('student', 'teacher', 'slot');

        // Email to student
        Mail::to($this->booking->student->email)
            ->send(new BookingConfirmedMail($this->booking, 'student'));

        // Email to teacher
        Mail::to($this->booking->teacher->email)
            ->send(new BookingConfirmedMail($this->booking, 'teacher'));
    }
}
