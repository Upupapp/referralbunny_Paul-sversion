<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PipelineStageWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
        public string $dealName,
        public string $stage,
        public int    $daysLeft,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        $urgency = $this->daysLeft <= 1 ? 'URGENT: ' : '';
        return new Envelope(
            to:      [$this->resellerEmail => $this->resellerName],
            subject: "{$urgency}\"{$this->dealName}\" needs your attention — {$this->daysLeft}d left",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pipeline-stage-warning',
            with: [
                'resellerName' => $this->resellerName,
                'tenantName'   => $this->tenantName,
                'dealName'     => $this->dealName,
                'stage'        => $this->stage,
                'daysLeft'     => $this->daysLeft,
                'loginUrl'     => $this->loginUrl,
            ],
        );
    }
}
