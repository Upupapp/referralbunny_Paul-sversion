<?php
namespace App\Services\Programs;
use Illuminate\Support\Facades\DB;
use App\Models\ProgramConnection;
class ReferrerProgramCards {
 public function build($memberships,string $tenantId,string $referrerId, array $period = []): array {
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),403);
  $result=[];
  foreach($memberships as $m){
   $p=$m->program;
   if(!$p||$m->tenant_id!==$tenantId||$m->reseller_id!==$referrerId||$p->tenant_id!==$tenantId)continue;
   $range = $period['range'] ?? 'all';
   $tz = $p->timezone ?: 'UTC';
   $start = $end = null;
   if ($range !== 'all') {
    $end = $range === 'custom' ? \Carbon\CarbonImmutable::parse($period['to'], $tz)->startOfDay()->addDay() : \Carbon\CarbonImmutable::now($tz)->startOfDay()->addDay();
    $start = $range === 'custom' ? \Carbon\CarbonImmutable::parse($period['from'], $tz)->startOfDay() : $end->subDays((int) $range);
   }
   $within = function ($query, $column) use ($start, $end) {
    return $start ? $query->where($column, '>=', $start->utc())->where($column, '<', $end->utc()) : $query;
   };
   if($p->effectiveOperatingMode()!=='automated'){
    $result[$p->id]=['manual'=>true,'referrals'=>$within(DB::table('leads')->where('tenant_id',$tenantId)->where('program_id',$p->id)->where('reseller_id',$referrerId)->whereNull('deleted_at'),'created_at')->count()];continue;
   }
   $connections=ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$p->id)->pluck('id');
   $clicks=$within(DB::table('program_referral_clicks')->where('membership_id',$m->id),'created_at')->count();
   $synced=DB::table('program_signup_sync')->whereIn('connection_id',$connections)->whereNotNull('synced_at')->exists();
   $signups=$within(DB::table('program_signup_events')->whereIn('connection_id',$connections)->where('membership_id',$m->id),'occurred_at')->count();
   $converted=$within(DB::table('program_referral_clicks as c')->where('membership_id',$m->id)->whereExists(fn($q)=>$q->selectRaw('1')->from('program_signup_events as s')->whereIn('s.connection_id',$connections)->where('s.membership_id',$m->id)->whereColumn('s.click_id','c.id')),'c.created_at')->count();
   $events=$within(DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('referrer_id',$referrerId)->whereIn('type',['payment','refund']),'occurred_at');
   $paying=(clone $events)->where('type','payment')->distinct()->count('customer_id');
   $rewards=(clone $events)->selectRaw('currency,SUM(reward_minor) as net')->groupBy('currency')->get();
   if($rewards->isEmpty())$rewards=collect([(object)['currency'=>$p->default_currency?:'PHP','net'=>0]]);
   $result[$p->id]=['manual'=>false,'clicks'=>$clicks,'signups'=>$synced?$signups:null,'conversion'=>$synced&&$clicks?round($converted/$clicks*100,1):null,'paying'=>$paying,'rewards'=>$rewards];
  }
  return $result;
 }
}
