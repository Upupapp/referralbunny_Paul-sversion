<?php
namespace App\Services\Programs;

use App\Models\{Program,ProgramConnection,Reseller};
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferrerSubscriptionDashboard
{
    public function compute(Program $program, Reseller $reseller, Request $request): array
    {
        abort_if(ProtectedTenants::isProtected($program->tenant_id) || $reseller->tenant_id !== $program->tenant_id,403);
        $tz=$program->timezone ?: 'UTC';
        $ids=ProgramConnection::where('tenant_id',$program->tenant_id)->where('program_id',$program->id)->pluck('id');
        $base=DB::table('program_conversion_events')->whereIn('connection_id',$ids)
            ->where('referrer_id',$reseller->id)->whereIn('type',['payment','refund']);
        $currencies=(clone $base)->distinct()->pluck('currency')->push($program->default_currency ?: 'PHP')->unique()->values();
        $data=$request->validate(['currency'=>'nullable|string|size:3','days'=>'nullable|integer|in:7,30,90']);
        $currency=$data['currency'] ?? ($program->default_currency ?: 'PHP');
        abort_unless($currencies->contains($currency),422,'Choose an available reward currency.');
        $days=(int)($data['days'] ?? 30);
        $end=Date::now($tz)->endOfDay(); $start=$end->subDays($days-1)->startOfDay();
        $money=(clone $base)->where('currency',$currency);
        $net=(int)(clone $money)->sum('reward_minor');
        $payments=(clone $money)->where('type','payment')->count();
        $customers=(clone $money)->where('type','payment')->distinct()->count('customer_id');
        $pending=DB::table('program_conversion_events as p')->whereIn('p.connection_id',$ids)
            ->where('p.referrer_id',$reseller->id)->where('p.currency',$currency)->where('p.type','payment')->where('p.status','pending_review')
            ->whereRaw("p.reward_minor + COALESCE((SELECT SUM(r.reward_minor) FROM program_conversion_events r WHERE r.connection_id=p.connection_id AND r.invoice_id=p.invoice_id AND r.referrer_id=p.referrer_id AND r.currency=p.currency AND r.type='refund'),0) > 0");
        $hold=(clone $pending)->where('p.available_at','>',now())->count();
        $ready=(clone $pending)->whereNotNull('p.available_at')->where('p.available_at','<=',now())->count();
        $daily=[];for($day=$start;$day->lte($end);$day=$day->addDay())$daily[$day->toDateString()]=0;
        $chart=(clone $money)->whereBetween('occurred_at',[$start->utc(),$end->utc()])->get(['occurred_at','reward_minor']);
        foreach($chart as $event) { $date=Date::parse($event->occurred_at,'UTC')->setTimezone($tz)->toDateString(); $daily[$date]+=$event->reward_minor/100; }
        $recent=(clone $money)->orderByDesc('occurred_at')->limit(5)->get(['type','reward_minor','currency','occurred_at','status','available_at']);
        $unread=DB::table('program_messages')->where('tenant_id',$program->tenant_id)->where('program_id',$program->id)
            ->where('reseller_id',$reseller->id)->where('sender_type','admin')->whereNull('read_at')->count();
        // Legacy production enrollment IDs are varchar; analytics IDs are UUID.
        // Resolve scoped IDs first so PostgreSQL binds values to the column type.
        $clickMemberships=DB::table('referrer_program_memberships')
            ->where('tenant_id',$program->tenant_id)->where('program_id',$program->id)
            ->where('reseller_id',$reseller->id)->pluck('id');
        $clickBase=DB::table('program_referral_clicks as clicks')->whereIn('membership_id',$clickMemberships);
        $linkClicks=(clone $clickBase)->count();
        $uniqueBrowsers=(clone $clickBase)->distinct()->count('clicks.visitor_hash');
        $dailyClicks=array_fill_keys(array_keys($daily),0);
        $clickEvents=(clone $clickBase)->whereBetween('clicks.created_at',[$start->utc(),$end->utc()])
            ->select('clicks.created_at')->cursor();
        foreach ($clickEvents as $event) {
            $date=Date::parse($event->created_at,'UTC')->setTimezone($tz)->toDateString();
            $dailyClicks[$date]++;
        }
        $periodClicks=array_sum($dailyClicks);
        $campaign=app(CampaignPeriod::class)->forProgram($program);
        $signupMetrics=app(SignupMetrics::class)->compute($program,$start,$end,$reseller->id);
        return compact('signupMetrics','currency','currencies','days','start','end','tz','net','payments','customers','hold','ready','daily','recent','unread','campaign','linkClicks','uniqueBrowsers','periodClicks','dailyClicks');
    }
}
