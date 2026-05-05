<?php

namespace App\Services\Messaging;

use App\Models\MessageReminderState;
use App\Models\MessageThread;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageReminderService
{
    /**
     * Get active reminders for a user, ranked by priority.
     */
    public function getActiveForUser(string $type, string $id, ?string $tenantId): Collection
    {
        return MessageReminderState::forUser($type, $id)
            ->active()
            ->orderByRaw(MessageReminderState::prioritySortRaw())
            ->orderBy('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Snooze a reminder by dedup key.
     */
    public function snooze(string $dedupKey, string $type, string $id, int $minutes): void
    {
        MessageReminderState::where('deduplication_key', $dedupKey)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->update([
                'status'        => 'snoozed',
                'snoozed_until' => now()->addMinutes($minutes),
                'next_remind_at'=> now()->addMinutes($minutes),
                'updated_at'    => now(),
            ]);
    }

    /**
     * Mark a reminder resolved by dedup key.
     */
    public function resolve(string $dedupKey, string $type, string $id): void
    {
        MessageReminderState::where('deduplication_key', $dedupKey)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Dismiss a reminder by dedup key.
     */
    public function dismiss(string $dedupKey, string $type, string $id): void
    {
        MessageReminderState::where('deduplication_key', $dedupKey)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->update(['status' => 'dismissed', 'dismissed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Resolve all reminders for a thread when user sends a message.
     */
    public function resolveForThread(string $threadId, string $type, string $id): void
    {
        MessageReminderState::where('thread_id', $threadId)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereIn('status', ['active', 'snoozed'])
            ->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Create or increment a reminder, respecting dedup and snooze.
     */
    public function createReminder(
        string  $type,
        string  $id,
        ?string $tenantId,
        string  $reminderType,
        string  $priority,
        string  $title,
        string  $body,
        string  $dedupKey,
        ?string $threadId  = null,
        ?string $dealId    = null,
        ?string $actionUrl = null,
        array   $metadata  = [],
    ): void {
        $existing = MessageReminderState::where('deduplication_key', $dedupKey)->first();

        if ($existing) {
            // Skip if already resolved/dismissed
            if (in_array($existing->status, ['resolved', 'dismissed'])) return;
            // Skip if snoozed and not yet due
            if ($existing->status === 'snoozed' && $existing->snoozed_until > now()) return;
            // Reactivate a snoozed-and-due reminder
            $existing->increment('reminder_count');
            if ($existing->status === 'snoozed') {
                $existing->update(['status' => 'active', 'next_remind_at' => null]);
            }
            return;
        }

        MessageReminderState::create([
            'id'                => (string) Str::uuid(),
            'notifiable_type'   => $type,
            'notifiable_id'     => $id,
            'tenant_id'         => $tenantId,
            'reminder_type'     => $reminderType,
            'thread_id'         => $threadId,
            'deal_id'           => $dealId,
            'deduplication_key' => $dedupKey,
            'status'            => 'active',
            'priority'          => $priority,
            'title'             => $title,
            'body'              => $body,
            'action_url'        => $actionUrl,
            'metadata'          => $metadata,
            'reminder_count'    => 1,
        ]);
    }

    /**
     * Scan all active tenants and create reminders where needed.
     * Called by the scheduled Artisan command.
     */
    public function checkAllTenants(): void
    {
        $tenantIds = DB::table('message_threads')
            ->where('last_message_at', '>', now()->subDays(14))
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $this->checkTenantAdminReminders($tenantId);
            $this->checkResellerReminders($tenantId);
        }
    }

    /**
     * Create reminders for tenant admins who have unread reseller messages.
     */
    private function checkTenantAdminReminders(string $tenantId): void
    {
        $threads = DB::table('message_threads')
            ->where('tenant_id', $tenantId)
            ->where('last_message_at', '<', now()->subHours(2))
            ->where('admin_unread', '>', 0)
            ->select('id', 'tenant_id', 'reseller_id', 'last_message_at', 'deal_id')
            ->get();

        if ($threads->isEmpty()) return;

        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin'])
            ->select('u.id')
            ->get();

        foreach ($threads as $thread) {
            $reseller    = DB::table('resellers')->where('id', $thread->reseller_id)->select('name', 'is_anonymous')->first();
            $senderName  = ($reseller && !$reseller->is_anonymous) ? $reseller->name : 'A reseller';
            $hoursOld    = now()->diffInHours($thread->last_message_at);
            $priority    = $hoursOld >= 24 ? 'high' : 'normal';

            foreach ($admins as $admin) {
                $key = "thread_unread:admin:{$thread->id}:{$admin->id}";

                $this->createReminder(
                    type:         'tenant_admin',
                    id:           (string) $admin->id,
                    tenantId:     $tenantId,
                    reminderType: 'unread_message',
                    priority:     $priority,
                    title:        'R Bunny noticed an unread message',
                    body:         "{$senderName} is waiting for your reply.",
                    dedupKey:     $key,
                    threadId:     $thread->id,
                    dealId:       $thread->deal_id ?? null,
                    actionUrl:    url("/tenant/{$tenantId}/messages"),
                    metadata:     ['sender_name' => $senderName, 'hours_old' => $hoursOld],
                );
            }
        }
    }

    /**
     * Create reminders for resellers who have unread admin messages.
     */
    private function checkResellerReminders(string $tenantId): void
    {
        $threads = DB::table('message_threads')
            ->where('tenant_id', $tenantId)
            ->where('last_message_at', '<', now()->subHours(2))
            ->where('reseller_unread', '>', 0)
            ->select('id', 'tenant_id', 'reseller_id', 'last_message_at', 'deal_id')
            ->get();

        foreach ($threads as $thread) {
            $hoursOld    = now()->diffInHours($thread->last_message_at);
            $priority    = $hoursOld >= 24 ? 'high' : 'normal';
            $key         = "thread_unread:reseller:{$thread->id}:{$thread->reseller_id}";

            $this->createReminder(
                type:         'reseller',
                id:           (string) $thread->reseller_id,
                tenantId:     $tenantId,
                reminderType: 'unread_message',
                priority:     $priority,
                title:        'R Bunny noticed an unread message',
                body:         'Your workspace admin sent you a message.',
                dedupKey:     $key,
                threadId:     $thread->id,
                dealId:       $thread->deal_id ?? null,
                actionUrl:    url("/reseller/{$tenantId}/dashboard"),
                metadata:     ['hours_old' => $hoursOld],
            );
        }
    }

    /**
     * Rule-based suggested replies. Replaced by AI later if needed.
     */
    public function getSuggestedReplies(string $reminderType): array
    {
        return match($reminderType) {
            'unread_message', 'unreplied_message' => [
                "Thank you for your message. I'm checking this now and will update you shortly.",
                "Noted. I'll get back to you as soon as possible.",
            ],
            'admin_request', 'document_request' => [
                "Thank you. I'll upload the required document and update this thread once completed.",
                "Understood. I'll prepare the requested information and respond shortly.",
            ],
            'deal_deadline' => [
                "Noted. I'll update the deal status and provide the latest movement.",
                "Thank you for the reminder. I'm working on this now.",
            ],
            default => [
                "Thank you for the update. I'll review and respond shortly.",
            ],
        };
    }
}
