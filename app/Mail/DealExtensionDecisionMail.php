<?php

namespace App\Mail;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DealExtensionDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DealAssignmentExtensionRequest $extensionRequest,
        public Lead $deal,
        public string $decision,
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        $dealName = $this->deal->name;
        $subject  = match ($this->decision) {
            'approved'                => "Extension approved — \"{$dealName}\"",
            'rejected'                => "Extension request rejected — \"{$dealName}\"",
            'clarification_requested' => "Clarification needed on your extension request — \"{$dealName}\"",
            default                   => "Extension request update — \"{$dealName}\"",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deal-extension-decision');
    }
}
