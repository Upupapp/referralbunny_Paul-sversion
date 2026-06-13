<?php

namespace App\Services\ReferralProgram;

use App\Models\TenantReferralProgramDraft;
use App\Models\TenantReferralProgramVersion;

/**
 * Core read/write operations for the Referral Program Setup Wizard draft.
 * The full 15-step wizard config is stored as one JSON document on the draft;
 * each step writes its own slice (snake_case of the step key) into that document.
 */
class ReferralProgramSetupService
{
    /**
     * The 15 wizard steps, in order. Slugs match the URL/step query param.
     */
    public const STEPS = [
        'program-basics',
        'industry-goal',
        'program-type',
        'participants',
        'pipeline',
        'fields',
        'rewards',
        'partner-split',
        'documents',
        'approvals',
        'forms',
        'import',
        'notifications',
        'dashboard',
        'review',
    ];

    /**
     * Steps with a working UI in this phase. Remaining steps render as
     * "coming soon" in the stepper and reject PATCH requests.
     */
    public const IMPLEMENTED_STEPS = [
        'program-basics',
        'industry-goal',
        'program-type',
        'participants',
        'pipeline',
        'fields',
        'rewards',
        'partner-split',
        'documents',
        'approvals',
        'forms',
        'import',
        'notifications',
        'dashboard',
        'review',
    ];

    /**
     * Display labels for the stepper, in the same order as STEPS.
     */
    public const STEP_LABELS = [
        'program-basics' => 'Program Basics',
        'industry-goal'  => 'Industry & Goal',
        'program-type'   => 'Program Type',
        'participants'   => 'Participants',
        'pipeline'       => 'Pipeline & Journey',
        'fields'         => 'Deal Fields',
        'rewards'        => 'Rewards & Commission',
        'partner-split'  => 'Partner Split',
        'documents'      => 'Documents & Agreements',
        'approvals'      => 'Approval Workflows',
        'forms'          => 'Forms, Links & QR',
        'import'         => 'Import Templates',
        'notifications'  => 'Notifications',
        'dashboard'      => 'Dashboard Metrics',
        'review'         => 'Preview & Publish',
    ];

    public function getOrCreateActiveDraft(string $tenantId, ?string $userId, string $mode = 'quick'): TenantReferralProgramDraft
    {
        $draft = TenantReferralProgramDraft::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();

        if ($draft) {
            return $draft;
        }

        // If this tenant has published before, start the new draft from their
        // last published config rather than blank, so "Edit Setup" after a
        // publish continues from the live settings instead of from scratch.
        $lastVersion = TenantReferralProgramVersion::where('tenant_id', $tenantId)
            ->latest('published_at')
            ->first();

        return TenantReferralProgramDraft::create([
            'tenant_id'    => $tenantId,
            'status'       => 'draft',
            'mode'         => $mode,
            'config'       => $lastVersion?->config ?? [],
            'current_step' => self::STEPS[0],
            'created_by'   => $userId,
        ]);
    }

    public function updateStep(TenantReferralProgramDraft $draft, string $step, array $data): TenantReferralProgramDraft
    {
        $config = $draft->config ?? [];
        $config[self::configKey($step)] = $data;

        $draft->config = $config;
        $draft->current_step = $step;
        $draft->save();

        return $draft;
    }

    /**
     * Completion summary used for the overview "health score" and the
     * wizard sidebar progress list.
     */
    public function healthScore(TenantReferralProgramDraft $draft): array
    {
        $config = $draft->config ?? [];
        $completedSteps = [];

        foreach (self::STEPS as $step) {
            // "Preview & Publish" has no config of its own — it's complete once
            // this draft has actually been published, not based on config shape.
            if ($step === 'review') {
                if ($draft->status === 'published') {
                    $completedSteps[] = $step;
                }
                continue;
            }

            if (!empty($config[self::configKey($step)])) {
                $completedSteps[] = $step;
            }
        }

        $total = count(self::STEPS);
        $done  = count($completedSteps);

        return [
            'completed_steps' => $completedSteps,
            'total_steps'     => $total,
            'done_steps'      => $done,
            'score'           => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    public static function configKey(string $step): string
    {
        return str_replace('-', '_', $step);
    }
}
