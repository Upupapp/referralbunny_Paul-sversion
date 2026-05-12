<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\PartnerMessage;
use App\Models\PartnerThread;
use App\Models\Reseller;
use App\Services\NotificationDispatchService;
use App\Services\UserDisplayNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerPortalController extends Controller
{
    private function partner(): Partner
    {
        return Auth::guard('partner')->user();
    }

    private ?array $_authorizedDealIds = null;

    private function authorizedDealIds(): array
    {
        if ($this->_authorizedDealIds !== null) {
            return $this->_authorizedDealIds;
        }

        $partner = $this->partner();

        $fromDealPartners = DealPartner::where('partner_user_id', $partner->id)
            ->where('tenant_id', $partner->tenant_id)
            ->where('status', 'active')
            ->pluck('deal_id')
            ->toArray();

        $fromSplits = DB::table('deal_partner_splits')
            ->where('partner_user_id', $partner->id)
            ->where('tenant_id', $partner->tenant_id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->pluck('deal_id')
            ->toArray();

        $this->_authorizedDealIds = array_values(array_unique(array_merge($fromDealPartners, $fromSplits)));
        return $this->_authorizedDealIds;
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

        // Security: partner must be in authorizedDealIds() (checks both deal_partners and splits)
        if (!in_array($dealId, $this->authorizedDealIds())) {
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
            ->where('deal_id', $dealId)
            ->where('tenant_id', $partner->tenant_id)
            ->where('partner_email', $partner->email)
            ->first();

        // Compute this partner's peso amount from the deal's commission pool.
        // NOTE: commission_pool, added_amount, base_cost are NEVER passed to the view —
        // partners are only entitled to see their own estimated commission amount.
        $commissionPool = round((float) ($lead->added_amount ?? 0) * 0.70, 2);
        if ($myPartnerSplit) {
            $myPartnerSplit->peso_amount = $myPartnerSplit->split_share_type === 'percentage'
                ? round($commissionPool * (float) $myPartnerSplit->split_share_value / 100, 2)
                : (float) $myPartnerSplit->split_share_value;
            // Do NOT store commission_pool on the split object — it must not reach the view
        }

        // Build a partner-safe deal summary: only fields partners are authorised to see.
        // Never pass the full Lead model — it contains base_cost, added_amount, tenant financials.
        $dealSummary = [
            'id'                => $lead->id,
            'name'              => $lead->name,
            'stage'             => $lead->stage,
            'status'            => $lead->status,
            'days_left'         => $lead->days_left,
            'deal_value'        => (float) ($lead->deal_value ?? 0),   // total contract value — visible
            'commission_status' => $lead->commission_status ?? 'pending', // pending/locked/paid — visible
            'reseller_name'     => $lead->reseller_name,               // referrer name only, no contact
            'data'              => [                                    // location context only
                'province'     => $lead->data['province']     ?? null,
                'municipality' => $lead->data['municipality'] ?? null,
            ],
        ];

        return view('partner.deals.show', compact(
            'partner', 'dealSummary', 'dealPartner', 'thread', 'myPartnerSplit'
        ));
    }

    // ── Deal Notes ──────────────────────────────────────────────────────────

    public function dealNotes(string $dealId)
    {
        $partner = $this->partner();

        if (!in_array($dealId, $this->authorizedDealIds())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $notes = \App\Models\DealComment::with('attachments')
            ->where('deal_id', $dealId)
            ->where('tenant_id', $partner->tenant_id)
            ->where('visibility', 'shared')  // partners only see shared notes
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'body'             => $c->body,
                'author_name'      => $c->author_name ?? ($c->author_role === 'partner' ? 'Partner' : 'Team'),
                'author_role'      => $c->author_role,
                'author_role_label'=> match($c->author_role) {
                    'tenant_admin' => 'Admin', 'referrer' => 'Referrer',
                    'partner'      => 'Partner', default => ucfirst($c->author_role),
                },
                'created_ago'      => $c->created_at?->diffForHumans() ?? 'just now',
                'attachments'      => $c->attachments->map(fn($a) => [
                    'id'                => $a->id,
                    'original_filename' => $a->original_filename,
                    'download_url'      => route('partner.deals.notes.attachments.download', ['dealId' => $dealId, 'commentId' => $c->id, 'attachmentId' => $a->id]),
                ])->values(),
            ]);

        return response()->json(['notes' => $notes]);
    }

    // ── Deal Notes (create) ──────────────────────────────────────────────────

    public function addNote(Request $request, string $dealId)
    {
        $partner = $this->partner();

        if (!in_array($dealId, $this->authorizedDealIds())) {
            return response()->json(['error' => 'You do not have access to this deal.'], 403);
        }

        $lead = Lead::where('id', $dealId)->where('tenant_id', $partner->tenant_id)->firstOrFail();

        $data = $request->validate([
            'body'    => 'nullable|string|max:10000',
            'files'   => 'nullable|array|max:5',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $hasBody  = !empty(trim($data['body'] ?? ''));
        $hasFiles = !empty($request->file('files'));
        if (!$hasBody && !$hasFiles) {
            return response()->json(['error' => 'Please add a note or attach a file.'], 422);
        }

        $note = \App\Models\DealComment::create([
            'tenant_id'      => $partner->tenant_id,
            'deal_id'        => $dealId,
            'author_user_id' => $partner->id,
            'author_role'    => 'partner',
            'body'           => $hasBody ? strip_tags($data['body']) : '',
            'visibility'     => 'shared',
        ]);

        $attachments = [];
        if ($hasFiles) {
            $attachments = \App\Http\Controllers\DealNoteAttachmentController::storeFiles(
                $request->file('files'), $partner->tenant_id, $dealId, $note->id, (string) $partner->id, 'partner'
            );
        }

        // Notify admins
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $partner->tenant_id,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Partner added a note on a deal',
                body:         ($partner->full_name ?: $partner->email) . ' added a note on "' . $lead->name . '"' . ($hasBody ? ': "' . \Illuminate\Support\Str::limit($data['body'], 60) . '"' : ' (with attachment)'),
                actionUrl:    url("/tenant/{$partner->tenant_id}/deals/{$dealId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: 'partner_note:' . $note->id,
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'note'    => [
                'id'          => $note->id,
                'body'        => $note->body,
                'author'      => $partner->full_name ?: $partner->email,
                'author_role' => 'partner',
                'created_ago' => 'just now',
                'attachments' => count($attachments),
            ],
        ]);
    }

    // ── Request Forms ────────────────────────────────────────────────────────

    public function forms()
    {
        $partner = $this->partner();

        $forms = \App\Models\RequestForm::where('tenant_id', $partner->tenant_id)
            ->where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'public_token', 'created_at']);

        return view('partner.forms.index', compact('partner', 'forms'));
    }

    public function formShow(string $token)
    {
        $partner = $this->partner();

        $form = \App\Models\RequestForm::where('tenant_id', $partner->tenant_id)
            ->where('public_token', $token)
            ->where('status', 'published')
            ->with(['fields', 'recipientOptions'])
            ->firstOrFail();

        return view('partner.forms.show', compact('partner', 'form'));
    }

    public function formSubmit(Request $request, string $token)
    {
        $partner = $this->partner();

        $form = \App\Models\RequestForm::where('tenant_id', $partner->tenant_id)
            ->where('public_token', $token)
            ->where('status', 'published')
            ->with('fields')
            ->firstOrFail();

        // Build validation rules from form fields
        $rules = ['notes' => 'nullable|string|max:5000'];
        foreach ($form->fields as $field) {
            $key   = 'fields.' . $field->id;
            $rule  = $field->is_required ? 'required' : 'nullable';
            $rule .= match($field->field_type ?? 'text') {
                'email'  => '|email|max:255',
                'number' => '|numeric',
                'url'    => '|url|max:500',
                default  => '|string|max:2000',
            };
            $rules[$key] = $rule;
        }

        $data = $request->validate($rules);

        $payload = [];
        foreach ($form->fields as $field) {
            $payload[$field->label ?? $field->id] = $data['fields'][$field->id] ?? null;
        }

        \App\Models\RequestFormSubmission::create([
            'tenant_id'        => $partner->tenant_id,
            'request_form_id'  => $form->id,
            'submitter_name'   => $partner->full_name ?: $partner->email,
            'submitter_email'  => $partner->email,
            'request_for'      => 'partner',
            'notes'            => $data['notes'] ?? null,
            'payload'          => $payload,
            'status'           => 'pending',
            'submitted_at'     => now(),
        ]);

        // Notify tenant admins
        try {
            app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $partner->tenant_id,
                category:     'tenant_workspace',
                priority:     'normal',
                title:        'New form submission from Partner',
                body:         ($partner->full_name ?: $partner->email) . ' submitted "' . $form->title . '".',
                actionUrl:    url("/tenant/{$partner->tenant_id}/request-forms"),
                actionLabel:  'View Submissions',
                dedupeSuffix: 'partner_form:' . $form->id . ':' . $partner->id . ':' . now()->format('Ymd'),
            );
        } catch (\Throwable) {}

        return redirect()->route('partner.forms')
            ->with('success', 'Your submission has been sent! The team will get back to you shortly.');
    }

    public function messages(Request $request)
    {
        $partner = $this->partner();

        $threads = PartnerThread::where('partner_id', $partner->id)
            ->where('tenant_id', $partner->tenant_id)
            ->with(['deal', 'reseller'])
            ->orderByDesc('last_message_at')
            ->get();

        // If deal_id param is provided and no thread exists yet, pass pending deal context
        // so the messages view can show a compose area for the first message.
        $pendingDeal = null;
        $dealId = $request->query('deal_id');
        if ($dealId && in_array($dealId, $this->authorizedDealIds())) {
            $hasThread = $threads->contains('deal_id', $dealId);
            if (!$hasThread) {
                $lead = Lead::find($dealId);
                if ($lead) {
                    $pendingDeal = ['id' => $lead->id, 'name' => $lead->name, 'reseller_name' => $lead->reseller_name];
                }
            }
        }

        return view('partner.messages', compact('partner', 'threads', 'pendingDeal'));
    }

    public function threadMessages(string $threadId)
    {
        $partner = $this->partner();

        $thread = PartnerThread::findOrFail($threadId);

        // Two-factor authorization: partner owns the thread AND still has deal access.
        // Prevents IDOR where a removed partner could read thread messages.
        if ((string) $thread->partner_id !== (string) $partner->id) {
            abort(403, 'You do not have access to this thread.');
        }
        if ($thread->deal_id && !in_array($thread->deal_id, $this->authorizedDealIds())) {
            abort(403, 'You do not have access to this thread.');
        }
        // Tenant isolation — thread must belong to the same tenant as the partner
        if ((string) $thread->tenant_id !== (string) $partner->tenant_id) {
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

        // Security: verify the partner has access to this deal.
        // Checks both deal_partners (formal) and deal_partner_splits (referrer-invited partners).
        if (!in_array($data['deal_id'], $this->authorizedDealIds())) {
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

        $tenantId    = $partner->tenant_id;
        $partnerName = $partner->full_name ?: $partner->email;
        $msgSnippet  = '"' . \Illuminate\Support\Str::limit($data['body'], 80) . '"';

        // Notify the Referrer on this deal (if known)
        try {
            if ($resellerId) {
                app(NotificationDispatchService::class)->dispatch(
                    category:         'deal_pipeline',
                    priority:         'normal',
                    title:            'New message from your Partner',
                    body:             $partnerName . ' sent a message on "' . $lead->name . '": ' . $msgSnippet,
                    notifiableType:   'reseller',
                    notifiableId:     (string) $resellerId,
                    tenantId:         $tenantId,
                    actionUrl:        url("/reseller/{$tenantId}/messages"),
                    actionLabel:      'View Message',
                    deduplicationKey: 'partner_msg:' . $thread->id . ':' . now()->format('YmdH'),
                    metadata:         ['sender_name' => $partnerName, 'deal_name' => $lead->name, 'thread_id' => $thread->id],
                );
            }
        } catch (\Throwable) {}

        // Also notify tenant admins (so they can monitor partner communications)
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'low',
                title:        'Partner sent a message',
                body:         $partnerName . ' sent a message on deal "' . $lead->name . '": ' . $msgSnippet,
                actionUrl:    url("/tenant/{$tenantId}/deals/{$data['deal_id']}"),
                actionLabel:  'View Deal',
                dedupeSuffix: 'partner_msg_admin:' . $thread->id . ':' . now()->format('YmdH'),
                metadata:     ['sender_name' => $partnerName, 'deal_name' => $lead->name],
            );
        } catch (\Throwable) {}

        return response()->json([
            'thread_id' => $thread->id,
            'message'   => [
                'id'          => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body'        => $message->body,
                'created_ago' => 'just now',
            ],
        ]);
    }

    // ── My Commissions ──────────────────────────────────────────────────────

    public function commissions()
    {
        $partner = $this->partner();
        $dealIds = $this->authorizedDealIds();

        // Load deal_partner_splits for this partner across all deals
        $splits = DB::table('deal_partner_splits')
            ->whereIn('deal_id', $dealIds)
            ->where('tenant_id', $partner->tenant_id)
            ->where('partner_email', $partner->email)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->get();

        // Load the corresponding deals
        $deals = Lead::whereIn('id', $splits->pluck('deal_id')->unique()->values()->toArray())
            ->get(['id', 'name', 'deal_value', 'added_amount', 'stage', 'status', 'commission_status', 'reseller_name'])
            ->keyBy('id');

        $commissions = $splits->map(function ($s) use ($deals) {
            $deal = $deals->get($s->deal_id);
            $pool = round((float) ($deal?->added_amount ?? 0) * 0.70, 2);
            $myAmount = $s->split_share_type === 'percentage'
                ? round($pool * (float) $s->split_share_value / 100, 2)
                : (float) $s->split_share_value;
            return [
                'deal_id'         => $s->deal_id,
                'deal_name'       => $deal?->name ?? '—',
                'deal_value'      => (float) ($deal?->deal_value ?? 0),
                'deal_stage'      => $deal?->stage ?? 'unknown',
                'deal_status'     => $deal?->status ?? 'active',
                'commission_status' => $deal?->commission_status ?? 'pending',
                'split_type'      => $s->split_share_type,
                'split_value'     => (float) $s->split_share_value,
                'my_amount'       => $myAmount,
                'status'          => $s->status ?? 'provisional',
            ];
        })->values();

        $totalProvisional = $commissions->where('commission_status', 'pending')->sum('my_amount');
        $totalLocked      = $commissions->where('commission_status', 'locked')->sum('my_amount');
        $totalPaid        = $commissions->where('commission_status', 'paid')->sum('my_amount');
        $totalAll         = $commissions->sum('my_amount');

        return view('partner.commissions', compact(
            'partner', 'commissions', 'totalProvisional', 'totalLocked', 'totalPaid', 'totalAll'
        ));
    }
}
