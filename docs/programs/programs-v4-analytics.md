<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Analytics Events

## Event Architecture

V4 program events are written to `activity_logs` with the `program_id` column populated.

**Critical constraint:** `activity_logs.user_id` is a bigint FK to staff `users` table. Never put reseller or partner IDs there. Use `metadata` for non-staff actor IDs.

**Base payload structure (all events):**

```json
{
  "event": "program.{action}",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "timestamp": "2026-06-17T10:00:00Z",
  "metadata": {}
}
```

`user_id` = null when the event is triggered by a reseller or partner action (or a system job). The actual actor goes in `metadata`.

---

## Event Definitions

### `program.created`

Fired when a new Program is created by an admin.

```json
{
  "event": "program.created",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "program_slug": "smb-referral",
    "visibility": "invite_only",
    "status": "draft",
    "is_default": false,
    "source": "admin_create"
  }
}
```

`source` values: `admin_create`, `migration_default`, `migration_lgu_ids`

---

### `program.launched`

Fired when a Program transitions to `active` status.

```json
{
  "event": "program.launched",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "previous_status": "draft",
    "launched_at": "2026-06-17T10:00:00Z"
  }
}
```

---

### `program.paused`

Fired when a Program is paused.

```json
{
  "event": "program.paused",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "previous_status": "active",
    "reason": "Seasonal pause"
  }
}
```

`reason` is optional; supplied by admin at pause time (Phase 2).

---

### `program.resumed`

Fired when a paused Program returns to `active`.

```json
{
  "event": "program.resumed",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "previous_status": "paused"
  }
}
```

---

### `program.ended`

Fired when a Program moves to `ended` status.

```json
{
  "event": "program.ended",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "previous_status": "active",
    "ended_at": "2026-06-17T10:00:00Z",
    "deal_count": 42,
    "total_commission_paid": "12500.00"
  }
}
```

---

### `program.archived`

Fired when a Program is archived.

```json
{
  "event": "program.archived",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "program_name": "SMB Referral Program",
    "archived_at": "2026-06-17T10:00:00Z"
  }
}
```

---

### `program.referrer_enrolled`

Fired when a referrer is enrolled in a program (immediate enrollment, not invite-only).

```json
{
  "event": "program.referrer_enrolled",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "reseller_id": "uuid-of-reseller",
    "reseller_name": "Jane Smith",
    "enrollment_type": "immediate",
    "enrolled_by": "admin"
  }
}
```

`user_id` = staff user who performed the enrollment. `reseller_id` is in metadata (UUID — not a bigint staff FK).

`enrollment_type` values: `immediate`, `invite_accepted`, `migration`

---

### `program.referrer_invited`

Fired when an invitation is sent to a referrer.

```json
{
  "event": "program.referrer_invited",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "reseller_id": "uuid-of-reseller",
    "reseller_name": "Jane Smith",
    "invite_expires_at": "2026-06-24T10:00:00Z"
  }
}
```

---

### `program.referrer_removed`

Fired when a referrer is removed or withdrawn from a program.

```json
{
  "event": "program.referrer_removed",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": 789,
  "metadata": {
    "reseller_id": "uuid-of-reseller",
    "reseller_name": "Jane Smith",
    "previous_status": "active",
    "removal_type": "admin_removed",
    "reason": null
  }
}
```

`removal_type` values: `admin_removed`, `referrer_withdrew`, `admin_suspended`

---

### `program.partner_enrolled`

Fired when a partner gains their first deal in a program (auto-membership creation).

```json
{
  "event": "program.partner_enrolled",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": null,
  "metadata": {
    "partner_id": "uuid-of-partner",
    "partner_name": "Acme Corp",
    "lead_id": 999,
    "enrollment_type": "auto_deal"
  }
}
```

`user_id` is null because this is a system-triggered event (triggered when a lead is created with a program_id and partner_id).

---

### `program.referrer_portal_viewed`

Fired when a referrer views a program in their portal. Used for engagement analytics.

```json
{
  "event": "program.referrer_portal_viewed",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": null,
  "metadata": {
    "reseller_id": "uuid-of-reseller",
    "page": "detail",
    "ip_hash": "sha256-of-ip"
  }
}
```

`page` values: `list`, `detail`, `deals`, `membership`

**Note:** This event is rate-limited to one log entry per reseller per program per 1-hour window to avoid log spam.

---

### `program.partner_portal_viewed`

Fired when a partner views a program in their portal.

```json
{
  "event": "program.partner_portal_viewed",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": null,
  "metadata": {
    "partner_id": "uuid-of-partner",
    "page": "detail",
    "ip_hash": "sha256-of-ip"
  }
}
```

Same rate-limiting as referrer view events.

---

### `program.public_viewed`

Fired when a public program page `/p/{slug}` is viewed.

```json
{
  "event": "program.public_viewed",
  "program_id": 123,
  "tenant_id": 456,
  "user_id": null,
  "metadata": {
    "slug": "smb-referral",
    "page": "landing",
    "program_status": "active",
    "referrer_source": "direct",
    "ip_hash": "sha256-of-ip",
    "user_agent_class": "browser"
  }
}
```

`page` values: `landing`, `apply`, `invite`
`referrer_source` values: `direct`, `social`, `email`, `unknown`

**Note:** Public view events use `ip_hash` (SHA-256 of raw IP) — never store raw IP in activity_logs.

---

## Event Delivery

- All events are written synchronously to `activity_logs` during the request cycle
- View events (portal_viewed, public_viewed) may be queued via a job to avoid slowing responses
- No external analytics provider integration in Phase 1 (internal only)
- Phase 3 may add Segment/Amplitude forwarding via `ProgramEventForwarded` listener

---

## Querying Events

```php
// All events for a program
ActivityLog::where('program_id', $program->id)
    ->where('tenant_id', $tenant->id)
    ->latest()
    ->get();

// Specific event types
ActivityLog::where('program_id', $program->id)
    ->whereIn('event', ['program.referrer_enrolled', 'program.referrer_removed'])
    ->get();

// Never query without tenant scope
// Wrong:
ActivityLog::where('program_id', $program->id)->get();
```
