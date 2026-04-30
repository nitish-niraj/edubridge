<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DisputeResolvedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $resolution,
        public float $refundAmount = 0,
        public string $recipientRole = 'student',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'EduBridge dispute update');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.dispute-resolved');
    }
}
