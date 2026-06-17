<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Risk Register

**Probability:** Low / Medium / High
**Impact:** Low / Medium / High / Critical
**Status:** Open / Mitigated / Closed

---

## R-01: Cross-Program Data Leakage

**Risk:** A staff user or API call exposes Program data from one tenant to another due to missing tenant scope on queries.

| Field | Value |
|-------|-------|
| Probability | Medium |
| Impact | Critical |
| Status | Open |
| Owner | Engineering lead |

**Description:**
Program data (memberships, deals, offers) must always be scoped to `tenant_id`. Any query that looks up a Program by `id` alone (without also filtering by `tenant_id`) could return data belonging to another tenant.

**Mitigation:**
1. The `program.resolve` middleware always fetches via `Program::where('tenant_id', $tenant->id)->findOrFail($id)` — never `Program::findOrFail($id)` alone.
2. Global `ProgramScope` or model boot method that auto-scopes by current tenant (if tenancy package is used).
3. `ProgramPolicy` verified in every controller method.
4. QA checklist item: attempt to access `/tenant/{wrongId}/programs/{programId}` — must 404.
5. Code review requirement: any new query touching `programs` must be reviewed for tenant scope.

**Alternatives rejected:**
- Global scope on Program model (risky for superadmin contexts; use explicit scoping instead)

---

## R-02: LGU IDS Regression

**Risk:** The schema/data migration alters LGU IDS pipeline stages, commissions, or deal data, breaking their live business operations.

| Field | Value |
|-------|-------|
| Probability | Low |
| Impact | Critical |
| Status | Open |
| Owner | Engineering lead |

**Description:**
LGU IDS has a locked, production-critical configuration. Any accidental modification to their pipeline stages, commission splits, or deal data during migration would be a P1 incident.

**Mitigation:**
1. LGU IDS gets a separate `programs:migrate-lgu-ids` command — not the general `programs:migrate-defaults`.
2. The command is idempotent and has `--dry-run` mode.
3. `is_locked`, `pipeline_locked`, `rewards_locked`, `import_locked` all set to `true` on migration.
4. `programs_v4` flag stays OFF for LGU IDS during all pilot phases.
5. Full regression test suite runs after migration: pipeline stages, commission formula, deal creation, notifications.
6. Schema migration (adding `program_id` column as nullable) has zero risk to existing LGU IDS data.
7. Rollback procedure documented and tested.

**Consequences if it occurs:**
- Immediate rollback via `programs:rollback-lgu-ids`
- Commission recalculation audit required
- Incident report

---

## R-03: Stale program_id on Legacy Leads

**Risk:** Leads created before V4 migration have `program_id = NULL`. Code that assumes all leads have a `program_id` breaks.

| Field | Value |
|-------|-------|
| Probability | High |
| Impact | Medium |
| Status | Mitigated |
| Owner | Engineering |

**Description:**
Between schema migration (adding `program_id` column) and data migration (backfilling with default program), there will be a window where leads have `program_id = NULL`. Even after the backfill, leads created in that window might be missed.

**Mitigation:**
1. `leads.program_id` is and remains nullable — code must handle null gracefully.
2. Data migration backfills all `program_id = NULL` leads per tenant immediately after creating the default Program.
3. Data migration is idempotent — safe to run multiple times.
4. Any analytics/reporting code filtering by `program_id` must include `WHERE program_id IS NOT NULL OR program_id = default_program_id` — not assume all leads have a program.
5. New lead creation (post-migration) always sets `program_id` from the current program context or the tenant's default program.

**Status:** Mitigated — nullable FK design explicitly handles this case.

---

## R-04: Commission Calculation Change

**Risk:** Adding `program_id` to leads or changing the commission split logic inadvertently changes the commission amounts calculated for existing or new deals.

| Field | Value |
|-------|-------|
| Probability | Low |
| Impact | High |
| Status | Open |
| Owner | Engineering |

