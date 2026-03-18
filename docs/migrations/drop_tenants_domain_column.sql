-- ============================================================
-- Migration: Drop legacy `domain` column from `tenants` table
-- Date:      2026-03-18
--
-- Rationale:
--   The `tenants.domain` column was kept only for backwards
--   compatibility.  All canonical domain data now lives in the
--   `tenant_domains` table (multi-domain registry).  Having the
--   same value stored in two places causes silent divergence bugs.
--
-- Before running this migration make sure that:
--   1. All application code that writes to `tenants.domain` has
--      been updated to write to `tenant_domains` instead.
--   2. The primary domain for every tenant is already present in
--      `tenant_domains` with type = 'primary'.
-- ============================================================

-- Optional safety check: copy any remaining values across
-- before dropping, so no data is silently lost.
INSERT IGNORE INTO tenant_domains (tenant_id, domain, type, is_verified, created_at)
SELECT t.id, t.domain, 'primary', 0, NOW()
FROM   tenants t
WHERE  t.domain IS NOT NULL
  AND  t.domain != ''
  AND  NOT EXISTS (
           SELECT 1
           FROM   tenant_domains td
           WHERE  td.tenant_id = t.id
             AND  td.type      = 'primary'
       );

-- Drop the now-redundant column
ALTER TABLE tenants DROP COLUMN domain;
