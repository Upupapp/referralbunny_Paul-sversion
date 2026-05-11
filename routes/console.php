<?php

use App\Jobs\Emails\SendResellerDailySummariesJob;
use App\Jobs\Emails\SendSuperAdminDailySummaryJob;
use App\Jobs\Emails\SendTenantAdminDailyBriefingJob;
use Illuminate\Support\Facades\Schedule;

// ── Email jobs (Asia/Manila timezone) ─────────────────────────
Schedule::job(new SendSuperAdminDailySummaryJob)->dailyAt('08:00')->timezone('Asia/Manila');
Schedule::job(new SendTenantAdminDailyBriefingJob)->dailyAt('08:00')->timezone('Asia/Manila');
Schedule::job(new SendResellerDailySummariesJob)->dailyAt('08:00')->timezone('Asia/Manila');

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

// ── Message reminders (R Bunny AI Dialog) ────────────────────
Schedule::command('messages:check-reminders')->hourly()->timezone('Asia/Manila');

// ── Invitation reminders (invitee + inviter) ──────────────────
Schedule::command('invitations:send-reminders')->hourly();

// ── Lead expiry & pipeline ────────────────────────────────────
Schedule::command('leads:expire')->dailyAt('00:05');
Schedule::command('leads:purge-archived')->dailyAt('01:30');
Schedule::command('leads:check-pipeline-limits')->dailyAt('07:30');
Schedule::command('leads:notify-expiring')->dailyAt('07:00');

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
