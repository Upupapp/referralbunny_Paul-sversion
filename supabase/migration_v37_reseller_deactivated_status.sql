-- Migration v37: Allow 'deactivated' status on resellers table
-- Drops any existing CHECK constraint on status and recreates it with 'deactivated' included.

ALTER TABLE resellers
  DROP CONSTRAINT IF EXISTS resellers_status_check;

ALTER TABLE resellers
  ADD CONSTRAINT resellers_status_check
  CHECK (status IN ('invited', 'active', 'nda_signed', 'deactivated'));
