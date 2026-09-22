# GetHired public referrer landing — local implementation

Status: pushed and deployed directly on 2026-09-22; public page published. Release commit `d63e62d`. User approved changing GetHired's recurring reward duration from six months to one year on 2026-09-22. That approval is separate from deployment.

## Features

- Intended public route: `/gethired/referrals` (404 until explicitly published).
- Official GetHired logo, existing compressed Referral Bunny mascot, coral/navy/white design. Desktop no-scroll at 1440×900, 1366×768 and 1280×800; mobile stacks without horizontal overflow.
- Backend-driven package estimator prefers the active PHP 3,490 monthly package, then a recommended package, then the first sorted package. Rate comes from the current published offer; duration is locked at twelve months and referrals at 100. At 20%, hypothetical earnings are PHP 837,600 over twelve qualifying monthly payments for all 100 employers. This is an estimate, not recorded income or a payout promise.
- Company admin: Programs → GetHired → Public Page. Plain-text editing, preview, copy URL, publish/unpublish. Form fields and mechanics are locked. Unsupported/non-twelve-month mechanics prevent publication and invitations.
- Fixed server-side GetHired tenant/program context. Public requests reuse `ProgramReferrerInvite`, the existing mailable, activation link, membership handling and resend cooldowns. No new invitation pipeline.
- Confirmed sent, resent, existing-account and failure dialog states; inline validation; disabled controls while sending. Preview disables submissions.
- CSRF, five requests/minute and twenty/hour per IP, per-email cooldown, cache lock, honeypot and session timing checks. Existing records aren't reassigned or renamed.

## Mechanics release step

`gethired:prepare-public-landing` defaults to dry run. `--apply --actor=<verified GetHired owner/admin ID>` creates a new published twelve-month offer version and switches the offer pointer. It refuses unexpected duration/structure or ambiguous offers, and is idempotent. Old offer versions, existing customer/payment bindings and recorded rewards remain unchanged. Customers already bound to their first payment's offer keep those terms through the existing conversion engine.

For a future explicitly authorized deployment:
1. Deploy only reviewed landing changes and dependencies, directly over SSH; never GitHub Actions or Netlify.
2. Run only the new empty `program_landing_pages` migration. It does not update any tenant records.
3. Run the offer command dry run, then apply with the verified GetHired owner/admin actor.
4. Review the saved page copy in the admin preview, then publish through the admin Public Page editor.
5. Read-only verify public rendering. Any live invitation test requires a specifically authorized recipient; synthetic tests stay local. Never access or modify LGUIDS.

Rollback: unpublish the page first. Do not delete a newly used offer version or change historical reward bindings. Revert code/assets from release backups if necessary; keep historical records intact.

## Validation

- 38 PHP tests / 291 assertions passed: landing, admin Public Page regression, conversion and reward history/estimator suites.
- 3 JS tests passed: server-provided estimate selection, whole-peso formatting, unavailable pricing, preview/repeat submit guard.
- Six browser sizes checked; no horizontal overflow. Desktop no-scroll targets passed.
- Browser verified package selection, loading/disabled state and all four backend-response dialogs with local mocked responses; no JS errors.
- Admin preview disabled state verified after Alpine initialization.
- Production Vite build and git diff whitespace checks passed.
- Screenshots: `1440.png`, `390.png`, `success.png`.
- Backend invitation tests use Mail fake with the real invitation service. Browser HTTP responses are mocked locally. No live emails, signups, payments or production data changes were performed.

## Files

New: `GetHiredPublicLanding` service, controller, `PrepareGetHiredPublicLanding` command, `ProgramLandingPage` model and migration; public Blade, admin editor partial, scoped CSS and Alpine module; official logo asset; PHP/JS tests.
Updated: route registration, app CSS/JS imports and the GetHired-only Public Page branch in the workspace. The older generic Public Page test fixture now includes its existing operating-mode column. Unrelated existing worktree changes remain separate.

