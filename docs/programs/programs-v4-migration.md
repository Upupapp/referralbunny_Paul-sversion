<!-- Programs V4 internal doc — not publicly accessible -->
<!-- last-updated: 2026-06-17 -->

# Programs V4 — Migration Strategy

## Overview

The migration has four parts executed in order:
1. Schema migration — new tables and columns
2. Data migration — create default Programs from V3 config
3. LGU IDS special migration — locked Program with preserved config
4. Rollback plan

All migrations are written as standard Laravel migrations. Data migrations run as a separate seeder/command called from the migration, or as a dedicated `DataMigration` class to keep schema and data changes separate and independently rollback-able.

---

## Part 1: Schema Migration

### Migration: `create_programs_table`

```
php artisan make:migration create_programs_table
```

Creates the `programs` table with all columns defined in `programs-v4-domain-model.md`.

**Key constraints:**
- `tenant_id` FK with `cascade on delete` (deleting a tenant deletes their programs — soft deletes used)
- `(tenant_id, slug)` unique index
- `status` enum: `draft`, `scheduled`, `active`, `paused`, `ended`, `archived`
- `visibility` enum: `private`, `invite_only`, `public`
- Soft deletes via `deleted_at`

### Migration: `create_program_groups_table`

Simple table; `tenant_id` FK.

### Migration: `create_referrer_program_memberships_table`

- `(program_id, reseller_id)` unique index
- `invite_token` unique index (nullable)
- Status enum: `invited`, `active`, `suspended`, `withdrawn`

### Migration: `create_partner_program_memberships_table`

- `(program_id, partner_id)` unique index
- Status enum: `active`, `inactive`

### Migration: `create_program_offers_table`

Commission type enum: `percentage`, `flat`, `tiered`

### Migration: `create_program_offer_versions_table`

Stores JSON snapshot; no enum columns.

### Migration: `create_program_contracts_table`

Status enum: `pending`, `signed`, `declined`, `expired`

### Migration: `create_program_configuration_versions_table`

JSON snapshot; `changed_by_user_id` nullable FK to `users`.

### Migration: `create_member_action_items_table`

Status enum: `pending`, `completed`, `dismissed`

### Migration: `add_program_id_to_leads_table`

```php
$table->unsignedBigInteger('program_id')->nullable()->after('tenant_id');
$table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
$table->index('program_id');
```

**Important:** Nullable and `nullOnDelete()` — if a program is deleted, leads retain their data but `program_id` becomes null. This is intentional: lead data is never lost due to program deletion.

### Migration: `add_program_id_to_activity_logs_table`

```php
$table->unsignedBigInteger('program_id')->nullable()->after('tenant_id');
$table->index('program_id');
```

No FK constraint on `activity_logs.program_id` — activity logs are append-only historical records; we do not want cascade deletes on logs.

---

## Part 2: Data Migration — Default Program Per Tenant

### Command: `programs:migrate-defaults`

```
php artisan programs:migrate-defaults [--dry-run] [--tenant=ID]
```

**Algorithm:**

```
For each Tenant where:
  - NOT LGU IDS (tenant.id != LGU_IDS_TENANT_ID)
  - programs_v4 flag is enabled (or always — see note below)
  - has no existing default Program (programs.is_default = true)

Do:
  1. Load TenantReferralProgramDraft for this tenant (V3 config JSON)
  2. Extract from JSON:
     - program name (from referral_program_name or tenant company name + " Referral Program")
     - description (from program_description if set)
     - visibility (default: invite_only)
  3. Create Program:
     {
       tenant_id: tenant.id,
       name: extracted name,
       slug: kebab(name) + '-' + shortId(),
       status: 'active',  -- default program starts active
       is_default: true,
       is_locked: false,
       metadata: { migrated_from_v3: true, v3_draft_id: draft.id }
     }
  4. Update all leads WHERE tenant_id = tenant.id AND program_id IS NULL:
     SET program_id = new_program.id
  5. Log: ActivityLog (event = 'program.created', source = 'migration_default', user_id = null)
  6. Mark migration complete in tenant metadata
```

**Note on flag gating:** The data migration creates default Programs for ALL tenants regardless of the `programs_v4` flag. The flag gates the UI, not the data. This ensures data consistency when the flag is later enabled.

