<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantReferralProgramDraft;
use App\Models\TenantReferralProgramVersion;
use App\Models\TenantUser;
use App\Services\ReferralProgram\ReferralProgramSetupService;
use App\Support\ProtectedTenants;
use App\Support\ReferralProgramOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralProgramSetupTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'id'     => (string) Str::uuid(),
            'name'   => 'Test Tenant '.Str::random(4),
            'slug'   => 'test-'.Str::random(6),
            'status' => 'active',
        ], $attrs));
    }

    private function createUser(array $attrs = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'user-'.Str::random(6).'@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ], $attrs));
    }

    private function createMembership(Tenant $tenant, TenantUser $user, string $role, array $extra = []): TenantMembership
    {
        return TenantMembership::create(array_merge([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ], $extra));
    }

    private function startDraft(Tenant $tenant, TenantUser $user): TenantReferralProgramDraft
    {
        return app(ReferralProgramSetupService::class)->getOrCreateActiveDraft($tenant->id, $user->id);
    }

    private function validPipelineConfig(): array
    {
        return ['stages' => ReferralProgramOptions::pipelineStageTemplates()['sales']];
    }

    private function validRewardsConfig(array $overrides = []): array
    {
        return array_merge([
            'commission_type'     => 'percentage_of_value',
            'company_share_pct'   => 30,
            'referrer_share_pct'  => 70,
            'default_expiry_days' => 21,
            'reassignment_mode'   => 'manual',
        ], $overrides);
    }

    private function validImportConfig(): array
    {
        return [
            'enable_imports'            => true,
            'template_key'              => ReferralProgramOptions::importTemplateForIndustry(null),
            'notify_on_import_complete' => true,
        ];
    }

    private function stepRoute(Tenant $tenant, string $step): string
    {
        return route('tenant.settings.referral-program.wizard.step', ['tenantId' => $tenant->id, 'step' => $step]);
    }

    // Access control

    public function test_owner_can_view_overview_and_wizard(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.settings.referral-program.overview', $tenant->id))
            ->assertOk();

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.settings.referral-program.wizard', $tenant->id))
            ->assertOk();
    }

    public function test_member_is_denied_access(): void
    {
        $tenant = $this->createTenant();
        $member = $this->createUser();
        $this->createMembership($tenant, $member, 'member');

        $this->actingAs($member, 'tenant')
            ->get(route('tenant.settings.referral-program.overview', $tenant->id))
            ->assertStatus(403);
    }

    public function test_manager_without_permission_is_denied_but_with_permission_is_allowed(): void
    {
        $tenant     = $this->createTenant();
        $manager    = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->get(route('tenant.settings.referral-program.overview', $tenant->id))
            ->assertStatus(403);

        $membership->update([
            'is_custom_permissions' => true,
            'permissions_json'      => ['manage_referral_program_setup' => true],
        ]);

        $this->actingAs($manager, 'tenant')
            ->get(route('tenant.settings.referral-program.overview', $tenant->id))
            ->assertOk();
    }

    public function test_cross_tenant_user_cannot_access_another_tenants_setup(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $owner   = $this->createUser();
        $this->createMembership($tenantA, $owner, 'owner');

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.settings.referral-program.overview', $tenantB->id))
            ->assertStatus(403);
    }

    // Step update + validation

    public function test_update_step_persists_valid_payload(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $response = $this->actingAs($owner, 'tenant')
            ->patchJson($this->stepRoute($tenant, 'rewards'), $this->validRewardsConfig());

        $response->assertOk()->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertEquals(30, $draft->config['rewards']['company_share_pct']);
        $this->assertSame('rewards', $draft->current_step);
    }

    public function test_update_step_rejects_invalid_payload(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $this->startDraft($tenant, $owner);

        $payload = $this->validRewardsConfig(['referrer_share_pct' => 50]);

        $this->actingAs($owner, 'tenant')
            ->patchJson($this->stepRoute($tenant, 'rewards'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('referrer_share_pct');
    }

    public function test_update_step_returns_404_without_active_draft(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');

        $this->actingAs($owner, 'tenant')
            ->patchJson($this->stepRoute($tenant, 'rewards'), $this->validRewardsConfig())
            ->assertStatus(404);
    }

    // LGU IDS protected steps

    public function test_protected_tenant_locked_steps_are_noop(): void
    {
        $tenant = $this->createTenant(['id' => 'lgu-ids']);
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $payloads = [
            'pipeline' => $this->validPipelineConfig(),
            'rewards'  => $this->validRewardsConfig(),
            'import'   => $this->validImportConfig(),
        ];

        foreach ($payloads as $step => $payload) {
            $response = $this->actingAs($owner, 'tenant')
                ->patchJson($this->stepRoute($tenant, $step), $payload);

            $response->assertOk()->assertJson(['success' => true]);
            $this->assertStringContainsString('managed by your administrator', $response->json('message'));
        }

        $draft->refresh();
        $config = $draft->config ?? [];
        $this->assertArrayNotHasKey('pipeline', $config);
        $this->assertArrayNotHasKey('rewards', $config);
        $this->assertArrayNotHasKey('import', $config);
    }

    public function test_non_protected_tenant_can_edit_locked_steps(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $this->actingAs($owner, 'tenant')
            ->patchJson($this->stepRoute($tenant, 'pipeline'), $this->validPipelineConfig())
            ->assertOk();

        $draft->refresh();
        $this->assertCount(5, $draft->config['pipeline']['stages']);
    }

    // Simulate

    public function test_simulate_blocks_publish_for_empty_draft(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $this->startDraft($tenant, $owner);

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.simulate', $tenant->id));

        $response->assertOk();
        $this->assertFalse($response->json('can_publish'));
        $this->assertGreaterThan(0, $response->json('blockers'));
    }

    public function test_simulate_can_publish_for_fully_configured_draft(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $setup = app(ReferralProgramSetupService::class);
        $setup->updateStep($draft, 'pipeline', $this->validPipelineConfig());
        $setup->updateStep($draft, 'rewards', $this->validRewardsConfig());
        $setup->updateStep($draft, 'import', $this->validImportConfig());

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.simulate', $tenant->id));

        $response->assertOk();
        $this->assertTrue($response->json('can_publish'));
        $this->assertSame(0, $response->json('blockers'));
    }

    public function test_simulate_for_protected_tenant_auto_passes_locked_steps(): void
    {
        $tenant = $this->createTenant(['id' => 'lgu-ids']);
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $this->startDraft($tenant, $owner);

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.simulate', $tenant->id));

        $response->assertOk();
        $this->assertTrue($response->json('can_publish'));

        $pipeline = collect($response->json('scenarios'))->firstWhere('label', 'Pipeline & Journey');
        $this->assertSame('pass', $pipeline['status']);
        $this->assertStringContainsString('dedicated service', $pipeline['message']);
    }

    // Publish

    public function test_publish_succeeds_and_syncs_runtime_tables(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $setup = app(ReferralProgramSetupService::class);
        $setup->updateStep($draft, 'pipeline', $this->validPipelineConfig());
        $setup->updateStep($draft, 'rewards', $this->validRewardsConfig());
        $setup->updateStep($draft, 'import', $this->validImportConfig());

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.publish', $tenant->id));

        $response->assertOk()->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertSame('published', $draft->status);

        $this->assertDatabaseHas('tenant_referral_program_versions', [
            'tenant_id' => $tenant->id,
            'draft_id'  => $draft->id,
        ]);
        $this->assertDatabaseCount('tenant_pipeline_stages', 5);
        $this->assertDatabaseHas('tenant_program_configs', [
            'tenant_id'       => $tenant->id,
            'commission_type' => 'percentage_of_value',
            'company_share_pct' => 30,
        ]);
        $this->assertDatabaseHas('tenant_import_templates', [
            'tenant_id'  => $tenant->id,
            'is_default' => true,
        ]);
    }

    public function test_publish_returns_422_with_blockers_when_simulation_fails(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.publish', $tenant->id));

        $response->assertStatus(422)->assertJson(['success' => false]);

        $draft->refresh();
        $this->assertSame('draft', $draft->status);
        $this->assertDatabaseCount('tenant_referral_program_versions', 0);
    }

    public function test_publish_for_protected_tenant_skips_locked_sections(): void
    {
        $tenant = $this->createTenant(['id' => 'lgu-ids']);
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        // Bypass the controller's no-op guard via the service directly, simulating
        // a crafted payload that somehow reached the draft config.
        $setup = app(ReferralProgramSetupService::class);
        $setup->updateStep($draft, 'pipeline', $this->validPipelineConfig());
        $setup->updateStep($draft, 'rewards', $this->validRewardsConfig(['company_share_pct' => 45, 'referrer_share_pct' => 55]));
        $setup->updateStep($draft, 'import', $this->validImportConfig());

        $response = $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.publish', $tenant->id));

        $response->assertOk()->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertSame('published', $draft->status);

        $this->assertDatabaseCount('tenant_pipeline_stages', 0);
        $this->assertDatabaseCount('tenant_import_templates', 0);
        $this->assertDatabaseHas('tenant_program_configs', [
            'tenant_id'         => 'lgu-ids',
            'company_share_pct' => 30,
        ]);
        $this->assertDatabaseMissing('tenant_program_configs', [
            'tenant_id'         => 'lgu-ids',
            'company_share_pct' => 45,
        ]);
    }

    // Restore

    public function test_restore_version_overwrites_active_draft(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $setup = app(ReferralProgramSetupService::class);
        $setup->updateStep($draft, 'pipeline', $this->validPipelineConfig());
        $setup->updateStep($draft, 'rewards', $this->validRewardsConfig());
        $setup->updateStep($draft, 'import', $this->validImportConfig());

        $this->actingAs($owner, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.publish', $tenant->id))
            ->assertOk();

        $version = TenantReferralProgramVersion::where('tenant_id', $tenant->id)->first();

        $draft2 = $this->startDraft($tenant, $owner);
        $setup->updateStep($draft2, 'rewards', $this->validRewardsConfig(['company_share_pct' => 50, 'referrer_share_pct' => 50]));

        $this->actingAs($owner, 'tenant')
            ->post(route('tenant.settings.referral-program.versions.restore', ['tenantId' => $tenant->id, 'versionId' => $version->id]))
            ->assertRedirect(route('tenant.settings.referral-program.wizard', $tenant->id));

        $draft2->refresh();
        $this->assertEquals(30, $draft2->config['rewards']['company_share_pct']);
        $this->assertSame('program-basics', $draft2->current_step);
    }

    public function test_restore_version_cross_tenant_is_blocked(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $ownerA  = $this->createUser();
        $ownerB  = $this->createUser();
        $this->createMembership($tenantA, $ownerA, 'owner');
        $this->createMembership($tenantB, $ownerB, 'owner');

        $draftA = $this->startDraft($tenantA, $ownerA);
        $setup  = app(ReferralProgramSetupService::class);
        $setup->updateStep($draftA, 'pipeline', $this->validPipelineConfig());
        $setup->updateStep($draftA, 'rewards', $this->validRewardsConfig());
        $setup->updateStep($draftA, 'import', $this->validImportConfig());

        $this->actingAs($ownerA, 'tenant')
            ->postJson(route('tenant.settings.referral-program.wizard.publish', $tenantA->id))
            ->assertOk();

        $versionA = TenantReferralProgramVersion::where('tenant_id', $tenantA->id)->first();

        $this->actingAs($ownerB, 'tenant')
            ->post(route('tenant.settings.referral-program.versions.restore', ['tenantId' => $tenantB->id, 'versionId' => $versionA->id]))
            ->assertRedirect(route('tenant.settings.referral-program.overview', $tenantB->id))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tenant_referral_program_drafts', ['tenant_id' => $tenantB->id]);
    }

    // Discard

    public function test_discard_draft_deletes_active_draft(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');
        $draft  = $this->startDraft($tenant, $owner);

        $this->actingAs($owner, 'tenant')
            ->post(route('tenant.settings.referral-program.wizard.discard', $tenant->id))
            ->assertRedirect(route('tenant.settings.referral-program.overview', $tenant->id));

        $this->assertDatabaseMissing('tenant_referral_program_drafts', ['id' => $draft->id]);
    }

    // ProtectedTenants canary

    public function test_protected_tenants_canary(): void
    {
        $this->assertSame(['pipeline', 'rewards', 'import'], ProtectedTenants::lockedConfigSteps());
        $this->assertTrue(ProtectedTenants::isProtected('lgu-ids'));
        $this->assertFalse(ProtectedTenants::isProtected('some-other-tenant'));
    }
}
