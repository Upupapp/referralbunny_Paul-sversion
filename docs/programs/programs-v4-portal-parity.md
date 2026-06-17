<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Portal Parity Contract

## Definition

Portal parity means: for every feature visible to an admin, the referrer and partner portals show a corresponding accurate view of that data. This document defines the contract for Phase 1 features.

---

## Parity Contract: Program List

**Feature:** Viewing all Programs

| Portal | Route | What is shown | Phase |
|--------|-------|---------------|-------|
| Admin | `/tenant/{id}/programs` | All programs for this tenant | 1 |
| Referrer | `/reseller/{id}/programs` | Programs where referrer has membership | 1 |
| Partner | `/partner/programs` | Programs where partner has deal activity | 1 |
| Public | N/A — no "list all programs" public page | — | — |

**States:**

| State | Admin | Referrer | Partner |
|-------|-------|---------|---------|
| Empty (no programs) | Empty state + Create CTA | "No programs yet" message | "No programs yet" message |
| Loading | Skeleton rows | Skeleton rows | Skeleton rows |
| Success | Program list table/grid | Program cards | Program cards |
| Feature flag off | Section hidden | Section hidden | Section hidden |
| Error (5xx) | Error banner + retry | Error banner | Error banner |

---

## Parity Contract: Program Detail

**Feature:** Viewing a single Program

| Portal | Route | What is shown | Phase |
|--------|-------|---------------|-------|
| Admin | `/tenant/{id}/programs/{programId}` | Full workspace with all tabs | 1 |
| Referrer | `/reseller/{id}/programs/{programId}` | Program detail + their deals | 1 |
| Partner | `/partner/programs/{programId}` | Program detail + their deals | 1 |
| Public | `/p/{slug}` | Public landing page | 2 |

**States:**

| State | Admin | Referrer | Partner | Public |
|-------|-------|---------|---------|--------|
| Empty (no deals yet) | Empty state in Deals tab | "No deals yet" message | "No deals yet" message | N/A |
| Loading | Tab skeleton | Page skeleton | Page skeleton | Page skeleton |
| Success | Full workspace | Program detail page | Program detail page | Landing page |
| Blocked (no membership) | N/A (admin can see all) | 404 | 404 | 404 if not public |
| Program not found | 404 | 404 | 404 | 404 |
| Program archived | Admin can see (read-only) | 404 | 404 | 404 |
| Locked (LGU IDS) | Read-only + lock badges | No change (normal view) | No change (normal view) | N/A |

---

## Parity Contract: Deal List (Program-Scoped)

**Feature:** Deals within a Program

| Portal | Route | Scope | Phase |
|--------|-------|-------|-------|
| Admin | `/tenant/{id}/programs/{programId}/deals` | All deals in program | 1 |
| Referrer | `/reseller/{id}/programs/{programId}/deals` | Only referrer's deals | 1 |
| Partner | `/partner/programs/{programId}/deals` | Only partner's deals | 1 |

**States:**

| State | Admin | Referrer | Partner |
|-------|-------|---------|---------|
| Empty | "No deals in this program" | "No deals submitted yet" | "No deals in this program" |
| Loading | Table skeleton | Table skeleton | Table skeleton |
| Success | Full deal table with all columns | Filtered deal table | Filtered deal table |
| Expired program | Deals visible (historical) | Historical deals visible | Historical deals visible |
| Historical (ended) | Deals visible | Deals visible (read-only) | Deals visible (read-only) |
| Failure (DB error) | Error banner | Error banner | Error banner |

**Column parity:**

| Column | Admin | Referrer | Partner |
|--------|-------|---------|---------|
| Contact Name | Yes | Yes | Yes |
| Organization | Yes | Yes | Yes |
| Stage | Yes | Yes | Yes |
| Deal Value | Yes | Yes | Yes |
| Referrer Name | Yes | No (it's them) | Yes |
| Partner Name | Yes | Yes | No (it's them) |
| Commission | Yes (full split) | Yes (their share only) | Yes (their share only) |
| Date | Yes | Yes | Yes |
| Actions | Full (edit, delete) | View only | View only |

---

## Parity Contract: Referrer Enrollment

**Feature:** Managing referrer membership in a Program

| Portal | Action | Phase |
|--------|--------|-------|
| Admin | Enroll referrer, remove, suspend | 1 |
| Referrer | See enrollment status, accept invite | 1 / 2 |
| Partner | No enrollment concept | N/A |

**States:**

| State | Admin view | Referrer view |
|-------|-----------|--------------|
| Not enrolled | Enroll button visible | Program not visible in their list |
| Invited | "Invited" badge, Resend/Cancel options | "Pending Invite" section with Accept/Decline |
| Invite expired | "Expired" badge, Resend option | "Invitation expired" message |
| Active | "Active" badge, Suspend/Remove options | Program visible in active list |
| Suspended | "Suspended" badge, Reactivate option | Program visible but deals blocked |
| Withdrawn | "Withdrawn" badge, Re-enroll option | Program not visible in active list |

---

## Parity Contract: Commission Display

**Feature:** Commission data

| Portal | What is shown | Phase |
|--------|---------------|-------|
| Admin | Full split: company share + referrer commission per deal | 1 |
| Referrer | Their commission portion only (70% of Added Amount) | 1 |
| Partner | Their commission portion (if applicable) | 1 |
| Public | Offer summary only (e.g., "Earn X% on each deal") | 2 |

Commission formula (global Phase 1):
- Company Share = Added Amount × 30%
- Commission Pool = Added Amount × 70%

No per-program rate overrides in Phase 1.

---

## Parity Contract: Program Status Visibility

| Status | Admin sees | Referrer sees | Partner sees | Public |
|--------|-----------|--------------|-------------|--------|
| `draft` | Full program | Hidden | Hidden | 404 |
| `scheduled` | Full program | If invited: upcoming notice | If has deals: visible | Upcoming page |
| `active` | Full program | Program visible | Program visible | Live landing |
| `paused` | Full program + paused badge | Program visible + paused notice | Program visible | Paused banner |
| `ended` | Full program + ended badge | Historical view | Historical view | Ended page |
| `archived` | Program visible in admin (read-only) | Hidden (404) | Hidden (404) | 404 |

---

## Notification Deep Link Parity

When a notification links to a program resource, the deep link must resolve correctly for each portal.

| Event | Admin link | Referrer link | Partner link |
|-------|-----------|--------------|-------------|
| New deal in program | `/tenant/{id}/programs/{pid}/deals/{lid}` | `/reseller/{id}/programs/{pid}/deals/{lid}` | `/partner/programs/{pid}/deals/{lid}` |
| Referrer enrolled | `/tenant/{id}/programs/{pid}/people/referrers` | `/reseller/{id}/programs/{pid}` | N/A |
| Program launched | `/tenant/{id}/programs/{pid}` | `/reseller/{id}/programs/{pid}` | N/A |

**Failure state:** If a deep link lands on a 404 (e.g., membership removed, program archived), show a friendly "This resource is no longer available" page with a link back to the portal dashboard. Do not show a bare 404.
