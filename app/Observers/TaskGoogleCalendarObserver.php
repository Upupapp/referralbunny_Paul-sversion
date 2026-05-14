<?php

namespace App\Observers;

use App\Jobs\DeleteGoogleCalendarEvent;
use App\Jobs\SyncEntityToGoogleCalendar;
use App\Models\Task;

class TaskGoogleCalendarObserver
{
    public function created(Task $task): void
    {
        if ($task->due_at && $task->assigned_to_type === 'tenant_user') {
            SyncEntityToGoogleCalendar::dispatch('task', $task->id);
        }
    }

    public function updated(Task $task): void
    {
        // Stop sync if task is now terminal
        if (in_array($task->status, ['completed', 'cancelled', 'archived'])) {
            DeleteGoogleCalendarEvent::dispatch('task', $task->id);
            return;
        }

        // Sync if due_at or title changed and task has a due date
        if ($task->due_at && $task->assigned_to_type === 'tenant_user') {
            if ($task->wasChanged('due_at') || $task->wasChanged('title') || $task->wasChanged('priority')) {
                SyncEntityToGoogleCalendar::dispatch('task', $task->id);
            }
        }

        // Remove calendar event if due_at was cleared
        if (!$task->due_at && $task->wasChanged('due_at')) {
            DeleteGoogleCalendarEvent::dispatch('task', $task->id);
        }

        // Sync if task is re-assigned (new assignee might have calendar connected)
        if ($task->due_at && $task->wasChanged('assigned_to_id')) {
            SyncEntityToGoogleCalendar::dispatch('task', $task->id);
        }
    }

    public function deleted(Task $task): void
    {
        DeleteGoogleCalendarEvent::dispatch('task', $task->id);
    }
}
