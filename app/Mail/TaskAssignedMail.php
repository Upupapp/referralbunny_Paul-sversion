<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $recipientName,
        public readonly string  $submitterName,
        public readonly string  $requestFor,
        public readonly string  $formTitle,
        public readonly ?string $notes,
        public readonly string  $tenantId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New request assigned to you: {$this->requestFor}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-assigned',
        );
    }
}
