<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Route Map

All V4 routes are gated by the `programs_v4` feature flag middleware unless noted.

**Middleware legend:**
- `auth:web` — staff user session (web guard)
- `auth:reseller` — reseller/referrer session
- `auth:partner` — partner session
- `tenant.resolve` — resolves and sets current tenant from route param
- `programs_v4` — feature flag gate; 404 if disabled
- `program.resolve` — resolves Program model, verifies tenant ownership
- `program.not_locked` — 403 if `program->is_locked`
- `program.accessible` — checks the authed reseller/partner can access this program

---

## Admin Routes — `/tenant/{tenantId}/programs`

Guard: `auth:web` + `tenant.resolve` + `programs_v4`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| GET | `/tenant/{tenantId}/programs` | `admin.programs.index` | `Admin\ProgramController@index` | 1 |
| GET | `/tenant/{tenantId}/programs/create` | `admin.programs.create` | `Admin\ProgramController@create` | 1 |
| POST | `/tenant/{tenantId}/programs` | `admin.programs.store` | `Admin\ProgramController@store` | 1 |
| GET | `/tenant/{tenantId}/programs/{programId}` | `admin.programs.show` | `Admin\ProgramController@show` | 1 |
| GET | `/tenant/{tenantId}/programs/{programId}/edit` | `admin.programs.edit` | `Admin\ProgramController@edit` | 1 |
| PUT/PATCH | `/tenant/{tenantId}/programs/{programId}` | `admin.programs.update` | `Admin\ProgramController@update` | 1 |
| DELETE | `/tenant/{tenantId}/programs/{programId}` | `admin.programs.destroy` | `Admin\ProgramController@destroy` | 1 |

### Admin Program Workspace Tabs

Guard: `auth:web` + `tenant.resolve` + `programs_v4` + `program.resolve`

| Method | Path | Route Name | Controller | Tab | Phase |
|--------|------|------------|------------|-----|-------|
| GET | `/{programId}/overview` | `admin.programs.overview` | `ProgramOverviewController@show` | Overview | 1 |
| GET | `/{programId}/setup` | `admin.programs.setup` | `ProgramSetupController@show` | Setup | 2 |
| GET | `/{programId}/setup/details` | `admin.programs.setup.details` | `ProgramSetupController@details` | Setup → Details | 2 |
| GET | `/{programId}/setup/offers` | `admin.programs.setup.offers` | `ProgramSetupController@offers` | Setup → Offers | 2 |
| GET | `/{programId}/setup/contracts` | `admin.programs.setup.contracts` | `ProgramSetupController@contracts` | Setup → Contracts | 2 |
| GET | `/{programId}/setup/notifications` | `admin.programs.setup.notifications` | `ProgramSetupController@notifications` | Setup → Notifications | 2 |
| GET | `/{programId}/people` | `admin.programs.people` | `ProgramPeopleController@index` | People | 1 |
| GET | `/{programId}/people/referrers` | `admin.programs.people.referrers` | `ProgramPeopleController@referrers` | People → Referrers | 1 |
| POST | `/{programId}/people/referrers/enroll` | `admin.programs.people.referrers.enroll` | `ProgramPeopleController@enroll` | — | 1 |
| DELETE | `/{programId}/people/referrers/{resellerId}` | `admin.programs.people.referrers.remove` | `ProgramPeopleController@removeReferrer` | — | 1 |
| GET | `/{programId}/people/partners` | `admin.programs.people.partners` | `ProgramPeopleController@partners` | People → Partners | 1 |
| GET | `/{programId}/deals` | `admin.programs.deals` | `ProgramDealController@index` | Deals | 1 |
| GET | `/{programId}/deals/{leadId}` | `admin.programs.deals.show` | `ProgramDealController@show` | — | 1 |
| GET | `/{programId}/activity` | `admin.programs.activity` | `ProgramActivityController@index` | Activity | 1 |
| GET | `/{programId}/performance` | `admin.programs.performance` | `ProgramPerformanceController@index` | Performance | 3 |
| GET | `/{programId}/commissions` | `admin.programs.commissions` | `ProgramCommissionController@index` | Commissions | 2 |

### Admin Program Lifecycle Actions

