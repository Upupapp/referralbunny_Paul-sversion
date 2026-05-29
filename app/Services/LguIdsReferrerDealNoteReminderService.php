<?php

namespace App\Services;

use App\Mail\LguIdsReferrerDealNoteReminderMail;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\EmailLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LGU IDS only — sends one grouped in-app notification + one queued email per
 * Referrer per week for every active deal they own that has zero notes ever.
 *
 * "Zero notes" = no deal_comments (shared, not deleted, no parent) AND no lead_notes.
 * "Active deal" = status IN ('active','expiring'), deleted_at IS NULL, stage != 'paid'.
 */
class LguIdsReferrerDealNoteReminderService
{
    public function __construct(
        private NotificationDispatchService $notifications,
    ) {}

    // ── Public API ─────────────────────────────────────────────────

    public function runWeeklyReminder(): array
    {
        $tenant = $this->getLguIdsTenant();
        if (! $tenant) {
            Log::warning('[LguIdsNoteReminder] lgu-ids tenant not found — aborting');
            return ['skipped' => 0, 'sent' => 0, 'failed' => 0];
        }

        $weekKey   = $this->weekKey();
        $referrers = $this->getEligibleReferrers($tenant->id);

        $sent   = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($referrers as $referrer) {
            try {
                if ($this->alreadyNotifiedThisWeek($tenant->id, $referrer->id, $weekKey)) {
                    $skipped++;
                    continue;
                }

                $deals = $this->getActiveDealsWithoutNotesForReferrer($tenant->id, $referrer->name);
                if ($deals->isEmpty()) {
                    $skipped++;
                    continue;
                }

                $dealIds   = $deals->pluck('id')->all();
                $dealCount = $deals->count();

                $notificationId = $this->createInAppNotification($tenant, $referrer, $deals, $weekKey);
                $emailLogId     = $this->queueEmailNotification($tenant, $referrer, $deals, $weekKey);
                $this->logReminderRun($tenant->id, $referrer->id, $weekKey, $dealIds, $dealCount, $notificationId, $emailLogId, 'sent');

                $sent++;
            } catch (\Throwable $e) {
                Log::error('[LguIdsNoteReminder] Failed for referrer', [
                    'referrer_id' => $referrer->id,
                    'error'       => $e->getMessage(),
                ]);
                $this->logReminderRun($tenant->id, $referrer->id, $weekKey, [], 0, null, null, 'failed');
                $failed++;
            }
        }

        Log::info('[LguIdsNoteReminder] Weekly run complete', compact('sent', 'skipped', 'failed', 'weekKey'));
        return compact('sent', 'skipped', 'failed');
    }

    // ── Tenant resolution ──────────────────────────────────────────

    public function getLguIdsTenant(): ?Tenant
    {
        return Tenant::where('id', 'lgu-ids')->first();
    }

    // ── Referrer eligibility ───────────────────────────────────────

