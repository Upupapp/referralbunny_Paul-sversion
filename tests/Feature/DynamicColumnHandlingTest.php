<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Models\TenantCustomField;
use App\Models\TenantImportTemplate;
use App\Models\TenantUser;
use App\Services\ColumnDetectionService;
use App\Services\TemplateAdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for dynamic import column handling.
 *
 * Tests cover:
 *  - Unmapped column detection
 *  - Per-column admin actions (ignore, create)
 *  - snake_case field-key generation
 *  - Data type detection (boolean, email, number)
 *  - Template adoption gating (LGU IDS blocked, double auth required)
 *  - Template versioning (previous default archived on new save)
 */
class DynamicColumnHandlingTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function makeTenant(string $id = null): Tenant
    {
        return Tenant::factory()->create(
            $id ? ['id' => $id] : []
        );
    }

    private function makeUser(string $tenantId, string $password = 'secret123'): TenantUser
    {
        return TenantUser::factory()->create([
            'password' => Hash::make($password),
        ]);
    }

    private function makeBatch(string $tenantId): ImportBatch
    {
        return ImportBatch::factory()->create([
            'tenant_id'   => $tenantId,
            'import_type' => 'generic_deals',
            'status'      => 'previewed',
        ]);
    }

    private function columnDetection(): ColumnDetectionService
    {
        return app(ColumnDetectionService::class);
    }

    private function templateAdoption(): TemplateAdoptionService
    {
        return app(TemplateAdoptionService::class);
    }

    // ── Test 1: Unmapped column detection ─────────────────────────

    /** @test */
    public function test_detects_unmapped_columns_in_upload(): void
    {
        $tenant = $this->makeTenant();

        // Known aliases — standard deal fields
        $knownAliases = [
            'deal name'   => 'deal_name',
            'amount'      => 'deal_amount',
        ];
        $knownFields  = ['deal_name', 'deal_amount', 'referrer_email', 'organization_name'];

        // Upload includes an extra column not in the template
        $uploaded = ['Deal Name', 'Amount', 'Lead Temperature'];

        $unmapped = $this->columnDetection()->detectUnmapped(
            $uploaded,
            $knownAliases,
            $knownFields,
            $tenant->id,
            'deals'
        );

        $headers = array_column($unmapped, 'header');
        $this->assertContains('Lead Temperature', $headers, 'Extra column should be detected as unmapped.');
        $this->assertNotContains('Deal Name', $headers, 'Aliased column should NOT be flagged as unmapped.');
        $this->assertNotContains('Amount', $headers, 'Aliased column should NOT be flagged as unmapped.');
    }

    // ── Test 2: Ignore action ─────────────────────────────────────

    /** @test */
    public function test_admin_can_ignore_unmapped_column(): void
    {
        $tenant = $this->makeTenant();
        $user   = $this->makeUser($tenant->id);
        $batch  = $this->makeBatch($tenant->id);

        $this->actingAs($user, 'tenant')
            ->postJson("/tenant/{$tenant->id}/imports/deals/{$batch->id}/column-actions", [
                'actions' => [
                    ['header' => 'Lead Temperature', 'action' => 'ignore'],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // No custom field should have been created for an 'ignore' action
        $this->assertDatabaseMissing('tenant_custom_fields', [
            'tenant_id'  => $tenant->id,
            'field_label' => 'Lead Temperature',
        ]);
    }

    // ── Test 3: Create custom field from column ───────────────────

    /** @test */
    public function test_admin_can_create_custom_field_from_column(): void
    {
        $tenant = $this->makeTenant();
        $user   = $this->makeUser($tenant->id);
        $batch  = $this->makeBatch($tenant->id);

        $this->actingAs($user, 'tenant')
            ->postJson("/tenant/{$tenant->id}/imports/deals/{$batch->id}/column-actions", [
                'actions' => [
                    [
                        'header'    => 'Lead Temperature',
                        'action'    => 'create',
                        'data_type' => 'text',
                        'label'     => 'Lead Temperature',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tenant_custom_fields', [
            'tenant_id'        => $tenant->id,
            'destination_type' => 'deals',
            'field_key'        => 'lead_temperature',
        ]);
    }

    // ── Test 4: snake_case key generation ────────────────────────

    /** @test */
    public function test_field_key_generated_as_snake_case(): void
    {
        $tenant = $this->makeTenant();

        $key = $this->columnDetection()->generateFieldKey(
            'Lead Temperature',
            $tenant->id,
            'deals'
        );

        $this->assertSame('lead_temperature', $key);
    }

    // ── Test 5: Data type detection — boolean ─────────────────────

    /** @test */
    public function test_data_type_detection_boolean(): void
    {
        $detected = $this->columnDetection()->detectDataType(['yes', 'no', 'yes', 'yes', 'no']);
        $this->assertSame('boolean', $detected);
    }

    // ── Test 6: Data type detection — email ──────────────────────

    /** @test */
    public function test_data_type_detection_email(): void
    {
        $detected = $this->columnDetection()->detectDataType([
            'alice@example.com',
            'bob@company.org',
            'carol@test.net',
        ]);
        $this->assertSame('email', $detected);
    }

    // ── Test 7: Data type detection — number ─────────────────────

    /** @test */
    public function test_data_type_detection_number(): void
    {
        $detected = $this->columnDetection()->detectDataType(['100', '200', '350', '500']);
        $this->assertSame('number', $detected);
    }

    // ── Test 8: Template adoption blocked for LGU IDS ────────────

    /** @test */
    public function test_template_adoption_blocked_for_lgu_ids(): void
    {
        $tenant = $this->makeTenant('lgu-ids');
        $user   = $this->makeUser('lgu-ids');
        $batch  = $this->makeBatch('lgu-ids');

        $this->actingAs($user, 'tenant')
            ->postJson("/tenant/lgu-ids/imports/deals/{$batch->id}/initiate-adoption")
            ->assertForbidden();
    }

    // ── Test 9: Template adoption requires double auth ────────────

    /** @test */
    public function test_template_adoption_requires_double_auth(): void
    {
        $tenant = $this->makeTenant();
        $user   = $this->makeUser($tenant->id, 'correct-password');
        $batch  = $this->makeBatch($tenant->id);

        // Missing password field
        $this->actingAs($user, 'tenant')
            ->postJson("/tenant/{$tenant->id}/imports/deals/{$batch->id}/verify-adoption", [
                'confirmation_phrase' => 'USE THIS TEMPLATE',
                // password intentionally omitted
            ])
            ->assertUnprocessable(); // 422 — validation failure
    }

    // ── Test 10: Wrong password does not save template ────────────

    /** @test */
    public function test_wrong_password_does_not_save_template(): void
    {
        $tenant = $this->makeTenant();
        $user   = $this->makeUser($tenant->id, 'correct-password');
        $batch  = $this->makeBatch($tenant->id);

        $response = $this->actingAs($user, 'tenant')
            ->postJson("/tenant/{$tenant->id}/imports/deals/{$batch->id}/verify-adoption", [
                'confirmation_phrase' => 'USE THIS TEMPLATE',
                'password'            => 'wrong-password',
            ]);

        $response->assertForbidden()
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('tenant_import_templates', [
            'tenant_id'        => $tenant->id,
            'destination_type' => 'deals',
        ]);
    }

    // ── Test 11: Correct password creates template version ────────

    /** @test */
    public function test_correct_password_creates_template_version(): void
    {
        $tenant   = $this->makeTenant();
        $password = 'my-secure-pass';
        $user     = $this->makeUser($tenant->id, $password);
        $batch    = $this->makeBatch($tenant->id);

        $response = $this->actingAs($user, 'tenant')
            ->postJson("/tenant/{$tenant->id}/imports/deals/{$batch->id}/verify-adoption", [
                'confirmation_phrase' => 'USE THIS TEMPLATE',
                'password'            => $password,
                'template_name'       => 'My Custom Template',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['template_id', 'version']);

        $this->assertDatabaseHas('tenant_import_templates', [
            'tenant_id'        => $tenant->id,
            'destination_type' => 'deals',
            'status'           => 'active',
            'is_default'       => true,
            'version_number'   => 1,
        ]);
    }

    // ── Test 12: Previous template archived on new default ────────

    /** @test */
    public function test_previous_template_archived_on_new_default(): void
    {
        $tenant = $this->makeTenant();

        // Seed an existing default template
        $existing = TenantImportTemplate::factory()->create([
            'tenant_id'        => $tenant->id,
            'destination_type' => 'deals',
            'is_default'       => true,
            'status'           => 'active',
            'version_number'   => 1,
        ]);

        // Trigger archivePreviousDefault as happens inside createVersion
        TenantImportTemplate::archivePreviousDefault($tenant->id, 'deals');

        $existing->refresh();

        $this->assertFalse((bool) $existing->is_default, 'Old default should be demoted.');
        $this->assertSame('archived', $existing->status, 'Old default should be archived.');
    }
}
