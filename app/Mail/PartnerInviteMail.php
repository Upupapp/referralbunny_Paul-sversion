<?php

namespace App\Mail;

use App\Models\Partner;
use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerInviteMail extends Mailable
{
    // Not queued — sends synchronously so no queue worker is required.
    use SerializesModels;

    public function __construct(
        public readonly Partner   $partner,
        public readonly Tenant    $tenant,
        public readonly ?Reseller $invitedBy = null,
    ) {}

    public function build(): static
    {
        $setupUrl    = url('/partner/invite/' . $this->partner->setup_token);
        $inviterName = $this->invitedBy?->name ?? 'A referrer';

        return $this
            ->subject("You're invited as a Partner — {$this->tenant->name}")
            ->view('mail.partner-invite')
            ->with([
                'partner'     => $this->partner,
                'tenant'      => $this->tenant,
                'invitedBy'   => $this->invitedBy,
                'inviterName' => $inviterName,
                'setupUrl'    => $setupUrl,
            ]);
    }
}
