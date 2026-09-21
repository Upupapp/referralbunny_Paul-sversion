<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{Program,ProgramConnection,ReferrerProgramMembership,Reseller,Tenant};
use Illuminate\Support\Facades\{DB,Gate};
class ProgramReferrersController extends Controller {
 public function cancel(\Illuminate\Http\Request $request,string $tenantId,string $programId,string $membershipId){
  abort_unless(config('programs.enabled') && !\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);$this->authorize('managePeople',$program);
  $request->validate(['confirmed'=>'accepted']);
  DB::transaction(function()use($tenantId,$programId,$membershipId){
   $member=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$programId)->findOrFail($membershipId);
   Reseller::where('tenant_id',$tenantId)->whereKey($member->reseller_id)->lockForUpdate()->firstOrFail();
   $member=ReferrerProgramMembership::whereKey($membershipId)->lockForUpdate()->firstOrFail();
   abort_unless($member->status==='invited',422,'Only pending invitations can be cancelled.');
   $meta=$member->metadata??[];$meta['activate_on_setup']=false;$meta['invite_cancelled_at']=now()->toIso8601String();
   $meta['invite_history']=array_slice(array_merge($meta['invite_history']??[],[['event'=>'cancelled','at'=>now()->toIso8601String()]]),-50);
   $member->update(['status'=>'removed','metadata'=>$meta]);
  });
  return back()->with('delivery_notice','Invitation cancelled for this program. Invitations to other programs are unchanged.');
 }
 public function bulkResend(\Illuminate\Http\Request $request,string $tenantId,string $programId){
  abort_unless(config('programs.enabled') && !\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);$this->authorize('managePeople',$program);
  $data=$request->validate(['members'=>'required|array|min:1|max:20','members.*'=>'required|string|distinct','message'=>'nullable|string|max:1000','confirmed'=>'accepted']);
  $members=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$programId)->where('status','invited')->whereIn('id',$data['members'])->with('reseller')->get();
  abort_unless($members->count()===count($data['members']),422,'Selection changed. Refresh and select pending invitations again.');
  abort_if($members->contains(fn($m)=>!$m->reseller || $m->reseller->tenant_id!==$tenantId),404);
  $results=[];
  foreach($members as $member){
   try{app(\App\Services\Programs\ProgramReferrerInvite::class)->send($program,$member->reseller->name,$member->reseller->email,['message'=>$data['message']??null]);$message='Sent · delivery unconfirmed';}
   catch(\Illuminate\Validation\ValidationException $e){$message=collect($e->errors())->flatten()->implode(' ');}
   catch(\Throwable $e){report($e);$message='Could not send. Check delivery before trying again.';}
   $results[]=['email'=>$member->reseller->email,'message'=>$message];
   if(!app()->runningUnitTests())usleep(600000);
  }
  return back()->with('bulk_invite_results',$results);
 }
 public function renew(string $tenantId,string $programId,string $membershipId){return $this->resend($tenantId,$programId,$membershipId,true);}
 public function resend(string $tenantId,string $programId,string $membershipId,bool $renew=false){
  abort_unless(config('programs.enabled'),404);
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);
  $this->authorize('managePeople',$program);
  $member=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$programId)->where('status','invited')->findOrFail($membershipId);
  $referrer=$member->reseller;
  abort_unless($referrer && $referrer->tenant_id===$tenantId,404);
  if($renew)abort_unless(!$referrer->password && $referrer->setupExpiresAt()?->isPast(),422,'Only expired setup invitations can be renewed.');
  try {app(\App\Services\Programs\ProgramReferrerInvite::class)->send($program,$referrer->name,$referrer->email,[],$renew);}
  catch(\Illuminate\Validation\ValidationException $e){throw $e;}
  catch(\Throwable $e){report($e);return back()->withErrors(['email'=>'The invitation could not be sent. Try again in one minute.']);}
  return back()->with('delivery_notice','Invitation resent. Delivery is not yet confirmed.');
 }
 public function delivery(string $tenantId,string $programId,string $membershipId){
  abort_unless(config('programs.enabled'),404);
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);
  $this->authorize('managePeople',$program);
  $member=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$programId)->findOrFail($membershipId);
  $ok=app(\App\Services\Programs\ProgramInviteDelivery::class)->check($member);
  return back()->with('delivery_notice',$ok?'Delivery status refreshed.':'Delivery status is unavailable right now. The last recorded status is unchanged. No email was sent.');
 }
 public function index($tenantId){
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $tenant=Tenant::findOrFail($tenantId);
  $actor=auth('web')->user()??auth('tenant')->user();
  $programs=Program::forTenant($tenantId)->visible()->get()->filter(fn($p)=>Gate::forUser($actor)->allows('view',$p));
  $explicit=request('program_id');
  $selected=$explicit??session('program_dashboard.'.$tenantId);
  $program=$programs->firstWhere('id',$selected);
  abort_if($explicit&&!$program,404);
  $program??=$programs->first();
  if(!$program)return view('tenant.referrers.program',compact('tenant','program','programs'));
  session(['program_dashboard.'.$tenantId=>$program->id]);
  $program->load('offers.currentVersion');
  $automated=$program->effectiveOperatingMode()==='automated';
  $currency=$program->default_currency?:'PHP';
  $members=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$program->id)->with('reseller')->get()->filter(fn($m)=>$m->reseller&&$m->reseller->tenant_id===$tenantId);
  $connections=ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$program->id)->pluck('id');
  $clicks=$automated?DB::table('program_referral_clicks')->whereIn('membership_id',$members->pluck('id'))->selectRaw('membership_id,COUNT(*) as total')->groupBy('membership_id')->pluck('total','membership_id'):collect();
  $signups=$automated?DB::table('program_signup_events')->whereIn('connection_id',$connections)->whereIn('membership_id',$members->pluck('id'))->selectRaw('membership_id,COUNT(*) as total')->groupBy('membership_id')->pluck('total','membership_id'):collect();
  $sync=$automated?DB::table('program_signup_sync')->whereIn('connection_id',$connections)->whereNotNull('synced_at')->exists():false;
  $money=$automated?DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('currency',$currency)->selectRaw("referrer_id,SUM(CASE WHEN type='payment' THEN amount_minor ELSE -amount_minor END) as revenue,SUM(reward_minor) as rewards")->groupBy('referrer_id')->get()->keyBy('referrer_id'):collect();
  $paid=$automated?DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('type','payment')->selectRaw('referrer_id,COUNT(DISTINCT customer_id) as total')->groupBy('referrer_id')->pluck('total','referrer_id'):collect();
  $leads=!$automated?DB::table('leads')->where('tenant_id',$tenantId)->where('program_id',$program->id)->whereNull('deleted_at')->selectRaw('reseller_id,COUNT(*) as total')->groupBy('reseller_id')->pluck('total','reseller_id'):collect();
  $summary=['total'=>$members->count(),'active'=>$members->where('status','active')->filter(fn($m)=>in_array($m->reseller->status,['active','nda_signed']))->count(),'invited'=>$members->where('status','invited')->count(),'clicks'=>$clicks->sum(),'signups'=>$sync?$signups->sum():null,'rewards'=>$money->sum('rewards')/100,'referrals'=>$leads->sum()];
  $invites=$members->where('source','invite');
  $funnel=['Invited people'=>$invites->count(),'Latest email delivered'=>$invites->filter(fn($m)=>($m->metadata['invite_delivery']['status']??'')==='delivered')->count(),'Joined program'=>$invites->filter(fn($m)=>$m->joined_at!==null)->count(),'Awaiting setup'=>$invites->where('status','invited')->count(),'Cancelled'=>$invites->filter(fn($m)=>isset($m->metadata['invite_cancelled_at']))->count()];
  request()->validate(['delivery'=>['nullable','in:sending,sent,delivered,failed,bounced,complained,delayed,suppressed,untracked']]);
  $deliveryFilter=(string)request('delivery','');
  $search=trim((string)request('q',''));$status=(string)request('status','');
  if($search)$members=$members->filter(fn($m)=>str_contains(mb_strtolower($m->reseller->name.' '.$m->reseller->email),mb_strtolower($search)));
  if($status)$members=$members->where('status',$status);
  if($deliveryFilter)$members=$members->filter(fn($m)=>($m->metadata['invite_delivery']['status']??'untracked')===$deliveryFilter);
  $rows=new \Illuminate\Pagination\LengthAwarePaginator($members->forPage(max(1,(int)request('page',1)),25)->values(),$members->count(),25,max(1,(int)request('page',1)),['path'=>request()->url(),'query'=>request()->query()]);
  return view('tenant.referrers.program',compact('tenant','program','programs','automated','currency','rows','summary','clicks','signups','sync','money','paid','leads','search','status','deliveryFilter','funnel'));
 }
}
