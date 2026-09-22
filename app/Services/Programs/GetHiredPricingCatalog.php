<?php
namespace App\Services\Programs;
use App\Models\{Program,ProgramConnection};
use App\Services\Platform\GetHiredConnector;
use Illuminate\Support\Facades\{Cache,Validator};
class GetHiredPricingCatalog {
 public function forProgram(Program $p): array {
  if(\App\Support\ProtectedTenants::isProtected($p->tenant_id))return ['error'=>'Pricing unavailable for this program.'];
  $c=ProgramConnection::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->where('platform','gethired')->whereNotNull('platform_connected_at')->first();
  if(!$c)return ['error'=>'Connect GetHired to see current package estimates.'];
  try {
   return Cache::remember('gethired-pricing:v1:'.$c->id.':'.hash('sha256',(string)$c->updated_at),now()->addMinutes(5),function()use($c){
    $f=app(GetHiredConnector::class)->request('pricing',['connectionId'=>$c->id]);
    abort_unless(($f['connectionId']??null)===$c->id && ($f['programId']??null)===$c->program_id,502);
    Validator::make($f,['version'=>'required|string|max:100','currency'=>'required|in:PHP','billing_mode'=>'required|in:UPFRONT','packages'=>'required|array|max:50','packages.*.id'=>'required|string|max:60|distinct','packages.*.name'=>'required|string|max:100','packages.*.currency'=>'required|in:PHP','packages.*.active'=>'required|boolean','packages.*.sort_order'=>'sometimes|integer|min:0','packages.*.recommended'=>'sometimes|boolean','packages.*.monthly_amount_minor'=>'present|nullable|integer|min:1|max:999999999999','packages.*.annual_amount_minor'=>'present|nullable|integer|min:1|max:999999999999'])->validate();
    return ['version'=>$f['version'],'currency'=>$f['currency'],'billing_mode'=>$f['billing_mode'],'packages'=>collect($f['packages'])->where('active',true)->map(fn($r)=>array_intersect_key($r,array_flip(['id','name','currency','monthly_amount_minor','annual_amount_minor','sort_order','recommended'])))->values()->all()];
   });
  } catch(\Throwable $e) {return ['error'=>'Pricing temporarily unavailable. Retry shortly.'];}
 }
}
