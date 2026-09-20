<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{Program,ProgramConnection,Tenant};
use App\Services\Programs\SubscriptionDashboard;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
class SubscriptionRecordsController extends Controller {
 public function index(Request $request,string $tenantId,string $programId) {
  abort_if(ProtectedTenants::isProtected($tenantId)||!config('programs.enabled'),404);
  $program=Program::forTenant($tenantId)->findOrFail($programId);
  Gate::forUser(auth('web')->user()??auth('tenant')->user())->authorize('view',$program);
  abort_unless($program->effectiveOperatingMode()==='automated',404);
  $tenant=Tenant::findOrFail($tenantId);
  $request->validate(['queue'=>'nullable|in:ready,hold','referrer'=>'nullable|string|max:100']);
  $summary=app(SubscriptionDashboard::class)->compute($program,$request);
  $ids=ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$programId)->pluck('id');
  $rows=DB::table('program_conversion_events as p')->whereIn('p.connection_id',$ids)->whereIn('p.type',['payment','refund']);
  $queue=$request->query('queue');
  if($queue) {
   $rows->where('p.type','payment')->where('p.status','pending_review')->whereNotNull('p.available_at')
    ->where('p.available_at',$queue==='ready'?'<=':'>',now())
    ->whereRaw("p.reward_minor + COALESCE((SELECT SUM(r.reward_minor) FROM program_conversion_events r WHERE r.connection_id=p.connection_id AND r.invoice_id=p.invoice_id AND r.type='refund'),0)>0");
  } else {
   $rows->where('p.currency',$summary['currency'])->whereBetween('p.occurred_at',[$summary['from']->utc(),$summary['to']->utc()]);
  }
  if($request->filled('referrer')) $rows->where('p.referrer_id',$request->query('referrer'));
  $records=$rows->orderByDesc('p.occurred_at')->paginate(25)->withQueryString();
  $names=DB::table('resellers')->where('tenant_id',$tenantId)->whereIn('id',$records->pluck('referrer_id'))->pluck('name','id');
  return view('tenant.programs.records',compact('tenant','program','summary','records','names','queue'));
 }
}
