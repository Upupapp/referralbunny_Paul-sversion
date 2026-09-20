# GetHired platform connector

## Delivered scope

Referral Bunny company owners/admins select Connect GetHired. They sign in on GetHired and a GetHired **platform administrator (role 1)** approves access to GetHired's own referral program. Employer customers (role 2) cannot authorize the platform's program. GetHired has one platform account, so no account-selection questionnaire is needed.

The native GetHired frontend captures referral query parameters, asks its backend to validate the membership through a signed Referral Bunny request, and keeps an encrypted attribution receipt until a new employer signs in. The backend rejects pre-existing accounts, self-referrals and receipts from disconnected/replaced connections. Signup attribution is persisted once per UID. No customer installs a snippet or supplies a key.

This release connects the account, referral visits and new account attribution. A separately authorized subscription payment adapter now delivers verified first payments and renewals. **Refund synchronization is not included.** Both approval screens disclose the active permission scope. Payment delivery remains disabled until the operator deploys and enables the adapter; existing signup-only connections require reauthorization. GetHired's existing in-progress billing files were preserved; the only changes to its existing backend wiring are a route import, route registration, and connector environment examples.

## Repositories / new files

- Referral Bunny: GetHiredConnectionController, GetHiredConnector service, PlatformReferralController, additive platform connection migration, connection page and scoped routes.
- GetHired backend: routes/referralBunnyRoutes.js, services/referral-bunny/service.cjs, db/referral_bunny_migration.sql, tests/referral-bunny.test.cjs.
- GetHired frontend: src/app/integrations/referral-bunny, registration in the root and alternate role routes, root startup hook.

## Operator rollout (not customer setup)

1. Deploy all three applications together after reviewing unrelated GetHired working-tree changes. Do not stage or deploy those changes incidentally. Respect the zero GitHub Actions / zero Netlify rule; verify Actions disabled before every push.
2. Apply Referral Bunny's `2026_09_20_000003_add_platform_connection.php` migration. In GetHired, apply `db/referral_bunny_migration.sql` with the production database schema explicitly set as search_path. It is additive and rerunnable. No billing tables are altered.
3. Configure an independently generated random client secret of at least 32 characters in `GETHIRED_CONNECTOR_SECRET` (Referral Bunny) and `REFERRAL_BUNNY_CLIENT_SECRET` (GetHired). Generate a separate 32-byte encryption key, stored as 64 hex characters, in `REFERRAL_BUNNY_ENCRYPTION_KEY`. Keep these in server secret configuration, never the frontend, logs, source control, or a customer screen. Back up the encryption key; changing it invalidates encrypted records.
4. Verify GetHired API and website addresses (`GETHIRED_API_URL`, `GETHIRED_WEB_URL`). GetHired's `REFERRAL_BUNNY_ORIGIN` must be `https://referralbunny.ai` for this frontend release. Deploy the native frontend capture/approval module before enabling the connector.
5. Set `REFERRAL_BUNNY_ENABLED=true` on GetHired and `GETHIRED_CONNECTOR_ENABLED=true` on Referral Bunny, refresh configuration/routes/assets once, and smoke-test connect, cancel, disconnect, a valid referral visit and a newly created test employer. Do not label payments ready until the separate billing adapter is implemented and verified.

The flags default off. No infrastructure credentials were generated or live accounts connected during implementation. No deployments are part of this source-only change.

## Authorization and data handling

Server-to-server provisioning requires the configured confidential client secret. Authorization requests expire after ten minutes; approvals are bound to the administrator identity, fixed callback origin/path, browser session state and PKCE challenge. Exchange rechecks the administrator's current database role. A consumed code cannot replace a connection; a matching exchange retry can recover a lost network response until expiration. Another program cannot overwrite an active connection.

The backend stores signing keys using AES-256-GCM. Browser receipts are encrypted and authenticated; original server arrival times cannot be edited. Email comparison uses an HMAC fingerprint and does not expose referrer email addresses to GetHired's frontend. Disconnect removes the active signing key and receipt generation while retaining historical attribution records.