**Dry run mode:** Prints what would be done without writing to the database.

**Idempotent:** Safe to run multiple times. Skips tenants that already have a default Program.

---

## Part 3: LGU IDS Special Migration

### Command: `programs:migrate-lgu-ids`

```
php artisan programs:migrate-lgu-ids [--dry-run]
```

LGU IDS is a specific tenant with a locked configuration. The `LGU_IDS_TENANT_ID` constant must be defined in config.

**Algorithm:**

```
1. Load LGU IDS tenant
2. Assert: no default Program exists yet (fail loudly if already migrated)
3. Load TenantReferralProgramDraft for LGU IDS
4. Create Program:
   {
     tenant_id: LGU_IDS_TENANT_ID,
     name: 'LGU IDS Referral Program',
     slug: 'lgu-ids-referral',
     status: 'active',
     is_default: true,
     is_locked: true,          -- LOCKED
     pipeline_locked: true,    -- LOCKED
     rewards_locked: true,     -- LOCKED
     import_locked: true,      -- LOCKED
     visibility: 'private',
     metadata: {
       migrated_from_v3: true,
       lgu_ids_special: true,
       pipeline_stages: ['Introduction', 'Presentation Done', 'Contract Sent', 'Signed', 'Paid'],
       commission_formula: '70/30 Added Amount split'
     }
   }
5. Update leads: SET program_id = new_program.id WHERE tenant_id = LGU_IDS_TENANT_ID
6. Log migration with activity event
7. Run LGU IDS regression test suite (or emit a reminder to do so)
```

**Post-migration verification:**
- `programs` has exactly 1 row for LGU IDS tenant with `is_locked = true`
- `leads` where `tenant_id = LGU_IDS_TENANT_ID` all have `program_id` set
- No pipeline stages were modified during migration
- Commission calculations unchanged — verify with a known test deal

---

## Part 4: Rollback Plan

### Schema rollback

Laravel's built-in `migrate:rollback` handles schema rollback. Migration order matters: tables with FK dependencies must be rolled back in reverse creation order.

**Rollback order:**
1. Drop `member_action_items`
2. Drop `program_configuration_versions`
3. Drop `program_contracts`
4. Drop `program_offer_versions`
5. Drop `program_offers`
6. Drop `partner_program_memberships`
7. Drop `referrer_program_memberships`
8. Remove `program_id` from `activity_logs`
9. Remove `program_id` from `leads`
10. Drop `programs`
11. Drop `program_groups`

### Data rollback

Schema rollback will cascade-null `leads.program_id` (via `nullOnDelete()`). The V3 `TenantReferralProgramDraft` records are never modified by the migration — they remain intact as the source of truth.

**Data rollback command:**

```
php artisan programs:rollback-defaults [--tenant=ID] [--dry-run]
```

This command:
1. Sets `leads.program_id = NULL` for all leads belonging to default programs (reverting data migration)
2. Deletes default `programs` rows where `is_default = true` and `metadata.migrated_from_v3 = true`
3. Does NOT delete LGU IDS program — requires separate explicit command

**LGU IDS data rollback:**

```
php artisan programs:rollback-lgu-ids [--dry-run]
```

Must be run BEFORE the main data rollback. Sets `leads.program_id = NULL` for LGU IDS leads, deletes the LGU IDS program row.

### What is never lost in rollback
- `TenantReferralProgramDraft` records (V3 config) — untouched
- `leads` data — program_id becomes null but lead data is preserved
- `activity_logs` — historical; program_id column exists but rows with program_id are retained even after schema rollback (column dropped but FK was never enforced on activity_logs)
- Referrer and partner data — no changes to resellers or partners tables

---

## Migration Checklist

Before running in production:

- [ ] Run `--dry-run` on staging and verify output
- [ ] Take a full database backup before running
- [ ] Confirm LGU IDS tenant ID constant is correct in config
- [ ] Run LGU IDS migration separately and verify immediately
- [ ] Confirm `leads.program_id` is nullable (never a hard require)
- [ ] Confirm `activity_logs.user_id` constraint is NOT violated by migration (use null, not reseller IDs)
- [ ] Verify schema rollback order in reverse migration files
- [ ] Run LGU IDS regression test suite post-migration
