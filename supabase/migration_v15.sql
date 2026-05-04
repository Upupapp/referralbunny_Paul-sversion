-- ============================================================
-- Referral Bunny — Migration v15
-- Reseller Anonymity
-- ============================================================

alter table resellers
    add column if not exists is_anonymous boolean not null default false;

create index if not exists resellers_is_anonymous_idx on resellers (is_anonymous);
