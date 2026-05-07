<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NoShowNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $recipientRole = 'student'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Session Marked as No-show'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.no-show',
            with: [
                'booking' => $this->booking->loadMissing('student', 'teacher'),
                'recipientRole' => $this->recipientRole,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
