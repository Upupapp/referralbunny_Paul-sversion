<?php

namespace App\Mail;

use App\Models\DealAssignmentExtensionRequest;
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
        public string $dealName,
        public string $dealId,
        public string $dealTenantId,
        public string $decision,
        public string $resellerName,
        public string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->decision) {
            'approved'                => "Extension approved — \"{$this->dealName}\"",
            'rejected'                => "Extension request rejected — \"{$this->dealName}\"",
            'clarification_requested' => "Clarification needed on your extension request — \"{$this->dealName}\"",
            default                   => "Extension request update — \"{$this->dealName}\"",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deal-extension-decision');
    }
}
