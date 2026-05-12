<?php

namespace App\Mail;

use App\Models\EmailDigest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly EmailDigest $digest) {}

    public function envelope(): Envelope
    {
        $count  = count($this->digest->items ?? []);
        $label  = $this->digest->topic_label ?? ucwords(str_replace('_', ' ', $this->digest->topic));
        $subject = $count === 1
            ? "1 {$label} update — ReferralBunny.ai"
            : "{$count} {$label} updates — ReferralBunny.ai";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }

    public function attachments(): array
    {
        return [];
    }
}
