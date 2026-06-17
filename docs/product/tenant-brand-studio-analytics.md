# Tenant Brand Studio 2.0 — Analytics & Measurement

---

## Events (Phase 1 stub — writes to activity_logs metadata only)

| Event | Trigger | Key Metadata |
|-------|---------|-------------|
| `brand.draft_saved` | POST /settings/branding/draft | tenant_id, fields_changed[] |
| `brand.published` | POST /settings/branding/publish | tenant_id, health_score |
| `brand.logo_uploaded` | POST /settings/branding/logo | tenant_id, file_size_kb |
| `brand.logo_deleted` | DELETE /settings/branding/logo | tenant_id |
| `brand.draft_reverted` | POST /settings/branding/revert | tenant_id |

All events log to `activity_logs` with `description = <event>` and `metadata = [...]`.  
`activity_logs.user_id` = staff user ID (bigint) only; tenant/owner ID goes in `metadata.actor_id`.

---

## Dashboard KPIs (Phase 2 — not yet built)

- Tenants with health score ≥ 70 (%)
- Tenants that have published at least once (%)
- Average health score across all active tenants
- Logo adoption rate (has logo_url set)
- Publish events per week

---

## Notes

- Phase 1 analytics are fire-and-forget (no hard dependency on logging success).
- Events are wrapped in `try/catch` so a logging failure never blocks a publish.
