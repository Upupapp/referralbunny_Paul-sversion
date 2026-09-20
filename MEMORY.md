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
