<?php
namespace App\Services\Programs;
use App\Models\ReferrerProgramMembership;
use App\Services\NotificationDispatchService;
use App\Support\ProtectedTenants;
class ProgramInviteAcceptance
{
 public function notify(string $tenantId,string $referrerId,string $name): bool {
  if(ProtectedTenants::isProtected($tenantId)||!config('programs.enabled'))return false;
  $members=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('reseller_id',$referrerId)
   ->where('source','invite')->where('status','active')->where('metadata->activate_on_setup',true)
   ->whereNotNull('joined_at')->with('program')->get();
  $notified=false;
  foreach($members as $m){
   if(!$m->program||$m->program->tenant_id!==$tenantId)continue;
   app(NotificationDispatchService::class)->dispatchToTenantAdmins(
    tenantId:$tenantId,category:'reseller_referrer',priority:'normal',
    title:'Program invitation accepted',body:$name.' joined '.$m->program->name.'.',
    actionUrl:route('tenant.referrers',['tenantId'=>$tenantId,'program_id'=>$m->program_id]),
    actionLabel:'View program referrers',dedupeSuffix:'program-accepted-'.$m->id,
    metadata:['program_id'=>$m->program_id,'membership_id'=>$m->id,'reseller_id'=>$referrerId]
   );
   $notified=true;
  }
  return $notified;
 }
}
