# Tenants Architecture

**Version**: 2.0 · **Author**: Platform Team · **Date**: 2026-03

---

## 1. Purpose

A *tenant* is the top-level organisational unit of the platform (SaaS layer).
Every entity, user, order, and content item ultimately belongs to one tenant.
This document records design decisions, explains the data model, and describes
all integrations.

---

## 2. Data Model

### 2.1 `tenants` (core)

| Column         | Type                         | Notes                                      |
|---------------|------------------------------|--------------------------------------------|
| id             | int unsigned, PK             | Auto-increment                             |
| name           | varchar(150), NOT NULL       | Display name (bad-words checked)           |
| domain         | varchar(255), UNIQUE, NULL   | **Canonical** domain – kept for backwards compatibility. New code should read from `tenant_domains`. |
| owner_user_id  | int unsigned, NOT NULL       | FK → users.id; the admin who owns the org  |
| status         | enum(active, suspended)      | Lifecycle state                            |
| created_at     | timestamp                    | Row creation time                          |
| updated_at     | timestamp                    | Auto-updated on any change                 |

### 2.2 `tenant_domains` (multi-domain registry)

**Design decision**: following the patterns of Shopify, WordPress Multisite, and
industry-standard SaaS platforms, each tenant can own *multiple* domains with
different roles.

| Column               | Type                                          | Notes                                      |
|---------------------|-----------------------------------------------|--------------------------------------------|
| id                   | bigint unsigned, PK                           |                                            |
| tenant_id            | int unsigned, FK → tenants.id ON DELETE CASCADE |                                          |
| domain               | varchar(255), UNIQUE                          | Globally unique (mirrors DNS uniqueness)   |
| type                 | enum(primary, custom, subdomain, alias)       | See §2.2.1                                 |
| is_verified          | tinyint(1)                                    | 1 = ownership proven via DNS/HTTP challenge|
| verification_token   | varchar(128), NULL                            | Used for DNS TXT / HTTP file challenge     |
| verified_at          | datetime, NULL                                | When verification succeeded                |
| ssl_status           | enum(none, pending, active, failed)           | TLS certificate lifecycle                  |
| ssl_expires_at       | datetime, NULL                                | Certificate expiry                         |
| redirect_to_primary  | tinyint(1)                                    | 301-redirect all requests to primary domain|
| meta                 | JSON, NULL                                    | Free-form extension bag (CDN config, geo…) |
| created_at / updated_at | timestamp                                | Standard audit timestamps                  |

#### 2.2.1 Domain Types

| Type      | Meaning                                           | Example                         |
|-----------|---------------------------------------------------|---------------------------------|
| primary   | Canonical URL (≤1 per tenant). Mirrors `tenants.domain`. | acme.example.com        |
| custom    | Customer-managed CNAME / A record                 | shop.acme.io                    |
| subdomain | Platform-generated subdomain                      | acme.platform.tld               |
| alias     | Vanity domain that 301-redirects to primary       | www.acme.io → acme.example.com  |

**Rules enforced by `TenantDomainsService`**:
- At most **one** `primary` domain per tenant.
- Downgrading a `primary` domain is blocked; another must be promoted first.
- Deleting a `primary` domain is blocked.
- All domain strings are normalised (protocol stripped, lowercased) before storage.

### 2.3 `tenant_users`

Junction table linking users to tenants with a role and optional entity scope.
Managed by `admin/fragments/tenant_users.php`.

### 2.4 `audit_logs`

Every create / update / delete / bulk-status mutation emits a row to `audit_logs`:

| Field        | Value                                 |
|-------------|---------------------------------------|
| entity_type  | `'tenant'`                            |
| entity_id    | Tenant primary key                    |
| action       | `tenant.create` / `tenant.update` / `tenant.delete` / `tenant.bulk_status` |
| old_values   | JSON snapshot BEFORE the change (update/delete only) |
| new_values   | JSON snapshot AFTER the change (create/update only)  |
| diff         | Auto-computed field-level diff `[{field,old,new}]`  |
| user_id      | Acting admin's ID                     |
| ip_address   | `$_SERVER['REMOTE_ADDR']`             |
| session_id   | PHP session ID                        |

Audit logging is **fault-tolerant**: a failure to write the log entry never
blocks the primary mutation.

---

## 3. Bad-Words Policy

Tenant `name` and `domain` are screened against the `bad_words` table before
any INSERT or UPDATE is persisted.

