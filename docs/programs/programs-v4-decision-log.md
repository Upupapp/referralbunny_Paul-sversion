<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Decision Log

---

## DL-01: Programs Are a New First-Class Relational Entity

**Date:** 2026-06-17
**Status:** Decided

**Context:**
V3 stores referral program configuration as a JSON blob in `TenantReferralProgramDraft`. This works for single-program tenants but cannot support multiple simultaneous programs, program-level analytics, program-scoped memberships, or independent program lifecycles.

**Decision:**
Create a new `programs` table as a first-class relational entity. Programs have their own model (`Program`), policy (`ProgramPolicy`), and route group. They are not a subfield or extension of `TenantReferralProgramDraft`.

**Rationale:**
- A JSON blob cannot be queried efficiently for program-level analytics
- Multiple programs per tenant requires separate rows, not a JSON array
- First-class entities get proper foreign keys, indexes, and cascade rules
- Policy-based authorization is cleaner on a dedicated model

**Consequences:**
- A new migration set is required (8+ tables)
- Existing `leads` table needs `program_id` FK added
- A data migration is needed to populate the default program

**Alternatives Rejected:**
- Extending `TenantReferralProgramDraft` with a "version" or "type" field — rejected because JSON blobs cannot participate in relational queries or indexing
- Storing programs as JSON in a tenant settings column — same problem, worse

---

## DL-02: V3 Wizard Is Preserved; V4 Adds Programs on Top

**Date:** 2026-06-17
**Status:** Decided

**Context:**
The V3 referral program wizard (`TenantReferralProgramDraft`) is in production use across all tenants. Replacing it outright would break existing functionality and require all tenants to migrate simultaneously.

**Decision:**
V3 wizard is preserved entirely. The Settings > Referral Program page continues to exist. V4 adds a new Programs section alongside V3, not instead of it. Tenants can use both simultaneously during the transition period.

**Rationale:**
- Zero-downtime migration for existing tenants
- V3 remains the config source of truth until a tenant creates V4 programs
- Gradual migration path: default program migrated from V3 config; tenants can then manage via V4 UI

**Consequences:**
- Two configuration surfaces exist simultaneously (V3 Settings and V4 Programs)
- Default program config mirrors V3 config at migration time; later edits to V3 do NOT auto-sync to V4
- Documentation must make it clear which surface controls what

**Alternatives Rejected:**
- Migrate all V3 config to V4 and disable V3 wizard immediately — too risky; no rollback path
- Make V4 Programs delegate to V3 config — creates a tangled dependency; prevents independent program config

---

## DL-03: program_id Added as Nullable FK to leads Table

**Date:** 2026-06-17
**Status:** Decided

**Context:**
Leads need to be associated with a Program so that program-scoped deal views, analytics, and commission attribution work correctly.

**Decision:**
Add `program_id` as a nullable foreign key on the `leads` table with `nullOnDelete()`. All existing leads get `program_id` backfilled to the tenant's default program during data migration. New leads must always have `program_id` set at creation time.

**Rationale:**
- Nullable allows schema migration to run before data migration without blocking lead creation
- `nullOnDelete()` ensures lead data is never lost if a program is deleted
- Backfill to default program preserves historical analytics context

**Consequences:**
- Code must handle `program_id = NULL` gracefully on pre-migration leads
- New lead creation logic must always set `program_id` (from current program context or tenant default)
- Queries that filter by `program_id` must account for nulls on legacy rows

**Alternatives Rejected:**
- Non-nullable `program_id` with a DEFAULT constraint — requires the default program to exist before any lead can be inserted; ordering dependency too fragile
- Storing `program_id` in `leads.metadata` JSON — not queryable or indexable

---

## DL-04: One Default Program Auto-Created Per Tenant from V3 Config on Migration

**Date:** 2026-06-17
**Status:** Decided

**Context:**
When V4 launches, all existing tenants need at least one Program so that their existing leads have a `program_id` and their referrers/partners have something to see in their portals.

**Decision:**
The `programs:migrate-defaults` command auto-creates one Program per tenant with:
- `is_default = true`
- `status = active`
- Name derived from V3 `TenantReferralProgramDraft` config or tenant company name
- All existing leads for that tenant backfilled with this Program's ID

