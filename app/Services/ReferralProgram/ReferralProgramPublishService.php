<?php

namespace App\Services\ReferralProgram;

use App\Models\TenantCustomField;
use App\Models\TenantImportTemplate;
use App\Models\TenantReferralProgramDraft;
use App\Models\TenantReferralProgramVersion;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publishes a Setup Wizard draft: re-runs the simulation as a final gate,
 * then syncs the relevant config slices into the existing runtime tables
 * (tenant_program_configs, tenant_pipeline_stages, tenant_custom_fields,
 * tenant_import_templates), writes an append-only version snapshot, and
 * marks the draft published.
 *
 * For protected tenants (ProtectedTenants::isProtected()), the pipeline,
 * rewards, and import sections are always skipped — independently of what
 * updateStep() allowed into the draft config — so a crafted payload can
 * never overwrite a protected tenant's locked pricing/pipeline/import setup.
 */
class ReferralProgramPublishService
{
    public function __construct(
        private ReferralProgramSimulationService $simulator,
    ) {}

    /**
     * @return array{success: bool, scenarios: array, version_id?: string}
     */
    public function publish(TenantReferralProgramDraft $draft, string $tenantId, ?string $userId): array
    {
        $simulation = $this->simulator->simulate($draft, $tenantId);

        if (!$simulation['can_publish']) {
            return ['success' => false, 'scenarios' => $simulation['scenarios']];
        }

        $config    = $draft->config ?? [];
        $protected = ProtectedTenants::isProtected($tenantId);
        $locked    = ProtectedTenants::lockedConfigSteps();

        $skipPipeline = $protected && in_array('pipeline', $locked, true);
        $skipRewards  = $protected && in_array('rewards', $locked, true);
        $skipImport   = $protected && in_array('import', $locked, true);

        $version = DB::transaction(function () use ($draft, $config, $tenantId, $userId, $skipPipeline, $skipRewards, $skipImport) {
            $this->syncProgramConfig($tenantId, $config, $skipRewards);

            if (!$skipPipeline) {
                $this->syncPipelineStages($tenantId, $config);
            }

            $this->syncCustomFields($tenantId, $config, $userId);

            if (!$skipImport) {
                $this->syncImportTemplate($tenantId, $config, $userId);
            }

            $version = TenantReferralProgramVersion::create([
                'tenant_id'    => $tenantId,
                'draft_id'     => $draft->id,
                'config'       => $config,
                'published_at' => now(),
                'created_by'   => $userId,
            ]);

            $draft->status       = 'published';
            $draft->published_at = now();
            $draft->save();

            return $version;
        });

        if (!$skipPipeline) {
            Cache::forget("stage_limits_{$tenantId}");
        }

        app(ReferralProgramAuditService::class)->log($tenantId, 'published', $userId, [
            'draft_id'   => $draft->id,
            'version_id' => $version->id,
        ]);

        return ['success' => true, 'version_id' => $version->id, 'scenarios' => $simulation['scenarios']];
    }

    private function syncProgramConfig(string $tenantId, array $config, bool $skipRewards): void
    {
        $values = [
            'industry'            => $config['industry_goal']['industry'] ?? null,
            'sub_industries'      => json_encode($config['industry_goal']['sub_industries'] ?? []),
            'template_applied'    => $config['program_type']['program_type'] ?? null,
            'onboarding_complete' => true,
            'updated_at'          => now(),
        ];

        if (!$skipRewards && !empty($config['rewards'])) {
            $rewards = $config['rewards'];
            $values['commission_type']     = $rewards['commission_type'] ?? null;
            $values['company_share_pct']   = $rewards['company_share_pct'] ?? null;
            $values['referrer_share_pct']  = $rewards['referrer_share_pct'] ?? null;
            $values['default_expiry_days'] = $rewards['default_expiry_days'] ?? null;
            $values['reassignment_mode']   = $rewards['reassignment_mode'] ?? null;
        }

        // Drop nulls so an update never clobbers an existing column with NULL,
        // and an insert falls back to the table's column defaults instead.
        $values = array_filter($values, fn ($v) => $v !== null);

        DB::table('tenant_program_configs')->updateOrInsert(['tenant_id' => $tenantId], $values);
    }

