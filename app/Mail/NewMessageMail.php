<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly ?string $senderAvatar,
        public readonly string $preview,
        public readonly int $conversationId
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New message from {$this->senderName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-message',
        );
    }
}
