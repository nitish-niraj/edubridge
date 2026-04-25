<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionReminderOneHourMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string  $recipientType = 'student'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your session starts in 1 hour — EduBridge',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.session-reminder-one-hour',
        );
    }
}
