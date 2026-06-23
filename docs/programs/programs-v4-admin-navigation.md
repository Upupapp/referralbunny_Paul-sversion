<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Admin Navigation

## Tenant Sidebar Navigation

The Programs section appears in the tenant sidebar when `programs_v4` feature flag is enabled for that tenant.

```
Tenant Sidebar
├── Dashboard
├── Deals (Leads)
├── Contacts
├── Organizations
├── Referrers
├── Partners
├── [Programs]  ← V4 new section (feature-flagged)
│   ├── All Programs
│   └── Program Groups
├── Reports
├── Settings
│   ├── Referral Program (V3 wizard — always visible)
│   └── ...
└── ...
```

**Implementation note:** The V3 "Referral Program" settings page stays in Settings. The V4 Programs section is additive — it does not replace the Settings entry.

---

## Program Switcher

When a staff user is inside a Program workspace, a Program Switcher appears at the top of the content area (not in the sidebar).

**Switcher behavior:**
- Displays: `[Program Name] ▾`
- Clicking opens a dropdown of all active Programs for this tenant
- Includes "All Programs" link back to the Programs index
- If only one Program exists, switcher is still shown (for future growth)
- Disabled/hidden if `programs_v4` flag is off

**Switcher state:**
| State | Display |
|-------|---------|
| Single program | Shows program name; dropdown has "All Programs" + current only |
| Multiple programs | Full list sorted by name |
| No programs | Hidden; user sees empty Programs index |
| Loading | Skeleton placeholder (same width) |

---

## Program Workspace — 11-Tab Structure

When viewing a specific Program at `/tenant/{tenantId}/programs/{programId}`, the workspace shows:

```
[Program Switcher: "Program Name ▾"]

Tab Bar:
Overview | Setup | People | Deals | Activity | Commissions | Offers | Contracts | Notifications | Performance | Settings
```

| # | Tab | Route Name | Phase | Notes |
|---|-----|------------|-------|-------|
| 1 | Overview | `admin.programs.overview` | 1 | Summary stats, quick actions, status badge |
| 2 | Setup | `admin.programs.setup` | 2 | Full wizard, see subtabs below |
| 3 | People | `admin.programs.people` | 1 | Referrers + Partners, see subtabs below |
| 4 | Deals | `admin.programs.deals` | 1 | Leads scoped to this program |
| 5 | Activity | `admin.programs.activity` | 1 | Event feed, see subtabs below |
| 6 | Commissions | `admin.programs.commissions` | 2 | Commission splits for this program |
| 7 | Offers | `admin.programs.setup.offers` | 2 | Offer management |
| 8 | Contracts | `admin.programs.setup.contracts` | 2 | Contract templates + signed copies |
| 9 | Notifications | `admin.programs.setup.notifications` | 2 | Notification templates for this program |
| 10 | Performance | `admin.programs.performance` | 3 | Analytics, charts, conversion rates |
| 11 | Settings | `admin.programs.edit` | 1 | Basic program settings (name, slug, status, visibility) |

**Phase 1 tabs:** Overview, People, Deals, Activity, Settings (tabs 1, 3, 4, 5, 11)
**Phase 2 tabs:** Setup, Commissions, Offers, Contracts, Notifications (tabs 2, 6, 7, 8, 9)
**Phase 3 tabs:** Performance (tab 10)

Tabs not yet implemented show a "Coming Soon" placeholder with phase label.

---

## Setup Subtabs (Phase 2)

When on the Setup tab:

```
Setup
├── Details    — Program name, description, slug, visibility, dates
├── Offers     — Commission offers presented to referrers
├── Contracts  — Member agreements and e-sign settings
└── Notifications — Email/notification templates for this program
```

---

## People Subtabs (Phase 1)

When on the People tab:

```
People
├── Referrers  — List of enrolled referrers; enroll/remove actions
└── Partners   — List of partners with deals in this program
```

**Referrers subtab:**
- Table: Name, Email, Status (invited/active/suspended/withdrawn), Enrolled At, Deal Count, Commission Earned
- Actions: Enroll Referrer (search existing resellers), Remove, Suspend
- Empty state: "No referrers enrolled yet. Enroll a referrer to get started."
- Locked state (LGU IDS): table is read-only, no enroll/remove buttons

**Partners subtab:**
- Table: Name, Email, Status, First Deal Date, Active Deal Count
- Actions: View Partner (links to partner detail page)
- Read-only — partner memberships are derived from deal activity, not manually managed in Phase 1

---

## Activity Subtabs (Phase 1)

When on the Activity tab:

```
Activity
├── All Events      — Full event feed for this program (filtered by program_id in activity_logs)
├── Referrer Events — Enrollment, withdrawal, invite events
└── Deal Events     — Deal created, stage changed, signed, paid
```

---

## Performance Subtabs (Phase 3)

When on the Performance tab:

```
Performance
├── Overview        — Summary metrics (deals, revenue, commissions)
├── Referrers       — Per-referrer breakdown
├── Partners        — Per-partner breakdown
├── Funnel          — Stage conversion rates
└── Timeline        — Month-over-month trend
```

---

## Programs Index Page (`/tenant/{tenantId}/programs`)

**Layout:** Card grid or table (user preference toggle)

**Columns/cards show:**
- Program Name
- Status badge (draft/active/paused/ended/archived)
- Visibility badge (private/invite_only/public)
- Referrer count
- Active deal count
- Commission paid (MTD)
- Created date
- Quick actions: View, Edit, Launch/Pause/End, Archive

**Filters:**
- Status (all / active / draft / paused / ended / archived)
- Program Group
- Search by name

**Empty state:** "No programs yet. Create your first program to get started." + [Create Program] CTA

**Locked programs (LGU IDS):** Show lock icon badge. Edit button opens program in read-only mode with locked-field indicators.
