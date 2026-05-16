<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LguIdsPendingTasksDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public array  $tasks,       // [{title, priority, status, due_at, due_label, assigned_to, url}]
        public string $tenantName,
        public string $tasksUrl,
        public string $date,
        public int    $totalCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->date}] Daily Pending Tasks — {$this->tenantName} ({$this->totalCount} task" . ($this->totalCount !== 1 ? 's' : '') . ')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lgu-ids-pending-tasks-digest');
    }
}
