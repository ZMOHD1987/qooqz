# Categories Module — Production Documentation

> **Version:** 1.0.0  
> **Last updated:** 2026-03-17  
> **Module path:** `api/v1/models/categories/`  
> **Route file:** `api/v1/routes/categories.php`  
> **Frontend fragment:** `admin/fragments/categories.php`  
> **Frontend JS:** `admin/assets/js/pages/categories.js`

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Database Schema](#database-schema)
4. [API Endpoints](#api-endpoints)
5. [Backend Architecture](#backend-architecture)
6. [Audit Logging](#audit-logging)
7. [SEO Auto-sync](#seo-auto-sync)
8. [Frontend Integration](#frontend-integration)
9. [Permissions](#permissions)
10. [Translations / i18n](#translations--i18n)
11. [Error Handling](#error-handling)

---

## Overview

The Categories module manages the hierarchical product taxonomy for a multi-tenant store platform.  
It is a **production-grade** module that provides:

- Hierarchical parent/child category trees
- Full multilingual translation support (name, description, slug, SEO meta)
- Image assignment per category
- Tenant-scoped data isolation
- Role-based access control
- Full audit logging with **before/after diffs** stored in `audit_logs`
- Automatic SEO meta generation via `SeoAutoManager`
- CSV/Excel import and export

---

## File Structure

```
api/v1/
├── models/categories/
│   ├── Contracts/
│   │   └── CategoriesRepositoryInterface.php   ← Interface (new)
│   ├── controllers/
│   │   ├── CategoriesController.php             ← HTTP layer
│   │   └── TenantCategoriesController.php       ← Tenant assignment sub-feature
│   ├── repositories/
│   │   ├── PdoCategoriesRepository.php          ← Data access (implements interface)
│   │   └── PdoTenantCategoriesRepository.php    ← Tenant-category join table
│   ├── services/
│   │   ├── CategoriesService.php                ← Business logic
│   │   └── TenantCategoriesService.php
│   └── validators/
│       ├── CategoriesValidator.php
│       └── TenantCategoriesValidator.php
└── routes/
    ├── categories.php                           ← Main REST route
    └── categories-tenants.php                  ← Tenant assignments route

admin/
├── fragments/categories.php                    ← PHP server fragment (embedded mode)
└── assets/js/pages/categories.js               ← SPA module

languages/
├── Categories/en.json                          ← English UI strings
└── Categories/ar.json                          ← Arabic UI strings

docs/
└── migrations/
    └── audit_logs_enhance.sql                  ← Schema migration (run once)
```

---

## Database Schema

### `categories` table

| Column       | Type         | Notes                                    |
|--------------|--------------|------------------------------------------|
| `id`         | INT PK AI    | Primary key                              |
| `tenant_id`  | INT NOT NULL | Tenant isolation                         |
| `parent_id`  | INT NULL     | Self-referencing hierarchy               |
| `slug`       | VARCHAR(255) | URL-safe identifier, unique per tenant   |
| `name`       | VARCHAR(255) | Default language name (fallback)         |
| `description`| TEXT NULL    | Default language description             |
| `sort_order` | INT          | Manual ordering (ASC)                    |
| `is_active`  | TINYINT(1)   | 1 = visible                              |
| `is_featured`| TINYINT(1)   | 1 = shown in featured sections           |
| `created_at` | DATETIME     | Auto-set on INSERT                       |
| `updated_at` | DATETIME     | Auto-set on UPDATE                       |

### `category_translations` table

| Column          | Type         | Notes                      |
|-----------------|--------------|----------------------------|
| `id`            | INT PK AI    |                            |
| `category_id`   | INT FK       | → categories.id            |
| `language_code` | VARCHAR(10)  | BCP-47 (e.g. `ar`, `en`)   |
| `name`          | VARCHAR(255) | Translated name            |
| `description`   | TEXT NULL    | Translated description     |
| `slug`          | VARCHAR(255) | Translated slug (optional) |
| `meta_title`    | VARCHAR(255) | SEO title                  |
| `meta_description` | TEXT NULL | SEO description            |
| `meta_keywords` | VARCHAR(500) | SEO keywords               |

### `tenant_categories` table (optional assignment filter)

| Column       | Type        | Notes                           |
|--------------|-------------|----------------------------------|
| `id`         | INT PK AI   |                                  |
| `tenant_id`  | INT         | Tenant FK                        |
| `category_id`| INT         | Category FK                      |
| `is_active`  | TINYINT(1)  | 1 = assigned to tenant           |

When any rows exist in `tenant_categories` for a tenant the repository
automatically restricts results to assigned categories only.

---

## API Endpoints

Base URL: `/api/categories`

All responses follow the shared `ResponseFormatter` structure:

```json
{
    "success": true,
    "data": { ... },
    "message": "...",
    "code": 200
}
```

### GET `/api/categories`

List categories with optional filters.

| Query param     | Type    | Default | Description                          |
|-----------------|---------|---------|--------------------------------------|
| `tenant_id`     | int     | 1       | Tenant context                       |
| `lang`          | string  | `ar`    | Translation language                 |
| `page`          | int     | 1       | Page number                          |
| `limit`         | int     | 25      | Page size (max 1000)                 |
| `parent_id`     | int     | —       | Filter by parent (0 = root only)     |
| `is_active`     | 0/1     | —       | Active/inactive filter               |
| `is_featured`   | 0/1     | —       | Featured filter                      |
| `search`        | string  | —       | Searches name/description            |
| `skip_tc_filter`| 0/1     | 0       | Skip tenant_categories join          |
| `format`        | string  | `json`  | `json` or `csv`                      |

**Response:**
```json
{
    "items": [ { "id": 1, "name": "Electronics", ... } ],
    "meta": { "total": 42, "page": 1, "per_page": 25, "total_pages": 2 }
}
```

### GET `/api/categories/tree`

Returns the full category tree for the tenant.

### GET `/api/categories/active`

Returns all active categories.

### GET `/api/categories/featured`

Returns all featured categories.

### GET `/api/categories/{id}`

Returns a single category with its translations.

| Query param       | Description                                |
|-------------------|--------------------------------------------|
| `lang`            | Language for default translation           |
| `all_translations`| `1` to embed all translations in response |

### POST `/api/categories/validate-slug`

Validate slug uniqueness before saving.

**Body:**
```json
{ "slug": "electronics", "exclude_id": 5 }
```

### POST `/api/categories` — Create

**Body:**
```json
{
    "name": "Electronics",
    "slug": "electronics",
    "description": "...",
    "parent_id": null,
    "is_active": 1,
    "is_featured": 0,
    "sort_order": 0,
    "image_id": 12,
    "translations": [
        { "language_code": "ar", "name": "إلكترونيات", "slug": "electronics-ar" }
    ]
}
```

### PUT `/api/categories` — Update

Same body as POST plus `"id": 5`.

### DELETE `/api/categories/{id}`

Hard-deletes the category (fails with 422 if it has sub-categories).

### DELETE `/api/categories/{id}/translations/{lang}`

Removes a single translation without deleting the category.

### POST `/api/categories/bulk`

Bulk operations.

**Body:**
```json
{ "action": "activate|deactivate|delete", "ids": [1, 2, 3] }
```

---

## Backend Architecture

### Layer responsibilities

```
Route (categories.php)
  └── CategoriesController        ← HTTP parsing, session access
        └── CategoriesService     ← Business rules, tree building, bulk ops
              └── PdoCategoriesRepository ← PDO queries, transactions, audit log
```

### CategoriesRepositoryInterface

`Contracts/CategoriesRepositoryInterface.php` declares all public methods.  
`PdoCategoriesRepository` implements this interface.  
The service depends on the interface type, enabling swapping of the concrete
implementation (e.g. for an in-memory stub in tests).

---

## Audit Logging

Every mutating operation (create, update, delete) writes a row to `audit_logs`
via `AuditLogsService::log()` (loaded by the route).

The repository's private `logAction()` method:
1. Strips sensitive fields (`password`, `token`, `api_key`, …)
2. Calls `AuditLogsService::log('category.{action}', ...)` with:
   - `old_values` — full snapshot before the change
   - `new_values` — full snapshot after the change
   - `diff` — auto-computed by `PdoAuditLogsRepository::computeDiff()`
3. Falls back to a direct INSERT using the new schema columns if `AuditLogsService` is not loaded

### Audit log fields written per operation

| Operation | `old_values` | `new_values` | `diff`          |
|-----------|-------------|-------------|-----------------|
| create    | `NULL`       | full record  | `NULL`          |
| update    | before state | after state  | field-level diff|
| delete    | full record  | `NULL`       | `NULL`          |

### Sample audit log row (update)

```json
{
    "action": "category.update",
    "entity_type": "category",
    "entity_id": 12,
    "old_values": { "name": "Old Name", "is_active": 1 },
    "new_values": { "name": "New Name", "is_active": 0 },
    "diff": [
        { "field": "name",      "old": "Old Name", "new": "New Name" },
        { "field": "is_active", "old": "1",        "new": "0" }
    ],
    "http_method": "PUT",
    "http_url": "/api/categories",
    "session_id": "abc123...",
    "request_id": "6a06883b-b467-40bd-a9c1-d4f83766d346"
}
```

---

## SEO Auto-sync

On every create, update, and delete the route calls `SeoAutoManager`:

```php
SeoAutoManager::sync($pdo, 'category', $catId, ['name', 'slug', 'description', ...]);
SeoAutoManager::syncAllTranslations($pdo, 'category', $catId);
// On delete:
SeoAutoManager::delete($pdo, 'category', $catId);
```

SEO sync failures are caught silently and do not abort the main operation.

---

## Frontend Integration

### Fragment mode (`admin/fragments/categories.php`)

The fragment is embedded inside the admin SPA shell via an AJAX load.
It:
- Reads the PHP session for `$tenantId`, `$lang`, `$dir`, `$csrf`, permissions
- Outputs `window.CATEGORIES_CONFIG` and `window.APP_CONFIG` globals
- Loads translation JSON from `/languages/Categories/{lang}.json`
- Initialises `window.Categories.init()` after both `AdminFramework` and the module are ready

### Standalone mode

When loaded directly (not via AJAX embed), the fragment includes `header.php`
and `footer.php` automatically.

### JS module (`admin/assets/js/pages/categories.js`)

Exports `window.Categories` with the following public methods:

| Method | Description |
|--------|-------------|
| `init()` | Bootstrap: load translations, build UI, fetch first page |
| `loadCategories()` | Fetch and render the current page |
| `openCreateModal()` | Open add-category form |
| `openEditModal(id)` | Open edit form for an existing category |
| `deleteCategory(id)` | Confirm + DELETE request |
| `bulkAction(action)` | Run bulk activate/deactivate/delete |
| `importExcel(file)` | Parse and batch-import from XLSX/CSV |

---

## Permissions

The fragment checks the following capabilities (defined in the admin RBAC system):

| Capability               | Usage |
|--------------------------|-------|
| `categories.manage`      | Master permission for create/edit |
| `categories.create`      | Create new categories |
| `categories.view_all`    | List all tenant categories |
| `categories.edit_all`    | Edit any category |
| `categories.delete_all`  | Delete any category |
| `categories.edit_own`    | Edit own categories only |
| `categories.delete_own`  | Delete own categories only |

Super admins bypass all permission checks.

---

## Translations / i18n

Language files are stored in `languages/Categories/{lang}.json`.  
The JS module fetches the file at runtime and applies it via `data-i18n` attributes.

Top-level keys:

| Key            | Description                     |
|----------------|---------------------------------|
| `categories`   | Page title and breadcrumb labels |
| `table`        | Column headers and empty states |
| `filters`      | Filter bar labels               |
| `form`         | Form field labels and hints     |
| `messages`     | Toast / alert messages          |
| `validation`   | Validation error messages       |
| `common`       | Shared (save, cancel, delete…)  |
| `accessibility`| Screen-reader hints             |
| `pagination`   | Pagination labels               |
| `excel`        | Import/export UI strings        |

---

## Error Handling

| HTTP Status | Cause |
|-------------|-------|
| `400` | Bad request / missing required field |
| `401` | Not authenticated |
| `403` | Insufficient permissions |
| `404` | Category not found |
| `405` | HTTP method not supported |
| `422` | Validation failure (JSON body with `errors` array) |
| `500` | Unexpected server error |

All errors follow `ResponseFormatter`:
```json
{ "success": false, "message": "...", "code": 422, "data": null }
```

Validation errors additionally include the `errors` object:
```json
{
    "success": false,
    "message": { "slug": "Slug is required", "name": "Name is required" },
    "code": 422
}
```
