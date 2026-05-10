<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\PartnerMessage;
use App\Models\PartnerThread;
use App\Models\Reseller;
use App\Services\UserDisplayNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerPortalController extends Controller
{
    private function partner(): Partner
    {
        return Auth::guard('partner')->user();
    }

    private function authorizedDealIds(): array
    {
        return DealPartner::where('partner_user_id', $this->partner()->id)
            ->where('tenant_id', $this->partner()->tenant_id)
            ->where('status', 'active')
            ->pluck('deal_id')
            ->toArray();
    }

    public function dashboard()
    {
        $partner    = $this->partner();
        $dealIds    = $this->authorizedDealIds();

        $dealCount     = count($dealIds);
        $unreadCount   = (int) PartnerThread::where('partner_id', $partner->id)->sum('partner_unread');
        $completion    = UserDisplayNameService::completionPercent($partner);
        $recentDeals   = Lead::whereIn('id', $dealIds)->orderByDesc('created_at')->limit(5)->get();

        // Expiring deals the partner is associated with (for Needs Attention panel)
        $expiringDeals = Lead::whereIn('id', $dealIds)
            ->where('status', 'expiring')
            ->orderBy('days_left')
            ->limit(3)
            ->get(['id', 'name', 'days_left', 'status']);

        return view('partner.dashboard', compact(
            'partner', 'dealCount', 'unreadCount', 'completion', 'recentDeals', 'expiringDeals'
        ));
    }

    public function deals()
    {
        $partner = $this->partner();
        $deals   = Lead::whereIn('id', $this->authorizedDealIds())
            ->orderByDesc('created_at')
            ->get();

        return view('partner.deals.index', compact('partner', 'deals'));
    }

    public function dealShow(string $dealId)
    {
        $partner = $this->partner();

        // Security: verify the partner has active access to this deal
        $authorized = DealPartner::where('partner_user_id', $partner->id)
            ->where('deal_id', $dealId)
            ->where('status', 'active')
            ->exists();

        if (!$authorized) {
            abort(403, 'You do not have access to this deal.');
        }

        $lead = Lead::findOrFail($dealId);

        // Verify the deal belongs to the partner's tenant
        if ($lead->tenant_id !== $partner->tenant_id) {
            abort(403, 'You do not have access to this deal.');
        }

        $dealPartner = DealPartner::where('partner_user_id', $partner->id)
            ->where('deal_id', $dealId)
            ->first();

        $thread = PartnerThread::where('partner_id', $partner->id)
            ->where('deal_id', $dealId)
            ->first();

        // Load only this partner's own split — never expose other partners' or referrer's shares
        $myPartnerSplit = \Illuminate\Support\Facades\DB::table('deal_partner_splits')
            ->where('lead_id', $dealId)
            ->where('tenant_id', $partner->tenant_id)
            ->where('partner_email', $partner->email)
            ->first();

        // Compute this partner's peso amount from the deal's commission pool
        $commissionPool = round((float) ($lead->added_amount ?? 0) * 0.70, 2);
        if ($myPartnerSplit) {
            $myPartnerSplit->peso_amount = $myPartnerSplit->split_share_type === 'percentage'
                ? round($commissionPool * (float) $myPartnerSplit->split_share_value / 100, 2)
                : (float) $myPartnerSplit->split_share_value;
            $myPartnerSplit->commission_pool = $commissionPool;
        }

        return view('partner.deals.show', compact('partner', 'lead', 'dealPartner', 'thread', 'myPartnerSplit'));
    }

    public function messages()
    {
        $partner = $this->partner();

        // Load threads with deal + reseller for list display only.
        // Messages are loaded on-demand via threadMessages() when a thread is opened.
        $threads = PartnerThread::where('partner_id', $partner->id)
            ->where('tenant_id', $partner->tenant_id)
            ->with(['deal', 'reseller'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('partner.messages', compact('partner', 'threads'));
    }

    public function threadMessages(string $threadId)
    {
        $partner = $this->partner();

        $thread = PartnerThread::findOrFail($threadId);

        if ((string) $thread->partner_id !== (string) $partner->id) {
            abort(403, 'You do not have access to this thread.');
        }

        // Mark partner messages as read
        $thread->update(['partner_unread' => 0]);

        PartnerMessage::where('thread_id', $threadId)
            ->where('sender_type', 'reseller')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = $thread->messages()->get()->map(fn($m) => [
            'id'          => $m->id,
            'sender_type' => $m->sender_type,
            'sender_name' => $m->sender_name,
            'body'        => $m->body,
            'created_ago' => $m->created_at?->diffForHumans() ?? 'just now',
        ]);

        return response()->json([
            'thread'   => ['id' => $thread->id, 'deal_id' => $thread->deal_id],
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $partner = $this->partner();

        $data = $request->validate([
            'deal_id' => 'required|string',
            'body'    => 'required|string|max:5000',
        ]);

        // Security: verify the partner has active access to this deal
        $authorized = DealPartner::where('partner_user_id', $partner->id)
            ->where('deal_id', $data['deal_id'])
            ->where('status', 'active')
            ->exists();

        if (!$authorized) {
            abort(403, 'You do not have access to this deal.');
        }

        $lead = Lead::findOrFail($data['deal_id']);

        if ($lead->tenant_id !== $partner->tenant_id) {
            abort(403, 'You do not have access to this deal.');
        }

        // Resolve reseller_id from the lead's reseller_name
        $resellerId = null;
        if ($lead->reseller_name) {
            $reseller = Reseller::where('tenant_id', $partner->tenant_id)
                ->where('name', $lead->reseller_name)
                ->first();
            $resellerId = $reseller?->id;
        }

        // Get or create the thread for this (deal, partner) pair
        $thread = PartnerThread::firstOrCreate(
            [
                'deal_id'    => $data['deal_id'],
                'partner_id' => $partner->id,
            ],
            [
                'tenant_id'   => $partner->tenant_id,
                'reseller_id' => $resellerId,
            ]
        );

        $preview = mb_substr($data['body'], 0, 100);

        $message = PartnerMessage::create([
            'thread_id'   => $thread->id,
            'tenant_id'   => $partner->tenant_id,
            'deal_id'     => $data['deal_id'],
            'sender_type' => 'partner',
            'sender_id'   => $partner->id,
            'sender_name' => $partner->display_name,
            'body'        => $data['body'],
            'is_read'     => false,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => $preview,
            'reseller_unread'      => $thread->reseller_unread + 1,
            'reseller_id'          => $thread->reseller_id ?? $resellerId,
        ]);

        return response()->json([
            'message' => [
                'id'          => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body'        => $message->body,
                'created_ago' => 'just now',
            ],
        ]);
    }
}
