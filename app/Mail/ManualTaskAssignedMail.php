<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManualTaskAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $assigneeName,
        public readonly string  $senderName,
        public readonly string  $taskTitle,
        public readonly string  $taskPriority,
        public readonly ?string $dueAt,
        public readonly string  $taskUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Task assigned to you: {$this->taskTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.manual-task-assigned');
    }
}
