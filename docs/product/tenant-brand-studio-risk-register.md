# Tenant Brand Studio 2.0 — Risk Register

| ID | Risk | Likelihood | Impact | Mitigation |
|----|------|-----------|--------|-----------|
| R1 | Malicious file upload (PHP shell via logo) | Medium | Critical | Server-side MIME check (finfo), extension allowlist, store outside webroot or use signed URLs |
| R2 | CSS injection via color input | Low | High | Regex-validate `#[0-9A-Fa-f]{6}` before accepting; never interpolate raw user input into CSS without validation |
| R3 | Logo storage fills disk | Low | Medium | Enforce 2 MB per file limit; one logo per tenant (replaces old on upload) |
| R4 | Publish writes wrong tenant's brand | Low | High | Gate: controller extracts tenant_id from route — never from user input |
| R5 | Cache stale after publish | Medium | Medium | `Cache::forget("tenant_config:{$tenantId}")` on every publish; tag-based flush in Phase 2 |
| R6 | Broken portal after bad color | Low | Medium | WCAG contrast check (Phase 1: warn only; Phase 2: block publish) |
| R7 | Migration failure on VPS | Low | Low | Additive-only table; no ALTER on existing tables; safe to re-run |
