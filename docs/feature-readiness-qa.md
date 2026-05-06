# ReferralBunny.ai — Feature Readiness QA Standard

**Permanent rule. Applies to every command, update, bug fix, feature, screen, or deployment.**

Before any implementation is considered complete, the developer must run through the following checklist. This is not optional.

---

## 1. Userflow Completeness

- [ ] All affected user types are identified (Super Admin, Tenant Owner, Tenant Admin, Tenant Manager, Tenant Staff, Referrer, Partner)
- [ ] Each user type has a complete end-to-end flow
- [ ] Entry points are clear (how does the user get here?)
- [ ] Exit points are clear (where does the user go after?)
- [ ] Empty state exists and is helpful
- [ ] Loading state exists and is non-blocking
- [ ] Error state exists and is actionable
- [ ] Permission-blocked state exists and is safe
- [ ] Success state is clear
- [ ] Failure state is clear
- [ ] Direct URL access is handled safely (not just hidden in UI)
- [ ] Wrong-role access returns 403 or 404 safely

---

## 2. Role & Permission Correctness

- [ ] Super Admin: platform-wide access is explicit and audited
- [ ] Tenant Owner: full tenant permissions
- [ ] Tenant Admin: full operational permissions
- [ ] Tenant Manager: operational permissions, no billing/deletion unless ticked
- [ ] Tenant Staff: limited operational permissions
- [ ] Referrer: Referrer portal rules enforced, cannot access Tenant Admin panel
- [ ] Partner: deal-scoped only, cannot import/export/message outside deal context
- [ ] LGU IDS locked rules are not changed
- [ ] Backend policies enforce all restrictions (not just UI hiding)
- [ ] Locked permissions cannot be toggled even via API or form manipulation

---

## 3. Tenant Isolation

- [ ] All DB queries include tenant_id (from auth context, never from user input)
- [ ] Route model binding is safe (tenant-scoped findOrFail, not global findOrFail)
- [ ] Imports, exports, files, messages, notifications, reports, cache all tenant-scoped
- [ ] No tenant can see another tenant's data in any format
- [ ] Super Admin platform access is explicit, not accidental
- [ ] Search and autocomplete return only current-tenant results
- [ ] Cross-tenant access returns 404 (not 403, to avoid revealing existence)
- [ ] TenantContext service used — tenant_id never from `$request->tenant_id`

---

## 4. UI/UX Best Practice

- [ ] Screen follows existing ReferralBunny.ai design system
- [ ] No unnecessary redesign; existing layout preserved
- [ ] Primary actions are prominent; secondary actions are subdued
- [ ] Labels are clear and use correct terminology (Referrer not Reseller, in UI)
- [ ] Tables/lists use pagination or efficient loading for large datasets
- [ ] Filters and search are present for long lists
- [ ] Empty states use R Bunny `sleeping` mascot where appropriate
- [ ] Error states use R Bunny `warning` mascot where appropriate
- [ ] Loading states use spinner or R Bunny `tech` mascot
- [ ] Success states use R Bunny `celebration` or `thumbsup` where appropriate
- [ ] R Bunny is only used when it adds emotional or functional value
- [ ] Correct logo variant is used for the context (white on dark, primary on light)
- [ ] No hardcoded "reseller" terminology visible to end users (use "Referrer")

---

## 5. Responsiveness

- [ ] Desktop (1440px+): full two-column or multi-panel layout
- [ ] Laptop (1024–1366px): same as desktop or gracefully adapted
- [ ] Tablet (768–1023px): split view or single-pane as appropriate
- [ ] Mobile (375–767px): single-pane, back navigation, stacked layout
- [ ] Small mobile (320–374px): text truncates, buttons remain tappable
- [ ] No horizontal scroll outside intentional containers
- [ ] Message bubbles/text bubbles have `break-words` and max-width
- [ ] Modals fit the screen and don't clip on small sizes
- [ ] Compose/reply area sticks to bottom without overlapping content
- [ ] Filters collapse or stack correctly on small screens
- [ ] Unread badges and counts display correctly at all sizes

---

## 6. Accessibility Basics

- [ ] Buttons have readable labels or `title` attributes
- [ ] Inputs have associated `label` elements
- [ ] Color is not the only indicator of state (use text labels too)
- [ ] Focus states are preserved for keyboard navigation
- [ ] Error messages are visible, not just color-coded
- [ ] Text contrast is readable on all backgrounds
- [ ] `aria-hidden="true"` on decorative images; meaningful alt text on informational images

---

## 7. Performance

- [ ] Long lists use server-side pagination (not client-side full-dataset load)
- [ ] Heavy imports/exports use queued jobs
- [ ] Search inputs are debounced (250–400ms)
- [ ] Dashboard metrics avoid N+1 queries
- [ ] Cache keys include `tenant:{id}:` prefix for tenant data
- [ ] Large files and attachments stream, not block UI
- [ ] No unnecessary re-renders on every keypress

---

## 8. Notifications & Alerts

- [ ] In-app notifications triggered for relevant actions
- [ ] Email notifications respect notification preferences
- [ ] Browser push notifications are tenant-scoped
- [ ] Push payloads do not expose: full message body, private deal amounts, anonymous Referrer identity, another tenant's data
- [ ] R Bunny AI Dialog reminders are helpful, dismissible, and not repeated excessively
- [ ] Notification deduplication key prevents duplicate alerts
- [ ] Notifications fire for: preview ready, completion, warnings, failures, review needed, invite accepted

---

## 9. Audit & Logging

- [ ] Sensitive actions are audit-logged (role changes, billing, imports, deletions)
- [ ] Permission changes include old and new values
- [ ] Billing/subscription changes are audit-logged
- [ ] Import/export batch actions are audit-logged
- [ ] Super Admin tenant data access is audit-logged
- [ ] Failed permission attempts are logged (not just blocked silently)
- [ ] Log includes: tenant_id, actor_id, actor_role, action, resource_type, resource_id, timestamp

---

## 10. Regression Check

- [ ] Existing Deals, Contacts, Organizations, Referrers, Messages, Reports, Imports tabs still work
- [ ] Existing filters, search, sort still function
- [ ] Existing imports/exports still function (including LGU IDS locked import)
- [ ] Existing notifications still trigger correctly
- [ ] Existing LGU IDS pricing, commission, stage, expiry, and Referrer rules are unchanged
- [ ] Existing Referrer portal flows still work
- [ ] Existing Partner limited-access flows still work
- [ ] Existing auth flows (login, setup, invite) still work
- [ ] Existing mobile responsive fixes are not regressed

---

## Terminology Reference

Always use these terms in UI:

| System term | UI label |
|---|---|
| `reseller` | Referrer |
| `partner` | Partner (separate from Referrer) |
| `tenant_manager` | Tenant Manager |
| `tenant_admin` | Tenant Admin |
| `tenant_user` | Team Member / Tenant Staff |

Never show "reseller" in any user-facing label, button, placeholder, or modal copy.

---

## Asset Locations (R Bunny + Logo)

Mascots: `/public/images/mascots/r-bunny-{variant}.webp`
Logos: `/public/images/logos/referralbunny-{variant}.webp`

Blade: `<x-r-bunny variant="sleeping" size="md" :decorative="true" />`
Fallback: `<img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-16 h-16 object-contain" loading="lazy">`

See full asset manifest in developer memory.

---

*This document is a permanent development standard for ReferralBunny.ai. Every future command, update, screen, or deployment must complete this QA pass before being considered done.*
