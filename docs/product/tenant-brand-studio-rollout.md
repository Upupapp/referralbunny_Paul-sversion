# Tenant Brand Studio 2.0 — Rollout Plan

---

## Phase 1 (Current)

**Scope:** Brand Identity (logo + colors) + CSS injection + draft/publish  
**Status:** In development

- [x] Codebase inspection
- [x] Documentation written
- [ ] Migration: `tenant_brand_profiles`
- [ ] Controller + routes
- [ ] Brand Studio view (Alpine.js)
- [ ] CSS var injection into `layouts/app.blade.php`
- [ ] Logo upload (secure, tenant-scoped)
- [ ] Brand health score
- [ ] Tests
- [ ] STITCH green

**Deployment:**
1. Run migration on VPS.
2. Run `php artisan optimize` + `npm run build`.
3. Existing tenant records are unaffected — `tenant_brand_profiles` will be empty until tenants save a draft.

---

## Phase 2 (Planned)

**Scope:** Portal injection, email branding, version history, preview

- [ ] Logo visible in admin/partner/referrer portal sidebars
- [ ] Email header logo injection (base email layout)
- [ ] Brand version history (last 5 publishes, one-click restore)
- [ ] Side-panel preview before publish
- [ ] Partner portal color overrides

---

## Phase 3 (Future)

**Scope:** Full white-label, custom domains, dark mode

---

## Rollback

Phase 1 is fully additive:
- New table only: drop `tenant_brand_profiles` and remove routes/controller to rollback.
- The `tenants` table is NOT modified in Phase 1 (logo_url and accent_color already existed).
- CSS injection is conditional on `$tenant` being set — reverts to default tokens if removed.
