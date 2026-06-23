<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Table Ownership Matrix

## Classification Definitions

| Class | Meaning |
|-------|---------|
| **PLATFORM-GLOBAL** | Owned by the platform; never scoped to a single tenant or program |
| **TENANT-GLOBAL** | Scoped to a tenant; shared across all programs within that tenant |
| **PROGRAM-OWNED** | Scoped to a specific program within a tenant |
| **HYBRID** | Has rows at multiple scopes; requires careful query discipline |

---

## Existing Tables

| Table | Classification | Scope Key | Notes |
|-------|---------------|-----------|-------|
| `users` | PLATFORM-GLOBAL | — | Staff users; `activity_logs.user_id` FK target |
| `tenants` | PLATFORM-GLOBAL | — | SaaS customers |
| `resellers` | TENANT-GLOBAL | `tenant_id` | Referrers; one reseller belongs to one tenant |
| `partner_users` | TENANT-GLOBAL | `tenant_id` (via partner) | Partners scoped to tenant |
| `partners` | TENANT-GLOBAL | `tenant_id` | Partner companies |
| `leads` | HYBRID | `tenant_id` + `program_id` (nullable) | After migration: program_id assigned; pre-V4 rows have program_id = default program |
| `contacts` | TENANT-GLOBAL | `tenant_id` | Contacts shared across programs within tenant |
| `organizations` | TENANT-GLOBAL | `tenant_id` | Organizations shared across programs within tenant |
| `commission_splits` | HYBRID | `tenant_id` + FK to lead | Inherits program scope via lead; no direct program_id |
| `activity_logs` | HYBRID | `tenant_id` + optional `program_id` | `user_id` must be staff bigint; program_id column added in V4 migration |
| `tenant_referral_program_drafts` | TENANT-GLOBAL | `tenant_id` | V3 wizard JSON blob; preserved, not replaced |
| `notifications` | PLATFORM-GLOBAL | notifiable polymorphic | Will need program_id in data payload for V4 deep links |
| `pipeline_stages` | TENANT-GLOBAL | `tenant_id` | Shared stages; LGU IDS stages are locked |
| `rewards` | TENANT-GLOBAL | `tenant_id` | Shared reward config; LGU IDS rewards are locked |
| `imports` | TENANT-GLOBAL | `tenant_id` | LGU IDS import is locked |
| `email_templates` | TENANT-GLOBAL | `tenant_id` | Shared email templates |
| `tags` | TENANT-GLOBAL | `tenant_id` | Shared tag taxonomy |
| `tasks` | TENANT-GLOBAL | `tenant_id` | Staff tasks; not program-scoped in Phase 1 |
| `notes` | HYBRID | `tenant_id` + noteable polymorphic | Notes on leads inherit program scope via lead |

---

## New Programs V4 Tables

| Table | Classification | Scope Key | Notes |
|-------|---------------|-----------|-------|
| `programs` | TENANT-GLOBAL | `tenant_id` | The program entity itself; belongs to tenant |
| `program_groups` | TENANT-GLOBAL | `tenant_id` | Groups for organizing programs |
| `referrer_program_memberships` | PROGRAM-OWNED | `program_id` + `reseller_id` | Membership of a referrer in a program |
| `partner_program_memberships` | PROGRAM-OWNED | `program_id` + `partner_id` | Membership of a partner in a program |
| `program_offers` | PROGRAM-OWNED | `program_id` | Offers defined per program |
| `program_offer_versions` | PROGRAM-OWNED | `program_offer_id` → `program_id` | Audit trail; inherits program scope |
| `program_contracts` | PROGRAM-OWNED | `program_id` | Contracts per program per member |
| `program_configuration_versions` | PROGRAM-OWNED | `program_id` | Config snapshot audit trail |
| `member_action_items` | PROGRAM-OWNED | `program_id` | Actions assigned within a program |

---

## Query Discipline Rules

### 1. Always scope Programs by tenant_id
```php
// Correct
Program::where('tenant_id', $tenant->id)->findOrFail($id);

// Wrong — never do a global program lookup
Program::findOrFail($id);
```

### 2. Never put reseller/partner UUIDs in activity_logs.user_id
```php
// Correct
ActivityLog::create([
    'user_id'    => auth('web')->id(),   // staff bigint
    'program_id' => $program->id,
    'metadata'   => ['reseller_id' => $reseller->id],
]);

// Wrong
ActivityLog::create([
    'user_id' => $reseller->id,  // reseller ID is not a staff user bigint
]);
```

### 3. Leads query — always double-scope for program context
```php
// When showing leads for a program
Lead::where('tenant_id', $tenant->id)
    ->where('program_id', $program->id)
    ->get();
```

### 4. Commission splits — derive program scope via lead
```php
// commission_splits has no direct program_id; join through lead
CommissionSplit::whereHas('lead', fn($q) =>
    $q->where('program_id', $program->id)
)->get();
```

### 5. LGU IDS locked tables — add guard before any write
```php
if ($program->is_locked) {
    abort(403, 'This program configuration is locked.');
}
```

---

## Cross-Scope Join Guidelines

| From | To | Join Path | Risk |
|------|----|-----------|------|
| `programs` | `leads` | `leads.program_id = programs.id` | Low — direct FK |
| `programs` | `resellers` | via `referrer_program_memberships` | Low — pivot table |
| `programs` | `partners` | via `partner_program_memberships` | Low — pivot table |
| `programs` | `commission_splits` | via `leads` | Medium — two hops |
| `programs` | `activity_logs` | `activity_logs.program_id = programs.id` | Low — direct after V4 migration |
| `programs` | `contacts` | via `leads` → `contacts` | Medium — two hops; contacts are tenant-global |
| `programs` | `organizations` | via `leads` → `organizations` | Medium — two hops |
