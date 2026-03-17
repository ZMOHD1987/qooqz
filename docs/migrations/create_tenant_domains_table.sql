-- ============================================================
-- Migration: Create tenant_domains table
-- International best practice: a tenant may have one primary
-- (canonical) domain plus unlimited custom/alias/subdomain
-- entries.  Mirrors how Shopify, WordPress Multisite, and
-- leading SaaS platforms handle multi-domain tenancy.
--
-- Design decisions
-- ────────────────
-- • The `domain` column in the `tenants` table continues to
--   hold the single "canonical" domain (backwards compat).
-- • tenant_domains is the authoritative registry for all
--   domains belonging to a tenant, including the canonical
--   one, additional custom domains, sub-domains, and aliases.
-- • `type` reflects the domain's purpose:
--     primary   – main canonical domain (mirrors tenants.domain)
--     custom    – customer-managed CNAME/A record
--     subdomain – auto-generated *.platform.tld sub-domain
--     alias     – vanity domain that redirects to primary
-- • `ssl_status` tracks the certificate lifecycle so the
--   platform can automate Let's Encrypt issuance.
-- • `redirect_to_primary` enables the common "redirect all
--   secondary domains to the canonical one" pattern.
-- • `verification_token` supports DNS TXT / HTTP challenge
--   domain ownership verification.
-- • `meta` (JSON) is a free-form bag for future extension
--   without schema changes (e.g. CDN config, geolocation).
--
-- Run once; the CREATE TABLE is idempotent via IF NOT EXISTS.
-- ============================================================

CREATE TABLE IF NOT EXISTS tenant_domains (
    id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tenant_id           INT(10) UNSIGNED NOT NULL,

    -- The domain name as registered (no protocol, no trailing slash)
    domain              VARCHAR(255)     NOT NULL,

    -- Domain classification
    type                ENUM('primary','custom','subdomain','alias')
                        NOT NULL DEFAULT 'custom',

    -- Ownership verification
    is_verified         TINYINT(1)       NOT NULL DEFAULT 0,
    verification_token  VARCHAR(128)     NULL     DEFAULT NULL
                        COMMENT 'Used for DNS TXT / HTTP file challenge',
    verified_at         DATETIME         NULL     DEFAULT NULL,

    -- TLS / SSL certificate lifecycle
    ssl_status          ENUM('none','pending','active','failed')
                        NOT NULL DEFAULT 'none',
    ssl_expires_at      DATETIME         NULL     DEFAULT NULL,

    -- Redirect behaviour
    redirect_to_primary TINYINT(1)       NOT NULL DEFAULT 0
                        COMMENT 'When 1, HTTP 301 to the primary domain',

    -- Free-form extension bag (CDN, geolocation, etc.)
    meta                JSON             NULL     DEFAULT NULL,

    -- Timestamps
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        NULL     DEFAULT NULL
                        ON UPDATE CURRENT_TIMESTAMP,

    -- ── Constraints ───────────────────────────────────────────
    PRIMARY KEY (id),

    -- Each domain string must be globally unique (like DNS)
    UNIQUE KEY uk_tenant_domains_domain (domain),

    -- Fast lookup of all domains for a tenant
    KEY idx_tenant_domains_tenant  (tenant_id),

    -- Quick filter by type or ssl_status
    KEY idx_tenant_domains_type    (type),
    KEY idx_tenant_domains_ssl     (ssl_status),

    -- Cascade delete: remove domain records when tenant is deleted
    CONSTRAINT fk_tenant_domains_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenants (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Multi-domain registry for tenants. One tenant can own
           many domains (primary, custom, subdomain, alias).';

-- ── Seed: migrate existing canonical domains ─────────────────
-- If the tenants table already has domain values, copy them into
-- tenant_domains as type=primary.  This INSERT ... SELECT is safe
-- to run even after a partial migration (ON DUPLICATE KEY skips
-- already-migrated rows).
INSERT INTO tenant_domains (tenant_id, domain, type, is_verified, ssl_status)
SELECT id, domain, 'primary', 1, 'none'
FROM   tenants
WHERE  domain IS NOT NULL
  AND  domain <> ''
ON DUPLICATE KEY UPDATE tenant_id = tenant_id; -- no-op if already exists
