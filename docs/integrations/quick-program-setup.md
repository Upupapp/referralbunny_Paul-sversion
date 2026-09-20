# Website-based referral program setup

## Runtime

The company admin layout opens the setup dialog for owners and admins whose tenant has neither a launched Programs record nor a legacy published referral-program version. Drafts do not suppress it. Protected tenants remain excluded. Minimization lasts only for the current page; the persistent button reopens the dialog and the next visit resumes the saved draft.

Website analysis reads at most three public HTTPS pages, caches results for 12 hours, and executes no website JavaScript or paid AI calls. Private/reserved IP addresses, credentials, custom ports, redirects, oversized responses and long-running requests are blocked. Prices are unverified candidates until the administrator selects/reviews them. Sites rendered entirely with JavaScript, redirects, paywalls, or missing public pricing use the manual fallback.

Publication uses the existing Programs, offer versions, configuration snapshots and lifecycle service. Quick setup data is saved in the existing referral-program draft JSON. Rewards use the invoice amount actually collected excluding taxes, not the legacy company/referrer commission pool split. Program creation is serialized per tenant and retry-safe.

## Activation

1. Run local tests and the asset build. GitHub Actions must remain disabled; follow AGENTS.md before any push.
2. Deploy code and apply the membership-table repair followed by the new connection migration, after verifying the other Programs tables exist:
   `php artisan migrate --path=database/migrations/2026_09_20_000000_ensure_program_membership_tables.php --force`
   `php artisan migrate --path=database/migrations/2026_09_20_000001_create_program_connections.php --force`
3. Enable `PROGRAMS_V4_ENABLED=true` and refresh the configuration, routes, views and asset build once for the release. Production was observed with Programs disabled before this change; enabling it exposes the existing Programs UI for eligible tenants.
4. Verify a new workspace prompts, an existing published program does not, and referrer/viewer sessions are not prompted.

No production migrations, flags, pushes, or deployments are performed merely by installing these source changes. Do not rerun all historical migrations: the repository's complete SQLite migration chain is incomplete. The targeted tests run the real relevant Programs migrations against an isolated fixture schema.

## GetHired connection

The connection screen supplies a per-program endpoint and encrypted-at-rest signing secret, plus a Node.js request example. This creates no live connection by itself. GetHired must capture `rb_ref` (active program membership ID), `rb_program`, and the arrival timestamp during signup. Preserve attribution server-side and confirm that the referred customer is new and not the referrer. No secret belongs in browser code.

After the payment provider's verified event is durably recorded, enqueue an outbound signed event in GetHired. Use the net paid invoice amount excluding taxes, an immutable event ID, stable customer/invoice IDs, actual UTC dates, and the active program membership ID. Send renewals with `first_payment=false`. Retry the same payload/event ID with a fresh timestamp/signature. Refunds must follow the original payment and have their own immutable event IDs. Out-of-order refunds return 409 so the sender can retry after the payment.

HMAC input: `X-RB-Timestamp + '.' + exact request body`, SHA-256, hexadecimal output in `X-RB-Signature`. Requests expire after five minutes. A test verifies transport and leaves status `tested`; only a live recorded payment changes it to `connected`. Failed scans or missing pricing never block manual setup.

Events are recorded in a dedicated tracking ledger. Percentage/fixed rewards, recurring windows, duplicate invoices, currency checks, attribution changes, inactive referrers and proportional refund reversals are enforced. Rewards remain pending manual review after the hold; they do not trigger payouts or silently enter the legacy deal commission ledger. Review the original payment and its reversals together before arranging payment separately.

GetHired's production domain, approved billing source, and server-side attribution/outbox hookup still need to be configured by the integration owner. Its existing billing implementation has unrelated uncommitted work; this change does not modify it or activate external payments.

## Validation

- `php artisan test --filter='QuickProgramTest|ProgramConversionTest|PortalViewSwitchTest|UnifiedLoginTest|CompanySignupPageTest|ProgramAdminTest|ProgramOfferTest'`
- `php artisan view:cache`
- `npm run build`
- Browser check: desktop creation, price selection, three options, review/confirmation, Escape/minimize/resume, and 390px mobile overflow.
