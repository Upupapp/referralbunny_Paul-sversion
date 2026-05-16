<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LguIdsTaskDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $adminName,
        public string  $taskTitle,
        public ?string $taskDescription,
        public string  $taskPriority,
        public string  $taskStatus,
        public string  $dueLabel,
        public ?string $assignedTo,
        public string  $taskUrl,
        public string  $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⏰ Task Due Today: {$this->taskTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lgu-ids-task-due-reminder');
    }
}
