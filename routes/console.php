<?php

use Illuminate\Support\Facades\Schedule;

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

// ── Lead expiry ───────────────────────────────────────────────
Schedule::command('leads:expire')->dailyAt('00:05');

// ── Promo jobs ────────────────────────────────────────────────
Schedule::command('promos:expire')->dailyAt('00:15');

// ── Billing jobs ──────────────────────────────────────────────
Schedule::command('billing:process-trials')->dailyAt('07:00');
Schedule::command('billing:retry-payments')->dailyAt('10:00');
Schedule::command('billing:update-rates')->dailyAt('00:30');
