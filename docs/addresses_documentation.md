# Addresses Module — Complete Technical Documentation

**Version**: 2.0 · **Author**: Platform Team · **Date**: 2026-03  
**Branch**: `copilot/fix-dashboard-php-errors`

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Permission System](#3-permission-system)
4. [API Endpoints](#4-api-endpoints)
5. [Backend Architecture](#5-backend-architecture)
   - 5.1 [Route — `api/v1/routes/addresses.php`](#51-route)
   - 5.2 [Repository — `PdoAddressesRepository`](#52-repository)
   - 5.3 [Service — `AddressesService`](#53-service)
   - 5.4 [Validator — `AddressesValidator`](#54-validator)
   - 5.5 [Controller — `AddressesController`](#55-controller)
6. [Frontend — PHP Fragment](#6-frontend--php-fragment)
7. [Frontend — JavaScript Module](#7-frontend--javascript-module)
8. [Frontend — CSS Stylesheet](#8-frontend--css-stylesheet)
9. [Design Tokens (DB Theme)](#9-design-tokens-db-theme)
10. [Error Reference](#10-error-reference)
11. [File Map](#11-file-map)

---

## 1. System Overview

The **Addresses** sub-system stores physical postal addresses for two types of
owners: platform users (`owner_type = 'user'`) and entities/branches
(`owner_type = 'entity'`). Each owner may have multiple addresses, with one
flagged as primary.

Key capabilities:
- Country + city selection with translated names (loaded from `country_translations` / `city_translations`)
- GPS coordinates (latitude + longitude) with browser geolocation support
- Translation panels for multi-language address labels
- Tenant-scoped views: tenant admins see addresses for their own entities;
  super-admins can view and edit all

---

## 2. Database Schema

### 2.1 `addresses` (core table)

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | PRI | auto_increment | Row identifier |
| `owner_type` | enum(`user`, `entity`) | NO | | `user` | The type of owner |
| `owner_id` | bigint unsigned | NO | MUL | — | FK to `users.id` or `entities.id` depending on `owner_type` |
| `tenant_id` | int unsigned | NO | MUL | — | Tenant scope |
| `country_id` | int unsigned | YES | MUL | NULL | FK → `countries.id` |
| `city_id` | int unsigned | YES | MUL | NULL | FK → `cities.id` |
| `address_line1` | varchar(255) | NO | | — | Primary address line (required) |
| `address_line2` | varchar(255) | YES | | NULL | Optional second address line |
| `postal_code` | varchar(20) | YES | | NULL | Postal / ZIP code |
| `latitude` | decimal(10,8) | YES | | NULL | GPS latitude |
| `longitude` | decimal(11,8) | YES | | NULL | GPS longitude |
| `is_primary` | tinyint(1) | NO | | 0 | 1 = primary address |
| `created_at` | timestamp | NO | | CURRENT_TIMESTAMP | Creation time |
| `updated_at` | timestamp | NO | | CURRENT_TIMESTAMP | Auto-updated |

### 2.2 Joined / computed columns returned by API

| Alias | Source | Notes |
|---|---|---|
| `country_name` | `COALESCE(country_translations.name, countries.name)` | Translated or fallback name |
| `city_name` | `COALESCE(city_translations.name, cities.name)` | Translated or fallback name |

The translation language is controlled by the `language` / `lang` query param
(default: `"ar"`).

### 2.3 Related tables

| Table | Relationship | Purpose |
|---|---|---|
| `users` | `addresses.owner_id → users.id` (when `owner_type=user`) | Personal user address |
| `entities` | `addresses.owner_id → entities.id` (when `owner_type=entity`) | Business location |
| `tenants` | `addresses.tenant_id → tenants.id` | Tenant scope |
| `countries` | `addresses.country_id → countries.id` | Country reference |
| `country_translations` | FK `country_id` + `language_code` | i18n country names |
| `cities` | `addresses.city_id → cities.id` | City reference |
| `city_translations` | FK `city_id` + `language_code` | i18n city names |

---

## 3. Permission System

### 3.1 PHP variables

| Variable | Type | Description |
|---|---|---|
| `$canCreate` | bool | Create new address |
| `$canEdit` | bool | Edit existing address |
| `$canDelete` | bool | Delete address |
| `$canEditAllFields` | bool | Super-admin can set `owner_type` and `owner_id` freely |
| `$tenantMode` | bool | True when user is in tenant scope (not super-admin) |
| `$ownerId` | int\|null | Fixed owner_id for non-super-admin, non-tenant contexts |
| `$ownerType` | string | Fixed owner_type for non-super-admin contexts |
| `$tenantId` | int | Current session tenant ID |

### 3.2 View modes

| Mode | `$canEditAllFields` | Form behavior |
|---|---|---|
| Super-Admin | `true` | Shows owner_type select + owner_id numeric input |
| Tenant Mode | `false` | Shows entity `<select>` (populated from tenant's entities) |
| Entity/User fixed | `false` | Hidden fields with pre-set owner |

---

## 4. API Endpoints

Base path: `/api/addresses`

`tenant_id` is derived from `$_SESSION['tenant_id']` or `?tenant_id=` GET param.

### 4.1 `GET /api/addresses`

List addresses with optional filters and pagination.

**Query parameters**:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | int | 1 | Page number |
| `limit` | int | 25 | Items per page (max 1000) |
| `order_by` | string | `id` | Sort column: `id`, `owner_type`, `owner_id`, `city_id`, `country_id`, `is_primary`, `created_at`, `updated_at` |
| `order_dir` | string | `DESC` | `ASC` or `DESC` |
| `language` / `lang` | string | `ar` | Translation language code |
| `id` | int | — | Filter by specific address ID |
| `owner_type` | string | — | Filter by owner type: `user` or `entity` |
| `owner_id` | int | — | Filter by owner ID |
| `city_id` | int | — | Filter by city |
| `country_id` | int | — | Filter by country |
| `is_primary` | 0\|1 | — | Filter by primary status |
| `filter_tenant_id` | int | — | Filter all entity addresses for a tenant (super-admin / tenant mode) |

**Success response**:
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1, "owner_type": "entity", "owner_id": 7, "tenant_id": 2,
        "country_id": 1, "country_name": "المملكة العربية السعودية",
        "city_id": 5, "city_name": "الرياض",
        "address_line1": "شارع الملك فهد", "address_line2": null,
        "postal_code": "12345", "latitude": "24.68960", "longitude": "46.72185",
        "is_primary": 1, "created_at": "2026-01-10 09:00:00", "updated_at": "2026-01-10 09:00:00"
      }
    ],
    "meta": {
      "total": 100, "page": 1, "per_page": 25, "total_pages": 4,
      "from": 1, "to": 25
    }
  }
}
```

### 4.2 `GET /api/addresses/{id}` or `GET /api/addresses?id={id}`

Get a single address by ID.

**Query params**: `language` / `lang` for translated names.

**Error**: `404` if not found.

### 4.3 `POST /api/addresses`

Create a new address.

**Request body** (JSON):

| Field | Type | Required | Notes |
|---|---|---|---|
| `owner_type` | string | YES | `user` or `entity` |
| `owner_id` | int | YES | User or entity ID |
| `country_id` | int | YES | Country reference |
| `city_id` | int | YES | City reference |
| `address_line1` | string | YES | Primary address line |
| `address_line2` | string | NO | Optional second line |
| `postal_code` | string | NO | Postal code |
| `latitude` | string | NO | GPS latitude (decimal) |
| `longitude` | string | NO | GPS longitude (decimal) |
| `is_primary` | 0\|1 | NO | Default: 0 |
| `csrf_token` | string | YES | CSRF protection |

**Success response**: `201` + `{ "id": 42 }`.

### 4.4 `PUT /api/addresses/{id}` or body `{ "id": … }`

Update an existing address. Accepts the same fields as POST; all are optional
except that `id` must be present.

**Success response**: `200` + `{ "updated": true }`.

### 4.5 `DELETE /api/addresses/{id}`

Delete an address.

**Request body**: `{ "id": 42 }`.

**Success response**: `200` + `{ "deleted": true }`.

---

## 5. Backend Architecture

### 5.1 Route

**File**: `api/v1/routes/addresses.php`

- Determines tenant from `?tenant_id` GET param or session
- Passes `language` / `lang` param to list/get calls for translated country+city names
- Supports `filter_tenant_id` GET param to fetch all entity addresses for a tenant
- `OPTIONS` handler for CORS pre-flight

### 5.2 Repository

**File**: `api/v1/models/addresses/repositories/PdoAddressesRepository.php`  
**Class**: `PdoAddressesRepository`

| Method | Signature | Description |
|---|---|---|
| `list` | `(int $limit, int $offset, array $filters, string $orderBy, string $orderDir): array` | Returns `{ items: [...], total: N }` |
| `get` | `(int $id, string $language): ?array` | Single row with translated country/city names |
| `create` | `(array $data): int` | Inserts row, returns new ID |
| `update` | `(int $id, array $data): bool` | Updates row |
| `delete` | `(int $id): bool` | Deletes row |

**Allowed ORDER BY columns**: `id`, `owner_type`, `owner_id`, `city_id`,
`country_id`, `is_primary`, `created_at`, `updated_at`.

**Tenant filter behaviour**: When `filters['tenant_id']` is set, the WHERE
clause uses a subquery:
```sql
WHERE (a.owner_type = 'entity'
  AND a.owner_id IN (SELECT id FROM entities WHERE tenant_id = :filter_tenant_id))
```

### 5.3 Service

**File**: `api/v1/models/addresses/services/AddressesService.php`  
**Class**: `AddressesService`

Thin orchestration layer that calls repository methods. Key responsibility:
ensuring `tenant_id` is stamped on creates.

### 5.4 Validator

**File**: `api/v1/models/addresses/validators/AddressesValidator.php`  
**Class**: `AddressesValidator`

| Method | Purpose |
|---|---|
| `validateCreate(array $data)` | Throws `InvalidArgumentException` if required fields missing |
| `validateUpdate(array $data)` | Throws if `id` missing or fields invalid |

**Required fields for create**: `owner_type`, `owner_id`, `country_id`,
`city_id`, `address_line1`.

### 5.5 Controller

**File**: `api/v1/models/addresses/controllers/AddressesController.php`  
**Class**: `AddressesController`

| Method | Delegates to |
|---|---|
| `list(int $limit, int $offset, array $filters, ...)` | `service->list` |
| `get(int $id, string $language)` | `service->get` |
| `create(array $data)` | `service->create` |
| `update(int $id, array $data)` | `service->update` |
| `delete(int $id)` | `service->delete` |

---

## 6. Frontend — PHP Fragment

**File**: `admin/fragments/addresses.php`

### 6.1 Load modes

| Mode | Trigger | Header loaded |
|---|---|---|
| Standalone | Direct browser navigation | `admin/includes/header.php` |
| Fragment / AJAX | `HTTP_X_REQUESTED_WITH: XMLHttpRequest` | `admin/includes/admin_context.php` |
| Embedded | `?embedded` query param | `admin/includes/admin_context.php` |

### 6.2 PHP variables available in template

| Variable | Type | Description |
|---|---|---|
| `$user` | array | Authenticated admin user |
| `$lang` | string | Language code (e.g. `"ar"`, `"en"`) |
| `$dir` | string | Text direction (`"rtl"` or `"ltr"`) |
| `$csrf` | string | CSRF token |
| `$tenantId` | int | Current session tenant ID |
| `$canCreate` | bool | Create permission |
| `$canEdit` | bool | Edit permission |
| `$canDelete` | bool | Delete permission |
| `$canEditAllFields` | bool | Super-admin full field control |
| `$tenantMode` | bool | True when tenant context (entity picker shown) |
| `$ownerId` | int\|null | Pre-set owner_id (non-super contexts) |
| `$ownerType` | string | Pre-set owner_type |
| `$entitiesApi` | string | API URL for loading entities dropdown |

### 6.3 HTML element IDs

| Element ID | Purpose |
|---|---|
| `addressesPage` | Outermost container (CSS scope root) |
| `btnAddAddress` | Opens the add form |
| `addressFormCard` | Add/Edit card (hidden by default) |
| `addressFormTitle` | `<h3>` heading — updated by JS |
| `btnCloseForm` | Closes the form |
| `addressForm` | The `<form>` element |
| `addressId` | Hidden field — empty for create, numeric for edit |
| `entitySelect` | Entity dropdown (tenant mode only) |
| `ownerTypeSelect` | Owner type select (super-admin only) |
| `ownerIdInput` | Owner ID numeric input (super-admin only) |
| `ownerTypeHidden` | Hidden owner_type (non-super, non-tenant) |
| `ownerIdHidden` | Hidden owner_id (non-super, non-tenant) |
| `countrySelect` | Country dropdown |
| `citySelect` | City dropdown (disabled until country selected) |
| `latitude` | GPS latitude input |
| `longitude` | GPS longitude input |
| `btnGetLocation` | Geolocation button |
| `btnDeleteAddress` | Delete button (hidden, shown in edit mode) |
| `addressesTable` | The `<table>` |
| `paginationInfo` | "Showing X–Y of Z" text |
| `pagination` | Pagination button container |

### 6.4 Client-side globals emitted

```js
window.ADDRESSES_CONFIG = {
    apiBase:      '/api',
    tenantId:     2,
    csrfToken:    '...',
    lang:         'ar',
    dir:          'rtl',
    canCreate:    true,
    canEdit:      true,
    canDelete:    false,
    canEditAllFields: false,
    tenantMode:   true,
    ownerId:      null,
    ownerType:    'entity',
    entitiesApi:  '/api/entities?tenant_id=2'
}
```

---

## 7. Frontend — JavaScript Module

**File**: `admin/assets/js/pages/addresses.js`  
**Global**: `window.Addresses`

### 7.1 Public API

| Method | Description |
|---|---|
| `init()` | Bootstrap: bind events, load initial data |
| `load(page)` | Fetch addresses from API and render table |
| `add()` | Show empty form for new address |
| `edit(id)` | Fetch address by ID and populate form |
| `remove(id)` | Confirm then DELETE address |

### 7.2 API helpers

| Function | Endpoint | Description |
|---|---|---|
| Load countries | `GET /api/countries?tenant_id=X&lang=Y` | Populate country `<select>` |
| Load cities | `GET /api/cities?country_id=X&lang=Y` | Populate city `<select>` on country change |
| Load entities | `GET /api/entities?tenant_id=X` | Populate entity `<select>` (tenant mode) |
| Get location | Browser `navigator.geolocation.getCurrentPosition` | Fill latitude/longitude fields |

### 7.3 Table rendering

`renderTable(items)` generates `<tbody>` HTML. Per-row classes (CSS-only):

| Element | CSS class(es) |
|---|---|
| Primary badge | `badge badge-active` |
| Non-primary badge | `badge badge-inactive` |
| Edit button | `btn btn-sm btn-outline` |
| Delete button | `btn btn-sm btn-danger` |
| Actions wrapper | `table-actions` |

---

## 8. Frontend — CSS Stylesheet

**File**: `admin/assets/css/pages/addresses.css`  
**Scope root**: `#addressesPage`

### 8.1 Design principles

1. **All colours from DB** — `var(--xxx)` for every colour declaration.
2. **`color-mix()` for transparency** — replaces raw `rgba()`.
3. **Fully scoped** — every selector prefixed `#addressesPage`.
4. **RTL-ready** — `[dir="rtl"] #addressesPage ...` rules.
5. **Responsive**: tablet ≤1024 px, mobile ≤768 px, small phone ≤480 px.
6. **Print**: hides form, action buttons, geolocation controls.

### 8.2 CSS variables consumed

| Variable | DB source | Usage |
|---|---|---|
| `--primary-color` | `color_settings.primary_color` | Buttons, focus borders, active pagination, translation panel header |
| `--primary-hover` | `color_settings.primary_hover` | btn-primary hover |
| `--secondary-color` | `color_settings.secondary_color` | btn-secondary |
| `--danger-color` | `color_settings.danger_color` | btn-danger, validation errors, inactive badge |
| `--success-color` | `color_settings.success_color` | active/primary badge |
| `--warning-color` | `color_settings.warning_color` | warning indicators |
| `--background-secondary` | `color_settings.background_secondary` | Page header panel |
| `--card-bg` | `color_settings.card_bg` | Card surfaces, translation panel |
| `--input-bg` | `color_settings.input_bg` | Inputs, selects, textareas |
| `--thead-bg` | `color_settings.thead_bg` | Table header row |
| `--text-primary` | `color_settings.text_primary` | Body text |
| `--text-secondary` | `color_settings.text_secondary` | Labels, muted text |
| `--text-on-primary` | *(fallback `#fff`)* | Text on coloured surfaces |
| `--border-color` | `color_settings.border_color` | All borders |
| `--body-font-family` | `font_settings` (body) | All text |
| `--border-radius` | `design_settings.border_radius` | Cards, inputs, buttons |

### 8.3 Key CSS class inventory

| Class | Scope | Purpose |
|---|---|---|
| `.page-header` | `#addressesPage .page-header` | Title + Add button panel |
| `.page-title` | `… .page-title` | `<h1>` |
| `.page-subtitle` | `… .page-subtitle` | Subtitle paragraph |
| `.card` | `… .card` | Generic card container |
| `.card-header` | `… .card-header` | Card top bar |
| `.card-body` | `… .card-body` | Card content area |
| `.form-row` | `… .form-row` | Responsive form grid |
| `.form-group` | `… .form-group` | Label + input column |
| `.form-control` | `… .form-control` | Inputs/selects/textareas |
| `.invalid-feedback` | `… .invalid-feedback` | Validation error |
| `.form-actions` | `… .form-actions` | Save/Delete button bar |
| `.filters-grid` | `… .filters-grid` | Filter controls grid |
| `.data-table` | `… .data-table` | Full-width table |
| `.table-responsive` | `… .table-responsive` | Horizontal scroll wrapper |
| `.table-actions` | `… .table-actions` | Per-row action flex row |
| `.badge` | `… .badge` | Pill label |
| `.badge-active` | `… .badge-active` | Green — primary address |
| `.badge-inactive` | `… .badge-inactive` | Red — non-primary |
| `.loading-state` | `… .loading-state` | Spinner + loading message |
| `.empty-state` | `… .empty-state` | No-results message |
| `.error-state` | `… .error-state` | Error message |
| `.spinner` | `… .spinner` | CSS-only rotating ring |
| `.pagination-wrapper` | `… .pagination-wrapper` | Pagination bar |
| `.pagination-info` | `… .pagination-info` | "Showing X–Y of Z" text |
| `.pagination` | `… .pagination` | Button container |
| `.page-btn` | `… .page-btn` | Individual pagination button |
| `.page-ellipsis` | `… .page-ellipsis` | "..." span |
| `.super-admin-notice` | `… .super-admin-notice` | Themed super-admin info box |
| `.translation-panel` | `… .translation-panel` | Translation card |
| `.translation-panel-header` | `… .translation-panel-header` | Panel title bar |
| `.translations-section` | `… .translations-section` | Tinted translations wrapper |
| `.results-count` | `… .results-count` | "N results" animated label |

### 8.4 Keyframe names (namespaced)

| Keyframe | Purpose |
|---|---|
| `addr-spin` | Loading spinner rotation |
| `addr-fade-in` | Results count fade-in |

---

## 9. Design Tokens (DB Theme)

All visual tokens are injected by `admin/includes/theme_injector.php` as CSS
custom properties on `:root`. Origins:

| DB table | CSS variables |
|---|---|
| `color_settings` | `--primary-color`, `--danger-color`, `--success-color`, `--card-bg`, etc. |
| `design_settings` | `--border-radius`, `--container-width`, `--header-height` |
| `font_settings` | `--body-font-family`, `--heading-font-family` |

**Rule**: Never hard-code hex colours in `addresses.css`. Use `var(--xxx)`
exclusively. Use `color-mix(in srgb, var(--xxx) N%, transparent)` for
transparency.

---

## 10. Error Reference

| HTTP | Scenario | Resolution |
|---|---|---|
| 401 | Session expired or `tenant_id` not found | Re-login; ensure session contains `tenant_id` |
| 404 | Address not found | Verify ID exists in `addresses` table |
| 422 | Validation failed | Check `errors` in response body |
| 500 | DB error or missing column | Check PHP error log; verify `addresses` schema |

---

## 11. File Map

```
admin/
├── fragments/
│   └── addresses.php                 ← PHP template / HTML fragment
├── assets/
│   ├── css/pages/
│   │   └── addresses.css             ← Complete CSS stylesheet (this module)
│   └── js/pages/
│       └── addresses.js              ← Frontend JS module (window.Addresses)
└── includes/
    ├── admin_context.php             ← Helper functions (admin_user, can, …)
    ├── header.php                    ← Standalone page header
    └── theme_injector.php            ← DB → CSS variable emitter

api/
└── v1/
    ├── routes/
    │   └── addresses.php             ← HTTP router
    └── models/addresses/
        ├── controllers/
        │   └── AddressesController.php
        ├── services/
        │   └── AddressesService.php
        ├── repositories/
        │   └── PdoAddressesRepository.php
        └── validators/
            └── AddressesValidator.php

docs/
└── addresses_documentation.md       ← This file
```
