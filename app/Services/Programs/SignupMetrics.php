<?php
namespace App\Services\Programs;
use App\Models\{Program,ProgramConnection};
use Illuminate\Support\Facades\DB;
class SignupMetrics {
 public function compute(Program $program,$from,$to,?string $referrer=null): array {
  abort_if(\App\Support\ProtectedTenants::isProtected($program->tenant_id),403);
  $connections=ProgramConnection::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->pluck('id');
  $members=DB::table('referrer_program_memberships')->where('tenant_id',$program->tenant_id)->where('program_id',$program->id);
  if($referrer)$members->where('reseller_id',$referrer);
  $ids=$members->pluck('id');
  $events=DB::table('program_signup_events')->whereIn('connection_id',$connections)->whereIn('membership_id',$ids);
  $signups=(clone $events)->whereBetween('occurred_at',[$from->utc(),$to->utc()])->count();
  $clicks=DB::table('program_referral_clicks as c')->whereIn('c.membership_id',$ids)->whereBetween('c.created_at',[$from->utc(),$to->utc()]);
  $total=(clone $clicks)->count();
  $converted=(clone $clicks)->whereExists(function($q)use($connections){$q->selectRaw('1')->from('program_signup_events as s')->whereIn('s.connection_id',$connections)->whereColumn('s.click_id','c.id');})->count();
  $sync=DB::table('program_signup_sync')->whereIn('connection_id',$connections)->orderByDesc('synced_at')->first();
  return ['signups'=>$signups,'converted'=>$converted,'rate'=>$total?round($converted/$total*100,1):null,'synced_at'=>$sync?->synced_at,'status'=>$sync?->status??'pending'];
 }
}
