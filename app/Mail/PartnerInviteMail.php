<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $partnerFirstName,
        public string  $tenantName,
        public string  $inviterName,
        public string  $setupUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject("You're invited as a Partner — {$this->tenantName}")
            ->view('mail.partner-invite')
            ->with([
                'partnerFirstName' => $this->partnerFirstName,
                'tenantName'       => $this->tenantName,
                'inviterName'      => $this->inviterName,
                'setupUrl'         => $this->setupUrl,
            ]);
    }
}
