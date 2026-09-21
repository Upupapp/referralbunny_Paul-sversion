<?php
namespace App\Services\Programs;
use Illuminate\Support\Facades\DB;
use App\Models\ProgramConnection;
class ReferrerProgramCards {
 public function build($memberships,string $tenantId,string $referrerId): array {
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),403);
  $result=[];
  foreach($memberships as $m){
   $p=$m->program;
   if(!$p||$m->tenant_id!==$tenantId||$m->reseller_id!==$referrerId||$p->tenant_id!==$tenantId)continue;
   if($p->effectiveOperatingMode()!=='automated'){
    $result[$p->id]=['manual'=>true,'referrals'=>DB::table('leads')->where('tenant_id',$tenantId)->where('program_id',$p->id)->where('reseller_id',$referrerId)->whereNull('deleted_at')->count()];continue;
   }
   $connections=ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$p->id)->pluck('id');
   $clicks=DB::table('program_referral_clicks')->where('membership_id',$m->id)->count();
   $synced=DB::table('program_signup_sync')->whereIn('connection_id',$connections)->whereNotNull('synced_at')->exists();
   $signups=DB::table('program_signup_events')->whereIn('connection_id',$connections)->where('membership_id',$m->id)->count();
   $converted=DB::table('program_referral_clicks as c')->where('membership_id',$m->id)->whereExists(fn($q)=>$q->selectRaw('1')->from('program_signup_events as s')->whereIn('s.connection_id',$connections)->where('s.membership_id',$m->id)->whereColumn('s.click_id','c.id'))->count();
   $events=DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('referrer_id',$referrerId)->whereIn('type',['payment','refund']);
   $paying=(clone $events)->where('type','payment')->distinct()->count('customer_id');
   $rewards=(clone $events)->selectRaw('currency,SUM(reward_minor) as net')->groupBy('currency')->get();
   if($rewards->isEmpty())$rewards=collect([(object)['currency'=>$p->default_currency?:'PHP','net'=>0]]);
   $result[$p->id]=['manual'=>false,'clicks'=>$clicks,'signups'=>$synced?$signups:null,'conversion'=>$synced&&$clicks?round($converted/$clicks*100,1):null,'paying'=>$paying,'rewards'=>$rewards];
  }
  return $result;
 }
}