Limitations: configured mail delivery must be available when deployed. Provider acceptance does not prove inbox delivery. Longer admin-written text can expand the page naturally rather than clip content. Public signup has basic anti-abuse controls; monitor delivery volume after launch before adding stronger bot challenges if needed.

## Refinement — 2026-09-22

- Added user-supplied pastel background as `public/images/landing/gethired-referrer-bg.jpg`, compressed from 1,066,890 bytes to about 78 KB; white overlay and scoped background preserve readability.
- Replaced free-entry price with active GetHired packages via existing authenticated `GetHiredPricingCatalog`. Server-rendered safe payload contains only package names, IDs, monthly prices and server-calculated estimates. Sorting honors optional backend sort order; otherwise ascending monthly price. Optional recommended metadata is validated and preserved by the existing catalog.
- Display-only earnings round to whole pesos; calculations retain integer minor units. Package prices retain cents where needed. Package selection never controls invitation targeting or writes rewards. Posted package IDs/prices are rejected because this invitation form does not collect them.
- No pricing means disabled selector and unavailable estimate, with invitations still usable when the program is open. No invented fallback prices. Backend pricing cache remains five minutes.
- Polished white cards, lighter earnings panel, steps and modal. Existing invite service unchanged.
- Local preview uses explicitly labeled sandbox package fixtures, not a verified live pricing snapshot. No deployment or production access in this refinement.

Refinement files: landing Blade/CSS/Alpine, `GetHiredPublicLanding` service/controller, `GetHiredPricingCatalog`, admin editor explanatory text, background asset and landing PHP/JS tests.

### Desktop fit follow-up
The admin preview banner no longer adds a full row. Short desktop viewports use tighter spacing and a two-column name/email row. No overflow hiding or clipped content. Preview checks passed at 1440×900, 1366×768, 1280×720, 1440×700, 1280×650, 1024×650, 1000×700 and 1280×600. Mobile retains natural scrolling for usability. Local only.

### Earnings emphasis and public-link sweep
- Added the requested two-line “Your Earning” next to the total. Browser measurement at 1280×600: amount and label each 36px tall; no desktop overflow.
- Rebuilt assets; 38 landing/rewards/conversion/admin tests (291 assertions) and 3 JS tests passed. Three additional invitation activation, expired-link renewal and cancellation tests passed (50 assertions).
- Browser rechecked responsive layout, package selection, all four invitation response dialogs, and How it works. Synthetic invite/email responses stayed in sandbox; no production send or deployment.
- Admin access after deployment: Programs → GetHired Online Referrals → Public Page → edit/preview → publish/save and copy link. Intended public address: https://referralbunny.ai/gethired/referrals. The editor and route remain local until explicitly deployed; publication also requires the approved twelve-month offer to be activated.

## Production release — 2026-09-22

User explicitly authorized push and deploy. GitHub Actions `enabled:false` verified before push and after release; no Actions/Netlify deployment. Commit `d63e62d` includes the landing work and source reconciliation of 42 previously deployed matching files. Only 20 new/changed release files (including compiled assets) were installed on the server. Existing assets retained.

- Backup/staging: `/root/gethired-landing-release-20260922/`.
- New empty landing-page table migrated via its explicit migration path only.
- GetHired offer v2 published with twelve-month duration; v1 still six months and unchanged.
- Page published using the existing authorized company-admin controller and verified GetHired owner.
- Route cache rebuilt to register the new routes.
- Public URL: https://referralbunny.ai/gethired/referrals
- Live read-only QA: HTTP 200, no preview banner, form enabled, no JavaScript errors, no scroll at 1366×768. Actual packages Starter 1490, Growth 3490 (default), Premium 5990 PHP/month. Growth estimate 837600 PHP/year; selecting Starter updates to 357600 PHP/year.
- No production invitation submission, email test, payment, signup or LGUIDS access.
- The separate GetHired frontend Job Board navigation change was not part of this Referral Bunny landing release.