The browser's capture request is best effort: ad blockers, unavailable storage, or transient service errors can prevent attribution. There is no claim that tracking bypasses consent requirements or browser restrictions. Capture is enabled only by the platform feature flag; the host remains responsible for its consent and privacy configuration. The connector does not poll ordinary page visits without a referral or pending receipt.

## Validation

- Referral Bunny feature tests cover session/state, role permissions, cancel/disconnect, payment-status separation, signature validation and inactive membership rejection.
- GetHired backend tests use isolated PostgreSQL-compatible PGlite fixtures to check role checks, expiry, PKCE, encryption, replay/retry, active connection exclusivity and referral eligibility.
- GetHired browser tests cover capture, signup claim, login continuation, transient failures and non-employer exclusion; Angular development build validates the lazy approval screen.

## Next billing milestone

Map persisted attribution UID to the customer company using trusted ownership records. Enqueue verified paid invoices and refund adjustments in a transactional outbox, distinguish first purchases from renewals, exclude tax and test payments, then deliver signed idempotent events to Referral Bunny. Reconcile failures before declaring payment tracking ready. No browser-supplied amount or success-page visit may create a reward.

## Connection experience completion

The portal now checks GetHired's authenticated status endpoint rather than assuming the saved local flag is current. Account connection, signup readiness/last observed signup, and payment availability appear separately. A failed status request shows “Unable to verify” and retains the saved connection; it does not silently disconnect or report success. Checks occur on opening the page and on explicit refresh, not continuous polling.

A revoked GetHired platform administrator causes “Reconnect required” and pauses new capture/claims until a current administrator authorizes access. Reauthorizing the same active connection with the same signing key preserves existing visitor receipts. Explicit disconnect requires an inline confirmation, invalidates pending local authorization and unclaimed receipts, and retains historical records. Old disconnected receipts stay invalid after reconnect.

Expired/completed callbacks return to the program connection page with recovery guidance. Unexpected state/user combinations remain forbidden. GetHired approval offers a normal sign-out/sign-in path for a user who opened it with an employer account, preserves the request through sign-in, and offers a return link for expired or failed requests. Subscription payment delivery is separately authorized and enabled as described below. Refund synchronization remains outside this release.


## Subscription payment delivery milestone

GetHired now has a verified-payment adapter, additive payment ledger migration, and bounded operator-run worker. It reads provider-confirmed live invoices for subscription starts and renewals, reconciles discounts/tax/collected amounts, revalidates referrals, binds a company to its original attribution and persists exact signed event bodies. Test payments, add-ons, upgrades, incomplete payment evidence, self-referrals and existing customers cannot generate rewards through this adapter.

The worker uses the existing signed `/api/program-connections/{id}/events` endpoint. Receiver idempotency prevents duplicate rewards when a response is lost. First and renewal events are delivered in order; known refunds or changed invoices are held rather than delivered incorrectly. Payments remain subject to the configured offer, hold period and manual review. No automatic payout is added.

GetHired requires `referral_bunny_payments_migration.sql`, `REFERRAL_BUNNY_PAYMENTS_ENABLED=true`, explicit worker DB settings, and `REFERRAL_BUNNY_LIVE_DELIVERY_AUTHORIZED=true`. Existing signup-only approvals must reconnect and approve the new payment permission. The portal shows whether payment authorization is needed, no payment has arrived yet, payments are arriving, or records need attention. All keys and deployment configuration remain operator responsibilities.

The worker is a bounded CLI batch, not a public endpoint. It has not been deployed or scheduled by this local implementation. Source recurring execution should be installed on the existing server during the coordinated release, with no GitHub Actions or Netlify usage. Advanced retry/backoff, refund synchronization and operational reconciliation remain subsequent milestones. See GetHired's `REFERRAL_BUNNY_CONNECTOR.md` for source prerequisites and rollout details.
