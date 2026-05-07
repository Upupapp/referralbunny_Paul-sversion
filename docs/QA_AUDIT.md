# ReferralBunny.ai — QA Audit System

## Overview

The QA Audit system provides a repeatable, automated way to check the full system for bugs, broken UI, broken flows, permission leaks, tenant isolation issues, data integrity problems, and regression failures — after every new feature, deployment, or code change.

---

## Running the QA Audit

### Full audit (all checks)
```bash
php artisan referralbunny:qa-audit --full
```

### Full audit with report files (JSON + Markdown)
```bash
php artisan referralbunny:qa-audit --full --report
```

### Quick audit (core checks only — default)
```bash
php artisan referralbunny:qa-audit
```

### Audit a specific tenant
```bash
php artisan referralbunny:qa-audit --tenant=lgu-ids
php artisan referralbunny:qa-audit --tenant=lgu-ids --full --report
```

### Audit a specific module
```bash
php artisan referralbunny:qa-audit --module=deals
php artisan referralbunny:qa-audit --module=referrers
php artisan referralbunny:qa-audit --module=partners
php artisan referralbunny:qa-audit --module=notifications
```

### Category-specific audits
```bash
php artisan referralbunny:qa-audit --emails
php artisan referralbunny:qa-audit --notifications
php artisan referralbunny:qa-audit --queues
php artisan referralbunny:qa-audit --tenant-isolation
php artisan referralbunny:qa-audit --lgu-ids
php artisan referralbunny:qa-audit --data
php artisan referralbunny:qa-audit --ui
php artisan referralbunny:qa-audit --api
```

### Quiet mode (only show failures and warnings)
```bash
php artisan referralbunny:qa-audit --full --quiet-pass
```

### Safe auto-fix mode
```bash
php artisan referralbunny:qa-audit --fix-safe
```
Clears view cache, config cache, application cache. Never touches data.

---

## What Each Check Does

### Routes & Middleware
- All named routes required for auth/invite flows exist
- All route controller classes exist
- Tenant routes are protected by `tenant.access` middleware
- No duplicate routes

### Data Integrity
- Required tables exist
- No orphan commission splits (lead_id resolves)
- No orphan partner splits (deal_id resolves)
- No orphan deal_partners (partner_user_id resolves)
- No orphan tenant memberships
- No records with NULL tenant_id
- No duplicate reseller emails per tenant
- No duplicate partner splits per deal+email
- Queue job backlog check
- Failed jobs check

### Tenant Isolation
- All tenant-scoped table records have valid tenant_id
- Cross-tenant email overlap flagged (expected but logged)
- Non-super-admin notifications have tenant_id
- Deactivated referrers with active deals flagged

### Email Rendering (Mail::fake)
- Every Mailable class exists
- Every Mailable has a subject
- Every Mailable's view template exists
- No instantiation exceptions
- **NEVER sends real emails**

### Scheduler & Queue Health
- Required commands are registered: `leads:expire`, `leads:notify-expiring`, `invitations:send-reminders`, `metrics:calculate`, `exports:cleanup-expired`
- Commands are scheduled in `routes/console.php`
- No failed jobs in last 24 hours

### LGU IDS Protected Rules
- Tenant 'lgu-ids' exists in database
- Stage limits: introduction=14, presentation=21, contract_sent=30, signed=30
- One-deal-per-organization rule present in LeadController
- Stage-based days_left reset rule present
- Default ₱4,000,000 deal value rule present
- CommissionSplit model is still reseller-based
- LGU IDS protected-rule comments not removed
- LGU IDS import controller template method exists

### UI / View Files
- All core Blade views exist (tenant, reseller, partner, auth)
- Email template views exist
- Reminder: filter selects should use x-effect+@change, not x-model

### Notification Health
- Notifications table exists
- All notifications have notifiable_id
- Expiry digest notifications exist (if expiring deals present)

### Feature Readiness
- CSRF middleware configured
- Laravel Sanctum installed
- PHP version >= 8.2
- Storage path writable
- APP_DEBUG is false in production
- Queue driver configured
- Mail driver configured

---

## Report Files

Reports are saved to `storage/app/qa-reports/` in two formats:

- `qa-audit-{env}-{timestamp}.json` — machine-readable
- `qa-audit-{env}-{timestamp}.md` — human-readable

### Severity Levels
| Level | Description |
|-------|-------------|
| 🔴 Critical | Immediate action required — data leak, broken auth, protected rule changed |
| ❌ Fail | Feature or flow is broken — fix before next deploy |
| ⚠️ Warning | Potential issue — review before deploy |
| ✅ Pass | Check passed |
| ℹ️ Info | Informational — no action required |

---

## QA Seeder (Local/Staging Only)

Creates test data for isolated QA testing:

```bash
php artisan db:seed --class=ReferralBunnyQaSeeder
```