**Rationale:**
- Eliminates empty state for all existing tenants on day one of V4
- Preserves continuity: referrers and partners see the same program they've always been part of
- `is_default` flag allows future logic to identify the migrated-from-V3 program

**Consequences:**
- Every tenant gets exactly one default program (cannot be deleted, only archived)
- Default program config may not perfectly match V3 JSON (best-effort extraction)
- Admin can edit the default program via V4 UI after migration

**Alternatives Rejected:**
- Require admins to create programs manually before enabling V4 — creates a barrier to adoption and breaks portal views immediately
- No default program, just nullable `program_id` everywhere — leads to empty states and broken analytics for all tenants

---

## DL-05: LGU IDS Gets Its Default Program with All Locked Behavior Preserved; Is NOT the First Pilot Tenant

**Date:** 2026-06-17
**Status:** Decided

**Context:**
LGU IDS is a protected tenant with a locked pipeline configuration (fixed stages, commission formula, import). Any change to their setup is a P1 incident risk.

**Decision:**
- LGU IDS gets a separate migration command (`programs:migrate-lgu-ids`)
- Their default Program is created with `is_locked=true`, `pipeline_locked=true`, `rewards_locked=true`, `import_locked=true`
- The `programs_v4` feature flag is NOT enabled for LGU IDS during Phase 3 or Phase 4
- LGU IDS is explicitly excluded from the pilot cohort
- A dedicated regression test suite runs against LGU IDS after migration

**Rationale:**
- LGU IDS has zero tolerance for config drift
- Keeping them off the V4 UI flag means they cannot accidentally trigger V4 code paths
- A separate migration command gives explicit, auditable control over their migration

**Consequences:**
- LGU IDS admin cannot use V4 Programs UI (by design)
- All existing locked behavior (pipeline, rewards, import) is preserved
- `programs_v4` flag for LGU IDS will only be considered for Phase 5 GA or later

**Alternatives Rejected:**
- Include LGU IDS in the standard migration — too risky; locked behavior could be inadvertently relaxed
- Skip creating a Program for LGU IDS entirely — their leads need `program_id` for schema consistency

---

## DL-06: Commission Rates Remain Global (30/70) for V4 Phase 1; Per-Program Rates Are Phase 2

**Date:** 2026-06-17
**Status:** Decided

**Context:**
The current commission formula is global: Company Share = 30% of Added Amount, Commission Pool = 70% of Added Amount. Some future programs may need different rates.

**Decision:**
Phase 1 does not introduce per-program commission rate fields or logic. The `program_offers` table (Phase 2) will be the vehicle for per-program commission configuration. Phase 1 uses the existing global rates for all programs.

**Rationale:**
- Adding per-program rate logic in Phase 1 risks introducing commission calculation bugs
- Phase 1 is already a large scope; deferring rates keeps it focused
- LGU IDS uses the global formula; per-program rates should never override their locked config

**Consequences:**
- `programs` table has no commission rate columns in Phase 1
- `program_offers` (Phase 2) will carry commission type/value fields
- All Phase 1 commission calculations use existing code paths unchanged

**Alternatives Rejected:**
- Add per-program rate fields now but leave them null — confusing; devs might start using them prematurely
- Override LGU IDS rates via per-program config — explicitly rejected; they are locked

---

## DL-07: Referrer (Reseller) Portal Shows Programs They're Enrolled In

**Date:** 2026-06-17
**Status:** Decided

**Context:**
Referrers need to see which programs they participate in and submit deals against specific programs.

**Decision:**
The referrer portal gets a "My Programs" section showing programs where `referrer_program_memberships.reseller_id = current referrer`. Membership is managed by admins (and optionally via invite flows in Phase 2). Referrers cannot self-enroll in Phase 1.

**Rationale:**
- Membership is admin-controlled: ensures program integrity
- Referrers should only see programs relevant to them (not all tenant programs)
- Program-scoped deal list gives referrers clarity on which deals belong to which program

**Consequences:**
- Referrers with no memberships see no Programs section in sidebar
- Admin must explicitly enroll referrers; no auto-enrollment
- Phase 2 will add invite links and self-service application flow

