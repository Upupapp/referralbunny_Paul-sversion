<?php

namespace App\Observers;

use App\Jobs\DeleteGoogleCalendarEvent;
use App\Jobs\SyncEntityToGoogleCalendar;
use App\Models\Task;

class TaskGoogleCalendarObserver
{
    private const TERMINAL = ['completed', 'cancelled', 'archived'];

    public function created(Task $task): void
    {
        if ($task->due_at && $task->assigned_to_type === 'tenant_user') {
            SyncEntityToGoogleCalendar::dispatch('task', $task->id);
        }
    }

    public function updated(Task $task): void
    {
        // Terminal status always deletes — nothing else should fire after this
        if (in_array($task->status, self::TERMINAL)) {
            DeleteGoogleCalendarEvent::dispatch('task', $task->id);
            return;
        }

        // due_at cleared — remove event
        if (!$task->due_at && $task->wasChanged('due_at')) {
            DeleteGoogleCalendarEvent::dispatch('task', $task->id);
            return;
        }

        // Sync if a calendar-relevant field changed and task still has a due date
        if (
            $task->due_at &&
            $task->assigned_to_type === 'tenant_user' &&
            $task->wasChanged(['due_at', 'title', 'priority', 'assigned_to_id'])
        ) {
            SyncEntityToGoogleCalendar::dispatch('task', $task->id);
        }
    }

    public function deleted(Task $task): void
    {
        DeleteGoogleCalendarEvent::dispatch('task', $task->id);
    }
}
