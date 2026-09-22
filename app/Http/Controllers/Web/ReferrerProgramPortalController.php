<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\{ProgramConnection, Tenant};
use App\Services\Programs\ReferrerProgramContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReferrerProgramPortalController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $context = app(ReferrerProgramContext::class)->resolve($tenantId,$request);
        extract($context);
        $tenant = Tenant::findOrFail($tenantId);
        $mode = $program?->effectiveOperatingMode();
        if ($mode === 'automated' && $request->routeIs('reseller.deals')) return app(ReferrerReferralAccountController::class)->index($request,$tenantId);
        if ($mode === 'automated' && $request->routeIs('reseller.commission')) return app(ReferrerRewardsController::class)->index($request,$tenantId);
        $records = null; $totals = collect(); $membership = null; $offers = collect(); $stages = collect();
        if ($program) {
            $membership = $program->referrerMemberships()->where('tenant_id',$tenantId)->where('reseller_id',$reseller->id)->first();
            $offers = $program->offers()->where('tenant_id',$tenantId)->where('status','active')->with('currentVersion')->get();
            if ($mode === 'automated') {
                $ids = ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$program->id)->pluck('id');
                $events = DB::table('program_conversion_events')->whereIn('connection_id',$ids)->where('referrer_id',$reseller->id)->whereIn('type',['payment','refund']);
                if ($request->filled('referral_reference') && $request->routeIs('reseller.commission','reseller.activity')) {
                    $request->validate(['referral_reference'=>'string|max:100']);
                    $read=app(\App\Services\Programs\ReferralAccountReadModel::class);
                    $read->detail($program,$reseller,$request->referral_reference,$program->default_currency ?: 'PHP');
                    $eventIds=DB::query()->fromSub($read->sources($program,$reseller),'source')->where('reference',$request->referral_reference)->whereNotNull('event_id')->select('event_id');
                    $events->whereIn(DB::raw('CAST(id AS TEXT)'),$eventIds);
                }
                $totals = (clone $events)->selectRaw("currency, SUM(reward_minor) as reward_minor, SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payments")->groupBy('currency')->get();
                $records = $events->orderByDesc('occurred_at')->paginate(20)->withQueryString();
            } else {
                $leads = DB::table('leads')->where('tenant_id',$tenantId)->where('program_id',$program->id)
                    ->where('reseller_id',$reseller->id)->whereNull('deleted_at')->where('status','!=','archived');
                $stages = (clone $leads)->selectRaw('stage, COUNT(*) as total')->groupBy('stage')->get();
                $records = $leads->orderByDesc('created_at')->paginate(20, ['id','name','stage','status','created_at'])->withQueryString();
            }
        }
        $referralLink = $program ? app(\App\Services\Programs\ReferrerReferralLink::class)->forMembership($program, $membership) : null;
        $page = $request->routeIs('reseller.commission') ? 'Rewards' : ($request->routeIs('reseller.deals') ? 'My Referrals' : ($request->routeIs('reseller.activity') ? 'Activity' : 'Dashboard'));
        $subscriptionDashboard = $program && $mode === 'automated' && $page === 'Dashboard'
            ? app(\App\Services\Programs\ReferrerSubscriptionDashboard::class)->compute($program,$reseller,$request) : null;
        return view('reseller.programs.portal', $context + compact('tenant','mode','records','totals','membership','offers','stages','page','referralLink','subscriptionDashboard'));
    }
}