**Description:**
Commission rates remain global (30/70) in Phase 1. No per-program rate overrides exist. However, if commission calculation code reads program config or if a data migration sets incorrect defaults, commission amounts could change.

**Mitigation:**
1. Commission calculation code is not modified in Phase 1 — no per-program rate logic introduced.
2. Schema migration only adds `program_id` column; no changes to `commission_splits` table structure.
3. Post-migration verification: run commission calculation on 5 known test deals and compare output to pre-migration baseline.
4. LGU IDS commission regression: specific step in Phase 3 rollout plan.
5. If per-program rates are added in Phase 2, a separate risk assessment is required.

---

## R-05: Portal Parity Failure

**Risk:** A feature is available in the admin panel but the referrer or partner portal shows incorrect, stale, or missing data for the same resource.

| Field | Value |
|-------|-------|
| Probability | Medium |
| Impact | Medium |
| Status | Open |
| Owner | Engineering + QA |

**Description:**
Referrers and partners rely on their portals to see accurate program and deal information. If the admin enrolls a referrer but the referrer's portal doesn't reflect this immediately, trust is broken.

**Mitigation:**
1. Portal parity contract documented in `programs-v4-portal-parity.md`.
2. QA checklist has explicit parity checks for each feature.
3. All portal views query live data (no cached summary tables in Phase 1).
4. Notification system sends deep links that route to the correct portal view.
5. Phase 1 avoids caching program membership data — always fresh from DB.

---

## R-06: Notification Dead Links

**Risk:** Notification emails or in-app notifications contain links to Program resources that no longer exist (program deleted, archived, or membership removed).

| Field | Value |
|-------|-------|
| Probability | Medium |
| Impact | Medium |
| Status | Open |
| Owner | Engineering |

**Description:**
When a program is archived or a membership is removed, existing notifications with deep links to that program become invalid. A referrer clicking a link in an old email should get a graceful experience, not a bare 404.

**Mitigation:**
1. All public-facing program routes handle missing/archived programs gracefully (see `programs-v4-public-routes.md`).
2. Referrer/partner portal routes return a friendly "This resource is no longer available" page (not a bare 404) when the program is archived or membership is removed.
3. Notification templates include the program name in the email body so recipients can identify the program without needing to click the link.
4. Phase 2: implement "notification link health check" — check if a notification's target URL still resolves before sending.

---

## R-07: Migration Rollback Failure

**Risk:** If the data migration must be rolled back (e.g., due to an issue discovered post-migration), the rollback process corrupts or loses lead data.

| Field | Value |
|-------|-------|
| Probability | Low |
| Impact | High |
| Status | Mitigated |
| Owner | Engineering |

**Description:**
Rollback involves setting `leads.program_id = NULL` and deleting the default Program rows. If this is done incorrectly, leads could lose their tenant association or other data.

**Mitigation:**
1. Schema rollback uses `nullOnDelete()` — `leads.program_id` becomes NULL when program is deleted; lead row is never deleted.
2. Data rollback command (`programs:rollback-defaults`) only touches `program_id` column — no other lead columns modified.
3. `TenantReferralProgramDraft` records (V3) are never deleted or modified by V4 migration.
4. Rollback commands have `--dry-run` mode.
5. Full database backup required before any production migration run (rollout checklist item).
6. Rollback tested on staging before production.

**Status:** Mitigated — by design decisions (nullable FK, nullOnDelete, V3 preservation).

---

## Risk Summary Table

| Risk | Probability | Impact | Status |
|------|-------------|--------|--------|
| R-01 Cross-program data leakage | Medium | Critical | Open |
| R-02 LGU IDS regression | Low | Critical | Open |
| R-03 Stale program_id on legacy leads | High | Medium | Mitigated |
| R-04 Commission calculation change | Low | High | Open |
| R-05 Portal parity failure | Medium | Medium | Open |
| R-06 Notification dead links | Medium | Medium | Open |
| R-07 Migration rollback failure | Low | High | Mitigated |
