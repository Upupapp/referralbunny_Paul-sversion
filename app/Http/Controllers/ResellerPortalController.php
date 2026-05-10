<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\CriticalActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerPortalController extends Controller
{
    private function reseller(): Reseller
    {
        $reseller = Auth::guard('reseller')->user();
        if ($reseller instanceof Reseller) {
            return $reseller;
        }
        // Super admin accessing reseller portal — not supported directly.
        // Super admins should use the tenant admin portal instead.
        abort(403, 'Reseller portal requires reseller authentication.');
    }

    public function dashboard($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        // Load all reseller-accessible leads safely
        try {
            $leads = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_name', $reseller->name)
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable) {
            $leads = collect();
        }

        $stats = [
            'total'      => $leads->count(),
            'active'     => $leads->whereIn('status', ['active', 'expiring'])->count(),
            'expiring'   => $leads->where('status', 'expiring')->count(),
            'paid'       => $leads->where('stage', 'paid')->count(),
            'pipeline'   => $leads->sum('deal_value'),
            'conversion' => $leads->count() > 0
                ? round($leads->where('stage', 'paid')->count() / $leads->count() * 100)
                : 0,
        ];

        $recentLeads = $leads->take(6);

        // Commission summary — safe fallback if commission_status column missing
        try {
            $commissionStats = [
                'pending' => $leads->where('commission_status', 'pending')->sum('deal_value'),
                'locked'  => $leads->where('commission_status', 'locked')->sum('deal_value'),
                'paid'    => $leads->where('commission_status', 'paid')->sum('deal_value'),
            ];
        } catch (\Throwable) {
            $commissionStats = ['pending' => 0, 'locked' => 0, 'paid' => 0];
        }

        // Unread messages count
        try {
            $thread      = \App\Models\MessageThread::where('tenant_id', $tenantId)
                ->where('reseller_id', $reseller->id)->first();
            $unreadCount = $thread ? (int) $thread->reseller_unread : 0;
        } catch (\Throwable) {
            $unreadCount = 0;
        }

        // Recent activity — wrapped so any DB issue never crashes the dashboard
        try {
            $recentActivity = app(CriticalActionService::class)
                ->forReseller($tenantId, $reseller->name, 6);
        } catch (\Throwable) {
            $recentActivity = [];
        }

        return view('reseller.dashboard', compact(
            'reseller', 'tenant', 'stats', 'recentLeads',
            'recentActivity', 'commissionStats', 'unreadCount'
        ));
    }

    public function deals($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.deals.index', compact('reseller', 'tenant'));
    }

    public function commission($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $leads = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('reseller_name', $reseller->name)
            ->get();

        // Load this referrer's commission splits to get their percentage per deal
        $leadIds = $leads->pluck('id');
        $splits  = DB::table('commission_splits')
            ->whereIn('lead_id', $leadIds)
            ->where('reseller_name', $reseller->name)
            ->get()
            ->keyBy('lead_id');

        // Attach computed commission amounts to each lead
        $leads = $leads->map(function ($lead) use ($splits) {
            $aa         = (float) ($lead->added_amount ?? 0);
            $pool       = $aa * 0.70;
            $splitRow   = $splits->get($lead->id);
            $pct        = $splitRow ? (float) ($splitRow->percentage ?? 100) : 100.0;
            $lead->commission_pool  = round($pool, 2);
            $lead->my_commission    = round($pool * $pct / 100, 2);
            $lead->split_percentage = $pct;
            return $lead;
        });

        // Summary cards show referrer's actual commission share (not deal value)
        $commissionStats = [
            'pending' => $leads->where('commission_status', 'pending')->sum('my_commission'),
            'locked'  => $leads->where('commission_status', 'locked')->sum('my_commission'),
            'paid'    => $leads->where('commission_status', 'paid')->sum('my_commission'),
        ];

        return view('reseller.commission', compact('reseller', 'tenant', 'leads', 'commissionStats'));
    }

    public function profile($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.profile', compact('reseller', 'tenant'));
    }

    public function messages($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $thread = \App\Models\MessageThread::where('tenant_id', $tenantId)
            ->where('reseller_id', $reseller->id)
            ->first();

        $messages = $thread
            ? \App\Models\ThreadMessage::where('thread_id', $thread->id)
                ->orderBy('created_at')
                ->get()
            : collect();

        // Mark reseller's unread messages as read
        if ($thread && $thread->reseller_unread > 0) {
            $thread->update(['reseller_unread' => 0]);
            \App\Models\ThreadMessage::where('thread_id', $thread->id)
                ->where('sender_type', 'admin')
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return view('reseller.messages', compact('reseller', 'tenant', 'thread', 'messages'));
    }

    public function notifications($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.notifications', compact('reseller', 'tenant'));
    }
}
