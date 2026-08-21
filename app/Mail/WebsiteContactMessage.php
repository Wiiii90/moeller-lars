<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class WebsiteContactMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $visitorName,
        public readonly string $visitorEmail,
        public readonly string $messageBody,
        public readonly string $senderAddress,
        public readonly string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->senderAddress, $this->senderName),
            replyTo: [new Address($this->visitorEmail, $this->visitorName)],
            subject: 'Website contact · Lars Möller',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.website-contact');
    }
}
