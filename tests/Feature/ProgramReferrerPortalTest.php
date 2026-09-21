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
    public function test_admin_uploads_and_replaces_logo_used_by_referrer_cards(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $url = route('tenant.programs.logo', ['company', $this->first->id]);
        $file = fn() => \Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png', file_get_contents(public_path('images/programs/test-sp4s9i/8184125a-ace1-4db8-b7d9-884e692002b4.png')));
        $this->actingAs($this->owner, 'tenant')->post($url, ['logo'=>$file()])->assertRedirect()->assertSessionHas('success');
        $old = $this->first->fresh()->logo_path;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($old);
        $this->actingAs($this->owner, 'tenant')->post($url, ['logo'=>$file()])->assertRedirect();
        $new = $this->first->fresh()->logo_path;
        $this->assertNotEquals($old, $new);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($old);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($new);
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.programs.index','company'))->assertOk()->assertSee($this->first->fresh()->logoUrl());
    }

    public function test_logo_rejects_invalid_files_cross_tenant_and_protected_tenant(): void
    {
        $url = route('tenant.programs.logo', ['company', $this->first->id]);
        $this->actingAs($this->owner, 'tenant')->post($url, ['logo'=>\Illuminate\Http\UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->post($url, ['logo'=>\Illuminate\Http\UploadedFile::fake()->create('large.png', 2049, 'image/png')])->assertSessionHasErrors('logo');
        $this->assertNull($this->first->fresh()->logo_path);
        $this->post(route('tenant.programs.logo', ['other', $this->first->id]))->assertForbidden();
        $this->post(route('tenant.programs.logo', ['lgu-ids', $this->first->id]))->assertForbidden();
        $outsider=TenantUser::create(['id'=>'logo-outsider','email'=>'logo-out@example.com','password'=>'x','status'=>'active']);
        $this->actingAs($outsider,'tenant')->post($url)->assertForbidden();
    }

    public function test_program_card_dates_use_local_day_boundaries_and_net_refunds(): void
    {
        $this->first->update(['timezone'=>'Asia/Manila']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        foreach (['2026-09-19 15:59:59','2026-09-19 16:00:00','2026-09-20 15:59:59','2026-09-20 16:00:00'] as $i=>$date) {
            DB::table('program_referral_clicks')->insert(['id'=>(string) \Illuminate\Support\Str::uuid(),'membership_id'=>$member->id,'visitor_hash'=>str_repeat('a',64),'created_at'=>$date]);
            DB::table('program_conversion_events')->insert(['id'=>'date'.$i,'connection_id'=>$connection->id,'external_id'=>'date'.$i,'customer_id'=>'c'.$i,'invoice_id'=>'i'.$i,'referrer_id'=>$this->referrer->id,'payload_hash'=>str_repeat('a',64),'type'=>$i===2?'refund':'payment','currency'=>'PHP','amount_minor'=>10000,'reward_minor'=>$i===2?-100:200,'status'=>'pending_review','occurred_at'=>$date]);
        }
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.programs.index','company').'?range=custom&from=2026-09-20&to=2026-09-20')->assertOk()->assertViewHas('cards',fn($cards)=>$cards[$this->first->id]['clicks']===2 && $cards[$this->first->id]['paying']===1 && (int)$cards[$this->first->id]['rewards']->first()->net===100);
        $this->get(route('reseller.programs.index','company').'?range=custom&from=2026-09-21&to=2026-09-20')->assertSessionHasErrors('to');
        $this->get(route('reseller.programs.index','company').'?range=custom')->assertSessionHasErrors(['from','to']);
    }

    public function test_invite_delivery_checks_provider_without_resending_or_losing_membership_metadata(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $id=(string) \Illuminate\Support\Str::uuid();
        $member->update(['metadata'=>['activate_on_setup'=>true,'invite_delivery'=>['attempt'=>'attempt-1','provider_id'=>$id,'status'=>'sent']]]);
        config(['services.resend.key'=>'test-key']);
        \Illuminate\Support\Facades\Http::fake(['api.resend.com/emails/'.$id=>\Illuminate\Support\Facades\Http::sequence()->push(['id'=>$id,'last_event'=>'delivered'])->push([],429)]);
        \Illuminate\Support\Facades\Mail::fake();
        $url=route('tenant.programs.members.delivery',['company',$this->first->id,$member->id]);
        $this->actingAs($this->owner,'tenant')->post($url)->assertRedirect()->assertSessionHas('delivery_notice','Delivery status refreshed.');
        $this->assertSame('delivered',$member->fresh()->metadata['invite_delivery']['status']);
        $this->assertTrue($member->fresh()->metadata['activate_on_setup']);
        $this->post($url)->assertRedirect();
        $this->assertSame('delivered',$member->fresh()->metadata['invite_delivery']['status']);
        $this->post(route('tenant.programs.members.delivery',['company',$this->second->id,$member->id]))->assertNotFound();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_delivery_event_mappings_and_stale_attempts(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $id=(string) \Illuminate\Support\Str::uuid();
        $member->update(['metadata'=>['invite_delivery'=>['attempt'=>'latest','provider_id'=>$id,'status'=>'sent']]]);
        config(['services.resend.key'=>'test-key']);
        $service=app(\App\Services\Programs\ProgramInviteDelivery::class);
        $event='';
        \Illuminate\Support\Facades\Http::fake(function()use($id,&$event){return \Illuminate\Support\Facades\Http::response(['id'=>$id,'last_event'=>$event]);});
        foreach(['bounced'=>'bounced','complained'=>'complained','delivery_delayed'=>'delayed','opened'=>'delivered'] as $event=>$expected){
            $this->assertTrue($service->check($member->fresh()));
            $this->assertSame($expected,$member->fresh()->metadata['invite_delivery']['status']);
        }
        $service->record($member,'old-attempt',['status'=>'failed']);
        $this->assertSame('delivered',$member->fresh()->metadata['invite_delivery']['status']);
    }

    public function test_delivery_filters_and_expiry_labels_are_program_scoped(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $member->update(['status'=>'invited','metadata'=>['invite_delivery'=>['status'=>'bounced']]]);
        $this->referrer->forceFill(['created_at'=>now()->subDays(91)])->save();
        $url=route('tenant.referrers',['tenantId'=>'company','program_id'=>$this->first->id]);
        $this->actingAs($this->owner,'tenant')->get($url.'&delivery=bounced')->assertOk()->assertSee('Setup link expired')->assertViewHas('rows',fn($rows)=>$rows->total()===1);
        $this->get($url.'&delivery=delivered')->assertOk()->assertViewHas('rows',fn($rows)=>$rows->total()===0);
        $this->get($url.'&delivery=invalid')->assertSessionHasErrors('delivery');
    }

    public function test_acceptance_notification_identifies_program_and_protected_tenants_are_skipped(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $member->update(['source'=>'invite','joined_at'=>now(),'metadata'=>['activate_on_setup'=>true]]);
        $this->mock(\App\Services\NotificationDispatchService::class,fn($mock)=>$mock->shouldReceive('dispatchToTenantAdmins')->once()->withArgs(fn($tenant,$category,$priority,$title,$body,$url,$label,$dedupe,$meta)=>$tenant==='company' && str_contains($body,'Online') && str_contains($url,$this->first->id) && $meta['membership_id']===$member->id));
        $service=app(\App\Services\Programs\ProgramInviteAcceptance::class);
        $this->assertTrue($service->notify('company',$this->referrer->id,'Referrer'));
        $this->assertFalse($service->notify('lgu-ids',$this->referrer->id,'Referrer'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','programs.enabled'=>true]);
        $this->buildQuickProgramSchema();
        (require database_path('migrations/2026_09_21_000001_add_program_logo_path.php'))->up();
        (require database_path('migrations/2026_09_20_000009_create_program_signup_tracking.php'))->up();
        (require database_path('migrations/2026_09_20_000008_create_program_referral_clicks.php'))->up();
        (require database_path('migrations/2026_09_20_000007_create_program_referral_links.php'))->up();
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
    public function test_program_cards_only_show_the_signed_in_referrers_activity(): void
    {
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->where('reseller_id',$this->referrer->id)->first();
        DB::table('program_referral_clicks')->insert(['id'=>(string) \Illuminate\Support\Str::uuid(),'membership_id'=>$member->id,'visitor_hash'=>str_repeat('a',64),'created_at'=>now()]);
        $other=Reseller::create(['tenant_id'=>'company','name'=>'Other','email'=>'else@example.com','status'=>'active']);
        $foreign=ReferrerProgramMembership::create(['tenant_id'=>'company','program_id'=>$this->first->id,'reseller_id'=>$other->id,'status'=>'active']);
        DB::table('program_referral_clicks')->insert(['id'=>(string) \Illuminate\Support\Str::uuid(),'membership_id'=>$foreign->id,'visitor_hash'=>str_repeat('b',64),'created_at'=>now()]);
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.programs.index','company'))->assertOk()
            ->assertSee('Grow your network with')->assertSee('Verified signups')->assertSee('Program mechanics')
            ->assertViewHas('cards',fn($c)=>$c[$this->first->id]['clicks']===1 && $c[$this->first->id]['signups']===null && $c[$this->second->id]['manual']===true);
    }
    public function test_referrers_page_is_scoped_to_program_and_mode(): void
    {
        $url=route('tenant.referrers','company');
        $this->actingAs($this->owner,'tenant')->get($url.'?program_id='.$this->first->id)->assertOk()->assertSee('PAYING CUSTOMERS')->assertSee('Invite a group')->assertSee('Import referrers')->assertDontSee('EST. COMMISSION');
        $this->get($url.'?program_id='.$this->second->id)->assertOk()->assertSee('ASSIGNED REFERRALS')->assertDontSee('PAYING CUSTOMERS');
        $this->get($url.'?program_id=foreign')->assertNotFound();
    }
    public function test_program_invite_sends_setup_link_and_enrolls_after_account_setup(): void
    {
        Schema::table('resellers',function(Blueprint $t){$t->string('password')->nullable();$t->string('setup_token')->nullable();$t->string('phone')->nullable();$t->string('territory')->nullable();$t->date('joined_date')->nullable();});
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Event::fake([\App\Events\ResellerJoined::class,\App\Events\InviteAcceptedEvent::class]);
        $this->mock(\App\Services\TenantPlanService::class,fn($m)=>$m->shouldReceive('canInviteReferrer')->once()->with('company')->andReturn(['allowed'=>true]));
        $url=route('tenant.programs.members.invite',['tenantId'=>'company','programId'=>$this->first->id]);
        $this->actingAs($this->owner,'tenant')->post($url,['name'=>'Invite Test','email'=>'invite@example.com'])->assertRedirect()->assertSessionHas('success');
        $r=Reseller::where('email','invite@example.com')->firstOrFail();
        $token=$r->setup_token;
        $this->assertDatabaseHas('referrer_program_memberships',['program_id'=>$this->first->id,'reseller_id'=>$r->id,'status'=>'invited']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ProgramReferrerInvitation::class,fn($m)=>$m->hasTo('invite@example.com') && str_contains($m->inviteUrl,$token) && $m->programName===$this->first->name);
        $this->post($url,['name'=>'Invite Test','email'=>'invite@example.com'])->assertRedirect()->assertSessionHasErrors('email');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ProgramReferrerInvitation::class,1);
        $this->travel(11)->minutes();
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->where('reseller_id',$r->id)->firstOrFail();
        $this->post(route('tenant.programs.members.resend',['company',$this->first->id,$member->id]))->assertRedirect()->assertSessionHas('delivery_notice');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ProgramReferrerInvitation::class,2);
        $this->assertSame($token,$r->fresh()->setup_token);
        $this->assertSame(1,Reseller::where('email','invite@example.com')->count());
        auth('tenant')->logout();
        $this->get(route('reseller.setup',['token'=>$token]))->assertOk()->assertSee('Invite Test');
        $this->post(route('reseller.setup.post'),['token'=>$token,'password'=>'LocalTest123!','password_confirmation'=>'LocalTest123!'])->assertRedirect(route('reseller.dashboard','company'));
        $this->assertNull($r->fresh()->setup_token);
        $this->assertDatabaseHas('referrer_program_memberships',['program_id'=>$this->first->id,'reseller_id'=>$r->id,'status'=>'active']);
        $this->assertSame('active',$r->fresh()->status);
        $this->get(route('reseller.programs.show',['tenantId'=>'company','programId'=>$this->first->id,'tab'=>'mechanics']))->assertOk();
        $this->post(route('reseller.setup.post'),['token'=>$token,'password'=>'LocalTest456!','password_confirmation'=>'LocalTest456!'])->assertSessionHasErrors('token');
    }
    public function test_mechanics_are_program_specific_and_manual_does_not_inherit_online_rules(): void
    {
        $this->first->update(['attribution_window_days'=>45]);
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.programs.show',['tenantId'=>'company','programId'=>$this->first->id,'tab'=>'mechanics']))
            ->assertOk()->assertSee('Program mechanics')->assertSee('45 days')->assertSee('Reward terms not published yet');
        $this->get(route('reseller.programs.show',['tenantId'=>'company','programId'=>$this->second->id,'tab'=>'mechanics']))
            ->assertOk()->assertSee('agreed process')->assertDontSee('first qualifying payment must arrive');
        $private=Program::create(['tenant_id'=>'company','name'=>'Private mechanics','status'=>'active','public_visibility'=>'private']);
        $this->get(route('reseller.programs.show',['tenantId'=>'company','programId'=>$private->id,'tab'=>'mechanics']))->assertNotFound();
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
        $this->assertMatchesRegularExpression('~/r/[a-z0-9]{8}$~', $link);
        $this->assertSame($link, app(\App\Services\Programs\ReferrerReferralLink::class)->forMembership($this->first,$member));
        $short=$this->get($link)->assertOk()->assertSee('og:image',false)->assertSee('summary_large_image')->assertSee('window.location.replace',false);
        $destination=$short->viewData('destination');
        $this->get($link.'?preview=1&url=https://evil.example')->assertOk()->assertDontSee('window.location.replace',false)->assertViewHas('destination',$destination);
        parse_str(parse_url($destination,PHP_URL_QUERY),$query);
        $this->assertSame(['campaign'=>'summer','rb_program'=>$this->first->id,'rb_ref'=>$member->id],$query);
        $this->assertSame('pricing',parse_url($destination,PHP_URL_FRAGMENT));
        $this->assertSame('/plans',parse_url($destination,PHP_URL_PATH));
        $this->assertNull($member->fresh()->referral_code);
        $this->get(route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->second->id]))->assertOk()->assertDontSee('Copy referral link');
        $member->update(['status'=>'approved']);
        $this->get($link)->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('Copy referral link');
        $member->update(['status'=>'active']);
        $this->first->update(['status'=>'paused']);
        $this->get($link)->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('Copy referral link');
    }
    public function test_short_link_clicks_count_browser_visits_once_and_scope_metrics(): void
    {
        ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $link=app(\App\Services\Programs\ReferrerReferralLink::class)->forMembership($this->first,$member);
        $click=$link.'/click';
        $visitor=(string) \Illuminate\Support\Str::uuid();
        $page=$this->withCookie('rb_link_visitor',$visitor)->get($link)->assertOk();
        $this->assertDatabaseCount('program_referral_clicks',0);
        $token=$page->viewData('clickToken');
        $this->assertNotNull($token);
        $this->post($click,['token'=>$token])->assertNoContent();
        $this->post($click,['token'=>$token])->assertNoContent();
        $this->assertDatabaseCount('program_referral_clicks',1);
        $page=$this->withCookie('rb_link_visitor',$visitor)->get($link)->assertOk();
        $this->post($click,['token'=>$page->viewData('clickToken')])->assertNoContent();
        $page=$this->withCookie('rb_link_visitor',(string) \Illuminate\Support\Str::uuid())->get($link)->assertOk();
        $this->post($click,['token'=>$page->viewData('clickToken')])->assertNoContent();
        $this->assertDatabaseCount('program_referral_clicks',3);
        $this->assertSame(2,DB::table('program_referral_clicks')->distinct()->count('visitor_hash'));
        $other=ReferrerProgramMembership::where('program_id',$this->second->id)->first();
        DB::table('program_referral_clicks')->insert(['id'=>(string) \Illuminate\Support\Str::uuid(),'membership_id'=>$other->id,'visitor_hash'=>str_repeat('a',64),'created_at'=>now()]);
        $data=app(\App\Services\Programs\ReferrerSubscriptionDashboard::class)->compute($this->first,$this->referrer,\Illuminate\Http\Request::create('/'));
        $this->assertSame(3,$data['linkClicks']);
        $this->assertSame(2,$data['uniqueBrowsers']);
        $this->assertSame(3,$data['periodClicks']);
        $this->travel(31)->days();
        $data=app(\App\Services\Programs\ReferrerSubscriptionDashboard::class)->compute($this->first,$this->referrer,\Illuminate\Http\Request::create('/'));
        $this->assertSame(3,$data['linkClicks']);
        $this->assertSame(0,$data['periodClicks']);
        $this->travelBack();
    }

    public function test_signup_sync_is_idempotent_and_links_only_same_member_clicks(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-20 12:00:00','UTC'));
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x','platform'=>'gethired','platform_connected_at'=>now()]);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $click=(string) \Illuminate\Support\Str::uuid();
        DB::table('program_referral_clicks')->insert(['id'=>$click,'membership_id'=>$member->id,'visitor_hash'=>str_repeat('b',64),'created_at'=>'2026-09-19 10:00:00']);
        $foreign=ReferrerProgramMembership::where('program_id',$this->second->id)->first();
        $rows=[['customerId'=>str_repeat('a',64),'membershipId'=>$member->id,'clickId'=>$click,'referredAt'=>'2026-09-19T10:00:00Z','occurredAt'=>'2026-09-20T11:00:00Z'],['customerId'=>str_repeat('b',64),'membershipId'=>$foreign->id,'clickId'=>$click,'referredAt'=>'2026-09-19T10:00:00Z','occurredAt'=>'2026-09-20T11:00:00Z']];
        $api=\Mockery::mock(\App\Services\Platform\GetHiredConnector::class);
        $api->shouldReceive('request')->twice()->with('signups',['connectionId'=>$connection->id,'cursor'=>null])->andReturn(['connectionId'=>$connection->id,'programId'=>$this->first->id,'cursor'=>null,'rows'=>$rows]);
        $this->app->instance(\App\Services\Platform\GetHiredConnector::class,$api);
        $sync=app(\App\Services\Programs\GetHiredSignupSync::class);
        $this->assertSame(1,$sync->sync($connection));$this->assertSame(0,$sync->sync($connection));
        $this->assertDatabaseCount('program_signup_events',1);
        $metrics=app(\App\Services\Programs\SignupMetrics::class)->compute($this->first,\Carbon\CarbonImmutable::parse('2026-09-19'),\Carbon\CarbonImmutable::parse('2026-09-19')->endOfDay(),$this->referrer->id);
        $this->assertSame(0,$metrics['signups']);$this->assertSame(1,$metrics['converted']);$this->assertEquals(100,$metrics['rate']);
        $this->assertSame('ready',$metrics['status']);
        $this->travelBack();
    }

    public function test_daily_click_chart_uses_local_dates_ranges_and_referrer_scope(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-20 04:00:00','UTC'));
        $this->first->update(['timezone'=>'Asia/Manila']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $other=ReferrerProgramMembership::where('program_id',$this->second->id)->first();
        $foreign=Reseller::create(['tenant_id'=>'company','name'=>'Another','email'=>'other@example.com','status'=>'active']);
        $foreignMember=ReferrerProgramMembership::create(['tenant_id'=>'company','program_id'=>$this->first->id,'reseller_id'=>$foreign->id,'status'=>'active']);
        foreach ([[$member->id,'2026-09-13 15:59:59'],[$member->id,'2026-09-13 16:00:00'],[$member->id,'2026-09-19 15:59:59'],[$member->id,'2026-09-19 16:00:00'],[$member->id,'2026-09-20 00:00:00'],[$other->id,'2026-09-20 00:00:00'],[$foreignMember->id,'2026-09-20 00:00:00']] as [$id,$date]) {
            DB::table('program_referral_clicks')->insert(['id'=>(string) \Illuminate\Support\Str::uuid(),'membership_id'=>$id,'visitor_hash'=>str_repeat('a',64),'created_at'=>$date]);
        }
        $service=app(\App\Services\Programs\ReferrerSubscriptionDashboard::class);
        foreach ([7,30,90] as $days) {
            $data=$service->compute($this->first,$this->referrer,\Illuminate\Http\Request::create('/','GET',['days'=>$days]));
            $this->assertCount($days,$data['dailyClicks']);
            $this->assertSame(2,$data['dailyClicks']['2026-09-20']);
            $this->assertSame(1,$data['dailyClicks']['2026-09-19']);
            $this->assertSame(0,$data['dailyClicks']['2026-09-18']);
            $this->assertSame($days===7?4:5,$data['periodClicks']);
            $this->assertSame($data['periodClicks'],array_sum($data['dailyClicks']));
        }
        ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        $adminRequest=\Illuminate\Http\Request::create('/','GET',['from'=>'2026-09-14','to'=>'2026-09-20']);
        $adminData=app(\App\Services\Programs\SubscriptionDashboard::class)->compute($this->first,$adminRequest);
        $this->assertSame(5,$adminData['clicks']);
        $this->actingAs($this->owner,'tenant')->get(route('tenant.dashboard',['tenantId'=>'company','program_id'=>$this->first->id,'from'=>'2026-09-14','to'=>'2026-09-20']))
            ->assertOk()->assertSeeInOrder(['Net referral revenue','Clicks','Signups','New paying customers'])->assertDontSee('Referred MRR')->assertDontSee('Active subscriptions');
        $report=route('tenant.programs.clicks',['tenantId'=>'company','programId'=>$this->first->id,'from'=>'2026-09-14','to'=>'2026-09-20']);
        $this->get($report)->assertOk()->assertViewHas('total',5)->assertViewHas('daily',fn($d)=>array_sum($d)===5 && $d['2026-09-20']===3)
            ->assertSee('Clicks by referrer')->assertSee('Another')->assertDontSee(str_repeat('a',64));
        $this->get($report.'&referrer='.$this->referrer->id)->assertOk()->assertViewHas('total',4)
            ->assertViewHas('breakdown',fn($b)=>$b->count()===1)->assertViewHas('recent',fn($r)=>$r->total()===4);
        $this->get($report.'&referrer=not-enrolled')->assertNotFound();
        $this->get(route('tenant.programs.clicks',['tenantId'=>'company','programId'=>$this->second->id]))->assertNotFound();
        $this->get(route('tenant.programs.clicks',['tenantId'=>'company','programId'=>$this->first->id,'from'=>'2026-09-20','to'=>'2026-09-14']))->assertSessionHasErrors('to');

        auth('tenant')->logout();
        $this->actingAs($this->referrer,'reseller')->get(route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->first->id,'days'=>7]))
            ->assertOk()->assertSee('Daily link clicks')->assertSee('View daily click counts')->assertSee('Asia/Manila');
        $this->get(route('reseller.dashboard',['tenantId'=>'company','program_id'=>$this->second->id]))->assertOk()->assertDontSee('Daily link clicks');
        $this->travelBack();
    }

    public function test_click_tracking_excludes_previews_bots_internal_visits_and_invalid_tokens(): void
    {
        ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $link=app(\App\Services\Programs\ReferrerReferralLink::class)->forMembership($this->first,$member);
        $this->get($link.'?preview=1')->assertOk()->assertViewHas('clickToken',null);
        $this->withHeader('User-Agent','facebookexternalhit/1.1')->get($link)->assertOk()->assertViewHas('clickToken',null);
        $this->withHeader('User-Agent','Mozilla/5.0')->withHeader('Sec-Purpose','prefetch')->get($link)->assertOk()->assertViewHas('clickToken',null);
        $this->flushHeaders();
        $token=$this->get($link)->viewData('clickToken');
        $this->post($link.'/click',['token'=>'tampered'])->assertStatus(422);
        $this->travel(11)->minutes();
        $this->post($link.'/click',['token'=>$token])->assertStatus(422);
        $this->travelBack();
        $this->actingAs($this->referrer,'reseller')->get($link)->assertOk()->assertViewHas('clickToken',null);
        $this->post($link.'/click',['token'=>$token])->assertNoContent();
        auth('reseller')->logout();
        $this->actingAs($this->owner,'tenant')->get($link)->assertOk()->assertViewHas('clickToken',null);
        auth('tenant')->logout();
        $member->update(['status'=>'paused']);
        $this->post($link.'/click',['token'=>$token])->assertNotFound();
        $this->assertDatabaseCount('program_referral_clicks',0);
    }

    public function test_short_link_rejects_unknown_and_mismatched_membership(): void
    {
        $this->get('/r/abcdefgh')->assertNotFound();
        $this->get('/r/invalid')->assertNotFound();
        $connection=ProgramConnection::create(['tenant_id'=>'company','program_id'=>$this->first->id,'website'=>'https://example.com','secret'=>'x']);
        $member=ReferrerProgramMembership::where('program_id',$this->first->id)->first();
        $link=app(\App\Services\Programs\ReferrerReferralLink::class)->forMembership($this->first,$member);
        $this->get($link)->assertOk();
        $connection->update(['website'=>'javascript:alert(1)']);
        $this->get($link)->assertNotFound();
        $connection->update(['website'=>'https://example.com']);
        Tenant::create(['id'=>'other','name'=>'Other','status'=>'active']);
        $member->update(['tenant_id'=>'other']);
        $this->get($link)->assertNotFound();
        $member->update(['tenant_id'=>'company']);
        $this->first->delete();
        $this->get($link)->assertNotFound();
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
