<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — User Flows

---

## UF-01: Admin Creates a Program from Scratch

**Actor:** Staff user (web guard)
**Preconditions:** `programs_v4` flag enabled for this tenant; user has permission to manage programs

**Flow:**

```
1. Staff user navigates to /tenant/{id}/programs
2. Clicks [+ New Program]
3. Lands on /tenant/{id}/programs/create
   Form fields:
   - Program Name * (auto-generates slug)
   - Slug (editable, validated unique per tenant)
   - Description (optional)
   - Visibility: Private / Invite Only / Public
   - Program Group (optional, Phase 2)
   - Start Date (optional)
   - End Date (optional)

4. Clicks [Create Program]
5. Server:
   a. Validates input
   b. Creates programs record (status = 'draft', is_default = false)
   c. Creates ProgramConfigurationVersion snapshot
   d. Logs activity: program.created (user_id = staff user, program_id = new program)
   e. Redirects to /tenant/{id}/programs/{newId}/overview

6. Overview tab shows:
   - Status badge: DRAFT
   - Quick start checklist (Phase 2 — links to Setup tabs)
   - [Launch Program] CTA button (prominent)
```

**Error states:**
- Slug already taken → inline validation error on slug field
- Name missing → required field error
- Permission denied → 403

---

## UF-02: Admin Launches a Program

**Actor:** Staff user (web guard)
**Preconditions:** Program exists with `status = draft`; user has manage permission

**Flow:**

```
1. Staff user is on program Overview tab
2. Sees [Launch Program] button
3. Clicks [Launch Program]
4. Confirmation modal appears:
   "Launch [Program Name]? This will make the program active.
   [Cancel] [Launch]"
5. Clicks [Launch]
6. Server:
   a. Checks program.is_locked → if true, 403
   b. Updates programs.status = 'active', launched_at = now()
   c. Logs activity: program.launched (user_id = staff, program_id)
   d. Fires ProgramLaunched event (triggers notifications if configured — Phase 2)
   e. Returns success response
7. UI updates: status badge changes to ACTIVE
8. [Launch Program] button replaced with [Pause Program] + [End Program] buttons

Alternate: Program has starts_at in future
   a. Status set to 'scheduled' instead of 'active'
   b. A scheduled job will flip to 'active' at starts_at
```

**Error states:**
- Program already active → 409 / toast: "Program is already active"
- Locked program (LGU IDS) → 403 modal: "This program is locked and cannot be modified"

---

## UF-03: Referrer Views Their Programs

**Actor:** Referrer/Reseller (reseller guard)
**Preconditions:** `programs_v4` enabled for tenant; referrer has active membership in at least one program

**Flow:**

```
1. Referrer logs into portal at /reseller/{id}/dashboard
2. Sees "My Programs" in sidebar (visible because they have memberships)
3. Clicks "My Programs"
4. Lands on /reseller/{id}/programs
5. Page shows:
   - Section: "Pending Invites" (if any invited memberships)
   - Section: "Active Programs" (active memberships)
   - Section: "Past Programs" (ended programs)

6. Referrer clicks on a program name
7. Lands on /reseller/{id}/programs/{programId}
8. Sees:
   - Program header (name, company, status)
   - Quick stats (my deals, my commissions)
   - My Recent Deals table (last 5)
   - [Submit New Deal] button (if program is active)

9. Referrer clicks [Submit New Deal]
10. Existing deal submission form opens, pre-populated:
    - program_id = current program (hidden field or locked selector)
11. Referrer fills in contact/deal info and submits
12. Lead created with program_id set
13. partner_program_memberships updated if deal has a partner_id
```

**Edge cases:**
- Referrer has no memberships → "My Programs" not in sidebar
- Program is paused → [Submit New Deal] disabled; paused notice shown
- Referrer's membership is suspended → program visible but [Submit New Deal] disabled with "Your membership is currently suspended"

---

## UF-04: Partner Views Program Deals

**Actor:** Partner (partner guard)
**Preconditions:** `programs_v4` enabled; partner has deal activity in at least one program

**Flow:**

