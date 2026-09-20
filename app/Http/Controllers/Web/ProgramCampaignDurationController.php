<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\ProtectedTenants;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;

class ProgramCampaignDurationController extends Controller
{
    public function store(Request $request, string $tenantId, string $programId)
    {
        abort_if(ProtectedTenants::isProtected($tenantId) || !config('programs.enabled'),404);
        $program = Program::forTenant($tenantId)->findOrFail($programId);
        Gate::forUser(auth('web')->user() ?? auth('tenant')->user())->authorize('update',$program);
        abort_unless($program->effectiveOperatingMode() === 'automated',404);
        $data = $request->validateWithBag('duration', [
            'campaign_start'=>'required|date_format:Y-m-d',
            'campaign_end'=>'required|date_format:Y-m-d|after_or_equal:campaign_start',
        ]);
        $start = Date::parse($data['campaign_start'],$program->timezone ?: 'UTC')->startOfDay();
        $end = Date::parse($data['campaign_end'],$program->timezone ?: 'UTC')->endOfDay();
        if ($start->diffInDays($end) > 3660) {
            throw ValidationException::withMessages(['campaign_end'=>'Choose a campaign duration of at most ten years.'])->errorBag('duration');
        }
        DB::transaction(function () use ($program,$start,$end) {
            $program->update(['referral_period_opens_at'=>$start->utc(), 'referral_period_closes_at'=>$end->utc(),
                'updated_by'=>(string)(auth('tenant')->id() ?? auth('web')->id())]);
        });
        return redirect()->route('tenant.dashboard',['tenantId'=>$tenantId,'program_id'=>$programId])
            ->with('success','Campaign duration saved. Your dashboard and daily referral target now use these dates.');
    }
}
