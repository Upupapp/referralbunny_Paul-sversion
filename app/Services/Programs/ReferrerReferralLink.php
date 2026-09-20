<?php

namespace App\Services\Programs;

use App\Models\{Program, ProgramConnection, ReferrerProgramMembership};
use App\Support\ProtectedTenants;
use GuzzleHttp\Psr7\Uri;

class ReferrerReferralLink
{
    public function forMembership(Program $program, ?ReferrerProgramMembership $membership): ?string
    {
        if (ProtectedTenants::isProtected($program->tenant_id)
            || $program->status !== 'active' || $program->effectiveOperatingMode() !== 'automated'
            || !$membership || $membership->status !== 'active'
            || $membership->tenant_id !== $program->tenant_id || $membership->program_id !== $program->id) {
            return null;
        }

        $website = ProgramConnection::where('tenant_id', $program->tenant_id)
            ->where('program_id', $program->id)->value('website');
        if (!$website || !filter_var($website, FILTER_VALIDATE_URL)) {
            return null;
        }
        $uri = new Uri($website);
        if (!in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getUserInfo() !== '') {
            return null;
        }

        // Both the website snippet and GetHired validate the enrollment ID, not referral_code.
        return (string) Uri::withQueryValues($uri, [
            'rb_program' => $program->id,
            'rb_ref' => $membership->id,
        ]);
    }
}
