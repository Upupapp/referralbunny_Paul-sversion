<?php

namespace App\Observers;

use App\Jobs\DeleteGoogleCalendarEvent;
use App\Jobs\SyncEntityToGoogleCalendar;

class LeadGoogleCalendarObserver
{
    public function updated(object $lead): void
    {
        if (!isset($lead->id) || !isset($lead->tenant_id)) return;

        $terminalStatuses = ['expired', 'archived', 'paid', 'declined'];

        // Remove calendar event when deal closes
        if (in_array($lead->status ?? '', $terminalStatuses)) {
            DeleteGoogleCalendarEvent::dispatch('deal', (string) $lead->id);
            return;
        }

        // Sync when deal becomes expiring or days_left changes
        $daysLeft = (int) ($lead->days_left ?? 0);
        if ($daysLeft > 0 && in_array($lead->status ?? '', ['active', 'expiring'])) {
            SyncEntityToGoogleCalendar::dispatch('deal', (string) $lead->id);
        }
    }

    public function deleted(object $lead): void
    {
        if (isset($lead->id)) {
            DeleteGoogleCalendarEvent::dispatch('deal', (string) $lead->id);
        }
    }
}
