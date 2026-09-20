# Referral Bunny project instructions

## Hard rule: zero GitHub Actions usage on push

User instruction, 2026-09-20: “no github action credits to be consumed as well on push as hard rule”.

- Never trigger GitHub Actions on pushes or pull requests, even if free minutes appear available.
- Before every push, verify repository Actions permissions are disabled. If disabled status cannot be verified, stop the push until a no-Actions path is established.
- Do not add automatic workflow triggers, re-enable Actions, dispatch workflows, or rerun jobs without explicit user authorization overriding this rule.
- Run verification locally. Deploy Referral Bunny directly to its existing server over SSH, reusing installed dependencies when lockfiles are unchanged and building assets only once per release.
- Avoid Netlify deployments and credit-consuming preview builds for this project unless the user explicitly requests them.
- A documentation-only change does not require an application rebuild.

Read [MEMORY.md](MEMORY.md) for the saved decision and [INDEX.md](INDEX.md) for the project memory index.
