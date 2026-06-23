<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Partner Portal Navigation

## Overview

The Partner portal shows Programs when:
1. `programs_v4` feature flag is enabled for the partner's tenant
2. The partner has at least one `partner_program_memberships` row with `status = active`

Partner memberships are derived automatically from deal activity — when a Lead with `program_id` is associated with a partner, a `partner_program_memberships` row is created or updated. Partners do not manually join programs.

---

## Partner Portal Sidebar (V4 additions)

```
Partner Portal Sidebar
├── Dashboard
├── My Deals
├── [My Programs]  ← V4 new section (feature-flagged + auto-membership-gated)
├── Commissions
├── Documents
└── Profile / Settings
```

"My Programs" is hidden if the partner has no active program memberships.

---

## Programs List Page

**Route:** `GET /partner/programs`
**Route name:** `partner.programs.index`

**What is shown:**
- Programs where `partner_program_memberships.partner_id = current partner` AND membership `status = active`
- Programs where partner has historical deals (membership `status = inactive`) shown in a "Past Programs" section

**Table columns:**

| Column | Notes |
|--------|-------|
| Program Name | Linked to program detail |
| Company Name | The tenant's company name |
| Program Status | Active / Paused / Ended |
| My Deals | Count of leads in this program associated with this partner |
| Active Deals | Count where stage is not Paid/Closed |
| Total Deal Value | Sum of `leads.deal_value` for this partner + program |
| First Deal Date | `partner_program_memberships.first_deal_at` |

**Sections:**
1. **Active Programs** — membership active + program status = active/paused
2. **Past Programs** — program ended or membership inactive

**Empty state:** "No programs with active deals yet. Once a deal is submitted through a program, it will appear here."

---

## Program Detail Page

**Route:** `GET /partner/programs/{programId}`
**Route name:** `partner.programs.show`

**Access control:** Partner must have a `partner_program_memberships` row for this program. Otherwise 404.

**Page sections:**

### Header
- Program name + status badge
- Company name (tenant)
- Program description
- Partner since: `first_deal_at` date

### Stats Row
| Stat | Value |
|------|-------|
| Total Deals | All leads in this program for this partner |
| Active Deals | Deals not yet at Paid stage |
| Total Value | Sum of deal values |
| Commission Earned | From `commission_splits` via leads in this program |

### My Deals in This Program (preview — last 5)
- Shows: Contact, Organization, Stage, Deal Value, Date
- [View All Deals] link

### Program Info
- Stages used in this program (read-only view of pipeline stages)
- Program dates (if set)
- Program visibility

---

## Program-Scoped Deal List

**Route:** `GET /partner/programs/{programId}/deals`
**Route name:** `partner.programs.deals`

Shows all `leads` where:
- `program_id = $programId`
- `partner_id = $partnerId` (current partner's deals only)
- `tenant_id = partner.tenant_id`

**Columns:** Contact, Organization, Stage, Deal Value, Referrer Name, Date Submitted, Last Activity

**Filters:** Stage, Date Range

**Empty state:** "No deals in this program yet."

---

## Deal Detail Page (Program-Scoped)

**Route:** `GET /partner/programs/{programId}/deals/{leadId}`
**Route name:** `partner.programs.deals.show`

Same as existing partner deal detail, but breadcrumb shows the program context:
```
My Programs > [Program Name] > Deals > [Contact Name]
```

---

## Partner Membership Model

Partner membership in Programs is automatic, not invite-based. The flow is:

1. An admin or referrer submits a Lead with `program_id = X` and `partner_id = Y`
2. A `partner_program_memberships` row is created (or updated) for `(program_id=X, partner_id=Y)`
3. The partner can now see Program X in their portal
4. If the program ends, existing memberships remain visible in "Past Programs"

No manual enroll/withdraw flow for partners in Phase 1.

---

## LGU IDS Behavior

LGU IDS partners:
- See their programs in the portal like any other partner
- Programs are read-only (cannot modify stages, etc.)
- No difference in the partner-facing view — lock only affects admin-side configuration

---

## Authorization Summary

| Action | Allowed when |
|--------|-------------|
| View Programs list | `programs_v4` flag on + partner has any membership |
| View Program detail | Active partnership membership for that program |
| View program deals | Active membership + deals attributed to this partner |
| See commission data | Always — commission splits visible per deal |
| Modify program config | Never — partners cannot change program settings |