Guard: `auth:web` + `tenant.resolve` + `programs_v4` + `program.resolve` + `program.not_locked`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| POST | `/{programId}/launch` | `admin.programs.launch` | `ProgramLifecycleController@launch` | 1 |
| POST | `/{programId}/pause` | `admin.programs.pause` | `ProgramLifecycleController@pause` | 1 |
| POST | `/{programId}/resume` | `admin.programs.resume` | `ProgramLifecycleController@resume` | 1 |
| POST | `/{programId}/end` | `admin.programs.end` | `ProgramLifecycleController@end` | 1 |
| POST | `/{programId}/archive` | `admin.programs.archive` | `ProgramLifecycleController@archive` | 1 |

---

## Referrer (Reseller) Portal Routes — `/reseller/{resellerId}/programs`

Guard: `auth:reseller` + `programs_v4`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| GET | `/reseller/{resellerId}/programs` | `reseller.programs.index` | `Reseller\ProgramController@index` | 1 |
| GET | `/reseller/{resellerId}/programs/{programId}` | `reseller.programs.show` | `Reseller\ProgramController@show` | 1 |
| GET | `/reseller/{resellerId}/programs/{programId}/deals` | `reseller.programs.deals` | `Reseller\ProgramDealController@index` | 1 |
| GET | `/reseller/{resellerId}/programs/{programId}/deals/{leadId}` | `reseller.programs.deals.show` | `Reseller\ProgramDealController@show` | 1 |
| GET | `/reseller/{resellerId}/programs/{programId}/membership` | `reseller.programs.membership` | `Reseller\ProgramMembershipController@show` | 2 |
| POST | `/reseller/{resellerId}/programs/{programId}/accept-invite` | `reseller.programs.accept_invite` | `Reseller\ProgramMembershipController@accept` | 2 |
| POST | `/reseller/{resellerId}/programs/{programId}/withdraw` | `reseller.programs.withdraw` | `Reseller\ProgramMembershipController@withdraw` | 2 |

Middleware: `program.accessible` applied on `{programId}` routes — verifies the reseller has an active `referrer_program_memberships` row.

---

## Partner Portal Routes — `/partner/programs`

Guard: `auth:partner` + `programs_v4`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| GET | `/partner/programs` | `partner.programs.index` | `Partner\ProgramController@index` | 1 |
| GET | `/partner/programs/{programId}` | `partner.programs.show` | `Partner\ProgramController@show` | 1 |
| GET | `/partner/programs/{programId}/deals` | `partner.programs.deals` | `Partner\ProgramDealController@index` | 1 |
| GET | `/partner/programs/{programId}/deals/{leadId}` | `partner.programs.deals.show` | `Partner\ProgramDealController@show` | 1 |

Middleware: `program.accessible` verifies partner has a `partner_program_memberships` row with status=active.

---

## Public Program Routes — `/p/{slug}`

Guard: none (public) — no feature flag gate required; programs are visible only if `status = active` and `visibility = public`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| GET | `/p/{slug}` | `public.program.show` | `Public\ProgramController@show` | 2 |
| GET | `/p/{slug}/apply` | `public.program.apply` | `Public\ProgramController@apply` | 2 |
| POST | `/p/{slug}/apply` | `public.program.apply.submit` | `Public\ProgramController@submitApplication` | 2 |
| GET | `/p/{slug}/invite/{token}` | `public.program.invite` | `Public\ProgramInviteController@show` | 2 |
| POST | `/p/{slug}/invite/{token}/accept` | `public.program.invite.accept` | `Public\ProgramInviteController@accept` | 2 |

Public route behavior by program status — see `programs-v4-public-routes.md`.

---

## Admin Program Groups Routes

Guard: `auth:web` + `tenant.resolve` + `programs_v4`

| Method | Path | Route Name | Controller | Phase |
|--------|------|------------|------------|-------|
| GET | `/tenant/{tenantId}/program-groups` | `admin.program_groups.index` | `Admin\ProgramGroupController@index` | 2 |
| POST | `/tenant/{tenantId}/program-groups` | `admin.program_groups.store` | `Admin\ProgramGroupController@store` | 2 |
| PUT | `/tenant/{tenantId}/program-groups/{id}` | `admin.program_groups.update` | `Admin\ProgramGroupController@update` | 2 |
| DELETE | `/tenant/{tenantId}/program-groups/{id}` | `admin.program_groups.destroy` | `Admin\ProgramGroupController@destroy` | 2 |

---

## Notes

- All `{programId}` params are resolved via `program.resolve` middleware which calls `Program::where('tenant_id', $tenant->id)->findOrFail($id)`.
- Phase 1 routes are wired first; Phase 2/3 routes are registered but return HTTP 501 until implemented.
- Never use `Program::findOrFail($id)` without the tenant scope — see table ownership rules.
