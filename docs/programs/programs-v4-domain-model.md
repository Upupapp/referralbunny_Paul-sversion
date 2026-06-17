<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Domain Model

## Existing Models Referenced

| Model | Table | Auth Guard | Notes |
|-------|-------|------------|-------|
| `Tenant` | `tenants` | web (staff) | The SaaS customer; owns all Programs |
| `Reseller` (= `Referrer`) | `resellers` | reseller | The person who refers leads |
| `Partner` | `partners` | partner | Business partner with active deals |
| `Lead` (= `Deal`) | `leads` | — | A deal/opportunity; gets `program_id` FK |
| `Contact` | `contacts` | — | Person associated with a Lead |
| `Organization` | `organizations` | — | Company associated with a Lead |
| `TenantReferralProgramDraft` | `tenant_referral_program_drafts` | — | V3 JSON blob; preserved, not replaced |

---

## New V4 Entities

### Program

The primary new entity. One tenant has many Programs.

**Table:** `programs`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `tenant_id` | bigint unsigned | No | FK → tenants.id |
| `program_group_id` | bigint unsigned | Yes | FK → program_groups.id |
| `name` | varchar(255) | No | Display name |
| `slug` | varchar(255) | No | Unique per tenant; used in public URL `/p/{slug}` |
| `description` | text | Yes | Short description for referrers/partners |
| `status` | enum | No | `draft`, `scheduled`, `active`, `paused`, `ended`, `archived` |
| `visibility` | enum | No | `private`, `invite_only`, `public` |
| `is_default` | boolean | No | True for auto-migrated V3 program |
| `is_locked` | boolean | No | True for LGU IDS; prevents config edits |
| `pipeline_locked` | boolean | No | Prevents pipeline stage changes |
| `rewards_locked` | boolean | No | Prevents reward config changes |
| `import_locked` | boolean | No | Prevents import config changes |
| `starts_at` | timestamp | Yes | Scheduled start date |
| `ends_at` | timestamp | Yes | Scheduled end date |
| `launched_at` | timestamp | Yes | When status first became `active` |
| `ended_at` | timestamp | Yes | When status became `ended` |
| `archived_at` | timestamp | Yes | When status became `archived` |
| `metadata` | json | Yes | Extensible config bag |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |
| `deleted_at` | timestamp | Yes | Soft delete |

**Indexes:** `(tenant_id)`, `(tenant_id, slug)` unique, `(status)`, `(tenant_id, is_default)`

**Relationships:**
- `belongsTo(Tenant::class)`
- `belongsTo(ProgramGroup::class)`
- `hasMany(Lead::class)` — via `leads.program_id`
- `hasMany(ReferrerProgramMembership::class)`
- `hasMany(PartnerProgramMembership::class)`
- `hasMany(ProgramOffer::class)`
- `hasMany(ProgramContract::class)`
- `hasMany(ProgramConfigurationVersion::class)`

**Scopes:**
- `scopeForTenant($tenantId)` — always scope by tenant
- `scopeActive()` — status = active
- `scopeVisible()` — not archived
- `scopeLocked()` — is_locked = true

---

### ProgramGroup

Organizes Programs into logical groups within a tenant.

**Table:** `program_groups`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `tenant_id` | bigint unsigned | No | FK → tenants.id |
| `name` | varchar(255) | No | Group display name |
| `description` | text | Yes | |
| `sort_order` | int | No | Default 0 |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

**Relationships:**
- `belongsTo(Tenant::class)`
- `hasMany(Program::class)`

---

### ReferrerProgramMembership

Tracks which Referrers (Resellers) are enrolled in which Programs.

**Table:** `referrer_program_memberships`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `reseller_id` | bigint unsigned | No | FK → resellers.id |
| `status` | enum | No | `invited`, `active`, `suspended`, `withdrawn` |
| `enrolled_at` | timestamp | Yes | When status became `active` |
| `withdrawn_at` | timestamp | Yes | When withdrawn |
| `invited_by_user_id` | bigint unsigned | Yes | FK → users.id (staff user who invited) |
| `invite_token` | varchar(64) | Yes | UUID token for invite link |
| `invite_expires_at` | timestamp | Yes | |
| `metadata` | json | Yes | |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