    /**
     * Returns active Referrers (status: active | nda_signed) who have at least
     * one active deal with no notes. Scoped to lgu-ids tenant.
     */
    public function getEligibleReferrers(string $tenantId)
    {
        return Reseller::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('leads')
                    ->where('leads.tenant_id', $tenantId)
                    ->whereNull('leads.deleted_at')
                    ->whereIn('leads.status', ['active', 'expiring'])
                    ->where('leads.stage', '!=', 'paid')
                    ->where(function ($lq) {
                        $lq->whereRaw('LOWER(leads.reseller_name) = LOWER(resellers.name)')
                           ->orWhereExists(fn($cs) => $cs
                               ->select(DB::raw(1))
                               ->from('commission_splits')
                               ->whereColumn('commission_splits.lead_id', 'leads.id')
                               ->whereRaw('LOWER(commission_splits.reseller_name) = LOWER(resellers.name)')
                           );
                    })
                    ->whereNotExists(fn($nc) => $nc
                        ->select(DB::raw(1))
                        ->from('deal_comments')
                        ->whereColumn('deal_comments.deal_id', 'leads.id')
                        ->where('deal_comments.visibility', 'shared')
                        ->whereNull('deal_comments.deleted_at')
                        ->whereNull('deal_comments.parent_comment_id')
                    )
                    ->whereNotExists(fn($nl) => $nl
                        ->select(DB::raw(1))
                        ->from('lead_notes')
                        ->whereColumn('lead_notes.lead_id', 'leads.id')
                    );
            })
            ->select('id', 'name', 'email', 'tenant_id')
            ->get();
    }

    /**
     * Returns active deals (with no notes) assigned to this referrer.
     * Checks both primary and co-referrer (commission_splits) assignment.
     */
    public function getActiveDealsWithoutNotesForReferrer(string $tenantId, string $referrerName)
    {
        $lower = strtolower($referrerName);

        return DB::table('leads')
            ->where('leads.tenant_id', $tenantId)
            ->whereNull('leads.deleted_at')
            ->whereIn('leads.status', ['active', 'expiring'])
            ->where('leads.stage', '!=', 'paid')
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(leads.reseller_name) = ?', [$lower])
                  ->orWhereExists(fn($cs) => $cs
                      ->select(DB::raw(1))
                      ->from('commission_splits')
                      ->whereColumn('commission_splits.lead_id', 'leads.id')
                      ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                  );
            })
            ->whereNotExists(fn($dc) => $dc
                ->select(DB::raw(1))
                ->from('deal_comments')
                ->whereColumn('deal_comments.deal_id', 'leads.id')
                ->where('deal_comments.visibility', 'shared')
                ->whereNull('deal_comments.deleted_at')
                ->whereNull('deal_comments.parent_comment_id')
            )
            ->whereNotExists(fn($ln) => $ln
                ->select(DB::raw(1))
                ->from('lead_notes')
                ->whereColumn('lead_notes.lead_id', 'leads.id')
            )
            ->select('leads.id', 'leads.name', 'leads.stage', 'leads.status', 'leads.days_left', 'leads.deal_value')
            ->orderByRaw("CASE WHEN leads.status = 'expiring' THEN 0 ELSE 1 END")
            ->orderBy('leads.days_left')
            ->limit(20)
            ->get();
    }

    // ── Deduplication ──────────────────────────────────────────────

    public function alreadyNotifiedThisWeek(string $tenantId, string $referrerId, string $weekKey): bool
    {
        return DB::table('lgu_ids_referrer_note_reminder_logs')
            ->where('tenant_id', $tenantId)
            ->where('referrer_id', $referrerId)
            ->where('week_key', $weekKey)
            ->where('status', 'sent')
            ->exists();
    }

    // ── In-app notification ────────────────────────────────────────

    public function createInAppNotification(Tenant $tenant, Reseller $referrer, $deals, string $weekKey): ?string
    {
        $payload = $this->buildNotificationPayload($tenant, $referrer, $deals, $weekKey);

        try {
            $dedupKey     = "deal_pipeline:{$referrer->id}:lgu_ids_note_reminder:{$weekKey}";
            $notification = $this->notifications->dispatch(
                category:         'deal_pipeline',
                priority:         'medium',
                title:            $payload['title'],
                body:             $payload['message'],
                notifiableType:   'reseller',
                notifiableId:     $referrer->id,
                tenantId:         $tenant->id,
                actionUrl:        $payload['action_url'],
                actionLabel:      'View Deals',
                deduplicationKey: $dedupKey,
                metadata:         $payload['metadata'],
            );

            return $notification?->id;
        } catch (\Throwable $e) {
            Log::warning('[LguIdsNoteReminder] In-app notification failed', [
                'referrer_id' => $referrer->id,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Email notification ─────────────────────────────────────────

    public function queueEmailNotification(Tenant $tenant, Reseller $referrer, $deals, string $weekKey): ?string
    {
        $emailKey = "lgu_ids_referrer_note_reminder_email:{$tenant->id}:{$referrer->id}:{$weekKey}";

        $dealsArray = $deals->map(fn($d) => [
            'id'         => $d->id,
            'name'       => $d->name,
            'stage'      => $d->stage,
            'status'     => $d->status,
            'days_left'  => $d->days_left,
            'deal_value' => $d->deal_value,
            'url'        => url("reseller/{$tenant->id}/deals/{$d->id}") . '#rb-notes',
        ])->all();

        $count = $deals->count();

        EmailLogger::send(
            mailable:       new LguIdsReferrerDealNoteReminderMail(
                referrerName: $referrer->name,
                deals:        $dealsArray,
                tenantName:   $tenant->name,
                dealsUrl:     url("reseller/{$tenant->id}/deals") . '?filter=no_notes',
                weekLabel:    Carbon::now()->timezone('Asia/Manila')->format('F j, Y'),
            ),
            recipientEmail: $referrer->email,
            recipientType:  'reseller',
            recipientId:    $referrer->id,
            emailKey:       $emailKey,
            subject:        $count === 1
                ? 'Weekly reminder: 1 of your deals has no notes yet'
                : "Weekly reminder: {$count} of your deals have no notes yet",
            tenantId:       $tenant->id,
            metadata:       ['type' => 'lgu_ids_referrer_note_reminder', 'week_key' => $weekKey],
        );

        return $emailKey;
    }

    // ── Payload builders ───────────────────────────────────────────

    public function buildNotificationPayload(Tenant $tenant, Reseller $referrer, $deals, string $weekKey): array
    {
        $count     = $deals->count();
        $dealNames = $deals->take(3)->pluck('name')->join(', ');
        $suffix    = $count > 3 ? ' and ' . ($count - 3) . ' more' : '';

        return [
            'title'      => $count === 1
                ? '📝 Your deal has no notes yet'
                : "📝 {$count} of your deals have no notes yet",
            'message'    => "Add a note to keep your deal" . ($count > 1 ? 's' : '') . " moving: {$dealNames}{$suffix}. Notes help your team track progress and increase your chances of closing.",
            'action_url' => url("reseller/{$tenant->id}/deals") . '?filter=no_notes',
            'metadata'   => [
                'deal_count' => $count,
                'deal_ids'   => $deals->pluck('id')->all(),
                'week_key'   => $weekKey,
            ],
        ];
    }

    // ── Logging ────────────────────────────────────────────────────

    public function logReminderRun(
        string  $tenantId,
        string  $referrerId,
        string  $weekKey,
        array   $dealIds,
        int     $dealCount,
        ?string $notificationId,
        ?string $emailLogId,
        string  $status,
    ): void {
        try {
            DB::table('lgu_ids_referrer_note_reminder_logs')->updateOrInsert(
                ['tenant_id' => $tenantId, 'referrer_id' => $referrerId, 'week_key' => $weekKey],
                [
                    'deal_ids'        => json_encode($dealIds),
                    'deal_count'      => $dealCount,
                    'notification_id' => $notificationId,
                    'email_log_id'    => $emailLogId,
                    'status'          => $status,
                    'sent_at'         => $status === 'sent' ? now() : null,
                    'metadata'        => json_encode(['run_at' => now()->toIso8601String()]),
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[LguIdsNoteReminder] Failed to write reminder log', ['error' => $e->getMessage()]);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function weekKey(): string
    {
        return Carbon::now()->timezone('Asia/Manila')->format('oW'); // ISO year + ISO week e.g. "202622"
    }
}
