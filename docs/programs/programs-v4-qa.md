<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — QA Checklists

All checklists must be executed before Phase 4 (pilot) goes live. Mark each item Pass / Fail / N/A.

---

## 1. Admin Program CRUD

### Create
- [ ] Admin can navigate to `/tenant/{id}/programs` and see Programs section in sidebar
- [ ] [+ New Program] button opens create form
- [ ] Form validates: Name is required
- [ ] Form validates: Slug is required and unique per tenant (not globally)
- [ ] Slug is auto-generated from Name (kebab-case)
- [ ] Slug with invalid characters is rejected (`^[a-z0-9-]+$`)
- [ ] Visibility selector shows: Private, Invite Only, Public
- [ ] Start Date / End Date are optional
- [ ] Saving creates a Program with `status = draft`
- [ ] New program appears in the Programs list immediately
- [ ] Activity log entry created: `program.created`, `user_id` = current staff user, `program_id` set

### Read
- [ ] Programs index lists all programs for the current tenant
- [ ] Programs index does NOT show programs from other tenants
- [ ] Status badge correctly shows: Draft, Active, Paused, Ended, Archived
- [ ] Clicking a program opens the workspace with Overview tab
- [ ] All 11 tabs are present (implemented tabs show content; unimplemented show "Coming Soon")

### Update
- [ ] Admin can edit program Name, Description, Visibility
- [ ] Slug can be changed on a `draft` program without warning
- [ ] Slug change on an `active` program shows warning: "Changing slug will break existing links"
- [ ] Locked program (LGU IDS): all fields are read-only; no save button
- [ ] Config version snapshot created in `program_configuration_versions` on save

### Delete / Archive
- [ ] Admin can archive a program (status → `archived`)
- [ ] Archived program no longer appears in the active Programs list (filtered by default)
- [ ] Archived programs are accessible via "Show Archived" filter
- [ ] Soft-deleted programs (via destroy action) are removed from list; DB row has `deleted_at`
- [ ] Cannot delete a program that has active deals (Phase 1: warn user; do not hard delete)

---

## 2. Referrer Portal Program Views

### Programs List
- [ ] Referrer sees "My Programs" in sidebar only when flag is ON and they have memberships
- [ ] "My Programs" sidebar item is hidden when flag is OFF
- [ ] "My Programs" sidebar item is hidden when referrer has no memberships
- [ ] Programs list shows only programs where referrer has a membership
- [ ] Programs from other tenants are NOT visible
- [ ] Pending invites section shows programs with `membership.status = invited`
- [ ] Active Programs section shows programs with `membership.status = active`
- [ ] Past Programs section shows programs with `status = ended`

### Program Detail
- [ ] Referrer can view program detail only for programs they have membership in
- [ ] Referrer cannot access program detail for a program they have no membership in (→ 404)
- [ ] Program detail shows: name, company, description, status, enrolled date
- [ ] Quick stats show this referrer's deal count and commission (not all referrers' totals)
- [ ] [Submit New Deal] is visible and enabled when program is active
- [ ] [Submit New Deal] is disabled/hidden when program is paused or ended
- [ ] Paused program shows paused notice to referrer

### Accept Invite (Phase 2)
- [ ] Pending invite shows [Accept] and [Decline] buttons
- [ ] Accepting invite changes membership status to `active`
- [ ] Accepted invite removes from Pending Invites section; appears in Active Programs
- [ ] Declined invite: membership removed (or withdrawn status)
- [ ] Expired invite shows expiry message, no Accept button

---

## 3. Partner Portal Program Views

### Programs List
- [ ] Partner sees "My Programs" in sidebar only when flag is ON and they have deal activity in programs
- [ ] "My Programs" is hidden when partner has no `partner_program_memberships` rows
- [ ] Programs list shows only programs where partner has deal activity
- [ ] Programs from other tenants are NOT visible

### Program Detail
- [ ] Partner can view program detail only for programs where they have deal activity
- [ ] Partner cannot access program detail for a program where they have no deals (→ 404)
- [ ] Program detail shows: name, company, description, status
- [ ] Stats show this partner's deals only (not all partners' totals)
- [ ] Deal list on detail page shows only this partner's deals in this program

