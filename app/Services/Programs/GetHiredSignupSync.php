<?php
namespace App\Services\Programs;
use App\Models\{ProgramConnection,Program,ReferrerProgramMembership,Tenant};
use App\Services\Platform\GetHiredConnector;
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\{DB,Validator};
class GetHiredSignupSync {
 public function sync(ProgramConnection $connection): int {
  abort_if(ProtectedTenants::isProtected($connection->tenant_id),403);
  abort_unless($connection->platform==='gethired' && $connection->platform_connected_at,409);
  $program=Program::forTenant($connection->tenant_id)->findOrFail($connection->program_id);
  abort_unless($program->effectiveOperatingMode()==='automated' && Tenant::whereKey($connection->tenant_id)->where('status','active')->exists(),403);
  DB::table('program_signup_sync')->insertOrIgnore(['connection_id'=>$connection->id,'status'=>'pending']);
  $count=0;
  for($page=0;$page<5;$page++) {
   $cursor=DB::table('program_signup_sync')->where('connection_id',$connection->id)->value('cursor');
   $feed=app(GetHiredConnector::class)->request('signups',['connectionId'=>$connection->id,'cursor'=>$cursor]);
   abort_unless(($feed['connectionId']??null)===$connection->id && ($feed['programId']??null)===$program->id,502);
   Validator::make($feed,['rows'=>'present|array|max:100','cursor'=>'nullable|string|max:128','rows.*.customerId'=>'required|string|size:64|regex:/^[a-f0-9]+$/','rows.*.membershipId'=>'required|string|max:100','rows.*.clickId'=>'nullable|uuid','rows.*.referredAt'=>'required|date','rows.*.occurredAt'=>'required|date|before_or_equal:'.now()->addMinutes(5)->toIso8601String()])->validate();
   DB::transaction(function()use($feed,$connection,$program,&$count){
    foreach($feed['rows'] as $row) {
     $member=ReferrerProgramMembership::where('tenant_id',$connection->tenant_id)->where('program_id',$program->id)->find($row['membershipId']);
     if(!$member)continue;
     $occurred=Date::parse($row['occurredAt'])->utc();$referred=Date::parse($row['referredAt'])->utc();
     if($occurred->lt($referred))continue;
     $clickId=null;
     if(!empty($row['clickId']) && DB::table('program_referral_clicks')->where('id',$row['clickId'])->where('membership_id',$member->id)->where('created_at','<=',$occurred)->exists())$clickId=$row['clickId'];
     $count+=DB::table('program_signup_events')->insertOrIgnore(['connection_id'=>$connection->id,'customer_id'=>$row['customerId'],'membership_id'=>$member->id,'click_id'=>$clickId,'occurred_at'=>$occurred,'referred_at'=>$referred,'created_at'=>now()]);
     if($clickId)DB::table('program_signup_events')->where('connection_id',$connection->id)->where('customer_id',$row['customerId'])->where('membership_id',$member->id)->whereNull('click_id')->update(['click_id'=>$clickId]);
    }
    DB::table('program_signup_sync')->where('connection_id',$connection->id)->update(['cursor'=>$feed['cursor']??null,'synced_at'=>now(),'status'=>'ready']);
   });
   if(empty($feed['cursor']))break;
  }
  return $count;
 }
}
