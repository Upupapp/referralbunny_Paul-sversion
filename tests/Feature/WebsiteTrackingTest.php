<?php
namespace Tests\Feature;
use App\Models\{Program, ProgramConnection, Tenant, TenantUser, TenantMembership, ReferrerProgramMembership};
use App\Services\QuickProgram\QuickProgramService;
use Illuminate\Support\Facades\DB;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;
class WebsiteTrackingTest extends TestCase
{
    use QuickProgramSchema;
    private ProgramConnection $connection;
    private string $member;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','programs.enabled'=>true]);
        $this->buildQuickProgramSchema();
        Tenant::create(['id'=>'acme','name'=>'Acme','status'=>'active']);
        TenantUser::create(['id'=>'owner','email'=>'owner@example.com','password'=>bcrypt('password'),'status'=>'active']);
        $program=app(QuickProgramService::class)->publish('acme','owner',[
            'website'=>'https://example.com','name'=>'Referrals','pricing_model'=>'subscription','price'=>1000,'currency'=>'PHP',
            'option'=>'recurring','reward_model'=>'percentage','reward_value'=>10,'reward_scope'=>'recurring','duration_months'=>6,'hold_days'=>30,
        ]);
        $this->connection=ProgramConnection::first();
        DB::table('resellers')->insert(['id'=>'referrer','tenant_id'=>'acme','name'=>'Referrer','email'=>'referrer@example.com','status'=>'active']);
        $this->member=ReferrerProgramMembership::create(['tenant_id'=>'acme','program_id'=>$program->id,'reseller_id'=>'referrer','status'=>'active'])->id;
    }

    private function visitSite(string $origin = 'https://example.com', array $extra = [])
    {
        return $this->call('POST', '/tracking/connections/'.$this->connection->id.'/visit', [], [], [],
            ['CONTENT_TYPE'=>'text/plain', 'HTTP_ACCEPT'=>'application/json', 'HTTP_ORIGIN'=>$origin],
            json_encode(array_replace(['program_id'=>$this->connection->program_id, 'membership_id'=>$this->member], $extra)));
    }
    public function test_signal_validates_referral_without_connecting_payments_or_setting_cookies(): void
    {
        $response = $this->visitSite()->assertOk()->assertHeader('Access-Control-Allow-Origin','https://example.com')
            ->assertJsonPath('referral.membership_id',$this->member);
        $this->assertEmpty($response->headers->getCookies());
        $this->assertNotNull($this->connection->fresh()->snippet_installed_at);
        $this->assertSame('not_connected',$this->connection->fresh()->status);
        $this->assertDatabaseCount('program_conversion_events',0);
        $first = $this->connection->fresh()->snippet_last_seen_at;
        $this->travel(1)->minutes();
        $this->visitSite()->assertOk();
        $this->assertEquals($first,$this->connection->fresh()->snippet_last_seen_at);
    }
    public function test_rejects_missing_unapproved_origins_and_wrong_program(): void
    {
        foreach (['','https://evil.example.com','https://example.com.evil.com','http://example.com'] as $origin) $this->visitSite($origin)->assertForbidden();
        $this->visitSite('https://example.com',['program_id'=>'wrong'])->assertUnprocessable();
        $this->assertNull($this->connection->fresh()->snippet_installed_at);
        $this->visitSite('https://www.example.com')->assertOk();
    }
    public function test_invalid_inactive_and_other_program_members_do_not_capture(): void
    {
        $this->visitSite('https://example.com',['membership_id'=>'unknown'])->assertOk()->assertJsonPath('referral',null);
        ReferrerProgramMembership::whereKey($this->member)->update(['status'=>'paused']);
        $this->visitSite()->assertOk()->assertJsonPath('referral',null);
        $other = Program::create(['tenant_id'=>'acme','name'=>'Other','status'=>'active']);
        ReferrerProgramMembership::whereKey($this->member)->update(['status'=>'active','program_id'=>$other->id]);
        $this->visitSite()->assertOk()->assertJsonPath('referral',null);
    }
    public function test_paused_program_detects_script_without_capturing_referrals(): void
    {
        Program::whereKey($this->connection->program_id)->update(['status'=>'paused']);
        $this->visitSite()->assertOk()->assertJsonPath('referral',null);
    }
    public function test_owner_can_edit_origins_and_removed_domain_resets_detection(): void
    {
        $this->withoutVite();
        \Illuminate\Support\Facades\Schema::create('tenant_brand_profiles', function ($table) { $table->string('tenant_id'); $table->string('status'); });
        $this->visitSite()->assertOk();
        TenantMembership::create(['tenant_id'=>'acme','tenant_user_id'=>'owner','role'=>'owner','status'=>'active']);
        $this->actingAs(TenantUser::find('owner'),'tenant');
        $base='/tenant/acme/quick-program/connection/'.$this->connection->program_id;
        $this->get($base)->assertOk()->assertSee('Copy snippet')->assertSee('data-connection');
        $this->getJson($base.'/installation')->assertOk()->assertJsonPath('installed',true)->assertJsonMissing(['secret'=>$this->connection->secret]);
        $this->putJson($base.'/origins',['origins'=>['https://app.example.com']])->assertOk()->assertJsonPath('installed',false);
        $this->visitSite()->assertForbidden();
        $this->visitSite('https://app.example.com')->assertOk();
        $this->putJson($base.'/origins',['origins'=>['http://localhost']])->assertUnprocessable();
        TenantMembership::where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->putJson($base.'/origins',['origins'=>['https://evil.com']])->assertForbidden();
    }
}
