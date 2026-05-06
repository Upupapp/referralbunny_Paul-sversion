<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReferrerPerformanceService
{
    /**
     * Calculate tenant-scoped performance metrics for a single referrer.
     * Uses only the existing leads table and approved commission logic.
     * Never invents new LGU IDS formulas.
     */
    public function forReseller(string $tenantId, string $resellerName): array
    {
        try {
            $leads = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_name', $resellerName)
                ->get();
        } catch (\Throwable) {
            $leads = collect();
        }

        $total            = $leads->count();
        $active           = $leads->whereIn('status', ['active', 'expiring'])->count();
        $expiring         = $leads->where('status', 'expiring')->count();
        $paid             = $leads->where('stage', 'paid')->count();
        $totalValue       = $leads->sum('deal_value');
        $avgValue         = $total > 0 ? round($totalValue / $total, 2) : 0;
        $conversionRate   = $total > 0 ? round($paid / $total * 100, 1) : null;

        // Commission summary — read existing columns, never recompute
        $pendingCommission = $leads->where('commission_status', 'pending')->sum('deal_value');
        $lockedCommission  = $leads->where('commission_status', 'locked')->sum('deal_value');
        $paidCommission    = $leads->where('commission_status', 'paid')->sum('deal_value');

        // Recent activity
        $lastDeal = $leads->sortByDesc('created_at')->first();

        // Unread messages
        $unreadMessages = 0;
        try {
            $thread = DB::table('message_threads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_name', $resellerName)
                ->first();
            $unreadMessages = $thread ? (int) ($thread->admin_unread ?? 0) : 0;
        } catch (\Throwable) {}

        // Overdue updates — leads that haven't been updated in >14 days
        $overdueUpdates = 0;
        try {
            $overdueUpdates = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_name', $resellerName)
                ->whereIn('status', ['active', 'expiring'])
                ->where('updated_at', '<', now()->subDays(14))
                ->count();
        } catch (\Throwable) {}

        return [
            'total_deals'          => $total,
            'active_deals'         => $active,
            'expiring_deals'       => $expiring,
            'closed_won_deals'     => $paid,
            'total_deal_value'     => (float) $totalValue,
            'average_deal_value'   => (float) $avgValue,
            'conversion_rate'      => $conversionRate,
            'pending_commission'   => (float) $pendingCommission,
            'locked_commission'    => (float) $lockedCommission,
            'paid_commission'      => (float) $paidCommission,
            'unread_messages'      => $unreadMessages,
            'overdue_updates'      => $overdueUpdates,
            'last_deal_created_at' => $lastDeal?->created_at,
        ];
    }

    /**
     * Compute details completeness for a reseller row.
     */
    public function completenessStatus(object $reseller): array
    {
        $missing = [];

        if (empty($reseller->name))  $missing[] = 'name';
        if (empty($reseller->email)) $missing[] = 'email';
        if (empty($reseller->phone)) $missing[] = 'phone';

        $hasSetup = !empty($reseller->password) || in_array($reseller->status ?? '', ['active', 'nda_signed']);

        if (!$hasSetup) $missing[] = 'account setup';

        $score = match(true) {
            count($missing) === 0                      => 'complete',
            in_array('email', $missing)                => 'needs_email',
            in_array('account setup', $missing)        => 'pending_setup',
            default                                    => 'needs_details',
        };

        return [
            'status'  => $score,
            'missing' => $missing,
        ];
    }

    /**
     * Batch completeness for a list of resellers.
     */
    public function batchCompleteness(iterable $resellers): array
    {
        $result = [];
        foreach ($resellers as $r) {
            $result[$r->id] = $this->completenessStatus($r);
        }
        return $result;
    }
}
