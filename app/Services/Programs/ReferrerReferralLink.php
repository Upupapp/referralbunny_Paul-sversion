<?php

namespace App\Services\Programs;

use App\Models\{Program, ProgramConnection, ReferrerProgramMembership};
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use GuzzleHttp\Psr7\Uri;

class ReferrerReferralLink
{
    public function forMembership(Program $program, ?ReferrerProgramMembership $membership): ?string
    {
        if (!$this->destination($program, $membership)) return null;

        // Separate aliases keep existing enrollment codes and tracking integrations unchanged.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = DB::table('program_referral_links')->where('membership_id', $membership->id)->value('code');
            if ($code) return route('referral.short', ['code' => $code]);
            DB::table('program_referral_links')->insertOrIgnore([
                'code' => Str::lower(Str::random(8)),
                'membership_id' => $membership->id,
                'created_at' => now(),
            ]);
        }
        throw new \RuntimeException('Unable to allocate referral link.');
    }

    public function destination(Program $program, ?ReferrerProgramMembership $membership): ?string
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
