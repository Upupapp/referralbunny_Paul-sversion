<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Rollout Plan

## Feature Flag: `programs_v4`

The `programs_v4` feature flag is disabled by default. It is enabled per-tenant.

**Flag behavior:**
- OFF: V4 Programs UI is completely hidden; V3 wizard unaffected; no new routes accessible
- ON: Programs section appears in sidebar; all V4 routes are accessible; V3 wizard remains accessible
- Disabling the flag never deletes or corrupts Program data already created
- The flag gates UI and routes only; the schema migration runs regardless

**Flag storage:** Stored in tenant settings/metadata JSON or a dedicated `feature_flags` table (per existing pattern in the codebase).

---

## Rollout Phases

### Phase 1 — Local Development

**Who:** Engineering team only
**Flag state:** ON for local dev tenants
**Goal:** Build and test all Phase 1 deliverables

Checklist:
- [ ] Schema migrations written and tested (up and down)
- [ ] Default Program data migration works with `--dry-run`
- [ ] LGU IDS migration works with `--dry-run`
- [ ] Admin CRUD routes implemented
- [ ] Feature flag middleware wired
- [ ] ProgramPolicy written and tested
- [ ] Referrer portal routes showing enrolled programs
- [ ] Partner portal routes showing program deals
- [ ] 15-point completion checklist (PRD) all green
- [ ] No `programs_v4` behavior visible when flag is OFF

---

### Phase 2 — Internal Testing

**Who:** Internal team + QA
**Flag state:** ON for a dedicated staging tenant (non-LGU IDS, non-pilot)
**Goal:** Full QA against the QA checklist; catch edge cases

Checklist:
- [ ] QA checklist in `programs-v4-qa.md` fully executed
- [ ] Portal parity contract verified (see `programs-v4-portal-parity.md`)
- [ ] Notification deep links work across all portals
- [ ] Permission enforcement tested (wrong tenant, wrong guard, no membership)
- [ ] Feature flag ON/OFF toggle tested — no UI bleed when OFF
- [ ] Activity log entries confirmed: correct user_id (staff), correct program_id, no reseller/partner IDs in user_id
- [ ] LGU IDS regression test suite run on staging (see Phase 3)

---

### Phase 3 — LGU IDS Regression

**Who:** Engineering + QA
**Flag state:** `programs_v4` flag OFF for LGU IDS tenant (they are NOT the pilot)
**Goal:** Confirm LGU IDS behavior is completely unchanged by schema + data migration

**This is a no-new-program-creation phase.** LGU IDS gets its default locked Program created by migration only. No admin should be able to create a second program for LGU IDS via the V4 UI.

LGU IDS Regression Checklist:
- [ ] Pipeline stages unchanged: Introduction, Presentation Done, Contract Sent, Signed, Paid
- [ ] Commission calculation unchanged: 70% commission pool, 30% company share on Added Amount
- [ ] All existing leads have `program_id` set to the LGU IDS default program (no nulls)
- [ ] LGU IDS Program row has `is_locked=1`, `pipeline_locked=1`, `rewards_locked=1`, `import_locked=1`
- [ ] V4 UI is NOT visible to LGU IDS admin (`programs_v4` flag = OFF)
- [ ] Attempting to access V4 routes for LGU IDS tenant → 404 (flag is off)
- [ ] Existing reports/commissions/activity logs unaffected
- [ ] No foreign key errors on `leads` table after migration
- [ ] Import functionality unchanged
- [ ] Notifications and emails still fire correctly

**Sign-off required before Phase 4:** LGU IDS regression must pass completely before enabling the flag for any pilot tenant.

---

### Phase 4 — Pilot Tenant

**Who:** One selected pilot tenant (NOT LGU IDS)
**Flag state:** `programs_v4` = ON for pilot tenant only
**Goal:** Real-world usage feedback; catch production-specific issues

Pilot tenant criteria:
- Active tenant with existing referrers and deals
- Not a large/critical account (acceptable to have minor issues)
- Team willing to provide feedback
- Has a V3 TenantReferralProgramDraft to validate default Program migration

Pilot Rollout Steps:
1. Confirm LGU IDS regression passed (Phase 3 sign-off)
2. Run schema migrations on production (already tested)
3. Run `programs:migrate-lgu-ids` on production (locked, verified)
4. Run `programs:migrate-defaults --tenant={pilot_id}` on production
5. Enable `programs_v4` flag for pilot tenant
6. Monitor for 48 hours (see monitoring checklist below)
7. Collect feedback; fix any issues
8. Decide: expand to more tenants or iterate

---

### Phase 5 — General Availability

**Who:** All tenants
**Flag state:** `programs_v4` enabled for all tenants (or progressive rollout by cohort)
**Goal:** Full production rollout

GA Rollout Steps:
1. Pilot phase complete with no P1/P2 issues
2. Run `programs:migrate-defaults` for all remaining tenants
3. Enable `programs_v4` flag for all tenants (or use percentage rollout)
4. Announce in product changelog
5. Update documentation / help center

---

## Monitoring Checklist (Per Phase)

After each flag enable or migration run, monitor for 48 hours:

### Database
- [ ] No FK constraint violations on `leads.program_id`
- [ ] No `NULL` `program_id` on newly created leads (should always get default program)
- [ ] `activity_logs.user_id` column has no reseller/partner UUIDs (check for non-bigint values)
- [ ] No unexpected soft-deleted programs

### Application Errors
- [ ] Error rate on `/tenant/*/programs*` routes is zero
- [ ] Error rate on `/reseller/*/programs*` routes is zero
- [ ] Error rate on `/partner/programs*` routes is zero
- [ ] No 403 errors on non-locked programs
- [ ] No 404 errors on existing program memberships

### Business Logic
- [ ] Commission calculations unchanged for existing deals
- [ ] No change to pipeline stage behavior
- [ ] Existing notifications still firing with correct links
- [ ] V3 referral program wizard still accessible and functional

### LGU IDS Specific
- [ ] LGU IDS admin has NO access to V4 Programs UI (flag OFF)
- [ ] LGU IDS commission calculations identical to pre-migration baseline
- [ ] LGU IDS pipeline stages unmodified

---

## Rollback Triggers

Initiate rollback if any of the following occur:
- P1: LGU IDS commission calculation produces different results post-migration
- P1: Cross-tenant program data exposure (any tenant seeing another tenant's programs)
- P1: `leads.program_id` constraint causing insert failures on new deals
- P1: Activity log writing reseller/partner UUIDs to `user_id` column
- P2: Referrer portal showing programs they have no membership in
- P2: Partner portal showing deals from other partners
- P3: Any 5xx error rate exceeding 0.1% on program routes

**Rollback procedure:** See `programs-v4-migration.md` Part 4.
