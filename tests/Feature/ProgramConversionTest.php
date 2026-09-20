<?php
namespace Tests\Feature;

use App\Models\{ProgramConnection, Tenant, TenantUser, ReferrerProgramMembership};
use App\Services\QuickProgram\QuickProgramService;
use Illuminate\Support\Facades\DB;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;

class ProgramConversionTest extends TestCase
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
    private function payload(array $overrides=[]): array
    {
        return array_replace(['event_id'=>'event-1','type'=>'payment','customer_id'=>'customer-1','invoice_id'=>'invoice-1','membership_id'=>$this->member,
            'currency'=>'PHP','amount_minor'=>100000,'occurred_at'=>now()->toIso8601String(),'referred_at'=>now()->subDay()->toIso8601String(),'first_payment'=>true,'self_referral'=>false],$overrides);
    }
    private function sendEvent(array $data, bool $valid=true, ?int $time=null)
    {
        $body=json_encode($data); $time ??= time();
        $signature=hash_hmac('sha256',$time.'.'.$body,$this->connection->secret);
        return $this->call('POST','/api/program-connections/'.$this->connection->id.'/events',[],[],[],[
            'CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_RB_TIMESTAMP'=>(string)$time,
            'HTTP_X_RB_SIGNATURE'=>$valid ? $signature : 'invalid',
        ],$body);
    }
    public function test_requires_valid_fresh_signature(): void
    {
        $this->sendEvent($this->payload(),false)->assertUnauthorized();
        $this->sendEvent($this->payload(),true,time()-600)->assertUnauthorized();
        $this->assertDatabaseCount('program_conversion_events',0);
    }
    public function test_test_event_does_not_claim_live_tracking(): void
    {
        $this->sendEvent(['event_id'=>'test','type'=>'test'])->assertOk();
        $this->assertSame('tested',$this->connection->fresh()->status);
        $this->assertDatabaseCount('program_conversion_events',0);
    }
    public function test_records_reward_once_and_refunds_reverse_proportionally(): void
    {
        $payment=$this->payload();
        $this->sendEvent($payment)->assertCreated()->assertJsonPath('reward_minor',10000);
        $this->sendEvent($payment)->assertOk()->assertJsonPath('duplicate',true);
        $this->sendEvent(array_replace($payment,['amount_minor'=>200000]))->assertConflict();
        $refund=$this->payload(['event_id'=>'refund-1','type'=>'refund','amount_minor'=>25000]);
        $this->sendEvent($refund)->assertCreated()->assertJsonPath('reward_minor',-2500);
        $this->sendEvent(array_replace($refund,['event_id'=>'refund-2','amount_minor'=>75000]))->assertCreated()->assertJsonPath('reward_minor',-7500);
        $this->assertEquals(0,DB::table('program_conversion_events')->sum('reward_minor'));
        $this->sendEvent(array_replace($refund,['event_id'=>'refund-too-much','amount_minor'=>1]))->assertUnprocessable();
        $this->assertSame('connected',$this->connection->fresh()->status);
    }
    public function test_renewal_outside_duration_records_zero_reward(): void
    {
        $this->sendEvent($this->payload(['occurred_at'=>now()->subMonths(7)->toIso8601String(),'referred_at'=>now()->subMonths(7)->subDay()->toIso8601String()]))->assertCreated();
        $this->sendEvent($this->payload(['event_id'=>'renewal','invoice_id'=>'invoice-2','first_payment'=>false]))
            ->assertCreated()->assertJsonPath('reward_minor',0)->assertJsonPath('status','not_eligible');
    }
    public function test_rejects_unknown_referrer_self_referral_and_existing_customer(): void
    {
        $this->sendEvent($this->payload(['membership_id'=>'foreign-member']))->assertNotFound();
        $this->sendEvent($this->payload(['self_referral'=>true]))->assertUnprocessable();
        $this->sendEvent($this->payload(['first_payment'=>false]))->assertUnprocessable();
        $this->assertDatabaseCount('program_conversion_events',0);
    }
    public function test_rejects_duplicate_invoice_with_new_event_id(): void
    {
        $this->sendEvent($this->payload())->assertCreated();
        $this->sendEvent($this->payload(['event_id'=>'different-id']))->assertConflict();
        $this->assertDatabaseCount('program_conversion_events',1);
    }
    public function test_gethired_net_revenue_and_renewal_are_rewarded_once(): void
    {
        $first=$this->payload(['event_id'=>'gh-payment-first','invoice_id'=>'gh-invoice-first','customer_id'=>'gh-company','amount_minor'=>90000,'occurred_at'=>now()->subMonth()->toIso8601String(),'referred_at'=>now()->subMonth()->subDay()->toIso8601String()]);
        $this->sendEvent($first)->assertCreated()->assertJsonPath('reward_minor',9000)->assertJsonPath('status','pending_review');
        $this->sendEvent($first)->assertOk()->assertJsonPath('duplicate',true);
        $renewal=array_replace($first,['event_id'=>'gh-payment-renewal','invoice_id'=>'gh-invoice-renewal','occurred_at'=>now()->toIso8601String(),'first_payment'=>false]);
        $this->sendEvent($renewal)->assertCreated()->assertJsonPath('reward_minor',9000);
        $this->sendEvent($renewal)->assertOk()->assertJsonPath('duplicate',true);
        $this->assertDatabaseCount('program_conversion_events',2);
        $this->assertEquals(18000,DB::table('program_conversion_events')->sum('reward_minor'));
    }
}
