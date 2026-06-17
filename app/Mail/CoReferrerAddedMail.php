<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoReferrerAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $dealName,
        public string $primaryName,
        public float  $percentage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been added as a co-referrer on \"{$this->dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.co-referrer-added');
    }
}
