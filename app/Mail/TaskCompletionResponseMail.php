<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskCompletionResponseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $recipientName,
        public readonly string  $senderName,
        public readonly string  $emailSubject,
        public readonly string  $bodyPlain,
        public readonly string  $bodyHtml,
        public readonly array   $fileAttachments,
        public readonly string  $taskTitle,
        public readonly string  $tenantId,
        // Legacy compat — prefer bodyHtml/bodyPlain
        public readonly string  $body = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-completion-response');
    }

    public function attachments(): array
    {
        $out = [];
        foreach ($this->fileAttachments as $att) {
            try {
                if (isset($att['path']) && \Illuminate\Support\Facades\Storage::disk($att['disk'] ?? 'local')->exists($att['path'])) {
                    $out[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                        $att['disk'] ?? 'local',
                        $att['path']
                    )->as($att['filename'] ?? basename($att['path']))
                     ->withMime($att['mime'] ?? 'application/octet-stream');
                }
            } catch (\Throwable) {}
        }
        return $out;
    }
}
