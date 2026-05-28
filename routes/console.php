<?php

use App\Console\Commands\CreateLguIdsDealNoteTasks;
use App\Console\Commands\SendLguIdsReferrerNoteReminders;
use App\Jobs\Emails\SendLguIdsPendingTasksDigestJob;
use App\Jobs\Emails\SendLguIdsTaskDueReminderJob;
use App\Jobs\Emails\SendResellerDailySummariesJob;
use App\Jobs\Emails\SendSuperAdminDailySummaryJob;
use App\Jobs\Emails\SendTenantAdminDailyBriefingJob;
use Illuminate\Support\Facades\Schedule;

// ── Email jobs (Asia/Manila timezone) ─────────────────────────
Schedule::job(new SendSuperAdminDailySummaryJob)->dailyAt('08:00')->timezone('Asia/Manila');
Schedule::job(new SendTenantAdminDailyBriefingJob)->dailyAt('08:00')->timezone('Asia/Manila');
Schedule::job(new SendResellerDailySummariesJob)->dailyAt('08:00')->timezone('Asia/Manila');

// ── LGU IDS weekly referrer note reminder (every Wednesday 10:00 AM Perth) ───
// Australia/Perth = UTC+8 year-round (no DST) — same wall-clock offset as Asia/Manila.
// Do NOT change to a DST-observing timezone; Perth is intentional for a fixed UTC+8 label.
Schedule::command(SendLguIdsReferrerNoteReminders::class)
    ->weeklyOn(3, '10:00')
    ->timezone('Australia/Perth')
    ->withoutOverlapping();

// ── LGU IDS deal note task sync (nightly safety net) ─────────
// Catches any deals that missed the event-driven hooks (e.g. bulk DB updates, imports).
// --no-email skips duplicate emails since event-time hooks already sent them.
Schedule::command(CreateLguIdsDealNoteTasks::class, ['--no-email'])
    ->dailyAt('02:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// ── LGU IDS task reminders (starts Mon May 18, 2026) ─────────
// Daily digest: all pending tasks emailed to lgu-ids admin/manager/owner
Schedule::job(new SendLguIdsPendingTasksDigestJob)
    ->dailyAt('08:00')->timezone('Asia/Manila')
    ->skip(fn() => now()->setTimezone('Asia/Manila')->lt(\Carbon\Carbon::parse('2026-05-18', 'Asia/Manila')));
// Per-task due-today: one separate email per task due on the day
Schedule::job(new SendLguIdsTaskDueReminderJob)
    ->dailyAt('07:30')->timezone('Asia/Manila')
    ->skip(fn() => now()->setTimezone('Asia/Manila')->lt(\Carbon\Carbon::parse('2026-05-18', 'Asia/Manila')));

// ── Daily jobs ────────────────────────────────────────────────
Schedule::command('metrics:calculate')->dailyAt('01:00');
Schedule::command('metrics:check-inactivity')->dailyAt('08:00');
Schedule::command('notifications:escalate')->dailyAt('09:00');

// ── Weekly jobs ───────────────────────────────────────────────
Schedule::command('reports:weekly')->weeklyOn(1, '08:00'); // Every Monday

// ── Monthly jobs ──────────────────────────────────────────────
Schedule::command('reports:monthly')->monthlyOn(1, '08:00');  // 1st of each month
Schedule::command('usage:reset-monthly')->monthlyOn(1, '00:00'); // Reset usage counters

// ── Search index ──────────────────────────────────────────────
Schedule::command('search:reindex')->dailyAt('03:00');

// ── Email digests (batch / anti-spam) ────────────────────────
Schedule::command('email:send-digests')->everyThirtyMinutes();

// ── Message reminders (R Bunny AI Dialog) ────────────────────
Schedule::command('messages:check-reminders')->hourly()->timezone('Asia/Manila');

// ── Invitation reminders (invitee + inviter) ──────────────────
Schedule::command('invitations:send-reminders')->hourly();

// ── Lead expiry & pipeline ────────────────────────────────────
Schedule::command('leads:expire')->dailyAt('00:05')->timezone('Asia/Manila');
Schedule::command('leads:purge-archived')->dailyAt('01:30')->timezone('Asia/Manila');
Schedule::command('leads:check-pipeline-limits')->dailyAt('07:30')->timezone('Asia/Manila');
Schedule::command('leads:notify-expiring')->dailyAt('07:00')->timezone('Asia/Manila');

// ── Promo jobs ────────────────────────────────────────────────
Schedule::command('promos:expire')->dailyAt('00:15');

// ── Billing jobs ──────────────────────────────────────────────
Schedule::command('billing:process-trials')->dailyAt('07:00');
Schedule::command('billing:retry-payments')->dailyAt('10:00');
Schedule::command('billing:update-rates')->dailyAt('00:30');

// ── Export cleanup (expire old export files daily) ────────────
Schedule::command('exports:cleanup-expired')->dailyAt('02:00');

// ── Subscription expiry check ─────────────────────────────────
Schedule::command('subscriptions:check-expiry')->dailyAt('08:30');
