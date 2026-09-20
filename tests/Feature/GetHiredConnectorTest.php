<?php
namespace Tests\Feature;
use App\Models\{Tenant,TenantUser,TenantMembership,ProgramConnection};
use Illuminate\Support\Facades\Http;
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;
class GetHiredConnectorTest extends TestCase
{
    use QuickProgramSchema;
    private ProgramConnection $connection;
    private string $base;
    protected function setUp():void {
        parent::setUp();config(['app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','programs.enabled'=>true,'services.gethired.enabled'=>true,'services.gethired.client_secret'=>str_repeat('x',64)]);
        $this->buildQuickProgramSchema();\Illuminate\Support\Facades\Schema::create('tenant_brand_profiles', function ($table) { $table->string('tenant_id'); $table->string('status'); });$this->withoutVite();Http::preventStrayRequests();
        Tenant::create(['id'=>'acme','name'=>'Acme','status'=>'active']);
        $user=TenantUser::create(['id'=>'owner','email'=>'owner@example.com','password'=>bcrypt('password'),'status'=>'active']);
        TenantMembership::create(['tenant_id'=>'acme','tenant_user_id'=>'owner','role'=>'owner','status'=>'active']);$this->actingAs($user,'tenant');
        $this->postJson('/tenant/acme/quick-program/publish',['website'=>'gethiredonline.app','name'=>'GetHired Referrals','pricing_model'=>'subscription','price'=>1000,'currency'=>'PHP','option'=>'first','reward_model'=>'percentage','reward_value'=>20,'reward_scope'=>'first_payment','duration_months'=>6,'hold_days'=>30,'confirmed'=>true])->assertOk();
        $this->connection=ProgramConnection::first();$this->base='/tenant/acme/quick-program/connection/'.$this->connection->program_id.'/gethired';
    }
    public function test_owner_connection_uses_pinned_server_authorization_without_extra_login():void {
        config(['services.gethired.owner_connection_id'=>$this->connection->id]);
        Http::fake(['*/owner-connect'=>Http::response(['connectionId'=>$this->connection->id])]);
        $this->post($this->base)->assertRedirect('/tenant/acme/quick-program/connection/'.$this->connection->program_id);
        $this->assertSame('gethired',$this->connection->fresh()->platform);
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/owner-connect') && $r['programId']===$this->connection->program_id && $r['eventSecret']===$this->connection->secret);
        Http::assertSentCount(1);
        config(['services.gethired.owner_connection_id'=>'another-connection']);
        $this->post($this->base)->assertForbidden();
        Http::assertSentCount(1);
        config(['services.gethired.owner_connection_id'=>$this->connection->id]);
        TenantMembership::where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->post($this->base)->assertForbidden();
        Http::assertSentCount(1);
    }
    public function test_authorization_binds_session_and_pkce_and_does_not_enable_payments():void {
        Http::fake(['*/requests'=>Http::response(['requestId'=>str_repeat('a',64)]),'*/exchange'=>Http::response(['connectionId'=>$this->connection->id])]);
        $this->post($this->base)->assertRedirect('https://gethiredonline.app/integrations/referral-bunny?request='.str_repeat('a',64));
        $pending=session('gethired.'.$this->connection->id);
        $this->get($this->base.'/callback?state=wrong&code='.str_repeat('b',64))->assertForbidden();
        $this->get($this->base.'/callback?'.http_build_query(['state'=>$pending['state'],'code'=>str_repeat('b',64)]))->assertRedirect();
        $this->assertSame('gethired',$this->connection->fresh()->platform);
        $this->assertSame('not_connected',$this->connection->fresh()->status);
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/exchange') && $r['verifier']===$pending['verifier'] && $r['eventSecret']===$this->connection->secret);
        $this->get($this->base.'/callback?'.http_build_query(['state'=>$pending['state'],'code'=>str_repeat('b',64)]))->assertRedirect()->assertSessionHasErrors('platform');
    }
    public function test_viewer_cannot_connect_and_cancel_retains_program():void {
        Http::fake(['*/requests'=>Http::response(['requestId'=>str_repeat('a',64)])]);
        $this->post($this->base)->assertRedirect();$pending=session('gethired.'.$this->connection->id);
        $this->get($this->base.'/callback?'.http_build_query(['state'=>$pending['state'],'error'=>'access_denied']))->assertRedirect();
        $this->assertNull($this->connection->fresh()->platform);
        TenantMembership::where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->post($this->base)->assertForbidden();
        $this->assertDatabaseCount('programs',1);
    }
    public function test_failed_disconnect_preserves_connection_and_success_removes_it():void {
        $this->connection->update(['platform'=>'gethired','platform_connected_at'=>now()]);
        Http::fake(['*/disconnect'=>Http::sequence()->push([],503)->push(['disconnected'=>true])]);
        $this->postJson($this->base.'/disconnect')->assertUnprocessable();
        $this->assertSame('gethired',$this->connection->fresh()->platform);
        $this->post($this->base.'/disconnect')->assertRedirect();
        $this->assertNull($this->connection->fresh()->platform);
    }
    public function test_referral_validation_requires_signature_and_excludes_email():void {
        \Illuminate\Support\Facades\DB::table('resellers')->insert(['id'=>'referrer','tenant_id'=>'acme','name'=>'Referrer','email'=>'referrer@example.com','status'=>'active']);
        $member=\App\Models\ReferrerProgramMembership::create(['tenant_id'=>'acme','program_id'=>$this->connection->program_id,'reseller_id'=>'referrer','status'=>'active']);
        $body=json_encode(['membership_id'=>$member->id]);$timestamp=(string)time();
        $path='/api/program-connections/'.$this->connection->id.'/referrals/validate';
        $this->postJson($path,['membership_id'=>$member->id])->assertUnauthorized();
        $headers=['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_RB_TIMESTAMP'=>$timestamp,'HTTP_X_RB_SIGNATURE'=>hash_hmac('sha256',$timestamp.'.'.$body,$this->connection->secret)];
        $res=$this->call('POST',$path,[],[],[],$headers,$body)->assertOk()->assertJsonPath('membershipId',$member->id);
        $this->assertStringNotContainsString('referrer@example.com',$res->getContent());
        $member->update(['status'=>'paused']);
        $this->call('POST',$path,[],[],[],$headers,$body)->assertNotFound();
    }
    public function test_live_status_overrides_saved_flag_and_network_failure_does_not_disconnect():void {
        $this->connection->update(['platform'=>'gethired','platform_connected_at'=>now()]);
        Http::fake(['*/status'=>Http::sequence()
            ->push(['account'=>'disconnected','signups'=>'not_connected','payments'=>'not_available','secret'=>'must-not-leak'])
            ->push([],503)
            ->push(['account'=>'reconnect_required','signups'=>'paused','payments'=>'not_available'])]);
        $this->getJson($this->base.'/status')->assertOk()->assertJsonPath('account','disconnected')->assertJsonMissingPath('secret')->assertHeader('Cache-Control','no-store, private');
        $this->getJson($this->base.'/status')->assertOk()->assertJsonPath('account','unknown');
        $this->assertSame('gethired',$this->connection->fresh()->platform);
        $this->getJson($this->base.'/status')->assertOk()->assertJsonPath('account','reconnect_required');
        TenantMembership::where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->getJson($this->base.'/status')->assertForbidden();
    }
    public function test_expired_callback_returns_to_connection_page_and_disconnect_invalidates_pending_request():void {
        Http::fake(['*/requests'=>Http::response(['requestId'=>str_repeat('a',64)]),'*/disconnect'=>Http::response(['disconnected'=>true])]);
        $this->post($this->base)->assertRedirect();
        $this->travel(11)->minutes();
        $this->get($this->base.'/callback?state=expired')->assertRedirect()->assertSessionHasErrors('platform');
        $this->assertNull(session('gethired.'.$this->connection->id));
        $this->travelBack();$this->post($this->base)->assertRedirect();
        $this->post($this->base.'/disconnect')->assertRedirect();
        $this->assertNull(session('gethired.'.$this->connection->id));
    }
    public function test_payment_delivery_status_is_exposed_without_granting_readiness_from_account_connection():void {
        Http::fake(['*/status'=>Http::sequence()
            ->push(['account'=>'connected','signups'=>'ready','payments'=>'authorization_required'])
            ->push(['account'=>'connected','signups'=>'ready','payments'=>'ready'])
            ->push(['account'=>'connected','signups'=>'ready','payments'=>'receiving'])
            ->push(['account'=>'connected','signups'=>'ready','payments'=>'attention'])]);
        foreach(['authorization_required','ready','receiving','attention'] as $state) {
            $this->getJson($this->base.'/status')->assertOk()->assertJsonPath('payments',$state);
        }
    }
    public function test_review_is_owner_admin_only_and_retry_uses_authenticated_actor():void {
        Http::fake(['*/review'=>Http::response(['items'=>[['id'=>str_repeat('a',64),'invoice_id'=>'invoice-1','amount_minor'=>'90000','currency'=>'PHP','attempts'=>2,'next_attempt_at'=>null,'reason'=>'DELIVERY_FAILED','canRetry'=>true]],'history'=>[],'hasMore'=>false]),'*/review/retry'=>Http::response(['queued'=>true])]);
        $this->get($this->base.'/review?status=failed')->assertOk()->assertSee('Queue a retry')->assertSee('900.00');
        $this->post($this->base.'/review/retry',['kind'=>'payment','id'=>str_repeat('a',64),'note'=>'Checked connection','confirmed'=>1,'actorId'=>'forged'])->assertRedirect()->assertSessionHas('review_message');
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/review/retry') && $r['actorId']==='owner' && $r['connectionId']===$this->connection->id);
        $this->postJson($this->base.'/review/retry',['kind'=>'payment','id'=>str_repeat('a',64),'note'=>'Checked connection'])->assertUnprocessable();
        TenantMembership::where('tenant_user_id','owner')->update(['role'=>'viewer']);
        $this->get($this->base.'/review')->assertForbidden();
        $this->post($this->base.'/review/retry')->assertForbidden();
    }
    public function test_review_handles_outage_and_escapes_audit_notes():void {
        Http::fake(['*/review'=>Http::sequence()->push([],503)->push(['items'=>[],'hasMore'=>false,'history'=>[['created_at'=>'2026-09-20T00:00:00Z','actor_id'=>'owner','event_kind'=>'payment','delivery_id'=>'id','note'=>'<script>alert(1)</script>']]])]);
        $this->get($this->base.'/review')->assertOk()->assertSee('review list is unavailable');
        $this->get($this->base.'/review')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }
}
