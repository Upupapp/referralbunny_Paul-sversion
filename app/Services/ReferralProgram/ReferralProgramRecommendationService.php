<?php

namespace App\Services\ReferralProgram;

use App\Models\TenantReferralProgramTemplate;

/**
 * Looks up the recommended program template for a given industry, used by
 * Quick Setup (Step 2 -> Step 3 recommendation badge) and "Set It Up for Me".
 * Falls back to the 'default' template when the industry has no specific match.
 */
class ReferralProgramRecommendationService
{
    public function recommendForIndustry(?string $industryKey): array
    {
        $template = null;

        if ($industryKey) {
            $template = TenantReferralProgramTemplate::find($industryKey);
        }

        $template ??= TenantReferralProgramTemplate::find('default');

        return [
            'template_id'  => $template?->id ?? 'default',
            'name'         => $template?->name,
            'description'  => $template?->description,
            'program_type' => $template?->config['program_type'] ?? 'referrer_program',
        ];
    }
}
