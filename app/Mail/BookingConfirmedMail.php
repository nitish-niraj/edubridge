<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string  $recipientType = 'student' // 'student' or 'teacher'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Session Confirmed — ' . ($this->booking->subject ?? 'EduBridge Session'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-confirmed',
        );
    }
}
