# Tenant Brand Studio 2.0 — User Flows

---

## UF-01: First-Time Brand Setup

**Actor:** Tenant owner or admin  
**Entry:** Admin nav → Brand Studio

1. Land on Brand Studio overview page.
2. See health score at 0 — no brand configured.
3. Upload logo → see live preview in sidebar area.
4. Pick accent color → see CSS var update in preview.
5. Pick sidebar color → sidebar preview updates.
6. Click "Save Draft" → toast "Draft saved".
7. Click "Publish" → confirmation modal → confirm → toast "Brand published".
8. Page reloads with published state reflected in the sidebar.

---

## UF-02: Update Existing Brand

**Actor:** Tenant owner or admin  
**Precondition:** Brand has been published at least once.

1. Land on Brand Studio — sees current published brand.
2. Upload new logo or change a color.
3. "Unsaved changes" badge appears.
4. Click "Save Draft" → draft saved (published brand unchanged).
5. Review changes, click "Publish" → live brand updates.

---

## UF-03: Delete Logo

**Actor:** Tenant owner or admin

1. On Brand Studio with a logo uploaded.
2. Click "Remove Logo" on the logo preview.
3. Confirmation modal.
4. Logo removed from draft. Publish to remove from portal.

---

## UF-04: View-Only (Manager Role)

**Actor:** Manager

1. Manager visits Brand Studio URL.
2. Sees read-only view of current brand (colors, logo preview, health score).
3. All edit controls are hidden/disabled.
4. No "Save Draft" or "Publish" buttons.

---

## UF-05: Brand Health Score

**Actor:** Any admin/owner

1. Health score widget on Brand Studio overview.
2. Score computed from:
   - Logo uploaded: +30 pts
   - Accent color customized (≠ default #FF5733): +25 pts
   - Sidebar color customized (≠ default #2D2B6E): +20 pts
   - Program name set: +15 pts
   - Business name set: +10 pts
3. Color-coded: < 40 red, 40–69 orange, ≥ 70 green.
4. Inline tips for each missing element.

---

## UF-06: Revert Draft to Published

**Actor:** Tenant owner or admin  
**Precondition:** There is a published brand and an unsaved draft.

1. Click "Revert Draft" button.
2. Confirmation modal: "This will discard your draft and reset to the published brand."
3. Draft overwritten with current published values.
