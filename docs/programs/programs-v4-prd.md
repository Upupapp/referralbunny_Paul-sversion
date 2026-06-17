<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Product Requirements Document

## Vision

Programs V4 introduces first-class relational `Program` entities to ReferralBunny.ai, replacing the single JSON-blob wizard approach (V3) with a proper multi-program-per-tenant architecture. Tenants can create, manage, and operate multiple referral programs simultaneously, each with its own referrers, partners, offers, commissions, and lifecycle.

V3 is preserved. V4 builds on top of it.

---

## Problem Statement

V3 stores referral program configuration as a JSON blob in `TenantReferralProgramDraft`. This works for single-program tenants but breaks down when a tenant needs:
- Multiple simultaneous programs (e.g. "SMB Referral" vs "Enterprise Referral")
- Per-program referrer enrollments
- Program-scoped analytics and reporting
- Program lifecycle management (draft → active → ended)
- Public-facing program landing pages

---

## MoSCoW Requirements

### Must Have (Phase 1 — Foundation + Basic CRUD)
- M-01: `programs` table with all core fields
- M-02: CRUD for Programs in admin panel
- M-03: `program_id` nullable FK on `leads` table
- M-04: Default Program auto-created per tenant from V3 config on migration
- M-05: LGU IDS gets locked default Program (pipeline/rewards/import locked)
- M-06: `programs_v4` feature flag gating all new UI
- M-07: Referrer portal shows Programs they are enrolled in
- M-08: Partner portal shows Programs where they have active deals
- M-09: Activity logs get `program_id` column for V4 events
- M-10: Authorization — tenants can only see/edit their own Programs

### Should Have (Phase 2 — Full Wizard + Offers + Contracts)
- S-01: Program Setup Wizard (replaces V3 wizard for new programs)
- S-02: `program_offers` and `program_offer_versions` tables
- S-03: `program_contracts` table with e-sign support
- S-04: `program_configuration_versions` audit trail
- S-05: `referrer_program_memberships` with status tracking
- S-06: `partner_program_memberships` with status tracking
- S-07: Program groups (`program_groups`)
- S-08: Member action items (`member_action_items`)
- S-09: Public program landing page `/p/{slug}`
- S-10: Program-scoped notifications

### Could Have (Phase 3 — Reports + Promotions + API)
- C-01: Per-program analytics dashboard
- C-02: Program promotions / bonus events
- C-03: Per-program commission rate overrides (Phase 2 decision)
- C-04: Public API endpoints for program data
- C-05: Program-scoped import templates

### Won't Have (V4 scope)
- W-01: Per-program commission rates (deferred to Phase 2 — rates remain global 30/70)
- W-02: Multi-tenant program sharing (cross-tenant programs)
- W-03: Program templates marketplace

---

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Admin can create a Program in < 3 minutes | 100% of test sessions | UX timing |
| Zero cross-tenant data leakage | 0 incidents | Security test suite |
| LGU IDS regression | 0 failures | Regression test suite |
| Referrer sees enrolled Programs in portal | 100% | QA checklist |
| Partner sees program-scoped deals | 100% | QA checklist |
| Default Program migration completes without data loss | 100% | Migration smoke test |
| Feature flag disables all V4 UI when off | 100% | Feature flag test |

---

## Guardrails

1. **LGU IDS is locked.** Its pipeline stages, rewards, and import config cannot be modified via Programs V4 UI or API. Any code path that could modify LGU IDS program config must hard-fail with a 403.
2. **No cross-tenant leakage.** Every Programs query must be scoped by `tenant_id`. No global Program lookups without explicit superadmin context.
3. **`activity_logs.user_id` is a bigint FK to staff users.** Never pass referrer/partner UUIDs into `user_id`. Use `metadata` or new columns for those.
4. **Feature flag `programs_v4` gates new UI only.** Disabling it never weakens authorization or breaks V3 functionality.
5. **Commission rates stay global (30/70) in Phase 1.** No per-program rate logic until Phase 2 is explicitly scoped.
6. **V3 wizard is never deleted.** It remains the configuration source of truth until a tenant explicitly migrates.

---

## 15-Point Completion Checklist

- [ ] 1. `programs` table created with all fields and indexes
- [ ] 2. `program_groups` table created
- [ ] 3. `referrer_program_memberships` table created
- [ ] 4. `partner_program_memberships` table created
- [ ] 5. `program_id` nullable FK added to `leads` table
- [ ] 6. `program_id` column added to `activity_logs` table
- [ ] 7. Data migration: default Program created per tenant from V3 config
- [ ] 8. LGU IDS special migration: locked Program with all locked flags set
- [ ] 9. `Program` Eloquent model with relationships, scopes, and policy
- [ ] 10. Admin CRUD routes and controllers (index, create, store, show, edit, update, destroy)
- [ ] 11. Referrer portal Program list and detail routes
- [ ] 12. Partner portal Program list and detail routes
- [ ] 13. `programs_v4` feature flag wired in middleware and blade checks
- [ ] 14. Authorization policies: ProgramPolicy scoped to tenant
- [ ] 15. All QA checklists in `programs-v4-qa.md` passing green

---

## Phase Overview

### Phase 1 — Foundation + Basic CRUD
**Goal:** Relational Programs exist, admin can manage them, referrers and partners can see them.

Deliverables:
- Schema migration (new tables + `program_id` on leads/activity_logs)
- Data migration (default Program per tenant)
- Basic admin CRUD (list, create, edit, delete)
- Referrer portal: Programs list + detail
- Partner portal: Programs list + detail
- Feature flag gating
- LGU IDS locked migration + regression tests

### Phase 2 — Full Wizard + Offers + Contracts
**Goal:** Programs have rich configuration, offers, contracts, and member management.

Deliverables:
- Program Setup Wizard (11-tab workspace)
- `program_offers` + `program_offer_versions`
- `program_contracts` with e-sign
- `program_configuration_versions` audit trail
- `referrer_program_memberships` with invite/enroll/withdraw flows
- `partner_program_memberships`
- Public landing pages `/p/{slug}`
- Program-scoped notifications
- Member action items

### Phase 3 — Reports + Promotions + API
**Goal:** Programs are a reporting and promotion surface.

Deliverables:
- Per-program analytics dashboard
- Program promotions / bonus commission events
- Public API endpoints
- Per-program commission rate overrides (if Phase 2 decision changes)
- Program-scoped import templates
