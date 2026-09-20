<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Program,Tenant};
use App\Services\Programs\SubscriptionDashboard;
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};

class ProgramClickReportController extends Controller
{
    public function index(Request $request,string $tenantId,string $programId)
    {
        abort_if(ProtectedTenants::isProtected($tenantId)||!config('programs.enabled'),404);
        $program=Program::forTenant($tenantId)->findOrFail($programId);
        Gate::forUser(auth('web')->user()??auth('tenant')->user())->authorize('view',$program);
        abort_unless($program->effectiveOperatingMode()==='automated',404);
        $tenant=Tenant::findOrFail($tenantId);
        $request->validate(['referrer'=>'nullable|string|max:100']);
        $summary=app(SubscriptionDashboard::class)->compute($program,$request);
        $members=DB::table('referrer_program_memberships')->where('tenant_id',$tenantId)->where('program_id',$programId)->get(['id','reseller_id']);
        $names=DB::table('resellers')->where('tenant_id',$tenantId)->whereIn('id',$members->pluck('reseller_id'))->pluck('name','id');
        $selected=$request->query('referrer');
        if ($selected) {
            abort_unless($members->contains('reseller_id',$selected),404);
            $members=$members->where('reseller_id',$selected);
        }
        $clicks=DB::table('program_referral_clicks')->whereIn('membership_id',$members->pluck('id'))
            ->whereBetween('created_at',[$summary['from']->utc(),$summary['to']->utc()]);
        $total=(clone $clicks)->count();
        $daily=array_fill_keys(array_keys($summary['daily']),0);
        foreach ((clone $clicks)->select('created_at')->cursor() as $click) {
            $daily[Date::parse($click->created_at,'UTC')->setTimezone($summary['tz'])->toDateString()]++;
        }
        $memberReferrers=$members->pluck('reseller_id','id');
        $breakdown=(clone $clicks)->select('membership_id')->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor_hash) as browsers, MAX(created_at) as latest')
            ->groupBy('membership_id')->orderByDesc('clicks')->get();
        $recent=(clone $clicks)->select(['id','membership_id','created_at'])->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $signupMetrics=app(\App\Services\Programs\SignupMetrics::class)->compute($program,$summary['from'],$summary['to'],$selected);
        return view('tenant.programs.clicks',compact('tenant','program','summary','selected','names','memberReferrers','total','daily','breakdown','recent','signupMetrics'));
    }
}
