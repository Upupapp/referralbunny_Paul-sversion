<?php
namespace Tests\Feature;
use App\Models\{Tenant,TenantUser,ProgramConnection,ProgramOfferVersion};
use App\Services\QuickProgram\{QuickProgramService,ProgramFinancialSummary};
use Illuminate\Support\Facades\DB;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;
class ProgramFinancialSummaryTest extends TestCase
{
    use QuickProgramSchema;
    private $program;
    protected function setUp():void {
        parent::setUp();config(['app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','programs.enabled'=>true]);$this->buildQuickProgramSchema();
        Tenant::create(['id'=>'test-sp4s9i','name'=>'Test','status'=>'active']);
        TenantUser::create(['id'=>'owner','email'=>'owner@example.com','password'=>'x','status'=>'active']);
        $this->program=app(QuickProgramService::class)->publish('test-sp4s9i','owner',['website'=>'https://example.com','name'=>'GetHired','pricing_model'=>'subscription','price'=>1000,'currency'=>'PHP','option'=>'recurring','reward_model'=>'percentage','reward_value'=>20,'reward_scope'=>'recurring','duration_months'=>6,'hold_days'=>30]);
    }
    public function test_program_navigation_scopes_online_entry_and_preserves_protected_tenant(): void {
        $nav = app(\App\Services\Programs\ProgramNavigation::class);
        $this->assertFalse($nav->allowsManualDeals('test-sp4s9i'));
        $this->assertTrue($nav->allowsManualDeals('unrelated'));
        $this->assertSame([$this->program->id], $nav->programs('test-sp4s9i')->pluck('id')->all());
        $this->assertCount(0, $nav->programs('unrelated'));
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertTrue($nav->allowsManualDeals('lgu-ids'));
        $this->assertCount(0, $nav->programs('lgu-ids'));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->program->update(['status' => 'archived']);
        $this->assertTrue($nav->allowsManualDeals('test-sp4s9i'));
        $this->assertCount(0, $nav->programs('test-sp4s9i'));
    }
    private function event($id,$type,$currency,$amount,$reward,$connection=null):void {
        DB::table('program_conversion_events')->insert(['id'=>$id,'connection_id'=>$connection??ProgramConnection::first()->id,'external_id'=>$id,'customer_id'=>'customer','invoice_id'=>$id,'payload_hash'=>str_repeat('a',64),'type'=>$type,'currency'=>$currency,'amount_minor'=>$amount,'reward_minor'=>$reward,'status'=>'pending_review','occurred_at'=>now()]);
    }
    public function test_actual_totals_use_recorded_rewards_refunds_and_separate_currencies():void {
        $this->event('p','payment','PHP',100000,20000);$this->event('r','refund','PHP',25000,-5000);$this->event('u','payment','USD',10000,2000);$this->event('unrelated','payment','PHP',9999999,999999,'other-connection');
        ProgramOfferVersion::query()->update(['percentage_rate'=>40]);
        $result=app(ProgramFinancialSummary::class)->forTenant('test-sp4s9i')[0];
        $totals=collect($result['totals'])->keyBy('currency');
        $this->assertEquals(750,$totals['PHP']['revenue']);$this->assertEquals(150,$totals['PHP']['reward']);$this->assertEquals(600,$totals['PHP']['retained']);$this->assertEquals(20,$totals['USD']['reward']);
        $this->assertSame('40%',$result['terms'][0]['label']);
        $html=view('tenant.programs._financial-dashboard',['programFinancials'=>[$result],'tenant'=>Tenant::first()])->render();
        $this->assertStringNotContainsString('Base Cost',$html);$this->assertStringNotContainsString('70%',$html);$this->assertStringContainsString('40%',$html);
    }
    public function test_terms_follow_percentage_fixed_scope_and_hold_rules():void {
        $service=app(ProgramFinancialSummary::class);$term=$service->forTenant('test-sp4s9i')[0]['terms'][0];
        $this->assertSame('20%',$term['label']);$this->assertStringContainsString('6 months',$term['scope']);$this->assertSame(30,$term['hold']);
        ProgramOfferVersion::first()->update(['reward_model'=>'fixed','fixed_amount'=>250,'reward_rules'=>['scope'=>'first_payment','basis'=>'net_collected_excluding_tax','hold_days'=>7]]);
        $result=$service->forTenant('test-sp4s9i');$term=$result[0]['terms'][0];$this->assertSame('PHP 250.00',$term['label']);$this->assertSame('First eligible payment only.',$term['scope']);$this->assertSame(7,$term['hold']);
        $html=view('tenant.programs._financial-detail',['programFinancials'=>$result,'tenant'=>Tenant::first(),'ssrLead'=>['deal_value'=>1000]])->render();$this->assertStringContainsString('Preview only',$html);$this->assertStringContainsString('PHP 250.00',$html);$this->assertStringNotContainsString('30%',$html);
    }
    public function test_sidebar_counts_hold_expiry_and_refund_reversals():void {
        $this->event('held','payment','PHP',100000,20000);
        $this->event('ready','payment','PHP',100000,20000);
        $this->event('reversed','payment','PHP',100000,20000);
        $this->event('refund','refund','PHP',100000,-20000);
        DB::table('program_conversion_events')->where('id','held')->update(['available_at'=>now()->addDay()]);
        DB::table('program_conversion_events')->whereIn('id',['ready','reversed'])->update(['available_at'=>now()->subDay()]);
        DB::table('program_conversion_events')->where('id','refund')->update(['invoice_id'=>'reversed']);
        DB::table('resellers')->insert(['id'=>'referrer','tenant_id'=>'test-sp4s9i','name'=>'Referrer','email'=>'test@example.com','status'=>'active']);
        \App\Models\ReferrerProgramMembership::create(['tenant_id'=>'test-sp4s9i','program_id'=>$this->program->id,'reseller_id'=>'referrer','status'=>'active']);
        $signals=app(ProgramFinancialSummary::class)->forTenant('test-sp4s9i')[0]['signals'];
        $this->assertSame(1,$signals['on_hold']);$this->assertSame(1,$signals['ready']);$this->assertCount(1,$signals['customers']);$this->assertSame(['referrer'],$signals['referrers']);
        DB::table('resellers')->where('id','referrer')->update(['deleted_at'=>now()]);
        $this->assertSame([],app(ProgramFinancialSummary::class)->forTenant('test-sp4s9i')[0]['signals']['referrers']);
    }
    public function test_protected_and_other_tenants_are_not_queried_or_changed():void {
        DB::enableQueryLog();DB::flushQueryLog();$service=app(ProgramFinancialSummary::class);
        $this->assertNull($service->forTenant('lgu-ids'));$this->assertNull($service->forTenant('another-company'));$this->assertSame([],DB::getQueryLog());
    }
}
