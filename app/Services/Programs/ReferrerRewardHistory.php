<?php
namespace App\Services\Programs;
use App\Models\{Program,Reseller};
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ReferrerRewardHistory {
 const STATES=['hold'=>'On hold','ready'=>'Ready for review','pending_review'=>'Pending review','reversed'=>'Reversed','not_eligible'=>'Not eligible'];
 public function report(Program $p,Reseller $r,Request $request):array {
  $accounts=app(ReferralAccountReadModel::class);$accounts->authorize($p,$r);
  $v=$request->validate(['currency'=>'nullable|regex:/^[A-Z]{3}$/','range'=>['nullable',Rule::in(['all','30','90','custom'])],'from'=>'nullable|required_if:range,custom|date_format:Y-m-d','to'=>'nullable|required_if:range,custom|date_format:Y-m-d|after_or_equal:from','q'=>'nullable|string|max:100','status'=>['nullable',Rule::in(array_merge(['all'],array_keys(self::STATES)))],'event'=>'nullable|in:all,payment,refund','page'=>'nullable|integer|min:1','referral_reference'=>'nullable|string|max:100']);
  $f=array_merge(['currency'=>$p->default_currency?:'PHP','range'=>'all','q'=>'','status'=>'all','event'=>'all','from'=>null,'to'=>null,'referral_reference'=>null],array_filter($v,fn($x)=>$x!==null));$tz=$p->timezone?:'UTC';
  if(in_array($f['range'],['30','90'])){$f['to']=Date::now($tz)->toDateString();$f['from']=Date::now($tz)->subDays((int)$f['range']-1)->toDateString();}
  if($f['range']==='all')$f['from']=$f['to']=null;
  $source=DB::query()->fromSub($accounts->sources($p,$r),'s')->whereNotNull('event_id')->select('s.event_id','s.reference');
  $base=DB::table('program_conversion_events as e')->joinSub($source,'account',fn($j)=>$j->on(DB::raw('CAST(e.id AS TEXT)'),'=','account.event_id'));
  $currencies=(clone $base)->distinct()->pluck('e.currency')->push($p->default_currency?:'PHP')->unique()->values();abort_unless($currencies->contains($f['currency']),422,'Choose an available reward currency.');
  if($f['referral_reference']){$accounts->detail($p,$r,$f['referral_reference'],$f['currency']);$base->where('account.reference',$f['referral_reference']);}
  // Same pending-review + availability + net-after-refunds semantics as existing dashboard.
  $remaining="e.reward_minor + COALESCE((SELECT SUM(rv.reward_minor) FROM program_conversion_events rv WHERE rv.connection_id=e.connection_id AND rv.invoice_id=e.invoice_id AND rv.referrer_id=e.referrer_id AND rv.currency=e.currency AND rv.type='refund'),0)";
  $now=Date::now('UTC')->format('Y-m-d H:i:s');
  $state="CASE WHEN e.type='refund' OR (e.type='payment' AND e.reward_minor>0 AND ($remaining)<=0) THEN 'reversed' WHEN e.status='not_eligible' THEN 'not_eligible' WHEN e.status='pending_review' AND ($remaining)>0 AND e.available_at IS NOT NULL THEN CASE WHEN e.available_at > ? THEN 'hold' ELSE 'ready' END ELSE e.status END";
  $base->where('e.currency',$f['currency']);if($f['from'])$base->whereBetween('e.occurred_at',[Date::parse($f['from'],$tz)->startOfDay()->utc(),Date::parse($f['to'],$tz)->endOfDay()->utc()]);
  $events=$base->select('e.id','e.type','e.currency','e.reward_minor','e.occurred_at','e.offer_version_id','account.reference')->selectRaw("($remaining) as remaining_minor")->selectRaw("$state as reward_state",[$now]);
  $all=DB::query()->fromSub($events,'rewards');
  $kpis=(clone $all)->selectRaw("COALESCE(SUM(reward_minor),0) as net, COALESCE(SUM(CASE WHEN reward_state='hold' THEN remaining_minor ELSE 0 END),0) as hold, COALESCE(SUM(CASE WHEN reward_state='ready' THEN remaining_minor ELSE 0 END),0) as ready, COALESCE(SUM(CASE WHEN type='refund' THEN reward_minor ELSE 0 END),0) as reversals")->first();
  $unfiltered=(clone $all)->count();
  if($f['q']!=='')$all->where(fn($q)=>$q->where('reference','like','%'.addcslashes($f['q'],'%_\\').'%')->orWhereRaw('CAST(id AS TEXT) = ?',[$f['q']]));
  if($f['status']!=='all')$all->where('reward_state',$f['status']);if($f['event']!=='all')$all->where('type',$f['event']);
  $records=$all->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15,['*'],'page',(int)$request->input('page',1))->withQueryString();
  $versions=\App\Models\ProgramOfferVersion::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->whereIn('id',$records->pluck('offer_version_id')->filter())->get()->keyBy('id');
  return compact('f','tz','currencies','kpis','records','versions','unfiltered');
 }
}
