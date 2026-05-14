<?php

namespace App\Observers;

use App\Jobs\DeleteGoogleCalendarEvent;
use App\Jobs\SyncEntityToGoogleCalendar;
use App\Models\Lead;

class LeadGoogleCalendarObserver
{
    private const TERMINAL = ['expired', 'archived', 'paid', 'declined'];
    private const SYNCABLE  = ['active', 'expiring'];

    public function updated(Lead $lead): void
    {
        if (in_array($lead->status, self::TERMINAL)) {
            DeleteGoogleCalendarEvent::dispatch('deal', (string) $lead->id);
            return;
        }

        if (
            in_array($lead->status, self::SYNCABLE) &&
            (int) ($lead->days_left ?? 0) > 0 &&
            ($lead->wasChanged('days_left') || $lead->wasChanged('status'))
        ) {
            SyncEntityToGoogleCalendar::dispatch('deal', (string) $lead->id);
        }
    }

    public function deleted(Lead $lead): void
    {
        DeleteGoogleCalendarEvent::dispatch('deal', (string) $lead->id);
    }
}