**Indexes:** `(program_id, reseller_id)` unique, `(reseller_id)`, `(invite_token)`

**Relationships:**
- `belongsTo(Program::class)`
- `belongsTo(Reseller::class)`

---

### PartnerProgramMembership

Tracks which Partners have deals in which Programs.

**Table:** `partner_program_memberships`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `partner_id` | bigint unsigned | No | FK → partners.id |
| `status` | enum | No | `active`, `inactive` |
| `first_deal_at` | timestamp | Yes | When first lead with this program_id appeared |
| `metadata` | json | Yes | |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

**Indexes:** `(program_id, partner_id)` unique, `(partner_id)`

**Relationships:**
- `belongsTo(Program::class)`
- `belongsTo(Partner::class)`

---

### ProgramOffer

The offer presented to referrers when they join or view a Program.

**Table:** `program_offers`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `title` | varchar(255) | No | |
| `description` | text | Yes | |
| `commission_type` | enum | No | `percentage`, `flat`, `tiered` |
| `commission_value` | decimal(10,4) | Yes | |
| `is_active` | boolean | No | Default true |
| `effective_at` | timestamp | Yes | |
| `expires_at` | timestamp | Yes | |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

**Relationships:**
- `belongsTo(Program::class)`
- `hasMany(ProgramOfferVersion::class)`

---

### ProgramOfferVersion

Audit trail for offer changes.

**Table:** `program_offer_versions`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_offer_id` | bigint unsigned | No | FK → program_offers.id |
| `snapshot` | json | No | Full offer state at this version |
| `changed_by_user_id` | bigint unsigned | Yes | FK → users.id |
| `created_at` | timestamp | No | |

---

### ProgramContract

Member agreements / e-sign documents for a Program.

**Table:** `program_contracts`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `reseller_id` | bigint unsigned | Yes | FK → resellers.id (if referrer contract) |
| `partner_id` | bigint unsigned | Yes | FK → partners.id (if partner contract) |
| `title` | varchar(255) | No | |
| `body` | longtext | No | HTML/markdown contract body |
| `status` | enum | No | `pending`, `signed`, `declined`, `expired` |
| `signed_at` | timestamp | Yes | |
| `signature` | text | Yes | |
| `expires_at` | timestamp | Yes | |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

---

### ProgramConfigurationVersion

Snapshot audit trail of Program config changes.

**Table:** `program_configuration_versions`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `snapshot` | json | No | Full program config at this point |
| `changed_by_user_id` | bigint unsigned | Yes | FK → users.id |
| `change_summary` | varchar(500) | Yes | Human-readable description |
| `created_at` | timestamp | No | |

---

### MemberActionItem

Tasks/actions assigned to referrers or partners within a Program.

**Table:** `member_action_items`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `program_id` | bigint unsigned | No | FK → programs.id |
| `reseller_id` | bigint unsigned | Yes | FK → resellers.id |
| `partner_id` | bigint unsigned | Yes | FK → partners.id |
| `title` | varchar(255) | No | |
| `description` | text | Yes | |
| `due_at` | timestamp | Yes | |
| `completed_at` | timestamp | Yes | |
| `status` | enum | No | `pending`, `completed`, `dismissed` |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

---

## Commission Formula (Global — Phase 1)

```
Deal Value        = Base Cost + Added Amount
Company Share     = Added Amount × 30%
Commission Pool   = Added Amount × 70%
```

Per-program rate overrides are deferred to Phase 2. All Programs in Phase 1 use these global rates.

---

## Entity Relationship Summary

```
Tenant
 └─ Program (many)
     ├─ ProgramGroup (optional grouping)
     ├─ Lead (many, via leads.program_id)
     ├─ ReferrerProgramMembership (many)
     │    └─ Reseller
     ├─ PartnerProgramMembership (many)
     │    └─ Partner
     ├─ ProgramOffer (many)
     │    └─ ProgramOfferVersion (many)
     ├─ ProgramContract (many)
     ├─ ProgramConfigurationVersion (many)
     └─ MemberActionItem (many)
```
