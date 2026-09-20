# Referral Bunny memory

## Hard rule — deployment and CI costs

Recorded: 2026-09-20. Source: explicit user instruction.

No GitHub Actions credits or runner minutes may be consumed as a consequence of a push or pull request. This is a hard rule, not an optimization preference. Do not rely on free allowances or skip-CI commit messages as the only safeguard.

Enforcement:
- GitHub Actions is disabled for `Upupapp/referralbunny_Paul-sversion` at repository level.
- Existing workflow triggers are inert while repository Actions are disabled. The current GitHub credential lacks workflow-edit scope; do not request broader access just to change those triggers. Never re-enable Actions or manually run workflows without an explicit user override.
- Verify repository Actions remain disabled before each push. Test locally and use direct SSH deployment to the existing server.
- Avoid Netlify deployment credits; reuse dependencies and batch changes into one asset build per application release.

The previous application release was deployed before this hard rule was given. Prior Actions consumption cannot be undone. Do not repeat it.

## Communication preference

Recorded: 2026-09-20. Always finish task updates with five recommended next steps. When the user must decide, state the recommended choice and explain why.

## Hard rule — DO NOT TOUCH LGUIDS

Recorded 2026-09-20 from explicit user instruction. LGU IDS (`lgu-ids`) must remain unchanged: screens, data, configuration, pricing, commissions, workflows, imports and integrations. Changes requested for the user’s Referral Bunny company account must be tenant-scoped; shared changes must preserve LGU IDS behavior and rendering. Never apply company-specific changes to LGU IDS.

## Program modes and navigation — requested 2026-09-20

Automated programs connected to online purchases should use the program-based dashboard, metrics, deals and pipeline as a system-wide standard, rather than an account-specific exception. Manual programs must use admin-configured rules and stages. LGU IDS is reference-only and remains untouched. This broader operating-mode rollout is pending; the current ProgramFinancialSummary still targets test-sp4s9i and must not be represented as a completed global rollout.

Upper menu bar: Tasks (including the deal-detail New Task shortcut), Overview/Full View, and the Google Calendar quick-connect button are LGU IDS-only. This is a navigation change, not removal of underlying task/calendar features.

Programs navigation: non-LGUIDS tenants have a main Programs tab immediately before Admin and an authorized, tenant-scoped topbar program picker with Add Program. The picker opens program workspaces; it does not filter aggregate dashboard data. New Deal is hidden on dashboard/deals aggregate pages when any visible program has an online connection record (including pending/disconnected setups); manual-only tenants keep it. Mixed-mode aggregate entry is withheld until explicit program assignment is implemented. LGUIDS retains its original controls.
