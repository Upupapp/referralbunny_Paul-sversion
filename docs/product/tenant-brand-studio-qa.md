# Tenant Brand Studio 2.0 — QA Checklist

---

## Phase 1 Manual QA

### Logo Upload
- [ ] Upload valid PNG — succeeds, preview shows
- [ ] Upload valid JPG — succeeds
- [ ] Upload valid WebP — succeeds
- [ ] Upload PHP file with .jpg extension — rejected (MIME check)
- [ ] Upload file > 2 MB — rejected with size error
- [ ] Upload SVG — rejected (not in allowlist)
- [ ] Delete logo — logo removed from preview, draft updated
- [ ] Upload logo → save draft → publish → logo visible in sidebar

### Color Pickers
- [ ] Set accent color to valid hex (#FF5733) — saves
- [ ] Set accent color to invalid value (abc123, no #) — rejected
- [ ] Set sidebar color to dark value — WCAG warning shows (if contrast fails)
- [ ] Color changes reflected in CSS immediately (Alpine reactivity)
- [ ] Accent color correctly injected into portal `<head>` after publish

### Draft / Publish
- [ ] Save Draft without logo — succeeds (logo is nullable)
- [ ] Publish with no draft row (first-time) — graceful (creates profile)
- [ ] Publish → `tenants.accent_color` updated in DB
- [ ] Publish → `tenants.logo_url` updated in DB
- [ ] Publish → `Cache::forget("tenant_config:{id}")` fired
- [ ] Revert draft → draft reset to published values

### Access Control
- [ ] Owner can access /settings/branding — 200
- [ ] Admin can access /settings/branding — 200
- [ ] Manager sees read-only view (no save/publish buttons)
- [ ] Non-admin tenant user is redirected or shown 403
- [ ] Super admin (web guard) can access as impersonator — 200
- [ ] Referrer/partner cannot access (wrong portal) — correct guard

### Health Score
- [ ] Score = 0 with nothing set
- [ ] Score = 30 after logo upload + publish
- [ ] Score = 55 after logo + accent
- [ ] Score ≥ 70 with logo + accent + sidebar + program name + business name
- [ ] Score color changes: red < 40, orange 40–69, green ≥ 70

### CSS Injection
- [ ] Portal loads with `--color-brand` overridden to tenant accent color
- [ ] Portal loads with `--color-sidebar` overridden to tenant sidebar color (if set)
- [ ] No tenant brand → defaults from app.css apply unchanged

---

## Automated Tests (TenantBrandingTest.php)

- [ ] draft_saves_without_logo
- [ ] publish_writes_to_tenants_table
- [ ] logo_upload_rejects_invalid_mime
- [ ] logo_upload_rejects_oversized_file
- [ ] manager_cannot_save_draft
- [ ] health_score_computation