- Uses `BadWordsService::checkText()` which normalises unicode, strips diacritics,
  and expands leet-speak substitutions before matching.
- A `422 Unprocessable Entity` response is returned listing the detected words.
- Bad-words check failures (e.g. table not yet seeded) are logged but do **not**
  block the operation (graceful degradation).

---

## 4. Layer Architecture

```
HTTP Request
    │
    ▼
api/v1/routes/tenants.php          ← Route file (DI wiring)
    │
    ▼
TenantsController                  ← Thin HTTP adapter
    │
    ▼
TenantsService                     ← Business rules, audit, bad-words
    │         │              │
    ▼         ▼              ▼
PdoTenantsRepo  PdoAuditLogsRepo  BadWordsService
    │
    ▼
MySQL (tenants table)
```

Domain management follows the same pattern under `api/v1/models/tenant_domains/`.

### 4.1 Contract Layer

`api/v1/models/tenants/Contracts/TenantsRepositoryInterface.php` defines the
persistence contract. `PdoTenantsRepository` implements it.  
`api/v1/models/tenant_domains/Contracts/TenantDomainsRepositoryInterface.php`
defines the domain persistence contract.

This enables:
- Mock repositories for unit tests.
- Swapping the storage engine (e.g. Eloquent, Doctrine) without touching the
  service or controller layers.

---

## 5. Admin UI

`admin/fragments/tenant.php` — production fragment, four tabs:

| Tab       | Content                                                   |
|----------|-----------------------------------------------------------|
| Basic Info | Name, canonical domain, owner user ID, status           |
| Domains    | Inline CRUD for `tenant_domains` (verify, delete, add)  |
| Users      | Embeds `tenant_users.php?embedded=1&tenant_id=N`        |
| Addresses  | Embeds `addresses.php?embedded=1&owner_type=entity&tenant_id=N` |

**Theme**: All colours from CSS custom properties (`var(--primary-color)`,
`var(--text-primary)`, `var(--success-color)`, etc.) set by the DB theme loader.
Zero hardcoded hex values.

**Permissions**: Uses `admin_context.php` helpers (`is_admin_logged_in`,
`is_super_admin`, `can_create`, `can_edit_all`, `can_delete_all`, etc.).

**i18n**: Translation JSON files at `languages/Tenants/{lang}.json`.
Arabic (`ar`) and English (`en`) are shipped.

---

## 6. API Endpoints

### Tenants (`/api/tenants`)

| Method | Path                   | Description              |
|--------|------------------------|--------------------------|
| GET    | /api/tenants           | List (paginated, filtered)|
| GET    | /api/tenants/{id}      | Single tenant            |
| POST   | /api/tenants           | Create                   |
| PUT    | /api/tenants/{id}      | Update                   |
| DELETE | /api/tenants/{id}      | Delete                   |
| GET    | /api/tenants?action=stats | Aggregate stats       |
| GET    | /api/tenants/active    | All active tenants       |
| GET    | /api/tenants/by_domain?domain=X | Lookup by domain |
| POST   | /api/tenants?action=bulk-status | Bulk status change |

### Tenant Domains (`/api/tenant_domains`)

| Method | Path                             | Description        |
|--------|----------------------------------|--------------------|
| GET    | /api/tenant_domains?tenant_id=N  | List for a tenant  |
| GET    | /api/tenant_domains/{id}         | Single record      |
| POST   | /api/tenant_domains              | Create             |
| PUT    | /api/tenant_domains/{id}         | Update             |
| DELETE | /api/tenant_domains/{id}         | Delete (non-primary only) |
| POST   | /api/tenant_domains/{id}/verify  | Mark as verified   |
| POST   | /api/tenant_domains/{id}/ssl     | Update SSL status  |

---

## 7. Database Migration

Run `docs/migrations/create_tenant_domains_table.sql` against the database once.
The script is idempotent (`CREATE TABLE IF NOT EXISTS`) and auto-seeds existing
canonical domains from `tenants.domain` into `tenant_domains` as `type=primary`.

---

## 8. Security Considerations

- **No permissions logic** in the fragment – auth is fully delegated to
  `admin_context.php` helpers (consistent with `entities.php`).
- Audit snapshots **strip** sensitive columns (`password`, `token`, `secret`)
  before writing to `audit_logs`.
- All user-facing text (names, domains) passes through bad-words screening.
- PDO prepared statements used throughout – no string-interpolated SQL.
- `LEFT JOIN users` (not `INNER JOIN`) prevents data-loss bugs when an owner
  user account is deleted.
