<?php
namespace App\Services\Programs;

use App\Models\Program;
use App\Services\QuickProgram\ProgramFinancialSummary;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\DB;

/** Program-scoped read model; never derives earned rewards from current offer rates. */
class ProgramPerformanceSummary
{
    public function compute(Program $program): ?array
    {
        if (ProtectedTenants::isProtected($program->tenant_id)) return null;
        $mode = $program->effectiveOperatingMode();
        if ($mode === 'automated') {
            return ['mode' => $mode, 'financials' => app(ProgramFinancialSummary::class)->forProgram($program)];
        }
        $leads = DB::table('leads')->where('tenant_id', $program->tenant_id)
            ->where('program_id', $program->id)->whereNull('deleted_at')->where('status', '!=', 'archived');
        $stages = (clone $leads)->selectRaw('stage, COUNT(*) as total')->groupBy('stage')->orderBy('stage')->get();
        $program->loadMissing('offers.currentVersion');
        $offers = $program->offers->where('status', 'active')->map(function ($offer) use ($program) {
            $v = $offer->currentVersion;
            if (!$v || $v->status !== 'published' || $v->tenant_id !== $program->tenant_id || $v->program_id !== $program->id) return null;
            $label = match ($v->reward_model) {
                'percentage' => rtrim(rtrim(number_format((float)$v->percentage_rate, 4, '.', ''), '0'), '.').'%',
                'fixed' => $v->currency.' '.number_format((float)$v->fixed_amount, 2),
                default => 'Custom reward',
            };
            return ['name' => $offer->name, 'label' => $label, 'event' => $v->qualifying_event, 'version' => $v->version_number];
        })->filter()->values()->all();
        return ['mode' => $mode, 'total' => (clone $leads)->count(),
            'active' => (clone $leads)->whereIn('status', ['active','expiring'])->count(),
            'expiring' => (clone $leads)->where('status', 'expiring')->count(),
            'stages' => $stages, 'offers' => $offers];
    }
}