### Deal List
- [ ] Deal list shows only deals associated with current partner + current program
- [ ] Deal from another partner in the same program is NOT visible
- [ ] Deal value and commission shown correctly (partner's share only)
- [ ] Deal detail breadcrumb shows program context

---

## 4. Public Program Pages (Phase 2)

### Landing Page `/p/{slug}`
- [ ] `status=draft` → 404 (no leak of program existence)
- [ ] `status=scheduled` → Upcoming page with countdown
- [ ] `status=active` + `visibility=public` → Live landing page with CTA
- [ ] `status=active` + `visibility=invite_only` → 404
- [ ] `status=active` + `visibility=private` → 404
- [ ] `status=paused` → Landing page with paused banner; Apply button hidden
- [ ] `status=ended` → Ended state page; Apply button hidden
- [ ] `status=archived` → 404 (no leak)
- [ ] SEO: active programs have `robots: index, follow`
- [ ] SEO: draft/archived programs have `robots: noindex, nofollow`

### Apply Page `/p/{slug}/apply`
- [ ] Apply page only accessible when program is `active` and `visibility=public`
- [ ] Any other state redirects to `/p/{slug}`
- [ ] Form requires: First Name, Last Name, Email
- [ ] Form validates email format
- [ ] Duplicate email submission: handled gracefully (link to existing reseller or show message)
- [ ] Rate limiting: 5 submissions per IP per hour
- [ ] Successful submission shows confirmation message
- [ ] Admin receives notification of new application

### Invite Page `/p/{slug}/invite/{token}`
- [ ] Valid token shows invitation page with program info and CTA
- [ ] Expired token shows expiry message
- [ ] Already-accepted token shows "already accepted" message with portal link
- [ ] Invalid token → 404 (no leak)
- [ ] Accepting invite updates membership status to `active`
- [ ] Token is single-use: second attempt after acceptance shows "already accepted"

---

## 5. LGU IDS Regression

These tests must all pass BEFORE any production migration runs.

### Pre-migration baseline (capture before running migration)
- [ ] Record current pipeline stages for LGU IDS: Introduction, Presentation Done, Contract Sent, Signed, Paid
- [ ] Record commission calculation for 3 known test deals (Deal Value, Base Cost, Added Amount, Company Share, Commission Pool)
- [ ] Record count of leads for LGU IDS tenant
- [ ] Record active notifications and emails working

### Post-schema-migration (after `program_id` column added, before data migration)
- [ ] All LGU IDS leads still load correctly (`program_id = NULL` is fine at this stage)
- [ ] Pipeline stages unchanged
- [ ] Commission calculations unchanged
- [ ] No FK errors on lead creation/update

### Post-data-migration (after `programs:migrate-lgu-ids` runs)
- [ ] LGU IDS has exactly 1 Program in `programs` table
- [ ] That Program has: `is_locked=1`, `pipeline_locked=1`, `rewards_locked=1`, `import_locked=1`
- [ ] All LGU IDS leads have `program_id` set (none are NULL)
- [ ] Pipeline stages still exactly: Introduction, Presentation Done, Contract Sent, Signed, Paid
- [ ] Commission calculation for the 3 baseline deals is identical to pre-migration values
- [ ] Lead count unchanged
- [ ] Import functionality works
- [ ] Notifications and emails still fire

### V4 UI isolation (LGU IDS flag OFF)
- [ ] LGU IDS admin cannot see "Programs" in sidebar
- [ ] Attempting to access `/tenant/{lguId}/programs` returns 404 (flag off = 404)
- [ ] V3 "Referral Program" settings page still accessible for LGU IDS
- [ ] No V4-specific UI elements visible anywhere in LGU IDS admin

---

## 6. Permission Enforcement

### Tenant isolation
- [ ] Staff user for Tenant A cannot access Tenant B's programs (403/404)
- [ ] Attempting `/tenant/{B_id}/programs` while authed as Tenant A staff → 403
- [ ] `Program::findOrFail(id)` without tenant scope: should not exist in codebase (grep check)

### Guard isolation
- [ ] Reseller (reseller guard) cannot access admin program routes (→ 401/403)
- [ ] Partner (partner guard) cannot access admin program routes (→ 401/403)
- [ ] Unauthenticated user cannot access admin or portal program routes (→ redirect to login)
- [ ] Reseller cannot access another reseller's program detail in portal (→ 404)
- [ ] Partner cannot access another partner's program detail in portal (→ 404)

### Locked programs
- [ ] Any attempt to POST/PUT/DELETE a locked program's config → 403
- [ ] Lifecycle actions (launch/pause/end) on locked programs → 403
- [ ] Referrer enrollment on locked programs: allowed (enrollment itself is not locked)

### Feature flag
- [ ] `programs_v4 = false`: all V4 sidebar items hidden
- [ ] `programs_v4 = false`: direct URL access to V4 routes → 404
- [ ] `programs_v4 = false`: V3 wizard still accessible
- [ ] `programs_v4 = true`: V4 UI visible; V3 wizard still accessible

---

## 7. Portal Parity

For each scenario, verify the admin view and the corresponding portal view are consistent:

- [ ] Admin enrolls referrer → referrer sees program in portal within the same request cycle
- [ ] Admin pauses program → referrer portal shows paused notice on next page load
- [ ] Admin ends program → program moves to "Past Programs" in referrer portal
- [ ] Admin archives program → program disappears from referrer portal (404)
- [ ] Deal submitted by referrer → appears in admin Deals tab for the program
- [ ] Deal submitted by referrer → commission shown correctly in both admin and referrer views
- [ ] Partner deal created with `program_id` → program appears in partner portal

---

## 8. Notification Deep Links

- [ ] `program.referrer_enrolled` notification → link routes to correct portal page for that referrer
- [ ] `program.launched` notification → admin link goes to program workspace
- [ ] Deal created in program → notification link goes to program-scoped deal detail (admin, referrer, partner versions)
- [ ] Program archived: deep link in old notification → friendly "no longer available" page (not bare 404)
- [ ] Membership removed: old deep link to program detail → 404 handled gracefully

---

## 9. Feature Flag Behavior

- [ ] Turning flag OFF for a tenant with existing Programs: Programs data preserved in DB; UI hidden
- [ ] Turning flag ON for a tenant with no Programs: empty state shown; create CTA visible
- [ ] Turning flag ON for a tenant with existing Programs: all programs appear correctly
- [ ] Flag toggle takes effect without requiring a server restart (config-based, not code-based)
- [ ] Flag does not affect V3 wizard behavior in either direction

---

## 10. Activity Logging

- [ ] `program.created`: `user_id` = staff user bigint, `program_id` set, no reseller/partner ID in `user_id`
- [ ] `program.launched`: `user_id` = staff user, `program_id` set
- [ ] `program.referrer_enrolled`: `user_id` = staff user who enrolled, `metadata.reseller_id` = reseller UUID
- [ ] `program.partner_enrolled`: `user_id` = null (system event), `metadata.partner_id` = partner UUID
- [ ] `program.referrer_portal_viewed`: `user_id` = null, `metadata.reseller_id` set
- [ ] No activity_log row has a non-bigint UUID value in `user_id` column (SELECT type check)
- [ ] `activity_logs.program_id` is populated for all V4 events
