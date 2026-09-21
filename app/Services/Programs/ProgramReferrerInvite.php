<?php
namespace App\Services\Programs;
use App\Models\{Program,Reseller,ReferrerProgramMembership,Tenant};
use App\Mail\ProgramReferrerInvitation;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ProgramReferrerInvite {
 public function send(Program $program,string $name,string $email,array $details=[]): Reseller {
  abort_if(ProtectedTenants::isProtected($program->tenant_id),403);
  abort_unless(in_array($program->status,['active','draft','scheduled']),422);
  $email=strtolower(trim($email));
  $reseller=DB::transaction(function()use($program,$name,$email,$details){
   Tenant::whereKey($program->tenant_id)->lockForUpdate()->firstOrFail();
   $r=Reseller::withTrashed()->where('tenant_id',$program->tenant_id)->whereRaw('LOWER(email) = ?',[$email])->first();
   if($r && ($r->trashed() || !in_array($r->status,['invited','active','nda_signed'])))throw ValidationException::withMessages(['email'=>'This referrer is inactive. Review their account before inviting.']);
   if(!$r){
    $limit=app(\App\Services\TenantPlanService::class)->canInviteReferrer($program->tenant_id);
    if(!$limit['allowed'])throw ValidationException::withMessages(['email'=>$limit['reason']]);
    $r=Reseller::create(['tenant_id'=>$program->tenant_id,'name'=>$name,'email'=>$email,'status'=>'invited','setup_token'=>Str::random(64),'phone'=>$details['phone']??null,'territory'=>$details['territory']??null]);
   }
   if(!$r->password && (!$r->setup_token || $r->created_at?->lt(now()->subDays(90))))throw ValidationException::withMessages(['email'=>'This account needs its setup invitation renewed before it can join.']);
   $m=ReferrerProgramMembership::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->where('reseller_id',$r->id)->first();
   if($m && !in_array($m->status,['active','approved']) && !($m->status==='invited' && ($m->metadata['activate_on_setup']??false)))throw ValidationException::withMessages(['email'=>'Review this referrer’s existing program membership before inviting.']);
   if(!$m)$m=ReferrerProgramMembership::create(['tenant_id'=>$program->tenant_id,'program_id'=>$program->id,'reseller_id'=>$r->id,'status'=>$r->password?'active':'invited','source'=>'invite','metadata'=>['activate_on_setup'=>!$r->password],'joined_at'=>$r->password?now():null,'activated_at'=>$r->password?now():null]);
   $meta=$m->metadata??[];
   $last=$meta['invite_last_attempt_at']??$meta['invite_delivery']['sent_at']??null;
   $cooldown=($meta['invite_delivery']['status']??'')==='failed'?60:600;
   if($last && \Carbon\Carbon::parse($last)->addSeconds($cooldown)->isFuture()) {
    throw ValidationException::withMessages(['email'=>'Please wait until '.\Carbon\Carbon::parse($last)->addSeconds($cooldown)->setTimezone($program->timezone?:'UTC')->format('M j, g:i A').' before resending this invitation.']);
   }
   $meta['invite_last_attempt_at']=now()->toIso8601String();$m->update(['metadata'=>$meta]);
   return $r;
  });
  $setup=!$reseller->password;
  $url=$setup?route('reseller.setup',['token'=>$reseller->setup_token]):route('reseller.dashboard',['tenantId'=>$program->tenant_id,'program_id'=>$program->id]);
  $member=ReferrerProgramMembership::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->where('reseller_id',$reseller->id)->firstOrFail();
  $delivery=app(ProgramInviteDelivery::class);$attempt=(string)Str::uuid();
  $delivery->record($member,$attempt,['status'=>'sending','sent_at'=>now()->toIso8601String()],true);
  try {
   $sent=Mail::to($email)->send(new ProgramReferrerInvitation($program->name,$reseller->name,$url,$setup));
  } catch(\Throwable $e){
   $delivery->record($member,$attempt,['status'=>'failed']);throw $e;
  }
  $providerId=$sent?->getOriginalMessage()?->getHeaders()?->get('X-Resend-Email-ID')?->getBodyAsString();
  $delivery->record($member,$attempt,['status'=>'sent','provider_id'=>Str::isUuid((string)$providerId)?$providerId:null]);
  return $reseller;
 }
}