```
1. Partner logs into portal at /partner/dashboard
2. Sees "My Programs" in sidebar
3. Clicks "My Programs"
4. Lands on /partner/programs
5. Sees programs where they have deal activity:
   - Program Name, Company, Status, Deal Count, Total Value

6. Partner clicks on a program
7. Lands on /partner/programs/{programId}
8. Sees:
   - Program header
   - Deal stats for this partner in this program
   - Recent deals table

9. Partner clicks [View All Deals]
10. Lands on /partner/programs/{programId}/deals
11. Full filterable deal table
12. Partner clicks on a deal row
13. Lands on /partner/programs/{programId}/deals/{leadId}
14. Deal detail page with program context in breadcrumb
```

**Edge cases:**
- Partner has no program deals → "My Programs" not in sidebar; dashboard shows no programs section
- Program ended → still visible in "Past Programs" section; deals visible read-only

---

## UF-05: Admin Enrolls a Referrer in a Program

**Actor:** Staff user (web guard)
**Preconditions:** Program exists and is active or draft; referrer exists in system

**Flow:**

```
1. Staff user navigates to /tenant/{id}/programs/{programId}/people/referrers
2. Sees list of enrolled referrers (may be empty)
3. Clicks [Enroll Referrer]
4. Modal opens with search field: "Search referrers..."
5. Staff types referrer name or email
6. Search results show matching resellers for this tenant
7. Staff selects a referrer
8. Options shown:
   - Enroll immediately (status = active)
   - Send invitation (status = invited, generates invite link)
   - Start date (optional)
9. Clicks [Enroll] or [Send Invitation]

If "Enroll immediately":
   a. Creates referrer_program_memberships row (status = active, enrolled_at = now())
   b. Logs activity: program.referrer_enrolled
   c. Sends notification to referrer (Phase 2)
   d. Referrer can now see this program in their portal immediately

If "Send invitation":
   a. Creates referrer_program_memberships row (status = invited, invite_token = uuid, invite_expires_at = now() + 7 days)
   b. Logs activity: program.referrer_invited
   c. Sends invitation email with link /p/{slug}/invite/{token}
   d. Referrer sees "Pending Invites" section in their portal

10. Modal closes; referrer appears in the list with appropriate status badge
```

**Error states:**
- Referrer already enrolled → "This referrer is already enrolled in this program"
- Referrer already invited → "This referrer already has a pending invitation" + option to resend
- Locked program → enroll action available but referrer is added without being able to change program config

---

## UF-06: Program Lifecycle Transition

**Actor:** Staff user (web guard)
**Preconditions:** Program exists

**Flow (full lifecycle):**

```
draft → active (launch)
   Server: status = 'active', launched_at = now()
   Event: ProgramLaunched
   Notification: enrolled referrers notified (Phase 2)

active → paused
   Server: status = 'paused'
   Event: ProgramPaused
   Effect: Public page shows paused banner; new deal submissions disabled

paused → active (resume)
   Server: status = 'active'
   Event: ProgramResumed
   Effect: Public page live again; deal submissions re-enabled

active/paused → ended
   Server: status = 'ended', ended_at = now()
   Event: ProgramEnded
   Effect: Public page shows ended state; all new activity disabled

ended → archived
   Server: status = 'archived', archived_at = now()
   Event: ProgramArchived
   Effect: Public page 404; program hidden from referrer/partner portals

draft → archived (skip lifecycle)
   Allowed for programs that were never launched
```

**Blocked transitions:**

| From | To | Blocked? | Reason |
|------|----|----------|--------|
| archived | any | Yes | Archived is terminal |
| ended | active | Yes | Cannot reopen an ended program; create a new one |
| any | any | Yes if locked | LGU IDS programs cannot change status via V4 UI |

**Confirmation prompts:**

| Transition | Prompt |
|------------|--------|
| active → ended | "End this program? This cannot be undone. All active deal submissions will be frozen." |
| ended → archived | "Archive this program? It will no longer be visible to referrers or partners." |
| any other | "Are you sure?" generic confirmation |
