<?php
namespace App\Services\Programs;
use App\Models\{Program,Reseller,ReferrerProgramMembership,Tenant};
use App\Mail\ProgramReferrerInvitation;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ProgramReferrerInvite {
 public function send(Program $program,string $name,string $email): Reseller {
  abort_if(ProtectedTenants::isProtected($program->tenant_id),403);
  abort_unless(in_array($program->status,['active','draft','scheduled']),422);
  $email=strtolower(trim($email));
  $reseller=DB::transaction(function()use($program,$name,$email){
   Tenant::whereKey($program->tenant_id)->lockForUpdate()->firstOrFail();
   $r=Reseller::withTrashed()->where('tenant_id',$program->tenant_id)->whereRaw('LOWER(email) = ?',[$email])->first();
   if($r && ($r->trashed() || !in_array($r->status,['invited','active','nda_signed'])))throw ValidationException::withMessages(['email'=>'This referrer is inactive. Review their account before inviting.']);
   if(!$r){
    $limit=app(\App\Services\TenantPlanService::class)->canInviteReferrer($program->tenant_id);
    if(!$limit['allowed'])throw ValidationException::withMessages(['email'=>$limit['reason']]);
    $r=Reseller::create(['tenant_id'=>$program->tenant_id,'name'=>$name,'email'=>$email,'status'=>'invited','setup_token'=>Str::random(64)]);
   }
   if(!$r->password && (!$r->setup_token || $r->created_at?->lt(now()->subDays(90))))throw ValidationException::withMessages(['email'=>'This account needs its setup invitation renewed before it can join.']);
   $m=ReferrerProgramMembership::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->where('reseller_id',$r->id)->first();
   if($m && !in_array($m->status,['active','approved']) && !($m->status==='invited' && ($m->metadata['activate_on_setup']??false)))throw ValidationException::withMessages(['email'=>'Review this referrer’s existing program membership before inviting.']);
   if(!$m)ReferrerProgramMembership::create(['tenant_id'=>$program->tenant_id,'program_id'=>$program->id,'reseller_id'=>$r->id,'status'=>$r->password?'active':'invited','source'=>'invite','metadata'=>['activate_on_setup'=>!$r->password],'joined_at'=>$r->password?now():null,'activated_at'=>$r->password?now():null]);
   return $r;
  });
  $setup=!$reseller->password;
  $url=$setup?route('reseller.setup',['token'=>$reseller->setup_token]):route('reseller.dashboard',['tenantId'=>$program->tenant_id,'program_id'=>$program->id]);
  Mail::to($email)->send(new ProgramReferrerInvitation($program->name,$reseller->name,$url,$setup));
  return $reseller;
 }
}
