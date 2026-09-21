<?php
namespace App\Services\Programs;
use App\Models\ReferrerProgramMembership;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
class ProgramInviteDelivery
{
 public function record(ReferrerProgramMembership $member, string $attempt, array $data, bool $start=false): void {
  abort_if(ProtectedTenants::isProtected($member->tenant_id),403);
  DB::transaction(function()use($member,$attempt,$data,$start){
   $locked=ReferrerProgramMembership::whereKey($member->id)->lockForUpdate()->firstOrFail();
   $meta=$locked->metadata??[];
   if(!$start && ($meta['invite_delivery']['attempt']??null)!==$attempt)return;
   $meta['invite_delivery']=$start?array_merge(['attempt'=>$attempt],$data):array_merge($meta['invite_delivery'],$data);
   $locked->update(['metadata'=>$meta]);
  });
 }
 public function check(ReferrerProgramMembership $member): bool {
  abort_if(ProtectedTenants::isProtected($member->tenant_id),403);
  $delivery=$member->metadata['invite_delivery']??[];
  $id=$delivery['provider_id']??null;
  if(!Str::isUuid((string)$id)||!config('services.resend.key'))return false;
  try {
   $response=Http::withToken(config('services.resend.key'))->acceptJson()->timeout(8)->get('https://api.resend.com/emails/'.$id);
   if(!$response->successful() || $response->json('id')!==$id)return false;
   $event=$response->json('last_event');
   $state=match($event){'sent'=>'sent','delivered','opened','clicked'=>'delivered','bounced'=>'bounced','complained'=>'complained','failed'=>'failed','delivery_delayed'=>'delayed','suppressed'=>'suppressed',default=>null};
   if(!$state)return false;
   $this->record($member,$delivery['attempt'],['status'=>$state,'checked_at'=>now()->toIso8601String()]);
   return true;
  } catch(\Throwable $e){return false;}
 }
}
