-- Migration v50: Add recipient_type to email_digests
-- Allows EmailDigestService to correctly label the email log entry (reseller, super_admin, etc.)
ALTER TABLE email_digests
    ADD COLUMN IF NOT EXISTS recipient_type TEXT NOT NULL DEFAULT 'reseller';
