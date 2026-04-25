<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public float   $refundAmount = 0,
        public string  $recipientType = 'student'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Session Cancelled — EduBridge',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-cancelled',
        );
    }
}
