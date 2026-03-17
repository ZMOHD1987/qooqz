-- ============================================================
-- Migration: Enhance audit_logs table
-- Adds diff, old_values, new_values, metadata, trace columns
-- to support world-class change tracking with full context.
-- Run once against the database. Each ADD COLUMN is guarded
-- by IF NOT EXISTS so re-runs are safe.
-- ============================================================

ALTER TABLE audit_logs
    -- Before-state snapshot (JSON object of the record before the mutation)
    ADD COLUMN IF NOT EXISTS old_values   LONGTEXT     NULL DEFAULT NULL COMMENT 'JSON snapshot of entity state BEFORE the change',

    -- After-state snapshot (JSON object of the record after the mutation)
    ADD COLUMN IF NOT EXISTS new_values   LONGTEXT     NULL DEFAULT NULL COMMENT 'JSON snapshot of entity state AFTER the change',

    -- Computed field-level diff (JSON array of {field, old, new} objects)
    ADD COLUMN IF NOT EXISTS diff         LONGTEXT     NULL DEFAULT NULL COMMENT 'JSON array [{field,old,new}] showing exactly what changed',

    -- Arbitrary key-value context bag (subscription plan, feature flags, etc.)
    ADD COLUMN IF NOT EXISTS metadata     LONGTEXT     NULL DEFAULT NULL COMMENT 'JSON object with arbitrary contextual metadata',

    -- Stack trace or call chain for debugging (optional, high-verbosity mode)
    ADD COLUMN IF NOT EXISTS trace        LONGTEXT     NULL DEFAULT NULL COMMENT 'Optional debug stack trace / breadcrumb trail',

    -- HTTP verb that triggered the action (GET/POST/PUT/DELETE/PATCH)
    ADD COLUMN IF NOT EXISTS http_method  VARCHAR(10)  NULL DEFAULT NULL COMMENT 'HTTP method of the originating request',

    -- Full request URL (path + query string, without host)
    ADD COLUMN IF NOT EXISTS http_url     VARCHAR(2048) NULL DEFAULT NULL COMMENT 'Request path and query string',

    -- PHP session ID for correlating multiple log entries from one user session
    ADD COLUMN IF NOT EXISTS session_id   VARCHAR(128) NULL DEFAULT NULL COMMENT 'PHP session identifier',

    -- Unique request ID (UUID or microtime) for distributed-trace correlation
    ADD COLUMN IF NOT EXISTS request_id   VARCHAR(64)  NULL DEFAULT NULL COMMENT 'Unique request identifier for distributed tracing',

    -- How long (ms) the operation took from entry to response
    ADD COLUMN IF NOT EXISTS duration_ms  INT UNSIGNED NULL DEFAULT NULL COMMENT 'Operation duration in milliseconds',

    -- Timestamp (safe to add; ignored silently if column already exists)
    ADD COLUMN IF NOT EXISTS created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the log entry was created';

-- Indexes for fast queries by entity, action timeline, request and session
ALTER TABLE audit_logs
    ADD INDEX IF NOT EXISTS idx_al_entity     (tenant_id, entity_type, entity_id),
    ADD INDEX IF NOT EXISTS idx_al_action_ts  (tenant_id, action, created_at),
    ADD INDEX IF NOT EXISTS idx_al_request    (request_id),
    ADD INDEX IF NOT EXISTS idx_al_session    (session_id);
