<?php
namespace App\Services\QuickProgram;

use App\Models\{Program, ProgramConnection};
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\DB;

/** Read-only presentation of published terms and the recorded conversion ledger. */
class ProgramFinancialSummary
{
    public function forTenant(string $tenantId): ?array
    {
        // Deliberately limited to the requested company. Never query LGU IDS here.
        if (ProtectedTenants::isProtected($tenantId) || $tenantId !== 'test-sp4s9i') return null;
        $programs = Program::forTenant($tenantId)->with('offers.currentVersion')->whereNotNull('launched_at')->get();
        return $programs->map(fn ($program) => $this->forProgram($program))->all();
    }

    public function forProgram(Program $program): ?array
    {
        $tenantId = $program->tenant_id;
        if (ProtectedTenants::isProtected($tenantId)) return null;
        $program->loadMissing('offers.currentVersion');
        $terms = [];
        foreach ($program->offers->where('status', 'active') as $offer) {
            $v = $offer->currentVersion;
            if (!$v || $v->status !== 'published' || $v->tenant_id !== $tenantId || $v->program_id !== $program->id) continue;
            $rules = $v->reward_rules ?? [];
            $rate = (float) $v->percentage_rate;
            $fixed = (float) $v->fixed_amount;
            $supported = in_array($v->reward_model, ['percentage','fixed'], true) && $v->qualifying_event === 'payment_received' && ($rules['basis'] ?? '') === 'net_collected_excluding_tax';
            $label = $v->reward_model === 'percentage' ? rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.').'%' : $v->currency.' '.number_format($fixed, 2);
            if (!$supported) $label = 'Custom';
            $scope = ($rules['scope'] ?? '') === 'recurring' ? 'Each eligible payment for '.(int)($rules['duration_months'] ?? 0).' months from the first payment.' : 'First eligible payment only.';
            $terms[] = ['name'=>$offer->name,'currency'=>$v->currency,'model'=>$v->reward_model,'rate'=>$rate,'fixed'=>$fixed,'supported'=>$supported,'label'=>$label,'scope'=>$scope,'hold'=>(int)($rules['hold_days'] ?? 30),'version'=>$v->version_number];
        }
        $ids = ProgramConnection::where('tenant_id',$tenantId)->where('program_id',$program->id)->pluck('id');
        // Monetary totals remain grouped by currency. Historical amounts come from
        // stored reward events, never a recalculation using today's offer rate.
        $totals = DB::table('program_conversion_events')->whereIn('connection_id',$ids)
            ->selectRaw("currency, SUM(CASE WHEN type = 'payment' THEN amount_minor ELSE -amount_minor END) as revenue_minor, SUM(reward_minor) as reward_minor, SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payment_count")
            ->whereIn('type',['payment','refund'])->groupBy('currency')->get()->map(fn($r)=>['currency'=>$r->currency,'revenue'=>(int)$r->revenue_minor / 100,'reward'=>(int)$r->reward_minor / 100,'retained'=>((int)$r->revenue_minor-(int)$r->reward_minor)/100,'count'=>(int)$r->payment_count])->all();
        if (!$totals) $totals = [['currency'=>$program->default_currency,'revenue'=>0,'reward'=>0,'retained'=>0,'count'=>0]];
        $referrers = DB::table('referrer_program_memberships as m')->join('resellers as r','r.id','=','m.reseller_id')
            ->where('m.tenant_id',$tenantId)->where('m.program_id',$program->id)->where('m.status','active')
            ->where('r.tenant_id',$tenantId)->whereNull('r.deleted_at')->whereIn('r.status',['active','nda_signed'])->distinct()->pluck('m.reseller_id')->all();
        $payments = DB::table('program_conversion_events as p')->whereIn('p.connection_id',$ids)->where('p.type','payment');
        $customers = (clone $payments)->select('p.connection_id','p.customer_id')->distinct()->get()->map(fn($p)=>$p->connection_id.':'.$p->customer_id)->all();
        $reviewable = (clone $payments)->where('p.status','pending_review')->whereRaw("p.reward_minor + COALESCE((SELECT SUM(r.reward_minor) FROM program_conversion_events r WHERE r.connection_id = p.connection_id AND r.invoice_id = p.invoice_id AND r.type = 'refund'),0) > 0");
        $signals = ['referrers'=>$referrers,'customers'=>$customers,'on_hold'=>(clone $reviewable)->where('p.available_at','>',now())->count(),'ready'=>(clone $reviewable)->whereNotNull('p.available_at')->where('p.available_at','<=',now())->count()];
        return ['id'=>$program->id,'name'=>$program->name,'status'=>$program->status,'terms'=>$terms,'totals'=>$totals,'signals'=>$signals];
    }
}