**Alternatives Rejected:**
- Show all tenant's programs to all referrers — exposes programs to referrers not yet enrolled; breaks access control
- Auto-enroll all referrers into the default program — some tenants have private programs; auto-enrollment breaks privacy expectations

---

## DL-08: Partner Portal Shows Programs Where They Have Active Deals

**Date:** 2026-06-17
**Status:** Decided

**Context:**
Partners (business partners, not referrers) have deals submitted on their behalf. They need to see program context for those deals.

**Decision:**
Partner memberships (`partner_program_memberships`) are created automatically when a lead with `program_id` and `partner_id` is created. Partners cannot be manually enrolled or invited. The partner portal shows programs where they have at least one deal.

**Rationale:**
- Partners don't "join" programs the way referrers do — they participate by having deals
- Auto-membership creation is simpler and avoids a manual management overhead
- Partners should see all programs where their deals exist, historically and currently

**Consequences:**
- No invite flow for partners
- Partner program membership is derived state (always consistent with deal data)
- If all deals in a program are deleted, the partner membership becomes `inactive` but the history is preserved

**Alternatives Rejected:**
- Manual partner enrollment like referrers — adds unnecessary overhead; partner involvement is deal-driven
- No partner program view at all — partners need program context for their deal submissions and commission tracking

---

## DL-09: Feature Flag `programs_v4` Gates New Programs UI; Never Weakens Authorization

**Date:** 2026-06-17
**Status:** Decided

**Context:**
V4 is a new feature that should be rolled out gradually. Feature flags are the mechanism for controlling rollout.

**Decision:**
The `programs_v4` flag:
- Gates the Programs sidebar section (hidden if off)
- Gates access to V4 routes (404 if off)
- Does NOT weaken authorization when on (same ProgramPolicy rules apply regardless)
- Does NOT affect V3 wizard functionality in either direction
- Is stored per-tenant
- Defaults to OFF for all tenants

**Rationale:**
- Gradual rollout without code deploys
- Disabling the flag is a safe "off switch" with no data loss
- Authorization must always be enforced regardless of flag state

**Consequences:**
- V4 routes return 404 (not 403) when flag is off — this is intentional (not revealing that routes exist)
- LGU IDS flag stays OFF indefinitely (until explicitly scoped)
- Internal staff can test V4 by enabling the flag on a test tenant

**Alternatives Rejected:**
- Code-level flag (deploy required to toggle) — too slow for rollout; no per-tenant granularity
- Soft-hiding (flag off hides UI but routes still respond) — routes must return 404 to prevent direct URL access

---

## DL-10: Activity Logs Get program_id Column Added for V4 Events

**Date:** 2026-06-17
**Status:** Decided

**Context:**
V4 events need to be associated with a specific program for program-scoped activity feeds and analytics. The existing `activity_logs` table does not have a `program_id` column.

**Decision:**
Add `program_id` (nullable bigint, no FK constraint) to `activity_logs`. All V4-sourced log entries populate this column. Pre-V4 log entries have `program_id = NULL`.

**Critical constraint:** `activity_logs.user_id` remains a FK to staff `users` table (bigint). It is never populated with reseller or partner IDs. Non-staff actors are identified in the `metadata` JSON column.

**Rationale:**
- Program-scoped activity feed requires a queryable `program_id` column
- No FK constraint on the column avoids cascade-delete of historical log entries if a program is deleted
- `user_id` FK constraint cannot be changed without breaking existing log queries and reports

**Consequences:**
- All V4 event writers must remember: `user_id` = staff bigint OR null; `metadata` = non-staff actor data
- `activity_logs` without `program_id` (pre-V4) are fine — nullable column
- Query for program activity: `WHERE program_id = ? AND tenant_id = ?`

**Alternatives Rejected:**
- Separate `program_activity_logs` table — duplicates the logging infrastructure; existing log viewers need program context inline
- Store `program_id` in `metadata` JSON — not queryable efficiently; prevents indexing
- Use a polymorphic `loggable` on `activity_logs` to point to programs — overly complex; existing log schema doesn't use polymorphic pattern
