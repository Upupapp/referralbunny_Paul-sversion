<?php

namespace App\Console\Commands;

use App\Models\TenantMembership;
use App\Models\TenantReferralProgramDraft;
use App\Models\TenantReferralProgramVersion;
use App\Services\PermissionService;
use App\Services\ReferralProgram\ReferralProgramSetupService;
use App\Support\ProtectedTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DiagnoseOnboardingAndSetupCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-onboarding-and-setup {tenant?} {--repair-safe}';

    protected $description = 'Diagnose Referral Program Setup Wizard state (drafts, published config sync, permissions, LGU IDS locks) for one or all tenants';

    private const ROUTES = [
        'tenant.settings.referral-program.overview',
        'tenant.settings.referral-program.wizard',
        'tenant.settings.referral-program.wizard.step',
        'tenant.settings.referral-program.wizard.discard',
        'tenant.settings.referral-program.wizard.simulate',
        'tenant.settings.referral-program.wizard.publish',
        'tenant.settings.referral-program.versions.restore',
    ];

    private const ROLES = ['owner', 'admin', 'manager', 'member', 'viewer'];

    public function handle(): int
    {
        $tenantId   = $this->argument('tenant');
        $repairSafe = (bool) $this->option('repair-safe');

        $this->info('─────────────────────────────────────────────────────');
        $this->info(' Referral Program Setup Wizard — Diagnostics');
        $this->info('─────────────────────────────────────────────────────');

        $this->line(' WIZARD ROUTES');
        foreach (self::ROUTES as $name) {
            $this->line((Route::has($name) ? '  ok      ' : '  MISSING ').$name);
        }
        $this->newLine();

        $this->line(' PERMISSION MATRIX — manage_referral_program_setup');
        $this->printPermissionMatrix();
        $this->newLine();

        if ($tenantId) {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant) {
                $this->error("Tenant '{$tenantId}' not found.");
                return 1;
            }
            $this->diagnoseTenant($tenant, $repairSafe);
        } else {
            $tenants = DB::table('tenants')->where('status', 'active')->orderBy('id')->get();
            $this->line(' Diagnosing '.$tenants->count().' active tenant(s)...');
            $this->newLine();
            foreach ($tenants as $tenant) {
                $this->diagnoseTenant($tenant, $repairSafe);
            }
        }

        $this->newLine();
        $this->line(' Diagnosis complete.');
        return 0;
    }

    private function printPermissionMatrix(): void
    {
        $perm = app(PermissionService::class);

        foreach (self::ROLES as $role) {
            $membership = (new TenantMembership())->forceFill([
                'role'                  => $role,
                'is_custom_permissions' => false,
            ]);

            $allowed = $perm->can($membership, 'manage_referral_program_setup');
            $line    = "  {$role}: ".($allowed ? 'allowed' : 'denied');

            if ($role === 'manager') {
                $override = (new TenantMembership())->forceFill([
                    'role'                  => 'manager',
                    'is_custom_permissions' => true,
                    'permissions_json'      => ['manage_referral_program_setup' => true],
                ]);
                $overrideAllowed = $perm->can($override, 'manage_referral_program_setup');
                $line .= ' (with is_custom_permissions override: '.($overrideAllowed ? 'allowed' : 'denied').')';
            }

            $this->line($line);
        }
    }

    private function diagnoseTenant(object $tenant, bool $repairSafe): void
    {
        $tenantId  = $tenant->id;
        $protected = ProtectedTenants::isProtected($tenantId);

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" {$tenant->name} ({$tenantId})".($protected ? ' [PROTECTED]' : ''));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $setup = app(ReferralProgramSetupService::class);
        $draft = TenantReferralProgramDraft::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();

        if ($draft) {
            $health = $setup->healthScore($draft);
            $this->line(" Active draft      : {$draft->id} (mode={$draft->mode}, step={$draft->current_step})");
            $this->line(" Setup health      : {$health['score']}% ({$health['done_steps']}/{$health['total_steps']} steps)");
        } else {
            $this->line(' Active draft      : none');
        }

        $versionCount  = TenantReferralProgramVersion::where('tenant_id', $tenantId)->count();
        $latestVersion = TenantReferralProgramVersion::where('tenant_id', $tenantId)->latest('published_at')->first();
        $this->line(" Published versions: {$versionCount}".($latestVersion ? " (latest: {$latestVersion->published_at})" : ''));

        $config = DB::table('tenant_program_configs')->where('tenant_id', $tenantId)->first();
        if ($config) {
            $onboarding = $config->onboarding_complete ? 'yes' : 'no';
            $this->line(" tenant_program_configs: industry={$config->industry}, commission={$config->commission_type}, split={$config->company_share_pct}/{$config->referrer_share_pct}, onboarding_complete={$onboarding}");
        } else {
            $this->line(' tenant_program_configs: MISSING');
        }

        $stageCount = DB::table('tenant_pipeline_stages')->where('tenant_id', $tenantId)->count();
        $this->line(" tenant_pipeline_stages: {$stageCount} stage(s)");

        $fieldCount        = DB::table('tenant_custom_fields')->where('tenant_id', $tenantId)->where('destination_type', 'deals')->count();
        $visibleFieldCount = DB::table('tenant_custom_fields')->where('tenant_id', $tenantId)->where('destination_type', 'deals')->where('is_visible', true)->count();
        $this->line(" tenant_custom_fields  : {$fieldCount} total ({$visibleFieldCount} visible)");

        $importDefault = DB::table('tenant_import_templates')->where('tenant_id', $tenantId)->where('destination_type', 'deals')->where('is_default', true)->first();
        $this->line(' tenant_import_templates: '.($importDefault ? "default = {$importDefault->template_key} (v{$importDefault->version_number})" : 'none'));

        if ($protected) {
            $this->line(' LGU IDS lock      : locked steps = '.implode(', ', ProtectedTenants::lockedConfigSteps()));
        }

        if ($repairSafe) {
            $this->repairTenant($tenantId, $config);
        }

        $this->newLine();
    }

    /**
     * Repairs limited to: clearing derived caches, and filling in a
     * tenant_program_configs row/flag that should exist given the data we
     * can already see. Never touches pipeline/rewards/import for any tenant
     * (including protected ones) and never publishes a draft.
     */
    private function repairTenant(string $tenantId, ?object $config): void
    {
        Cache::forget("stage_limits_{$tenantId}");
        Cache::forget("tenant_status:{$tenantId}");

        $repairs = [];

        if (!$config) {
            DB::table('tenant_program_configs')->insertOrIgnore([
                'tenant_id'  => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $repairs[] = 'created missing tenant_program_configs row with defaults';
        } elseif (!$config->onboarding_complete && TenantReferralProgramVersion::where('tenant_id', $tenantId)->exists()) {
            DB::table('tenant_program_configs')->where('tenant_id', $tenantId)->update([
                'onboarding_complete' => true,
                'updated_at'          => now(),
            ]);
            $repairs[] = 'set onboarding_complete=true (a published version already exists)';
        }

        $this->line(' repair-safe       : cleared caches'.(empty($repairs) ? '' : '; '.implode('; ', $repairs)));
    }
}
