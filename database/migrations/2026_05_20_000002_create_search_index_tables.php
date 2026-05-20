<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // search_index — main full-text search table, upserted by IndexingService
        DB::statement('
            CREATE TABLE IF NOT EXISTS search_index (
                id              BIGSERIAL PRIMARY KEY,
                entity_type     VARCHAR(50)  NOT NULL,
                entity_id       VARCHAR(100) NOT NULL,
                title           VARCHAR(500) NOT NULL DEFAULT \'\',
                description     TEXT,
                keywords        TEXT,
                tags            TEXT,
                status          VARCHAR(50),
                url             TEXT,
                tenant_id       VARCHAR(100),
                relationships_json TEXT,
                searchable_text TEXT,
                last_activity_at TIMESTAMPTZ,
                is_deleted      BOOLEAN NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMPTZ DEFAULT NOW(),
                updated_at      TIMESTAMPTZ DEFAULT NOW()
            )
        ');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_search_index_type_entity ON search_index (entity_type, entity_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_search_index_tenant        ON search_index (tenant_id) WHERE tenant_id IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_search_index_entity_type   ON search_index (entity_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_search_index_last_activity ON search_index (last_activity_at DESC NULLS LAST)');

        // synonyms — term expansion for search
        DB::statement('
            CREATE TABLE IF NOT EXISTS synonyms (
                id         BIGSERIAL PRIMARY KEY,
                term       VARCHAR(100) NOT NULL,
                synonym    VARCHAR(100) NOT NULL,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_synonyms_term    ON synonyms (term)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_synonyms_synonym ON synonyms (synonym)');

        // reindex_jobs — tracks status of each indexing run (fix: include updated_at)
        DB::statement('
            CREATE TABLE IF NOT EXISTS reindex_jobs (
                id               BIGSERIAL PRIMARY KEY,
                entity_type      VARCHAR(50) NOT NULL,
                status           VARCHAR(20) NOT NULL DEFAULT \'pending\',
                records_indexed  INTEGER,
                last_run         TIMESTAMPTZ,
                error            TEXT,
                created_at       TIMESTAMPTZ DEFAULT NOW(),
                updated_at       TIMESTAMPTZ DEFAULT NOW()
            )
        ');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_reindex_jobs_entity ON reindex_jobs (entity_type)');

        // favorites — per-user pinned search results
        DB::statement('
            CREATE TABLE IF NOT EXISTS favorites (
                id          BIGSERIAL PRIMARY KEY,
                user_id     BIGINT NOT NULL,
                entity_type VARCHAR(50)  NOT NULL,
                entity_id   VARCHAR(100) NOT NULL,
                label       VARCHAR(255),
                url         TEXT,
                created_at  TIMESTAMPTZ DEFAULT NOW(),
                updated_at  TIMESTAMPTZ DEFAULT NOW()
            )
        ');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_favorites_user_entity ON favorites (user_id, entity_type, entity_id)');
        DB::statement('CREATE        INDEX IF NOT EXISTS idx_favorites_user        ON favorites (user_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS favorites');
        DB::statement('DROP TABLE IF EXISTS reindex_jobs');
        DB::statement('DROP TABLE IF EXISTS synonyms');
        DB::statement('DROP TABLE IF EXISTS search_index');
    }
};
