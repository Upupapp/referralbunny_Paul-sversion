<?php
namespace Tests\Feature;
use App\Models\{Program,ProgramLandingPage,ProgramOffer,ProgramOfferVersion,Tenant,TenantMembership,TenantUser,Reseller,ReferrerProgramMembership};
use App\Services\Programs\GetHiredPublicLanding;
use App\Mail\ProgramReferrerInvitation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,Mail,Cache,DB};
use Tests\Support\QuickProgramSchema;
use Tests\TestCase;
class GetHiredPublicLandingTest extends TestCase {
 use QuickProgramSchema;
 private $p;private $v;private $user;
 protected function setUp():void {
  parent::setUp();config(['programs.enabled'=>true,'app.key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);$this->buildQuickProgramSchema();Cache::flush();Mail::fake();
  $this->mock(\App\Services\Programs\GetHiredPricingCatalog::class,fn($m)=>$m->shouldReceive('forProgram')->andReturn(['packages'=>[['id'=>'fixture-growth','name'=>'Sandbox Growth','currency'=>'PHP','monthly_amount_minor'=>349000],['id'=>'fixture-starter','name'=>'Sandbox Starter','currency'=>'PHP','monthly_amount_minor'=>149000]]]));
  (require database_path('migrations/2026_09_22_000001_create_program_landing_pages.php'))->up();
  Schema::table('resellers',function(Blueprint $t){$t->string('password')->nullable();$t->string('setup_token')->nullable();$t->timestamp('setup_token_created_at')->nullable();$t->string('phone')->nullable();$t->string('territory')->nullable();});
  $this->withoutMiddleware(\App\Http\Middleware\EnsureLegalAgreementsAccepted::class);
  $this->mock(\App\Services\TenantPlanService::class,fn($m)=>$m->shouldReceive('canInviteReferrer')->andReturn(['allowed'=>true]));
  Tenant::create(['id'=>GetHiredPublicLanding::TENANT,'name'=>'GetHired','status'=>'active']);
  $this->p=Program::create(['tenant_id'=>GetHiredPublicLanding::TENANT,'name'=>'GetHired Online Referrals','status'=>'active','operating_mode'=>'automated','timezone'=>'Asia/Manila','default_currency'=>'PHP']);$this->p->forceFill(['id'=>GetHiredPublicLanding::PROGRAM])->save();
  $o=ProgramOffer::create(['tenant_id'=>$this->p->tenant_id,'program_id'=>$this->p->id,'name'=>'Subscription rewards','status'=>'active']);
  $this->v=ProgramOfferVersion::create(['tenant_id'=>$this->p->tenant_id,'program_id'=>$this->p->id,'offer_id'=>$o->id,'version_number'=>1,'status'=>'published','currency'=>'PHP','reward_model'=>'percentage','percentage_rate'=>'20.0000','qualifying_event'=>'payment_received','reward_rules'=>['scope'=>'recurring','duration_months'=>12,'hold_days'=>30,'basis'=>'net_collected_excluding_tax']]);$o->update(['current_version_id'=>$this->v->id]);
  ProgramLandingPage::create(['tenant_id'=>$this->p->tenant_id,'program_id'=>$this->p->id,'published'=>true]);
  $this->user=TenantUser::create(['email'=>'owner@example.test','password'=>bcrypt('local'),'status'=>'active']);TenantMembership::create(['tenant_id'=>$this->p->tenant_id,'tenant_user_id'=>$this->user->id,'role'=>'owner','status'=>'active']);
 }
 private function sendInvite($data=[]){return $this->withSession(['gethired_landing_opened'=>now()->subSeconds(3)->timestamp])->postJson('/gethired/referrals/invite',array_replace(['name'=>'Local Person','email'=>'person@example.test'],$data));}
 private function editor(){return route('tenant.programs.landing.update',[$this->p->tenant_id,$this->p->id]);}
 public function test_public_page_and_preview_render_with_locked_terms_and_real_assets():void {
  $r=$this->get('/gethired/referrals')->assertOk()->assertSee('837,600')->assertDontSee('837,600.00')->assertSee('Sandbox Growth')->assertDontSee('id="package-price"',false)->assertSee('1 year')->assertSee('100')->assertSee('images/gethired/logo-horizontal.png')->assertDontSee('Great companies hire great people');
  $this->assertStringContainsString('no-store',$r->headers->get('Cache-Control'));
  if($path=getenv('RB_LANDING_PREVIEW'))file_put_contents($path.'/landing.html',str_replace('http://localhost','http://127.0.0.1:4335',$r->getContent()));
  $preview=$this->actingAs($this->user,'tenant')->get(route('tenant.programs.landing.preview',[$this->p->tenant_id,$this->p->id]))->assertOk()->assertSee('Admin preview')->assertSee('noindex,nofollow')->assertSee(':disabled="busy || true"',false);
  if($path=getenv('RB_LANDING_PREVIEW'))file_put_contents($path.'/landing-preview.html',str_replace('http://localhost','http://127.0.0.1:4335',$preview->getContent()));
 }
 public function test_new_invite_reuses_mail_service_and_cannot_duplicate():void {
  $this->sendInvite(['email'=>'PERSON@example.test'])->assertOk()->assertJson(['state'=>'sent','email'=>'person@example.test']);
  Mail::assertSent(ProgramReferrerInvitation::class,fn($m)=>$m->hasTo('person@example.test'));
  $this->assertDatabaseHas('resellers',['email'=>'person@example.test','tenant_id'=>$this->p->tenant_id,'status'=>'invited']);$this->assertDatabaseHas('referrer_program_memberships',['program_id'=>$this->p->id,'status'=>'invited']);
  $this->sendInvite()->assertStatus(429);$this->assertDatabaseCount('resellers',1);Mail::assertSentCount(1);
  $this->travel(11)->minutes();$this->sendInvite()->assertOk()->assertJson(['state'=>'resent']);Mail::assertSentCount(2);$this->assertDatabaseCount('resellers',1);
 }
 public function test_existing_account_receives_access_instructions_without_profile_changes():void {
  $r=Reseller::create(['tenant_id'=>$this->p->tenant_id,'name'=>'Existing name','email'=>'person@example.test','password'=>bcrypt('local'),'status'=>'active']);ReferrerProgramMembership::create(['tenant_id'=>$this->p->tenant_id,'program_id'=>$this->p->id,'reseller_id'=>$r->id,'status'=>'active']);
  $this->sendInvite(['phone'=>'+631234'])->assertOk()->assertJson(['state'=>'existing']);$this->assertSame('Existing name',$r->fresh()->name);Mail::assertSentCount(1);
 }
 public function test_invalid_input_honeypot_and_program_override_are_rejected():void {
  $this->sendInvite(['name'=>'','email'=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name','email']);
  $this->sendInvite(['website'=>'spam'])->assertUnprocessable()->assertJsonValidationErrors('website');
  $this->sendInvite(['program_id'=>'arbitrary','tenant_id'=>'arbitrary'])->assertUnprocessable()->assertJsonValidationErrors(['program_id','tenant_id']);Mail::assertNothingSent();$this->assertDatabaseCount('resellers',0);
 }
 public function test_nonce_and_ip_rate_limit():void {
  $this->postJson('/gethired/referrals/invite',['name'=>'Local','email'=>'person@example.test'])->assertUnprocessable();
  for($i=0;$i<4;$i++)$this->postJson('/gethired/referrals/invite',[])->assertUnprocessable();
  $this->sendInvite()->assertStatus(429);Mail::assertNothingSent();
 }
 public function test_mail_failure_never_returns_success():void {
  Mail::shouldReceive('to')->once()->andReturnSelf();Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('private provider error'));
  $this->sendInvite()->assertStatus(503)->assertJsonMissing(['state'=>'sent'])->assertDontSee('private provider error');
  $this->assertSame('failed',ReferrerProgramMembership::first()->metadata['invite_delivery']['status']);
 }
 public function test_unpublished_closed_or_unready_program_cannot_send():void {
  ProgramLandingPage::query()->update(['published'=>false]);$this->get('/gethired/referrals')->assertNotFound();$this->sendInvite()->assertNotFound();ProgramLandingPage::query()->update(['published'=>true]);
  $this->p->update(['status'=>'paused']);$this->sendInvite()->assertUnprocessable();$this->get('/gethired/referrals')->assertOk()->assertSee('Invitations are currently unavailable');$this->p->update(['status'=>'active']);
  $this->v->update(['reward_rules'=>array_replace($this->v->reward_rules,['duration_months'=>6])]);$this->sendInvite()->assertUnprocessable();Mail::assertNothingSent();
 }
 public function test_editor_saves_only_text_escapes_markup_and_requires_owner():void {
  $s=app(GetHiredPublicLanding::class);$content=$s->defaults();$content['headline']='<script>alert(1)</script>';
  $this->actingAs($this->user,'tenant')->patch($this->editor(),['content'=>$content,'published'=>true])->assertRedirect()->assertSessionHasNoErrors();
  $this->get('/gethired/referrals')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $this->patch($this->editor(),['content'=>$content+['form_fields'=>[]],'published'=>true])->assertSessionHasErrors('content');
  $viewer=TenantUser::create(['email'=>'viewer@example.test','password'=>bcrypt('local'),'status'=>'active']);TenantMembership::create(['tenant_id'=>$this->p->tenant_id,'tenant_user_id'=>$viewer->id,'role'=>'viewer','status'=>'active']);$this->actingAs($viewer,'tenant')->patch($this->editor(),['content'=>$content,'published'=>true])->assertForbidden();
 }
 public function test_six_month_offer_cannot_publish_page_and_approved_change_preserves_history():void {
  $s=app(GetHiredPublicLanding::class);$this->v->update(['reward_rules'=>array_replace($this->v->reward_rules,['duration_months'=>6])]);$old=$this->v->fresh()->getAttributes();
  $this->actingAs($this->user,'tenant')->patch($this->editor(),['content'=>$s->defaults(),'published'=>true])->assertSessionHasErrors('published');
  $new=$s->publishOneYearOffer($this->user->id);$this->assertNotSame($this->v->id,$new->id);$this->assertSame(12,$new->reward_rules['duration_months']);$this->assertSame(2,$new->version_number);$this->assertSame($old,$this->v->fresh()->getAttributes());$this->assertSame($new->id,$s->publishOneYearOffer($this->user->id)->id);$this->assertDatabaseCount('program_offer_versions',2);$this->assertTrue($s->terms($this->p)['ready']);
 }
 public function test_admin_workspace_contains_text_editor_and_preview():void {
  Schema::create('tenant_brand_profiles',function(Blueprint $t){$t->id();$t->string('tenant_id');$t->string('status');$t->timestamps();});
  $this->actingAs($this->user,'tenant')->get(route('tenant.programs.workspace',[$this->p->tenant_id,$this->p->id]).'?tab=public-page')->assertOk()->assertSee('GetHired public referrer page')->assertSee('Save public page')->assertSee('Preview page');
 }
 public function test_csrf_is_required_for_public_submission():void {
  $this->app['env']='local';
  $this->sendInvite()->assertStatus(419);Mail::assertNothingSent();
 }
 public function test_release_command_is_dry_run_by_default_and_requires_admin():void {
  $this->v->update(['reward_rules'=>array_replace($this->v->reward_rules,['duration_months'=>6])]);
  $this->artisan('gethired:prepare-public-landing')->assertSuccessful();$this->assertDatabaseCount('program_offer_versions',1);
  $this->artisan('gethired:prepare-public-landing',['--apply'=>true,'--actor'=>'missing'])->assertFailed();$this->assertDatabaseCount('program_offer_versions',1);
  $this->artisan('gethired:prepare-public-landing',['--apply'=>true,'--actor'=>$this->user->id])->assertSuccessful();$this->assertDatabaseCount('program_offer_versions',2);
 }
 public function test_catalog_defaults_sorting_missing_pricing_and_authoritative_estimates():void {
  $s=app(GetHiredPublicLanding::class);$d=$s->packages($this->p);$this->assertSame('fixture-growth',$d['default']);$this->assertSame('fixture-starter',$d['packages'][0]['id']);$this->assertSame(83760000,$d['packages'][1]['estimate']['annual']);
  $this->mock(\App\Services\Programs\GetHiredPricingCatalog::class,fn($m)=>$m->shouldReceive('forProgram')->andReturn(['packages'=>[['id'=>'a','name'=>'A','currency'=>'PHP','monthly_amount_minor'=>100000,'recommended'=>true,'sort_order'=>2],['id'=>'b','name'=>'B','currency'=>'PHP','monthly_amount_minor'=>200000,'sort_order'=>1],['id'=>'annual-only','name'=>'Annual only','currency'=>'PHP','monthly_amount_minor'=>null]]]));
  $d=$s->packages($this->p);$this->assertSame('a',$d['default']);$this->assertSame(['b','a'],array_column($d['packages'],'id'));
  $this->mock(\App\Services\Programs\GetHiredPricingCatalog::class,fn($m)=>$m->shouldReceive('forProgram')->andReturn(['error'=>'Unavailable']));
  $this->get('/gethired/referrals')->assertOk()->assertSee('Packages temporarily unavailable')->assertDontSee('837,600');
  $this->sendInvite(['package_id'=>'tampered','package_price'=>1])->assertUnprocessable()->assertJsonValidationErrors(['package_id','package_price']);
  $this->sendInvite()->assertOk();
 }
 public function test_other_program_editor_is_outside_scope():void {
  $other=Program::create(['tenant_id'=>$this->p->tenant_id,'name'=>'Other','status'=>'active']);
  $this->actingAs($this->user,'tenant')->patch(route('tenant.programs.landing.update',[$other->tenant_id,$other->id]),['content'=>app(GetHiredPublicLanding::class)->defaults(),'published'=>true])->assertNotFound();$this->assertDatabaseCount('program_landing_pages',1);
 }
}
