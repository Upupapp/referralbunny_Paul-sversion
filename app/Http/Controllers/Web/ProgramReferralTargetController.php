<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
class ProgramReferralTargetController extends Controller {
 public function store(Request $request, string $tenantId, string $programId) {
  abort_if(ProtectedTenants::isProtected($tenantId) || !config('programs.enabled'),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);
  Gate::forUser(auth('web')->user() ?? auth('tenant')->user())->authorize('update',$program);
  abort_unless($program->effectiveOperatingMode()==='automated',404);
  $data=$request->validate(['total'=>'required|integer|min:1|max:100000000','starts_on'=>'required|date_format:Y-m-d','ends_on'=>'required|date_format:Y-m-d|after_or_equal:starts_on']);
  if (\Carbon\Carbon::parse($data['starts_on'])->diffInDays(\Carbon\Carbon::parse($data['ends_on'])) > 3659) { throw \Illuminate\Validation\ValidationException::withMessages(['ends_on'=>'Choose a target duration of at most ten years.']); }
  DB::table('program_referral_targets')->updateOrInsert(['tenant_id'=>$tenantId,'program_id'=>$programId],$data+['updated_at'=>now(),'created_at'=>now()]);
  return redirect()->route('tenant.dashboard',['tenantId'=>$tenantId,'program_id'=>$programId,'from'=>$data['starts_on'],'to'=>$data['ends_on']])->with('success','Referral target saved.');
 }
}
