<?php

namespace Tests\Feature;

use App\Models\{Program, ProgramConnection, Tenant, TenantMembership, TenantUser};
use App\Services\Programs\ProgramNavigation;
use Illuminate\Support\Facades\DB;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;

class ProgramOperatingModeTest extends TestCase
{
    use QuickProgramSchema;

    private TenantUser $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'programs.enabled' => true]);
        $this->buildQuickProgramSchema();
        \Illuminate\Support\Facades\Schema::create('tenant_brand_profiles', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id(); $table->string('tenant_id'); $table->string('status')->default('draft');
            $table->timestamps();
        });
        Tenant::create(['id' => 'modes', 'name' => 'Modes', 'status' => 'active']);
        $this->owner = TenantUser::create(['id' => 'owner', 'email' => 'owner@example.com', 'password' => 'x', 'status' => 'active']);
        TenantMembership::create(['tenant_id' => 'modes', 'tenant_user_id' => 'owner', 'role' => 'owner', 'status' => 'active']);
    }

    public function test_creation_requires_valid_mode_and_stores_selection(): void
    {
        $this->actingAs($this->owner, 'tenant');
        $url = route('tenant.programs.store', 'modes');
        $this->post($url, ['name' => 'Online', 'program_type' => 'referral', 'operating_mode' => 'invalid'])
            ->assertSessionHasErrors('operating_mode');
        $this->post($url, ['name' => 'Online', 'program_type' => 'referral', 'operating_mode' => 'automated'])->assertRedirect();
        $this->assertDatabaseHas('programs', ['tenant_id' => 'modes', 'operating_mode' => 'automated']);
        $this->assertFalse(app(ProgramNavigation::class)->allowsManualDeals('modes'));
    }

    public function test_draft_mode_changes_but_live_program_cannot_switch(): void
    {
        $program = Program::create(['tenant_id' => 'modes', 'name' => 'Manual', 'status' => 'draft', 'operating_mode' => 'manual']);
        $url = route('tenant.programs.update', ['modes', $program->id]);
        $this->actingAs($this->owner, 'tenant')->patch($url, ['operating_mode' => 'automated'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('automated', $program->fresh()->operating_mode);
        $program->update(['status' => 'active']);
        $this->patch($url, ['operating_mode' => 'manual'])->assertSessionHasErrors('operating_mode');
        $this->assertSame('automated', $program->fresh()->operating_mode);
    }

    public function test_legacy_modes_and_connected_drafts_are_preserved(): void
    {
        $program = Program::create(['tenant_id' => 'modes', 'name' => 'Legacy', 'status' => 'draft']);
        $this->assertSame('manual', $program->effectiveOperatingMode());
        $this->assertTrue(app(ProgramNavigation::class)->allowsManualDeals('modes'));
        ProgramConnection::create(['tenant_id' => 'modes', 'program_id' => $program->id, 'website' => 'https://example.com', 'secret' => 'secret', 'status' => 'not_connected']);
        $this->assertSame('automated', $program->fresh()->effectiveOperatingMode());
        $this->assertFalse($program->canChangeOperatingMode());
        $this->assertFalse(app(ProgramNavigation::class)->allowsManualDeals('modes'));
        $this->actingAs($this->owner, 'tenant')->patch(route('tenant.programs.update', ['modes', $program->id]), ['operating_mode' => 'manual'])->assertSessionHasErrors('operating_mode');
        $this->assertNull($program->fresh()->operating_mode);
    }

    public function test_dashboard_selection_persists_and_rejects_other_tenants(): void
    {
        $first = Program::create(['tenant_id'=>'modes','name'=>'First','status'=>'draft','operating_mode'=>'automated','default_currency'=>'PHP']);
        $second = Program::create(['tenant_id'=>'modes','name'=>'Second','status'=>'draft','operating_mode'=>'automated','default_currency'=>'USD']);
        Tenant::create(['id'=>'other','name'=>'Other','status'=>'active']);
        $foreign = Program::create(['tenant_id'=>'other','name'=>'Foreign','status'=>'draft','operating_mode'=>'automated']);
        $url = route('tenant.dashboard', 'modes');
        $this->actingAs($this->owner, 'tenant')->get($url.'?program_id='.$second->id)
            ->assertOk()->assertViewIs('tenant.programs.dashboard')
            ->assertViewHas('program', fn ($p) => $p->id === $second->id)
            ->assertSee('USD 0.00')->assertDontSee('PHP 0.00');
        $this->get($url)->assertOk()->assertViewHas('program', fn ($p) => $p->id === $second->id);
        $this->get($url.'?program_id='.$foreign->id)->assertNotFound();
        $this->get($url.'?program_id[]=bad')->assertStatus(422);
        $second->update(['status'=>'archived']);
        $this->get($url)->assertOk()->assertViewHas('program', fn ($p) => $p->id === $first->id);
    }

    public function test_protected_mode_never_queries_or_changes_data(): void
    {
        $program = new Program(['tenant_id' => 'lgu-ids', 'status' => 'draft', 'operating_mode' => 'automated']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame('manual', $program->effectiveOperatingMode());
        $this->assertFalse($program->canChangeOperatingMode());
        $this->assertTrue(app(ProgramNavigation::class)->allowsManualDeals('lgu-ids'));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }
}