    private function syncPipelineStages(string $tenantId, array $config): void
    {
        $stages = $config['pipeline']['stages'] ?? [];
        if (empty($stages)) {
            return;
        }

        DB::table('tenant_pipeline_stages')->where('tenant_id', $tenantId)->delete();

        $rows = [];
        foreach (array_values($stages) as $position => $stage) {
            $rows[] = [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'stage_key'  => $stage['stage_key'],
                'name'       => $stage['name'],
                'position'   => $position,
                'days_limit' => $stage['days'] ?? null,
                'color'      => $stage['color'] ?? '#9CA3AF',
                'is_final'   => !empty($stage['is_final']),
                'is_won'     => !empty($stage['is_won']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('tenant_pipeline_stages')->insert($rows);
    }

    /**
     * Creates/updates tenant_custom_fields for each field in config.fields,
     * and archives (is_visible=false) fields that were part of the previously
     * published config but were removed — never deletes rows with data.
     */
    private function syncCustomFields(string $tenantId, array $config, ?string $userId): void
    {
        $fields  = $config['fields']['fields'] ?? [];
        $newKeys = array_map(fn ($f) => $f['field_key'], $fields);

        foreach ($fields as $field) {
            TenantCustomField::updateOrCreate(
                ['tenant_id' => $tenantId, 'destination_type' => 'deals', 'field_key' => $field['field_key']],
                [
                    'field_label'        => $field['field_label'],
                    'data_type'          => $field['data_type'],
                    'is_required'        => !empty($field['is_required']),
                    'is_visible'         => true,
                    'is_importable'      => true,
                    'is_exportable'      => true,
                    'created_by_user_id' => $userId,
                ]
            );
        }

        $previousVersion = TenantReferralProgramVersion::where('tenant_id', $tenantId)
            ->latest('published_at')
            ->first();

        if ($previousVersion) {
            $previousKeys = array_filter(array_map(
                fn ($f) => $f['field_key'] ?? null,
                $previousVersion->config['fields']['fields'] ?? []
            ));
            $removedKeys = array_diff($previousKeys, $newKeys);

            if (!empty($removedKeys)) {
                TenantCustomField::where('tenant_id', $tenantId)
                    ->where('destination_type', 'deals')
                    ->whereIn('field_key', $removedKeys)
                    ->update(['is_visible' => false]);
            }
        }
    }

    /**
     * Creates a new default tenant_import_templates row from the generic
     * template selected on Step 12, archiving the previous default.
     */
    private function syncImportTemplate(string $tenantId, array $config, ?string $userId): void
    {
        $templateKey = $config['import']['template_key'] ?? null;
        $templates   = config('referralbunny_import_templates', []);

        if (!$templateKey || !isset($templates[$templateKey])) {
            return;
        }

        $tpl = $templates[$templateKey];

        TenantImportTemplate::archivePreviousDefault($tenantId, 'deals');

        $nextVersion = (TenantImportTemplate::where('tenant_id', $tenantId)
            ->where('destination_type', 'deals')
            ->max('version_number') ?? 0) + 1;

        TenantImportTemplate::create([
            'tenant_id'             => $tenantId,
            'destination_type'      => 'deals',
            'template_name'         => $tpl['label'] ?? $templateKey,
            'template_key'          => "referral_program_{$tenantId}_deals_v{$nextVersion}",
            'industry_key'          => $templateKey,
            'is_default'            => true,
            'is_locked'             => false,
            'fields_json'           => array_values(array_merge($tpl['required_fields'] ?? [], $tpl['optional_fields'] ?? [])),
            'required_fields_json'  => $tpl['required_fields'] ?? [],
            'aliases_json'          => $tpl['aliases'] ?? [],
            'sample_headers_json'   => $tpl['sample_row'] ?? [],
            'version_number'        => $nextVersion,
            'status'                => 'active',
            'created_by_user_id'    => $userId,
        ]);
    }
}
