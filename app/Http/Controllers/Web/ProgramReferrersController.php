<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{Program,ProgramConnection,ReferrerProgramMembership,Reseller,Tenant};
use Illuminate\Support\Facades\{DB,Gate};
class ProgramReferrersController extends Controller {
 public function index($tenantId){
  abort_if(\App\Support\ProtectedTenants::isProtected($tenantId),404);
  $tenant=Tenant::findOrFail($tenantId);
  $actor=auth('web')->user()??auth('tenant')->user();
  $programs=Program::forTenant($tenantId)->visible()->get()->filter(fn($p)=>Gate::forUser($actor)->allows('view',$p));
  $explicit=request('program_id');
  $selected=$explicit??session('program_dashboard.'.$tenantId);
  $program=$programs->firstWhere('id',$selected);
  abort_if($explicit&&!$program,404);
  $program??=$programs->first();
  if(!$program)return view('tenant.referrers.program',compact('tenant','program','programs'));
  session(['program_dashboard.'.$tenantId=>$program->id]);
  $program->load('offers.currentVersion');
  $automated=$program->effectiveOperatingMode()==='automated';
  $currency=$program->default_currency?:'PHP';
  $members=ReferrerProgramMembership::where('tenant_id',$tenantId)->where('program_id',$program->id)->with('reseller')->get()->filter(fn($m)=>$m->reseller&&$m->reseller->tenant_id===$tenantId);
  $connections=ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$program->id)->pluck('id');
  $clicks=$automated?DB::table('program_referral_clicks')->whereIn('membership_id',$members->pluck('id'))->selectRaw('membership_id,COUNT(*) as total')->groupBy('membership_id')->pluck('total','membership_id'):collect();
  $signups=$automated?DB::table('program_signup_events')->whereIn('connection_id',$connections)->whereIn('membership_id',$members->pluck('id'))->selectRaw('membership_id,COUNT(*) as total')->groupBy('membership_id')->pluck('total','membership_id'):collect();
  $sync=$automated?DB::table('program_signup_sync')->whereIn('connection_id',$connections)->whereNotNull('synced_at')->exists():false;
  $money=$automated?DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('currency',$currency)->selectRaw("referrer_id,SUM(CASE WHEN type='payment' THEN amount_minor ELSE -amount_minor END) as revenue,SUM(reward_minor) as rewards")->groupBy('referrer_id')->get()->keyBy('referrer_id'):collect();
  $paid=$automated?DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('type','payment')->selectRaw('referrer_id,COUNT(DISTINCT customer_id) as total')->groupBy('referrer_id')->pluck('total','referrer_id'):collect();
  $leads=!$automated?DB::table('leads')->where('tenant_id',$tenantId)->where('program_id',$program->id)->whereNull('deleted_at')->selectRaw('reseller_id,COUNT(*) as total')->groupBy('reseller_id')->pluck('total','reseller_id'):collect();
  $summary=['total'=>$members->count(),'active'=>$members->where('status','active')->filter(fn($m)=>in_array($m->reseller->status,['active','nda_signed']))->count(),'invited'=>$members->where('status','invited')->count(),'clicks'=>$clicks->sum(),'signups'=>$sync?$signups->sum():null,'rewards'=>$money->sum('rewards')/100,'referrals'=>$leads->sum()];
  $search=trim((string)request('q',''));$status=(string)request('status','');
  if($search)$members=$members->filter(fn($m)=>str_contains(mb_strtolower($m->reseller->name.' '.$m->reseller->email),mb_strtolower($search)));
  if($status)$members=$members->where('status',$status);
  $rows=new \Illuminate\Pagination\LengthAwarePaginator($members->forPage(max(1,(int)request('page',1)),25)->values(),$members->count(),25,max(1,(int)request('page',1)),['path'=>request()->url(),'query'=>request()->query()]);
  return view('tenant.referrers.program',compact('tenant','program','programs','automated','currency','rows','summary','clicks','signups','sync','money','paid','leads','search','status'));
 }
}
