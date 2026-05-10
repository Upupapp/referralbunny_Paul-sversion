<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\CommissionCalculationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantCommissionController extends Controller
{
    public function __construct(private CommissionCalculationService $calc) {}

    /**
     * GET /tenant/{tenantId}/commission
     * Commission overview report for tenant admins and managers.
     */
    public function index(Request $request, string $tenantId): \Illuminate\View\View
    {
        // Verify the requesting user belongs to this tenant
        $ctxId = TenantContext::id();
        if ($ctxId && $ctxId !== $tenantId) {
            abort(403);
        }

        $tenant = Tenant::findOrFail($tenantId);

        // Filters
        $filterStatus  = $request->query('status');   // pending|locked|paid
        $filterDate    = $request->query('date_from');
        $filterDateTo  = $request->query('date_to');
        $filterReferrer = $request->query('referrer');

        // Build base query — all deals for this tenant with financial data
        $query = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select([
                'id', 'name', 'stage', 'status',
                'base_cost', 'added_amount', 'deal_value',
                'commission_status', 'reseller_name',
                'created_at', 'updated_at',
            ]);

        if ($filterStatus && in_array($filterStatus, ['pending', 'locked', 'paid'])) {
            $query->where('commission_status', $filterStatus);
        }
        if ($filterReferrer) {
            $query->where('reseller_name', 'like', '%' . $filterReferrer . '%');
        }
        if ($filterDate) {
            $query->where('created_at', '>=', $filterDate);
        }
        if ($filterDateTo) {
            $query->where('created_at', '<=', $filterDateTo . ' 23:59:59');
        }

        $deals = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        // Compute per-deal breakdown
        $enriched = collect($deals->items())->map(function ($deal) {
            $b = $this->calc->breakdownFromLead($deal);
            return array_merge((array) $deal, [
                'computed_added_amount'    => $b['added_amount'],
                'computed_company_share'   => $b['company_share'],
                'computed_commission_pool' => $b['commission_pool'],
                'computed_contract_value'  => $b['deal_value'],
            ]);
        });

        // Referrer splits — load for all deals on this page
        $dealIds = collect($deals->items())->pluck('id');
        $splits  = DB::table('commission_splits')
            ->whereIn('lead_id', $dealIds)
            ->get()
            ->groupBy('lead_id');

        // Partner splits
        $partnerSplits = DB::table('deal_partner_splits')
            ->whereIn('lead_id', $dealIds)
            ->where('tenant_id', $tenantId)
            ->get()
            ->groupBy('lead_id');

        // Summary totals (all deals, no page filter)
        $totals = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*) as total_deals,
                SUM(deal_value)   as total_contract,
                SUM(base_cost)    as total_base_cost,
                SUM(added_amount) as total_added_amount,
                SUM(CASE WHEN commission_status = \'paid\'   THEN added_amount ELSE 0 END) as paid_added_amount,
                SUM(CASE WHEN commission_status = \'locked\' THEN added_amount ELSE 0 END) as locked_added_amount,
                SUM(CASE WHEN commission_status = \'pending\' THEN added_amount ELSE 0 END) as pending_added_amount
            ')
            ->first();

        $summary = [
            'total_deals'            => $totals->total_deals    ?? 0,
            'total_contract'         => (float) ($totals->total_contract      ?? 0),
            'total_base_cost'        => (float) ($totals->total_base_cost     ?? 0),
            'total_added_amount'     => (float) ($totals->total_added_amount  ?? 0),
            'total_company_share'    => $this->calc->companyShare((float) ($totals->total_added_amount ?? 0)),
            'total_commission_pool'  => $this->calc->commissionPool((float) ($totals->total_added_amount ?? 0)),
            'paid_commission_pool'   => $this->calc->commissionPool((float) ($totals->paid_added_amount ?? 0)),
            'locked_commission_pool' => $this->calc->commissionPool((float) ($totals->locked_added_amount ?? 0)),
            'pending_commission_pool'=> $this->calc->commissionPool((float) ($totals->pending_added_amount ?? 0)),
        ];

        return view('tenant.commission.index', compact(
            'tenant', 'deals', 'enriched', 'splits', 'partnerSplits', 'summary',
            'filterStatus', 'filterDate', 'filterDateTo', 'filterReferrer'
        ));
    }

    /**
     * GET /tenant/{tenantId}/commission/export
     * CSV export of commission data.
     */
    public function export(Request $request, string $tenantId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ctxId = TenantContext::id();
        if ($ctxId && $ctxId !== $tenantId) {
            abort(403);
        }

        $tenant = Tenant::findOrFail($tenantId);

        $filterStatus  = $request->query('status');
        $filterReferrer = $request->query('referrer');

        $query = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select(['id','name','stage','status','base_cost','added_amount',
                      'deal_value','commission_status','reseller_name','created_at']);

        if ($filterStatus && in_array($filterStatus, ['pending', 'locked', 'paid'])) {
            $query->where('commission_status', $filterStatus);
        }
        if ($filterReferrer) {
            $query->where('reseller_name', 'like', '%' . $filterReferrer . '%');
        }

        $deals     = $query->orderByDesc('created_at')->get();
        $dealIds   = $deals->pluck('id');
        $splits    = DB::table('commission_splits')->whereIn('lead_id', $dealIds)->get()->groupBy('lead_id');
        $pSplits   = DB::table('deal_partner_splits')->whereIn('lead_id', $dealIds)->where('tenant_id', $tenantId)->get()->groupBy('lead_id');

        $filename = 'commission-' . $tenant->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($deals, $splits, $pSplits) {
            $out = fopen('php://output', 'w');

            // Header row — prefixed with tab to prevent CSV injection
            fputcsv($out, [
                'Deal Name', 'Stage', 'Deal Value', 'Base Cost', 'Added Amount',
                'Company Share (30%)', 'Commission Pool (70%)',
                'Referrer', 'Referrer Split %', 'Referrer Commission',
                'Partner', 'Partner Split', 'Partner Split Type',
                'Commission Status', 'Created At',
            ]);

            foreach ($deals as $deal) {
                $b  = $this->calc->breakdownFromLead($deal);
                $sp = ($splits[$deal->id] ?? collect())->first();
                $pp = ($pSplits[$deal->id] ?? collect())->first();

                $referrerAmount = $sp
                    ? $this->calc->referrerShare($b['commission_pool'], (float)($sp->percentage ?? 100))
                    : 0;

                // Sanitize: prefix text fields that could be formula injections
                $safeName = preg_match('/^[=+\-@]/', $deal->name) ? "\t" . $deal->name : $deal->name;

                fputcsv($out, [
                    $safeName,
                    ucwords(str_replace('_', ' ', $deal->stage ?? '')),
                    number_format($b['deal_value'], 2),
                    number_format($b['base_cost'], 2),
                    number_format($b['added_amount'], 2),
                    number_format($b['company_share'], 2),
                    number_format($b['commission_pool'], 2),
                    $deal->reseller_name ?? '',
                    $sp ? ($sp->percentage . '%') : '',
                    number_format($referrerAmount, 2),
                    $pp ? $pp->partner_name : '',
                    $pp ? number_format($pp->split_share_value, 2) : '',
                    $pp ? ($pp->split_share_type === 'percentage' ? 'Percentage' : 'Fixed Amount') : '',
                    ucfirst($deal->commission_status ?? 'pending'),
                    $deal->created_at,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
