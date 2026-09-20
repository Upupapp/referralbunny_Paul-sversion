<?php
namespace Tests\Feature;
use App\Models\{Program, ProgramConnection, ReferrerProgramMembership, Reseller, Tenant, TenantMembership, TenantUser};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Database\Schema\Blueprint;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;
class ProgramReferrerPortalTest extends TestCase
{
    use QuickProgramSchema;
    private $referrer; private $owner; private $first; private $second;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','programs.enabled'=>true]);
        $this->buildQuickProgramSchema();
        (require database_path('migrations/2026_09_20_000005_create_program_messages.php'))->up();
        Schema::create('tenant_brand_profiles', function(Blueprint $t){$t->id();$t->string('tenant_id');$t->string('status')->default('draft');$t->timestamps();});
        Schema::create('leads',function(Blueprint $t){$t->string('id');$t->string('tenant_id');$t->string('program_id');$t->string('reseller_id');$t->string('name');$t->string('stage');$t->string('status');$t->timestamps();$t->softDeletes();});
        $this->withoutMiddleware(\App\Http\Middleware\EnsureLegalAgreementsAccepted::class);
        Tenant::create(['id'=>'company','name'=>'Company','status'=>'active']);
        $this->owner=TenantUser::create(['id'=>'owner','email'=>'owner@example.com','password'=>'x','status'=>'active']);
        TenantMembership::create(['tenant_id'=>'company','tenant_user_id'=>'owner','role'=>'owner','status'=>'active']);
        $this->referrer=Reseller::create(['tenant_id'=>'company','name'=>'Referrer','email'=>'r@example.com','status'=>'active']);
        $this->first=Program::create(['tenant_id'=>'company','name'=>'Online','status'=>'active','operating_mode'=>'automated']);
        $this->second=Program::create(['tenant_id'=>'company','name'=>'Manual','status'=>'active','operating_mode'=>'manual']);
        foreach([$this->first,$this->second] as $program) ReferrerProgramMembership::create(['tenant_id'=>'company','program_id'=>$program->id,'reseller_id'=>$this->referrer->id,'status'=>'active']);
    }
    public function test_referrer_messages_and_admin_replies_are_program_scoped(): void
    {
        $send=route('reseller.messages.program.send','company');
        $this->actingAs($this->referrer,'reseller')->post($send,['program_id'=>$this->first->id,'body'=>'Online question'])->assertRedirect();
        $this->post($send,['program_id'=>$this->second->id,'body'=>'Manual question'])->assertRedirect();
        $this->get(route('reseller.messages',['tenantId'=>'company','program_id'=>$this->first->id]))->assertOk()->assertSee('Online question')->assertDontSee('Manual question');
        auth('reseller')->logout();
        $this->actingAs($this->owner,'tenant')->get(route('tenant.messages',['tenantId'=>'company','program_id'=>$this->first->id]))->assertOk()->assertSee('Online question')->assertDontSee('Manual question');
        $this->post(route('tenant.messages.program.send','company'),['program_id'=>$this->first->id,'reseller_id'=>$this->referrer->id,'body'=>'Admin reply'])->assertRedirect();
        auth('tenant')->logout();
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.messages',['tenantId'=>'company','program_id'=>$this->first->id]))->assertOk()->assertSee('Admin reply');
        $this->assertDatabaseHas('program_messages',['body'=>'Admin reply','sender_type'=>'admin']);
    }
    public function test_referrer_cannot_select_unenrolled_program_or_impersonate_another_referrer(): void
    {
        $hidden=Program::create(['tenant_id'=>'company','name'=>'Private','status'=>'active']);
        $send=route('reseller.messages.program.send','company');
        $this->actingAs($this->referrer,'reseller')->post($send,['program_id'=>$hidden->id,'body'=>'No'])->assertNotFound();
        $this->post($send,['program_id'=>$this->first->id,'reseller_id'=>'someone-else','body'=>'No'])->assertForbidden();
        $this->post($send,['program_id'=>$this->first->id,'body'=>'   '])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('program_messages',0);
        $this->get(route('reseller.request-forms','company'))->assertNotFound();
    }
    public function test_switching_programs_changes_dashboard_and_excludes_other_referrer_data(): void
    {
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x','status'=>'not_connected']);
        foreach([[$this->referrer->id,2000],['another',990000]] as [$referrerId,$reward]) DB::table('program_conversion_events')->insert(['id'=>$referrerId,'connection_id'=>$connection->id,'external_id'=>$referrerId,'customer_id'=>'c','invoice_id'=>$referrerId,'referrer_id'=>$referrerId,'payload_hash'=>str_repeat('a',64),'type'=>'payment','currency'=>'PHP','amount_minor'=>10000,'reward_minor'=>$reward,'status'=>'pending_review','occurred_at'=>now()]);
        DB::table('leads')->insert(['id'=>'manual-one','tenant_id'=>'company','program_id'=>$this->second->id,'reseller_id'=>$this->referrer->id,'name'=>'Assigned referral','stage'=>'demo','status'=>'active','created_at'=>now()]);
        $url=route('reseller.dashboard','company');
        $this->actingAs($this->referrer,'reseller')->get($url.'?program_id='.$this->first->id.'&currency=PHP')->assertOk()->assertSee('20.00')->assertDontSee('9,900.00')->assertDontSee('Assigned referral')->assertDontSee('Request Forms')->assertSee('My Referrals')->assertSee('Referral Programs');
        $this->get($url.'?program_id='.$this->second->id)->assertOk()->assertSee('Assigned referral')->assertDontSee('20.00');
        $this->get(route('reseller.deals','company'))->assertOk()->assertSee('Assigned referral');
        $this->get(route('reseller.messages','company'))->assertOk()->assertViewHas('program',fn($p)=>$p->id===$this->second->id);
    }
    public function test_removed_members_and_foreign_admin_cannot_read_or_send(): void
    {
        ReferrerProgramMembership::where('program_id',$this->first->id)->update(['status'=>'removed']);
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.messages',['tenantId'=>'company','program_id'=>$this->first->id]))->assertNotFound();
        auth('reseller')->logout();
        $outsider=TenantUser::create(['id'=>'outsider','email'=>'out@example.com','password'=>'x','status'=>'active']);
        $this->actingAs($outsider,'tenant')->post(route('tenant.messages.program.send','company'),['program_id'=>$this->first->id,'reseller_id'=>$this->referrer->id,'body'=>'No'])->assertForbidden();
        $this->assertDatabaseCount('program_messages',0);
    }
    public function test_referral_link_uses_selected_enrollment_and_preserves_website_url(): void
    {
        ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com/plans?campaign=summer&rb_ref=old#pricing','secret'=>'x']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $url=route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->first->id]);
        $response=$this->actingAs($this->referrer,'reseller')->get($url)->assertOk()->assertSee('Copy referral link');
        $link=$response->viewData('referralLink');
        parse_str(parse_url($link,PHP_URL_QUERY),$query);
        $this->assertSame(['campaign'=>'summer','rb_program'=>$this->first->id,'rb_ref'=>$member->id],$query);
        $this->assertSame('pricing',parse_url($link,PHP_URL_FRAGMENT));
        $this->assertSame('/plans',parse_url($link,PHP_URL_PATH));
        $this->assertNull($member->fresh()->referral_code);
        $this->get(route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->second->id]))->assertOk()->assertDontSee('Copy referral link');
        $member->update(['status'=>'approved']);
        $this->get($url)->assertOk()->assertDontSee('Copy referral link');
        $member->update(['status'=>'active']);
        $this->first->update(['status'=>'paused']);
        $this->get($url)->assertOk()->assertDontSee('Copy referral link');
    }
    public function test_referral_links_reject_invalid_destinations_and_membership_mismatch(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $service=app(\App\Services\Programs\ReferrerReferralLink::class);
        $this->assertNull($service->forMembership($this->first,$member));
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'javascript:alert(1)','secret'=>'x']);
        $this->assertNull($service->forMembership($this->first,$member));
        $connection->update(['website'=>'https://user:password@example.com']);
        $this->assertNull($service->forMembership($this->first,$member));
        $connection->update(['website'=>'https://example.com']);
        $member->tenant_id='another';
        $this->assertNull($service->forMembership($this->first,$member));
        $protected=new Program(['tenant_id'=>'lgu-ids','status'=>'active','operating_mode'=>'automated']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertNull($service->forMembership($protected,$member));
        $this->assertSame([],DB::getQueryLog()); DB::disableQueryLog();
    }

    public function test_subscription_dashboard_target_and_first_payment_counts(): void
    {
        (require database_path('migrations/2026_09_20_000006_create_program_referral_targets.php'))->up();
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        foreach([['p1','c1','2026-08-01 12:00:00','payment',10000,2000],['p2','c1','2026-08-02 12:00:00','payment',10000,2000],['p3','c2','2026-08-02 12:00:00','payment',10000,2000],['r1','c2','2026-08-03 12:00:00','refund',5000,-1000]] as [$id,$customer,$date,$type,$amount,$reward]) {
            DB::table('program_conversion_events')->insert(['id'=>$id,'connection_id'=>$connection->id,'external_id'=>$id,'customer_id'=>$customer,'invoice_id'=>$id,'referrer_id'=>$this->referrer->id,'payload_hash'=>str_repeat('a',64),'type'=>$type,'currency'=>'PHP','amount_minor'=>$amount,'reward_minor'=>$reward,'status'=>'pending_review','occurred_at'=>$date]);
        }
        $save=route('tenant.programs.referral-target',['tenantId'=>'company','programId'=>$this->first->id]);
        $this->actingAs($this->owner,'tenant')->post($save,['total'=>30,'starts_on'=>'2026-08-01','ends_on'=>'2026-08-03'])->assertRedirect();
        $request=\Illuminate\Http\Request::create('/','GET',['from'=>'2026-08-02','to'=>'2026-08-04','currency'=>'PHP']);
        $data=app(\App\Services\Programs\SubscriptionDashboard::class)->compute($this->first,$request);
        $this->assertSame([1,0,0],array_values($data['daily']));
        $this->assertEquals([10,10,null],$data['targetDaily']);
        $this->assertEquals(150,$data['revenue']);
        $this->assertEquals(30,$data['rewards']);
        $records=route('tenant.programs.subscription-records',['tenantId'=>'company','programId'=>$this->first->id,'from'=>'2026-08-02','to'=>'2026-08-04','currency'=>'PHP']);
        $this->get($records)->assertOk()->assertSee('p2')->assertSee('r1')->assertDontSee('p1');
        $this->get($records.'&referrer=someone-else')->assertOk()->assertSee('No recorded events match this view.');
        $this->get(route('tenant.programs.subscription-records',['tenantId'=>'company','programId'=>$this->second->id]))->assertNotFound();

        $this->post($save,['total'=>-1,'starts_on'=>'2026-08-03','ends_on'=>'2026-08-01'])->assertSessionHasErrors(['total','ends_on']);
        $this->post($save,['total'=>20,'starts_on'=>'2026-08-02','ends_on'=>'2026-08-02'])->assertRedirect();
        $this->assertDatabaseCount('program_referral_targets',1);
        $data=app(\App\Services\Programs\SubscriptionDashboard::class)->compute($this->first,$request);
        $this->assertEquals([20,null,null],$data['targetDaily']);
        $this->post(route('tenant.programs.referral-target',['tenantId'=>'company','programId'=>$this->second->id]),['total'=>20,'starts_on'=>'2026-08-02','ends_on'=>'2026-08-02'])->assertNotFound();
        $this->get(route('tenant.dashboard',['tenantId'=>'company','program_id'=>$this->first->id,'from'=>'2026-08-01','to'=>'2026-08-03']))->assertOk()->assertSee('Referrals vs target')->assertSee('Edit target');
        Tenant::create(['id'=>'another','name'=>'Another','status'=>'active']);
        $foreign=Program::create(['tenant_id'=>'another','name'=>'Foreign','status'=>'active','operating_mode'=>'automated']);
        $this->post(route('tenant.programs.referral-target',['tenantId'=>'company','programId'=>$foreign->id]),['total'=>20,'starts_on'=>'2026-08-02','ends_on'=>'2026-08-02'])->assertNotFound();
    }

    public function test_campaign_duration_drives_dashboard_and_target_in_program_timezone(): void
    {
        (require database_path('migrations/2026_09_20_000006_create_program_referral_targets.php'))->up();
        $this->first->update(['timezone'=>'Asia/Manila']);
        $save=route('tenant.programs.campaign-duration',['tenantId'=>'company','programId'=>$this->first->id]);
        $this->actingAs($this->owner,'tenant')->post($save,['campaign_start'=>'2026-08-01','campaign_end'=>'2026-08-03'])->assertRedirect();
        $this->first->refresh();
        $this->assertSame('2026-07-31 16:00:00',$this->first->referral_period_opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-03 15:59:59',$this->first->referral_period_closes_at->format('Y-m-d H:i:s'));
        $targetRoute=route('tenant.programs.referral-target',['tenantId'=>'company','programId'=>$this->first->id]);
        // A submitted target cannot override the campaign dates.
        $this->post($targetRoute,['total'=>30,'starts_on'=>'2025-01-01','ends_on'=>'2025-01-02'])->assertRedirect();
        $service=app(\App\Services\Programs\SubscriptionDashboard::class);
        $data=$service->compute($this->first,\Illuminate\Http\Request::create('/'));
        $this->assertSame('2026-08-01',$data['from']->toDateString());
        $this->assertSame('2026-08-03',$data['to']->toDateString());
        $this->assertEquals(10,$data['average']);
        $this->post($save,['campaign_start'=>'2026-08-01','campaign_end'=>'2026-08-06'])->assertRedirect();
        $data=$service->compute($this->first->fresh(),\Illuminate\Http\Request::create('/','GET',['from'=>'2026-08-05','to'=>'2026-08-07']));
        $this->assertEquals(30,$data['target']->total);
        $this->assertEquals([5,5,null],$data['targetDaily']);
        $this->assertSame(6,$data['campaign']['days']);
        $this->post($save,['campaign_start'=>'2026-08-02','campaign_end'=>'2026-08-02'])->assertRedirect();
        $data=$service->compute($this->first->fresh(),\Illuminate\Http\Request::create('/'));
        $this->assertEquals(30,$data['average']);
        $this->assertCount(1,$data['daily']);
        $this->get(route('tenant.dashboard',['tenantId'=>'company','program_id'=>$this->first->id]))->assertOk()->assertSee('Full campaign')->assertSee('Edit duration');
        $this->post($save,['campaign_start'=>'2026-08-03','campaign_end'=>'2026-08-01'])->assertSessionHasErrorsIn('duration','campaign_end');
        $this->assertSame('2026-08-02',app(\App\Services\Programs\CampaignPeriod::class)->forProgram($this->first->fresh())['starts_on']);
        $this->post(route('tenant.programs.campaign-duration',['tenantId'=>'company','programId'=>$this->second->id]),['campaign_start'=>'2026-08-01','campaign_end'=>'2026-08-02'])->assertNotFound();
        $outsider=TenantUser::create(['id'=>'duration-outsider','email'=>'duration@example.com','password'=>'x','status'=>'active']);
        $this->actingAs($outsider,'tenant')->post($save,['campaign_start'=>'2026-08-01','campaign_end'=>'2026-08-02'])->assertForbidden();
    }
    public function test_campaign_period_state_uses_local_dates_and_protects_lgu(): void
    {
        $service=app(\App\Services\Programs\CampaignPeriod::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-08-01 16:00:00','UTC'));
        $program=new Program(['tenant_id'=>'company','timezone'=>'Asia/Manila','referral_period_opens_at'=>'2026-08-01 16:00:00','referral_period_closes_at'=>'2026-08-02 15:59:59']);
        $this->assertSame('In progress',$service->forProgram($program)['state']);
        $this->travelTo(\Carbon\Carbon::parse('2026-08-01 15:59:59','UTC'));
        $this->assertSame('Upcoming',$service->forProgram($program)['state']);
        $this->travelTo(\Carbon\Carbon::parse('2026-08-02 16:00:00','UTC'));
        $this->assertSame('Completed',$service->forProgram($program)['state']);
        $program->tenant_id='lgu-ids';
        $this->assertNull($service->forProgram($program));
        $this->travelBack();
    }

    public function test_referrer_design_scopes_chart_currencies_and_refund_adjusted_review_counts(): void
    {
        $this->first->update(['default_currency'=>'PHP','timezone'=>'Asia/Manila']);
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        foreach([['paid','payment',2000,'PHP',$this->referrer->id],['reversal','refund',-2000,'PHP',$this->referrer->id],['foreign','payment',990000,'PHP','another'],['usd','payment',500,'USD',$this->referrer->id]] as [$id,$type,$reward,$currency,$referrerId]) {
            DB::table('program_conversion_events')->insert(['id'=>$id,'connection_id'=>$connection->id,'external_id'=>$id,'customer_id'=>'c','invoice_id'=>$currency,'referrer_id'=>$referrerId,'payload_hash'=>str_repeat('a',64),'type'=>$type,'currency'=>$currency,'amount_minor'=>10000,'reward_minor'=>$reward,'status'=>'pending_review','occurred_at'=>now()->subDay(),'available_at'=>now()->subHour()]);
        }
        $url=route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->first->id,'days'=>7]);
        $response=$this->actingAs($this->referrer,'reseller')->get($url)->assertOk()->assertSee('Your reward activity')->assertSee('Copy referral link')->assertDontSee('9,900.00');
        $d=$response->viewData('subscriptionDashboard');
        $this->assertEquals(0,$d['net']);
        $this->assertEquals(0,$d['ready']);
        $this->assertCount(7,$d['daily']);
        $this->assertEquals(0,array_sum($d['daily']));
        $this->assertEquals(1,$d['customers']);
        $this->assertEquals(1,$d['payments']);
        $this->get($url.'&currency=USD')->assertOk()->assertViewHas('subscriptionDashboard',fn($s)=>$s['net']===500 && $s['ready']===1);
        $this->get($url.'&currency=EUR')->assertSessionHasNoErrors()->assertStatus(422);
        $this->get(route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->second->id]))->assertOk()->assertDontSee('Your reward activity');
    }

}
