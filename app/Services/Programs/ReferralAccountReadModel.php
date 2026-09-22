<?php
namespace App\Services\Programs;

use App\Models\{Program, ProgramConnection, ReferrerProgramMembership, Reseller};
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Read-only account cohort; child events are aggregated once, never joined to each other. */
class ReferralAccountReadModel
{
    public function authorize(Program $program, Reseller $referrer): void
    {
        abort_if(ProtectedTenants::isProtected($program->tenant_id),404);
        abort_unless(config('programs.enabled') && $referrer->tenant_id===$program->tenant_id
            && in_array($referrer->status,['active','nda_signed']) && $program->effectiveOperatingMode()==='automated'
            && Program::forTenant($program->tenant_id)->visible()->whereKey($program->id)->exists()
            && ReferrerProgramMembership::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)
                ->where('reseller_id',$referrer->id)->whereIn('status',['active','approved'])->exists(),403);
    }

    public function filters(Request $request, Program $program): array
    {
        $v=$request->validate(['q'=>'nullable|string|max:100','range'=>['nullable',Rule::in(['all','30','90','custom'])],
            'from'=>'nullable|required_if:range,custom|date_format:Y-m-d','to'=>'nullable|required_if:range,custom|date_format:Y-m-d|after_or_equal:from',
            'progress'=>['nullable',Rule::in(['all','registered','unpaid','paying','repeat'])],
            'sort'=>['nullable',Rule::in(['newest','oldest','activity','rewards'])],
            'currency'=>'nullable|string|regex:/^[A-Z]{3}$/','per_page'=>'nullable|integer|in:10,25,50','page'=>'nullable|integer|min:1']);
        $f=array_merge(['q'=>'','range'=>'all','from'=>null,'to'=>null,'progress'=>'all','sort'=>'newest','currency'=>$program->default_currency ?: 'PHP','per_page'=>10],array_filter($v,fn($x)=>$x!==null));
        if(in_array($f['range'],['30','90'])) {
            $f['to']=Date::now($program->timezone ?: 'UTC')->toDateString();
            $f['from']=Date::now($program->timezone ?: 'UTC')->subDays((int)$f['range']-1)->toDateString();
        }
        if($f['range']==='all') $f['from']=$f['to']=null;
        return $f;
    }

    private function scope(Program $p, Reseller $r): array
    {
        $this->authorize($p,$r);
        return [ProgramConnection::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->pluck('id'),
            ReferrerProgramMembership::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->where('reseller_id',$r->id)->pluck('id')];
    }

    public function sources(Program $p, Reseller $r)
    {
        [$connections,$members]=$this->scope($p,$r);
        $signup=DB::table('program_signup_events as s')->whereIn('s.connection_id',$connections)->whereIn('s.membership_id',$members)
            ->selectRaw("'RB-S' || CAST(s.id AS TEXT) AS reference, s.referred_at, s.occurred_at AS registered_at, s.occurred_at AS activity_at, NULL AS purchase_key, NULL AS event_id, NULL AS currency, 0 AS reward_minor, 0 AS reversal_minor, 'Registration' AS activity_kind");
        $events=DB::table('program_conversion_events as e')->whereIn('e.connection_id',$connections)->where('e.referrer_id',$r->id)
            ->whereIn('e.type',['payment','refund'])->whereNotNull('e.customer_id')->where('e.customer_id','!=','')
            ->leftJoin('program_signup_account_links as a',function($join)use($members){$join->on('a.connection_id','=',DB::raw('CAST(e.connection_id AS TEXT)'))->on('a.payment_customer_id','=','e.customer_id')->whereIn('a.membership_id',$members);})
            ->leftJoin('program_signup_events as s',function($join)use($members){$join->on('s.connection_id','=','a.connection_id')->on('s.customer_id','=','a.signup_customer_id')->on('s.membership_id','=','a.membership_id')->whereIn('s.membership_id',$members);});
        // Earliest recorded event ID is public-safe; raw connector/customer identifiers never leave this query.
        $reference="COALESCE('RB-S' || CAST(s.id AS TEXT), 'RB-P' || (SELECT CAST(f.id AS TEXT) FROM program_conversion_events f WHERE f.connection_id=e.connection_id AND f.customer_id=e.customer_id AND f.referrer_id=e.referrer_id AND f.type IN ('payment','refund') ORDER BY f.occurred_at, f.id LIMIT 1))";
        $events->selectRaw("$reference AS reference, s.referred_at, s.occurred_at AS registered_at, e.occurred_at AS activity_at, CASE WHEN e.type='payment' AND e.invoice_id IS NOT NULL AND e.invoice_id<>'' THEN CAST(e.connection_id AS TEXT)||':'||e.invoice_id ELSE NULL END AS purchase_key, CAST(e.id AS TEXT) AS event_id, e.currency, e.reward_minor, CASE WHEN e.type='refund' THEN e.reward_minor ELSE 0 END AS reversal_minor, CASE WHEN e.type='refund' THEN 'Refund reversal' ELSE 'Payment recorded' END AS activity_kind");
        return $signup->unionAll($events);
    }

    public function cohort(Program $p, Reseller $r, array $f)
    {
        $q=DB::query()->fromSub($this->sources($p,$r),'source')->selectRaw("reference, MIN(referred_at) AS referred_at, MIN(registered_at) AS registered_at, MAX(activity_at) AS activity_at, MAX(CAST(activity_at AS TEXT)||'|'||activity_kind) AS activity_description, MAX(CASE WHEN purchase_key IS NOT NULL THEN activity_at ELSE NULL END) AS last_payment, COUNT(DISTINCT purchase_key) AS payments, SUM(CASE WHEN currency=? THEN reward_minor ELSE 0 END) AS rewards, SUM(CASE WHEN currency=? THEN reversal_minor ELSE 0 END) AS reversals",[$f['currency'],$f['currency']])->groupBy('reference');
        $q=DB::query()->fromSub($q,'accounts');
        if($f['from']) $q->whereBetween('referred_at',[Date::parse($f['from'],$p->timezone ?: 'UTC')->startOfDay()->utc(),Date::parse($f['to'],$p->timezone ?: 'UTC')->endOfDay()->utc()]);
        return $q;
    }

    public function matching($q, array $f)
    {
        if($f['q']!=='') $q->whereRaw('LOWER(reference) LIKE ?', ['%'.strtolower(str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$f['q'])).'%']);
        match($f['progress']) {
            'registered'=>$q->whereNotNull('registered_at'),
            'unpaid'=>$q->whereNotNull('registered_at')->where('payments',0),
            'paying'=>$q->where('payments','>=',1),
            'repeat'=>$q->where('payments','>=',2),
            default=>null,
        };
        [$column,$direction]=match($f['sort']){'oldest'=>['referred_at','asc'],'activity'=>['activity_at','desc'],'rewards'=>['rewards','desc'],default=>['referred_at','desc']};
        return $q->orderByRaw("CASE WHEN $column IS NULL THEN 1 ELSE 0 END")->orderBy($column,$direction)->orderBy('reference');
    }

    public function report(Program $p, Reseller $r, Request $request): array
    {
        $f=$this->filters($request,$p);[$connections]=$this->scope($p,$r);
        $currencies=DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('referrer_id',$r->id)->distinct()->pluck('currency')->push($p->default_currency ?: 'PHP')->unique()->values();
        abort_unless($currencies->contains($f['currency']),422,'Choose an available reward currency.');
        $cohort=$this->cohort($p,$r,$f);
        $kpis=(clone $cohort)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN registered_at IS NOT NULL AND payments=0 THEN 1 ELSE 0 END),0) AS unpaid, COALESCE(SUM(CASE WHEN payments>0 THEN 1 ELSE 0 END),0) AS paying, COALESCE(SUM(CASE WHEN payments>1 THEN 1 ELSE 0 END),0) AS repeat_paying, COALESCE(SUM(rewards),0) AS rewards')->first();
        $rows=$this->matching(clone $cohort,$f)->paginate($f['per_page'])->withQueryString();
        $sync=DB::table('program_signup_sync')->whereIn('connection_id',$connections)->orderByDesc('synced_at')->first();
        $unmatched=DB::table('program_conversion_events')->whereIn('connection_id',$connections)->where('referrer_id',$r->id)->whereIn('type',['payment','refund'])->where(fn($q)=>$q->whereNull('customer_id')->orWhere('customer_id',''))->count();
        $unlinked=(clone $cohort)->whereNull('registered_at')->count();
        return compact('f','currencies','kpis','rows','sync','unmatched','unlinked');
    }

    public function detail(Program $p, Reseller $r, string $reference, string $currency, int $page=1): array
    {
        $f=['currency'=>$currency,'from'=>null,'to'=>null];
        $account=$this->cohort($p,$r,$f)->where('reference',$reference)->first();abort_unless($account,404);
        $eventIds=DB::query()->fromSub($this->sources($p,$r),'source')->where('reference',$reference)->whereNotNull('event_id')->select('event_id');
        $events=DB::table('program_conversion_events')->whereIn(DB::raw('CAST(id AS TEXT)'),$eventIds);
        abort_unless($currency===($p->default_currency ?: 'PHP') || DB::query()->fromSub($this->sources($p,$r),'source')->where('currency',$currency)->exists(),422,'Choose an available reward currency.');
        $timelineEvents=(clone $events)->selectRaw('type,currency,reward_minor,occurred_at,status,available_at,offer_version_id,CAST(id AS TEXT) AS sort_id');
        $registration=DB::query()->fromSub($this->sources($p,$r),'source')->where('reference',$reference)->whereNull('event_id')
            ->selectRaw("'registration' AS type, NULL AS currency, 0 AS reward_minor, registered_at AS occurred_at, 'registered' AS status, NULL AS available_at, NULL AS offer_version_id, '0' AS sort_id");
        $timeline=DB::query()->fromSub($timelineEvents->unionAll($registration),'timeline')->orderByDesc('occurred_at')->orderBy('sort_id')->paginate(25,['*'],'history_page',$page);
        $versions=\App\Models\ProgramOfferVersion::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->whereIn('id',(clone $events)->whereNotNull('offer_version_id')->select('offer_version_id'))->get();
        $format=fn($date)=>$date ? Date::parse($date,'UTC')->setTimezone($p->timezone ?: 'UTC')->format('M j, Y · H:i') : 'Date unavailable';
        $money=fn($minor)=>$currency.' '.number_format($minor/100,2);
        $sync=ProgramConnection::where('tenant_id',$p->tenant_id)->where('program_id',$p->id)->pluck('id');
        $syncAt=DB::table('program_signup_sync')->whereIn('connection_id',$sync)->max('synced_at');
        $firstPayment=(clone $events)->where('type','payment')->min('occurred_at');
        $lastPayment=(clone $events)->where('type','payment')->max('occurred_at');
        $rewardStates=(clone $events)->where('currency',$currency)->selectRaw('status, SUM(reward_minor) as total')->groupBy('status')->get();
        return ['reference'=>$reference,'program'=>$p->name,'referred'=>$format($account->referred_at),'registered'=>$format($account->registered_at),'synced'=>$format($syncAt),'timezone'=>$p->timezone ?: 'UTC',
            'attribution'=>$account->registered_at?'Verified attributed registration':'Payment attribution recorded; signup mapping unavailable',
            'first_payment'=>$format($firstPayment),'last_payment'=>$format($lastPayment),'latest_activity'=>$format($account->activity_at),
            'reward_states'=>$rewardStates->map(fn($state)=>['status'=>str_replace('_',' ',$state->status),'amount'=>$money($state->total)])->values(),
            'payments'=>(int)$account->payments,'rewards'=>$money($account->rewards),'reversals'=>$money($account->reversals),'eligibility'=>'Not available — subscription lifecycle and reward-window status are not supplied by this feed.',
            'offers'=>$versions->map(fn($v)=>['version'=>$v->version_number,'rate'=>$v->reward_model==='percentage' ? $v->percentage_rate.'%' : $v->reward_model,'event'=>$v->qualifying_event,'scope'=>$v->reward_rules['scope'] ?? 'Not specified','duration'=>$v->reward_rules['duration_months'] ?? null,'hold'=>$v->reward_rules['hold_days'] ?? null])->values(),
            'history'=>$timeline->getCollection()->map(fn($e)=>['type'=>match($e->type){'refund'=>'Refund reversal','registration'=>'Registration',default=>'Payment recorded'},'date'=>$format($e->occurred_at),'amount'=>$e->type==='registration' ? null : $e->currency.' '.number_format($e->reward_minor/100,2),'status'=>str_replace('_',' ',$e->status),'available'=>$format($e->available_at)])->values(),
            'history_page'=>$timeline->currentPage(),'history_pages'=>$timeline->lastPage()];
    }
}
