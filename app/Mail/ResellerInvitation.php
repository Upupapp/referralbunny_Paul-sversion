<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $setupUrl,
        public ?string $dealName  = null, // legacy single-deal (kept for backwards compat)
        public int     $dealCount = 0,    // total assigned deals
        public array   $dealNames = [],   // up to 3 deal names for summary
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->dealCount > 1
            ? "You're invited as a Referrer for {$this->tenantName} — {$this->dealCount} deal(s) assigned"
            : "You've been invited as a referrer for {$this->tenantName}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        // Resolve a single dealName for the legacy template section
        $singleDealName = $this->dealName
            ?? ($this->dealCount === 1 && !empty($this->dealNames) ? $this->dealNames[0] : null);

        return new Content(
            view: 'emails.reseller-invitation',
            with: [
                'resellerName'  => $this->resellerName,
                'tenantName'    => $this->tenantName,
                'setupUrl'      => $this->setupUrl,
                'dealName'      => $singleDealName,
                'dealCount'     => $this->dealCount,
                'dealNames'     => $this->dealNames,
            ],
        );
    }
}
