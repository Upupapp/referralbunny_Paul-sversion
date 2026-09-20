<?php
namespace Tests\Feature;

use App\Models\{Tenant, TenantUser, TenantMembership, TenantReferralProgramVersion, Program, ProgramConnection, ProgramOfferVersion};
use App\Services\QuickProgram\WebsiteAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;

class QuickProgramTest extends TestCase
{
    use QuickProgramSchema;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'programs.enabled' => true]);
        $this->withoutVite(); $this->buildQuickProgramSchema();
        Tenant::create(['id'=>'acme','name'=>'Acme','status'=>'active']);
        $user = TenantUser::create(['id'=>'owner','email'=>'owner@example.com','password'=>bcrypt('password'),'status'=>'active']);
        TenantMembership::create(['tenant_id'=>'acme','tenant_user_id'=>'owner','role'=>'owner','status'=>'active']);
        $this->actingAs($user, 'tenant');
    }
    protected function payload(): array
    {
        return ['website'=>'example.com','name'=>'Acme Referrals','pricing_model'=>'subscription','price'=>1000,'currency'=>'PHP',
            'option'=>'recurring','reward_model'=>'percentage','reward_value'=>10,'reward_scope'=>'recurring','duration_months'=>6,'hold_days'=>30,'confirmed'=>true];
    }
    public function test_empty_workspace_prompts_and_saved_draft_resumes(): void
    {
        $this->getJson('/tenant/acme/quick-program/status')->assertOk()->assertJson(['needed'=>true,'draft'=>null]);
        $this->putJson('/tenant/acme/quick-program/draft', $this->payload())->assertOk();
        $this->getJson('/tenant/acme/quick-program/status')->assertOk()->assertJsonPath('draft.reward_value',10)->assertJsonPath('draft.website','https://example.com');
    }
    public function test_publish_creates_program_offer_and_disconnected_connection_once(): void
    {
        $this->postJson('/tenant/acme/quick-program/publish',$this->payload())->assertOk();
        $this->postJson('/tenant/acme/quick-program/publish',$this->payload())->assertOk();
        $this->assertDatabaseCount('programs',1); $this->assertDatabaseCount('program_offers',1);
        $this->assertDatabaseHas('programs',['tenant_id'=>'acme','status'=>'active']);
        $this->assertDatabaseHas('program_connections',['tenant_id'=>'acme','status'=>'not_connected']);
        $version = ProgramOfferVersion::first();
        $this->assertEquals(10,$version->percentage_rate); $this->assertSame('recurring',$version->reward_rules['scope']);
        $this->assertSame(6,$version->reward_rules['duration_months']);
        $this->assertNotSame(ProgramConnection::first()->secret,DB::table('program_connections')->value('secret'));
        $this->getJson('/tenant/acme/quick-program/status')->assertJsonPath('needed',false);
    }
    public function test_existing_draft_program_does_not_suppress_prompt_but_paused_program_does(): void
    {
        $program=Program::create(['tenant_id'=>'acme','name'=>'Draft','status'=>'draft']);
        $this->getJson('/tenant/acme/quick-program/status')->assertJsonPath('needed',true);
        $program->update(['status'=>'paused']);
        $this->getJson('/tenant/acme/quick-program/status')->assertJsonPath('needed',false);
    }
    public function test_legacy_published_program_suppresses_prompt(): void
    {
        DB::table('tenant_referral_program_versions')->insert(['id'=>(string)Str::uuid(),'tenant_id'=>'acme','draft_id'=>'old','config'=>'{}']);
        $this->getJson('/tenant/acme/quick-program/status')->assertJsonPath('needed',false);
    }
    public function test_viewer_other_tenant_and_protected_tenant_are_blocked(): void
    {
        Tenant::create(['id'=>'other','name'=>'Other','status'=>'active']);
        $this->getJson('/tenant/other/quick-program/status')->assertForbidden();
        DB::table('tenant_memberships')->where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->postJson('/tenant/acme/quick-program/publish',$this->payload())->assertForbidden();
        Tenant::create(['id'=>'lgu-ids','name'=>'Protected','status'=>'active']);
        TenantMembership::create(['tenant_id'=>'lgu-ids','tenant_user_id'=>'owner','role'=>'owner','status'=>'active']);
        $this->getJson('/tenant/lgu-ids/quick-program/status')->assertNotFound();
    }
    public function test_invalid_rate_and_unconfirmed_publish_are_rejected(): void
    {
        $this->postJson('/tenant/acme/quick-program/publish',array_replace($this->payload(),['reward_value'=>101]))->assertUnprocessable();
        $this->postJson('/tenant/acme/quick-program/publish',array_replace($this->payload(),['confirmed'=>false]))->assertUnprocessable();
        $this->assertDatabaseCount('programs',0);
    }
    public function test_analysis_failure_has_manual_fallback_without_invented_prices(): void
    {
        $analyzer = new class extends WebsiteAnalyzer { protected function fetch(string $url): string { throw new \RuntimeException('unavailable'); } };
        $this->app->instance(WebsiteAnalyzer::class,$analyzer);
        $this->postJson('/tenant/acme/quick-program/analyze',['website'=>'example.com'])->assertOk()->assertJson(['prices'=>[],'pricing_model'=>'unknown']);
    }
    public function test_membership_repair_creates_only_missing_tables_and_preserves_data(): void
    {
        \Illuminate\Support\Facades\Schema::create('partner_users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->uuid('id')->primary();
        });
        $migration = require database_path('migrations/2026_09_20_000000_ensure_program_membership_tables.php');
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('partner_program_memberships'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('referrer_program_memberships'));
        $migration->up();
        $migration->down();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('partner_program_memberships'));
    }
    public function test_rejects_nonpublic_urls_and_extracts_untrusted_text_without_html(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $analyzer=new WebsiteAnalyzer;
        foreach (['http://example.com','https://127.0.0.1','https://user@example.com','https://localhost','https://example.com:8443','https://example.internal'] as $url) {
            $this->postJson('/tenant/acme/quick-program/analyze',['website'=>$url])->assertUnprocessable();
        }
        $parsed=$analyzer->extract('<html><title>GetHired</title><script>USD 9999</script><p>PHP 1,000 per month</p></html>','https://example.com/pricing');
        $this->assertCount(1,$parsed['prices']); $this->assertEquals(1000,$parsed['prices'][0]['amount']);
        $this->assertTrue($parsed['prices'][0]['needs_confirmation']);
    }
}