Creates:
- 2 test tenants: `qa-tenant-a`, `qa-tenant-b`
- Tenant admin users for each
- Active and pending referrers
- Active, expiring, and pending-referrer deals
- Active and pending partners
- Partner splits
- Extension requests

**NEVER run on production.**

---

## Feature Readiness QA Checklist

Run after every new feature, screen, route, migration, or deployment.

```
[ ] 1. Route exists and is reachable
[ ] 2. Route is protected by correct auth middleware
[ ] 3. Route enforces tenant context if tenant-facing
[ ] 4. Route enforces role permissions
[ ] 5. Page loads without 500 errors
[ ] 6. Page loads without frontend console errors
[ ] 7. Page is responsive (mobile 375px, tablet 768px, laptop 1366px, desktop 1440px)
[ ] 8. All buttons are clickable and perform the intended action
[ ] 9. All forms validate required fields
[ ] 10. Success state appears after valid submission
[ ] 11. Error state appears after invalid submission
[ ] 12. Loading state appears during async action
[ ] 13. Empty state appears when there is no data
[ ] 14. Pagination works for long lists
[ ] 15. Search works
[ ] 16. Filters work and use x-effect+@change (not x-model) on selects
[ ] 17. Sorting works where applicable
[ ] 18. Metrics are accurate and tenant-scoped
[ ] 19. Info icons exist on metrics and explain the values
[ ] 20. Metric cards link to the correct filtered list
[ ] 21. Emails render correctly in log/sandbox mode
[ ] 22. Notifications are created for the correct recipients only
[ ] 23. Audit logs are created for important actions
[ ] 24. Critical actions created for risky or urgent events
[ ] 25. Cache is invalidated after data changes
[ ] 26. Queue jobs do not fail
[ ] 27. No cross-tenant data leak
[ ] 28. No role permission leak
[ ] 29. No LGU IDS protected logic was changed
[ ] 30. Existing tests still pass (php artisan test)
```

---

## Diagnostic Commands Reference

```bash
# Multi-role referrer diagnosis
php artisan referralbunny:diagnose-multirole-referrers lgu-ids

# Referrer invite deduplication diagnosis
php artisan referralbunny:diagnose-referrer-invite-dedup lgu-ids
php artisan referralbunny:diagnose-referrer-invite-dedup lgu-ids --repair-safe

# Partner splits diagnosis
php artisan referralbunny:diagnose-partner-splits lgu-ids
php artisan referralbunny:diagnose-partner-splits lgu-ids --repair-safe

# Pending deal participants
php artisan referralbunny:diagnose-pending-deal-participants lgu-ids

# Extension requests
php artisan referralbunny:diagnose-extension-requests lgu-ids

# Expiry notification digests
php artisan referralbunny:diagnose-expiry-digests lgu-ids

# Full QA audit
php artisan referralbunny:qa-audit --full --tenant=lgu-ids --report
```

---

## What the QA Audit Does NOT Do

- Does not send real emails (uses Mail::fake())
- Does not create, update, or delete database records (except `--fix-safe` cache clears)
- Does not approve/reject exports
- Does not trigger payments
- Does not change LGU IDS protected rules
- Does not run browser tests (UI checks are file-level only — use Laravel Dusk for browser testing)

---

## Composer Script

```bash
composer qa           # php artisan referralbunny:qa-audit
composer qa-full      # php artisan referralbunny:qa-audit --full --report
composer qa-lgu       # php artisan referralbunny:qa-audit --tenant=lgu-ids --lgu-ids
```

---

## Recommended Pre-Deploy Checklist

```bash
# 1. Run full QA audit
php artisan referralbunny:qa-audit --full --report

# 2. Run tenant-specific checks  
php artisan referralbunny:qa-audit --tenant=lgu-ids --lgu-ids

# 3. Run email check
php artisan referralbunny:qa-audit --emails

# 4. Check failed jobs
php artisan queue:failed

# 5. Check scheduler
php artisan schedule:list

# 6. Run tests
php artisan test

# 7. Check route list for new routes
php artisan route:list | grep -v vendor

# 8. Clear and warm caches
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Troubleshooting

**"Command not found"**
- Run `composer dump-autoload` on server after new command files

**"Table does not exist" errors**
- Run missing migrations in Supabase SQL editor

**"Mail::fake not sending"**
- Correct — fake mode is intentional. Emails are tested for rendering only.

**Critical: stage limit changed**
- Check `tenant_pipeline_stage_rules` table for LGU IDS tenant
- Expected: introduction=14, presentation=21, contract_sent=30, signed=30
- Restore if changed accidentally

**Critical: tenant isolation check failed**
- Review the specific table mentioned
- Find the query that is missing `where('tenant_id', $tenantId)`
- Add tenant scope to all queries for that model

---

_Last updated: 2026-05-07_
_Maintained by the ReferralBunny.ai development team_
