<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Referrer Portal Navigation

## Overview

The Referrer (Reseller) portal shows Programs only when:
1. The `programs_v4` feature flag is enabled for the tenant the referrer belongs to
2. The referrer has at least one row in `referrer_program_memberships`

If both conditions are not met, the Programs section is hidden from the sidebar entirely.

---

## Referrer Portal Sidebar (V4 additions)

```
Referrer Portal Sidebar
├── Dashboard
├── My Deals
├── [My Programs]  ← V4 new section (feature-flagged + membership-gated)
│   └── (program list inline or separate page)
├── Commissions
├── Resources
└── Profile / Settings
```

The "My Programs" item is hidden if the referrer has no active program memberships.

---

## Programs List Page

**Route:** `GET /reseller/{resellerId}/programs`
**Route name:** `reseller.programs.index`

**What is shown:**
- Only Programs where `referrer_program_memberships.reseller_id = current reseller` AND `status IN (active, paused, ended)`
- Does NOT show programs where membership is `withdrawn` or `invited` (invited → shown in a separate "Pending Invites" section)

**Table columns:**
| Column | Notes |
|--------|-------|
| Program Name | Linked to program detail |
| Tenant (company) name | So referrer knows which client this is for |
| Membership Status | active / suspended |
| Enrolled Since | `enrolled_at` date |
| My Deals | Count of leads in this program attributed to this referrer |
| My Commissions | Total earned commission in this program |
| Status | Program status (active/paused/ended) |

**Sections on the page:**
1. **Pending Invites** — Programs with membership status = `invited`. Shows [Accept] and [Decline] buttons. Invite expiry shown.
2. **Active Programs** — membership status = `active`, program status = `active` or `paused`
3. **Past Programs** — program status = `ended`; membership still active

**Empty state:** "You haven't been enrolled in any programs yet. Contact your program manager to get started."

**Paused program badge:** Yellow "Paused" badge on the program card; referrer can still view but cannot submit new deals.

---

## Program Detail Page

**Route:** `GET /reseller/{resellerId}/programs/{programId}`
**Route name:** `reseller.programs.show`

**Access control:** Referrer must have an active (or suspended) membership in this program, OR the program must have `visibility = public`. Otherwise 404.

**Page sections:**

### Header
- Program name + status badge
- Tenant (company) name
- Program description (from `programs.description`)
- Enrolled Since date
- Quick stats: My Deals count, My Commission total, Active Deal count

### Quick Actions
- [Submit New Deal] — links to existing deal submission flow, pre-populated with `program_id`; disabled if program is paused or ended
- [View All My Deals in This Program] — links to deals page
- [View Offer Details] — Phase 2; links to ProgramOffer

### Program Info Card
- Visibility: Private / Invite Only / Public
- Program dates (starts_at / ends_at if set)
- Commission offer summary (Phase 2; Phase 1 shows global 30/70 rates)

### My Recent Deals
- Last 5 deals in this program attributed to this referrer
- Columns: Contact, Organization, Stage, Deal Value, Commission, Date

### Membership Status Card
- Status: Active / Suspended / Withdrawn
- Enrolled: date
- [Withdraw from Program] button — Phase 2

---

## Program-Scoped Deal List

**Route:** `GET /reseller/{resellerId}/programs/{programId}/deals`
**Route name:** `reseller.programs.deals`

Shows all `leads` where:
- `program_id = $programId`
- `reseller_id = $resellerId` (referrer's deals only)
- `tenant_id = reseller.tenant_id`

**Columns:** Contact, Organization, Stage, Deal Value, Commission Earned, Last Activity, Date Submitted

**Filters:** Stage, Date Range, Status (active/closed)

**Empty state:** "No deals in this program yet. Submit your first deal to get started."

---

## Program Membership Page (Phase 2)

**Route:** `GET /reseller/{resellerId}/programs/{programId}/membership`
**Route name:** `reseller.programs.membership`

**Sections:**
- Membership details (status, enrolled_at, invited_by)
- Signed contracts (links to `program_contracts` records)
- Offer details (current ProgramOffer)
- Action items (MemberActionItems assigned to this referrer)
- [Accept Invite] / [Withdraw from Program] actions

---

## Invite Acceptance Flow (Phase 2)

When a referrer receives an invite:

1. Email contains link: `/p/{slug}/invite/{token}` (public) OR `/reseller/{id}/programs/{programId}/accept-invite`
2. Referrer lands on invite page showing: program name, company, offer summary, contract (if required)
3. Referrer clicks [Accept] → membership status changes from `invited` to `active`
4. Referrer is redirected to the Program Detail page
5. Activity log entry created (with `program_id`, `user_id` = null or staff invite sender, `metadata.reseller_id`)

---

## Authorization Summary

| Action | Allowed when |
|--------|-------------|
| View Programs list | `programs_v4` flag on + reseller has any membership |
| View Program detail | Active or suspended membership in that program |
| View program deals | Active membership + deals belong to this reseller |
| Accept invite | Membership status = `invited` + token valid + not expired |
| Withdraw | Membership status = `active` (Phase 2) |
| Submit deal | Active membership + program status = `active` |
