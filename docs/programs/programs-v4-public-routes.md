<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Public Program Routes

## Overview

Public program routes are available at `/p/{slug}` with no authentication required. These routes are part of Phase 2. The `{slug}` is unique per tenant and stored in `programs.slug`.

**Important:** Public routes are NOT gated by the `programs_v4` feature flag in middleware. However, only programs with `visibility = public` are reachable via these routes. `invite_only` and `private` programs return 404 on public routes even if the slug exists.

---

## Route Definitions

| Method | Path | Route Name | Phase |
|--------|------|------------|-------|
| GET | `/p/{slug}` | `public.program.show` | 2 |
| GET | `/p/{slug}/apply` | `public.program.apply` | 2 |
| POST | `/p/{slug}/apply` | `public.program.apply.submit` | 2 |
| GET | `/p/{slug}/invite/{token}` | `public.program.invite` | 2 |
| POST | `/p/{slug}/invite/{token}/accept` | `public.program.invite.accept` | 2 |

---

## Program Status → Public Route Behavior

### `draft` — Return 404
Program is not discoverable. Slug returns 404 as if the page does not exist.

```
GET /p/my-program → 404 Not Found
```

Do not leak that the program exists.

### `scheduled` — Upcoming Page
Program is configured and has a `starts_at` in the future.

```
GET /p/my-program → 200 OK
```

**Page displays:**
- Program name and description
- Company logo/branding
- "Coming Soon" banner with countdown to `starts_at`
- Optional: "Get notified" email capture (Phase 3)
- Apply button: HIDDEN or shows "Applications open on [date]"

### `active` — Live Landing Page
Program is open for referrers to apply or join.

```
GET /p/my-program → 200 OK
```

**Page displays:**
- Program name, description, hero image/branding
- Commission offer summary (from active `ProgramOffer`)
- How it works (from program metadata)
- [Apply to Join] / [Get Started] CTA button → `/p/{slug}/apply`
- Testimonials (Phase 3)

### `paused` — Paused Banner
Program is temporarily not accepting new referrers/deals.

```
GET /p/my-program → 200 OK
```

**Page displays:**
- Program landing page with reduced CTA
- Yellow banner: "This program is temporarily paused. We're not accepting new applications right now. Check back soon."
- Apply button: HIDDEN

### `ended` — Ended State
Program has concluded. Historical landing page preserved.

```
GET /p/my-program → 200 OK
```

**Page displays:**
- Program name and description
- Gray banner: "This program has ended. Thank you to all our referral partners."
- Apply button: HIDDEN
- Optional: Link to other active programs by same company (if any are public)

### `archived` — Gone (404)
Archived programs are no longer publicly accessible.

```
GET /p/my-program → 404 Not Found
```

Do not leak existence.

---

## Apply Page — `/p/{slug}/apply`

**Phase 2**

Available only when program status = `active` and `visibility = public`.

Any other status → redirect to `/p/{slug}` (which will show appropriate state).

**Form fields:**
- First Name *
- Last Name *
- Email *
- Phone
- Company/Organization
- How do you know this company? (textarea)
- Custom fields (from program metadata — Phase 3)

**On submit:**
1. Validate fields
2. Check if email matches an existing `resellers` record for this tenant
   - Yes: link to existing reseller, create `referrer_program_memberships` with status = `invited` (requires admin approval) or `active` (if auto-approve enabled)
   - No: create a new `resellers` record + membership
3. Send confirmation email to applicant
4. Send notification to admin
5. Redirect to success page: "Application received! We'll be in touch shortly."

**Rate limiting:** 5 applications per IP per hour.

---

## Invite Page — `/p/{slug}/invite/{token}`

**Phase 2**

Used when an admin enrolls a referrer via invite link (creates a `referrer_program_memberships` row with `status = invited` and a `invite_token`).

**Token resolution:**
1. Look up `referrer_program_memberships` by `invite_token = {token}`
2. Verify token is not expired (`invite_expires_at > now()`)
3. Verify membership belongs to the program matching `{slug}`
4. If any check fails → 404 (do not reveal token exists but is wrong)

**Page displays (valid token):**
- "You've been invited to join [Program Name]"
- Company name + branding
- Commission offer summary
- Contract to review + e-sign (if `program_contracts` configured)
- [Accept Invitation] button
- [Decline] link

**On accept:**
1. Update `referrer_program_memberships.status` = `active`, `enrolled_at` = now()
2. Nullify `invite_token` (single-use)
3. Log activity (`program.referrer_enrolled`)
4. Redirect referrer to their portal: `/reseller/{id}/programs/{programId}`

**Token states:**

| State | Behavior |
|-------|---------|
| Valid + not expired | Show invitation page |
| Expired | "This invitation has expired. Please contact [company] to request a new one." |
| Already accepted | "You've already accepted this invitation. View your program here." + link |
| Invalid / not found | 404 |

---

## SEO and Meta Tags

For active/scheduled public programs:

```html
<meta property="og:title" content="[Program Name] — Referral Program">
<meta property="og:description" content="[programs.description]">
<meta property="og:url" content="https://[domain]/p/{slug}">
<meta name="robots" content="index, follow">  <!-- active only -->
```

For draft/archived/ended:
```html
<meta name="robots" content="noindex, nofollow">
```

---

## Slug Uniqueness

- Slug is unique per tenant (not globally unique)
- Slug is auto-generated from program name on creation (kebab-case, max 100 chars)
- Admin can customize slug in program settings
- Once a program is `active`, slug changes require confirmation warning ("Changing the slug will break existing links")
- Slug must match `^[a-z0-9-]+$`
