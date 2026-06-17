# Tenant Brand Studio 2.0 — Decision Log

---

## DL-01: Use `tenant_brand_profiles` table, not extend `tenants` table

**Date:** 2026-06-17  
**Decision:** New `tenant_brand_profiles` table holds brand draft state; `tenants.logo_url` and `tenants.accent_color` remain the published "live" canonical values.  
**Rationale:** Keeps the draft/publish separation clean. Tenants table is the live source; brand profile is the draft workspace. Avoids wide table bloat on `tenants`.  
**Trade-off:** One extra join/query to get brand state. Acceptable at this scale.

---

## DL-02: Sidebar color stored in brand_profiles only (Phase 1)

**Date:** 2026-06-17  
**Decision:** `sidebar_color` is stored in `tenant_brand_profiles`. On publish, it is NOT written back to `tenants` table (no `sidebar_color` column there). CSS injection reads from the published brand profile.  
**Rationale:** Avoids schema change on `tenants` for Phase 1. Can be promoted in Phase 2 if needed.  
**Trade-off:** Slightly more complex CSS injection logic.

---

## DL-03: Logo stored on `public` disk, path-relative

**Date:** 2026-06-17  
**Decision:** Logos stored at `tenant-logos/{tenantId}/{filename}` on the `public` disk. URL generated via `Storage::url()`.  
**Rationale:** No image processing library installed; public disk gives direct HTTP access. S3 migration trivial via `.env` change.  
**Trade-off:** Files are publicly accessible by URL if guessed. Mitigation: UUID-randomized filenames.

---

## DL-04: Phase 1 draft/publish = one profile row per tenant (upsert)

**Date:** 2026-06-17  
**Decision:** `TenantBrandProfile` uses `updateOrCreate(['tenant_id' => $tenantId])`. One row per tenant; status field tracks draft vs. published.  
**Rationale:** Simplest model for Phase 1. Full version history deferred to Phase 2 (modeled on `tenant_referral_program_versions` pattern).

---

## DL-05: WCAG contrast check — warn only in Phase 1

**Date:** 2026-06-17  
**Decision:** Phase 1 shows a contrast warning if the accent color fails WCAG AA against white, but does not block publish.  
**Rationale:** Don't block tenant progress for edge-case colors; improve with Phase 2 gating after user feedback.
