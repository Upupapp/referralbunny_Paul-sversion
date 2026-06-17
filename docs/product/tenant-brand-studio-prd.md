# Tenant Brand Studio 2.0 — Product Requirements Document

**Version:** 1.0  
**Status:** In Progress (Phase 1 shipped)  
**Owner:** paul@moveup.app

---

## Overview

Tenant Brand Studio 2.0 is a self-serve customization system that lets each tenant configure their brand identity, white-label partner/referrer portals, and publish a consistent visual experience across the ReferralBunny platform — without engineering intervention.

---

## Problem Statement

Tenants currently rely on platform staff to update their logo and accent color. There is no draft/publish workflow, no brand health visibility, and no way to preview changes before going live.

---

## Goals

1. Tenant owners can fully configure their brand without contacting support.
2. Changes go through draft → preview → publish — never live until intentional.
3. Brand coverage is measurable via a health score.
4. All portals (admin, partner, referrer) reflect the published brand.

---

## MoSCoW Requirements

### Must Have (Phase 1)

| ID | Requirement |
|----|-------------|
| M1 | Logo upload (PNG/JPG/WebP, max 2 MB, tenant-scoped storage) |
| M2 | Accent color picker with WCAG AA contrast validation |
| M3 | Sidebar/nav color picker |
| M4 | Draft → Publish workflow |
| M5 | CSS variable injection into admin portal |
| M6 | Basic brand health score |
| M7 | Revert published brand to saved draft |

### Should Have (Phase 2)

| ID | Requirement |
|----|-------------|
| S1 | Live portal preview (iframe or side-panel) |
| S2 | Logo injection into partner/referrer portals |
| S3 | Email header branding (logo in transactional emails) |
| S4 | Brand version history (last 5 publishes) |
| S5 | Partner portal color overrides |
| S6 | Favicon upload |

### Could Have (Phase 3)

| ID | Requirement |
|----|-------------|
| C1 | Custom domain per portal |
| C2 | Dark mode support |
| C3 | Custom typography (font family picker) |
| C4 | Full white-label (remove ReferralBunny branding entirely) |
| C5 | Analytics dashboard for brand adoption |

### Won't Have (this cycle)

- Real-time collaborative editing
- AI-generated brand palettes (evaluate post-Phase 3)
- Mobile app theming

---

## Success Metrics

- **Activation rate:** ≥ 60% of active tenants publish at least one brand update within 30 days of feature launch.
- **Support deflection:** Zero support tickets for logo/color changes after launch.
- **Brand health:** Average health score ≥ 70/100 within 60 days.
- **Error rate:** < 1% of publish operations fail.

---

## Data Model (Phase 1)

### `tenant_brand_profiles`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| tenant_id | string FK → tenants.id | |
| logo_url | string nullable | public URL |
| logo_path | string nullable | storage-relative path |
| accent_color | string(7) nullable | #RRGGBB |
| sidebar_color | string(7) nullable | #RRGGBB |
| status | string | draft, published |
| published_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

The `tenants` table canonical fields (`logo_url`, `accent_color`) are the "live" values written on publish.

---

## Access Control

| Role | Permission |
|------|-----------|
| owner | Full read/write/publish |
| admin | Full read/write/publish |
| manager | Read-only view |
| super_admin | Full read/write/publish |
| viewer, partner, referrer | No access |

---

## Non-Functional Requirements

- Logo uploads validate: extension (jpg/png/webp), MIME type (server-side), max 2 MB.
- Color inputs validated as `#[0-9A-Fa-f]{6}` regex before save.
- Cache key `tenant_config:{tenantId}` is cleared on every publish.
- All brand mutations are owner/admin-only (gate enforced in controller).

---

## Open Questions

- [ ] Should sidebar_color also be written to `tenants` table on publish, or stay in brand profile only?
- [ ] Phase 2 preview: full-page iframe vs. side-panel component?
- [ ] Email branding: inject tenant logo into base email layout, or per-template opt-in?
