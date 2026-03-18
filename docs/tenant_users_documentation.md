# Tenant Users Module — Complete Technical Documentation

**Version**: 2.0 · **Author**: Platform Team · **Date**: 2026-03  
**Branch**: `copilot/fix-dashboard-php-errors`

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Permission System](#3-permission-system)
4. [API Endpoints](#4-api-endpoints)
5. [Backend Architecture](#5-backend-architecture)
   - 5.1 [Route — `api/v1/routes/tenant_users.php`](#51-route)
   - 5.2 [Repository — `PdoTenant_usersRepository`](#52-repository)
   - 5.3 [Service — `Tenant_usersService`](#53-service)
   - 5.4 [Validator — `Tenant_usersValidator`](#54-validator)
   - 5.5 [Controller — `Tenant_usersController`](#55-controller)
6. [Frontend — PHP Fragment](#6-frontend--php-fragment)
7. [Frontend — JavaScript Module](#7-frontend--javascript-module)
8. [Frontend — CSS Stylesheet](#8-frontend--css-stylesheet)
9. [Translation System](#9-translation-system)
10. [Design Tokens (DB Theme)](#10-design-tokens-db-theme)
11. [Error Reference](#11-error-reference)
12. [File Map](#12-file-map)

---

## 1. System Overview

The **Tenant Users** sub-system manages the many-to-many relationship between
platform users and tenants. A single user can belong to multiple tenants, and
within each membership they carry a specific role and optional entity
(store/branch) assignment.

| Domain | Description |
|---|---|
| **Membership** | A row in `tenant_users` connecting `users.id` → `tenants.id` |
| **Role** | The permission role the user has within that tenant (FK → `roles.id`) |
| **Entity** | Optional branch/store assignment within the tenant (FK → `entities.id`) |
| **Scope** | All reads & writes are scoped to `tenant_id` derived from the authenticated session |

---

## 2. Database Schema

### 2.1 `tenant_users` (core table)

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | PRI | auto_increment | Row identifier |
| `tenant_id` | int unsigned | NO | MUL | — | FK → `tenants.id` ON DELETE CASCADE |
| `user_id` | int unsigned | NO | MUL | — | FK → `users.id` ON DELETE CASCADE |
| `role_id` | int unsigned | YES | MUL | NULL | FK → `roles.id` ON DELETE SET NULL |
| `entity_id` | bigint unsigned | YES | MUL | NULL | FK → `entities.id` ON DELETE SET NULL |
| `joined_at` | timestamp | NO | | CURRENT_TIMESTAMP | Membership creation time |
| `is_active` | tinyint(1) | NO | | 1 | 0 = suspended, 1 = active |
| `updated_at` | timestamp | NO | | CURRENT_TIMESTAMP | Auto-updated on any change |

**Unique constraint**: `UNIQUE KEY (tenant_id, user_id)` — one membership row per user per tenant.

### 2.2 Joined columns returned by API

The repository JOIN-selects these additional display columns:

| Alias | Source | Notes |
|---|---|---|
| `username` | `users.username` | Display name of the user |
| `email` | `users.email` | User email |
| `tenant_name` | `tenants.name` | Tenant display name |
| `role_name` | `COALESCE(roles.display_name, roles.key_name, '')` | Human-readable role label |
| `entity_name` | `entities.store_name` | Store/branch display name (nullable) |
| `entity_slug` | `entities.slug` | Store URL slug (nullable) |

### 2.3 Related tables

| Table | Relationship | Purpose |
|---|---|---|
| `users` | `tenant_users.user_id → users.id` | The platform user |
| `tenants` | `tenant_users.tenant_id → tenants.id` | The organisational tenant |
| `roles` | `tenant_users.role_id → roles.id` | Permission role within the tenant |
| `entities` | `tenant_users.entity_id → entities.id` | Store/branch within the tenant |

---

## 3. Permission System

The fragment implements **dual-layer** permission control.

### 3.1 Role-based permissions (`permissions` + `role_permissions`)

| Permission key | Purpose |
|---|---|
| `tenant_users.manage` | Full management access |
| `tenant_users.create` | Create new memberships |
| `tenant_users.view` | View memberships |
| `tenant_users.edit` | Edit existing memberships |
| `tenant_users.delete` | Delete memberships |

### 3.2 Resource-based permissions (`resource_permissions`)

| Resource permission | Purpose |
|---|---|
| `can_view_all` | View all tenant users across **all** tenants (super-admin only) |
| `can_view_tenant` | View all users within the **current** tenant |
| `can_view_own` | View only the authenticated user's own membership record |
| `can_create` | Create new tenant user assignments |
| `can_edit_all` | Edit any tenant user |
| `can_edit_own` | Edit only the authenticated user's own record |
| `can_delete_all` | Delete any tenant user |
| `can_delete_own` | Delete only the authenticated user's own record |

### 3.3 Data filtering by permission level

| Session state | Filter applied |
|---|---|
| `is_super_admin()` | No tenant filter — can see all tenants |
| `can_view_all` | No tenant filter |
| `can_view_tenant` | Results scoped to `tenant_id = session.tenant_id` |
| `can_view_own + entity_id set` | Results scoped to `entity_id` + `tenant_id` |
| `can_view_own + no entity` | Results scoped to `user_id` + `tenant_id` |
| No permissions configured | Fallback: scoped to `tenant_id` (graceful degradation) |

### 3.4 PHP variables exposed to the view

```php
$canView         // true if user can view any record
$canCreate       // true if user can create
$canEdit         // true if user can edit any record
$canDelete       // true if user can delete any record
$canViewAll      // resource-level view-all
$canViewOwn      // resource-level view-own
$canViewTenant   // resource-level view-tenant
$canEditAll      // resource-level edit-all
$canEditOwn      // resource-level edit-own
$canDeleteAll    // resource-level delete-all
$canDeleteOwn    // resource-level delete-own
```

---

## 4. API Endpoints

Base path: `/api/tenant_users`

All endpoints require:
- An authenticated admin session (`$_SESSION['admin_id']`)
- The `tenant_id` is derived from `$_SESSION['tenant_id']`

### 4.1 `GET /api/tenant_users`

List memberships with optional filters and pagination.

**Query parameters**:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | int | 1 | Page number |
| `per_page` | int | 10 | Items per page |
| `search` | string | — | Full-text search across username, email, tenant name, entity name |
| `tenant_id` | int | session | Filter by specific tenant ID (super-admin only) |
| `user_id` | int | — | Filter by user ID |
| `entity_id` | int | — | Filter by entity ID |
| `role_id` | int | — | Filter by role ID |
| `is_active` | 0\|1 | — | Filter by active status |

**Success response**:
```json
{
  "success": true,
  "data": {
    "items": [ { "id": 1, "user_id": 5, "username": "john", "email": "john@x.com",
                 "tenant_id": 2, "tenant_name": "ACME", "role_id": 3,
                 "role_name": "Manager", "entity_id": 7, "entity_name": "Branch 1",
                 "joined_at": "2026-01-15 10:00:00", "is_active": 1 } ],
    "meta": { "total": 42, "page": 1, "per_page": 10, "pages": 5 }
  }
}
```

### 4.2 `GET /api/tenant_users/{id}`

Get a single membership by ID.

**Success response**: same shape as one item from the list.

**Error**: `404` if not found.

### 4.3 `POST /api/tenant_users`

Create a new membership (or update if membership already exists — upsert).

**Request body** (JSON):

| Field | Type | Required | Notes |
|---|---|---|---|
| `tenant_id` | int | YES | Target tenant ID |
| `user_id` | int | YES | Target user ID |
| `role_id` | int | YES | Role to assign |
| `entity_id` | int | NO | Optional entity/branch assignment |
| `is_active` | 0\|1 | NO | Default: 1 |
| `csrf_token` | string | YES | CSRF protection token |

**Success response**: `201` + created/updated row.

**Validation errors**: `422` + `{ "errors": { "user_id": "..." } }`.

### 4.4 `PUT /api/tenant_users/{id}`

Update an existing membership.

**Request body** (JSON):

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | int | YES | Membership ID |
| `role_id` | int | NO | New role (omit to keep current) |
| `entity_id` | int\|null | NO | New entity assignment (null to clear) |
| `is_active` | 0\|1 | NO | New status |

**Success response**: `200` + updated row.

### 4.5 `DELETE /api/tenant_users/{id}`

Delete a membership.

**Request body** (JSON): `{ "id": 123 }`

**Success response**: `200` + `{ "deleted": true }`.

---

## 5. Backend Architecture

### 5.1 Route

**File**: `api/v1/routes/tenant_users.php`

Bootstraps dependencies, enforces session-based permission scoping, routes HTTP
methods to the controller, and handles all exceptions:

| Exception | HTTP status |
|---|---|
| `InvalidArgumentException` | 422 — validation error payload |
| `RuntimeException` | 404 — not found |
| `PDOException` | 500 — DB error (logged via `safe_log`) |
| `Throwable` | 500 — generic internal error (logged) |

Special constant `TENANT_ID_ALL = 0` signals "super-admin, all tenants" to the
repository.

### 5.2 Repository

**File**: `api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php`  
**Class**: `PdoTenant_usersRepository`

| Method | Signature | Description |
|---|---|---|
| `all` | `(int $tenantId, int $perPage, int $offset, array $filters): array` | Paginated list with filters |
| `count` | `(int $tenantId, array $filters): int` | Total row count for pagination |
| `find` | `(int $tenantId, int $id): ?array` | Single row by ID |
| `getByUserAndTenant` | `(int $tenantId, int $userId): ?array` | Membership lookup for upsert |
| `userExistsInTenant` | `(int $tenantId, int $userId): bool` | Check membership existence |
| `userExists` | `(int $userId): bool` | Check global user existence |
| `roleExists` | `(int $roleId): bool` | Check role existence |
| `save` | `(int $tenantId, array $data, ?int $actingUserId): int` | Create or update |
| `delete` | `(int $tenantId, int $id): void` | Hard-delete row |

**Note**: When `$tenantId === 0` (super-admin), the tenant filter is omitted
from WHERE clauses.

### 5.3 Service

**File**: `api/v1/models/tenant_users/services/Tenant_usersService.php`  
**Class**: `Tenant_usersService`

Orchestrates validation, existence checks, and upsert logic:

1. **`create(int $tenantId, array $data, ?int $actingUserId): array`**
   - Validates input; throws `InvalidArgumentException` on failure
   - Checks `userExists` globally
   - Checks `roleExists` if `role_id` provided
   - If membership exists → calls `save` with `id` (update path)
   - Else → calls `save` without `id` (insert path)
   - Returns full row; falls back to `{ id, tenant_id, user_id }` if retrieval fails

2. **`update(int $tenantId, array $data, ?int $actingUserId): array`**
   - Validates update payload; throws on invalid fields
   - Verifies row exists; throws `RuntimeException` if not
   - Calls `save` and returns updated row

3. **`delete(int $tenantId, array $data): void`**
   - Validates `id` is present
   - Verifies row exists; throws `RuntimeException` if not
   - Calls `repo->delete`

4. **`get(int $tenantId, int $id): array`**
   - Calls `repo->find`; throws `RuntimeException` if not found

5. **`list(int $tenantId, array $query): array`**
   - Parses `page`, `per_page` from `$query`
   - Returns `{ items: [...], meta: { total, page, per_page, pages } }`

### 5.4 Validator

**File**: `api/v1/models/tenant_users/validators/Tenant_usersValidator.php`  
**Class**: `Tenant_usersValidator`

Static method `validate(array $data, bool $isUpdate): array` returns a map of
`field → error message`.

| Field | Create rule | Update rule |
|---|---|---|
| `user_id` | Required, numeric > 0 | Not validated (immutable) |
| `role_id` | Required, numeric > 0 | Optional; if present must be numeric > 0 |
| `entity_id` | Optional; if present must be numeric > 0 | Optional; same |
| `tenant_id` | Optional; if present must be numeric > 0 | Optional; same |
| `is_active` | Optional; must be 0 or 1 | Optional; same |

### 5.5 Controller

**File**: `api/v1/models/tenant_users/controllers/Tenant_usersController.php`  
**Class**: `Tenant_usersController`

Thin HTTP adapter that delegates to the service:

| Method | Delegates to |
|---|---|
| `list(int $tenantId, array $query)` | `service->list` |
| `get(int $tenantId, int $id)` | `service->get` |
| `create(int $tenantId, array $data, ?int $actingUserId)` | `service->create` |
| `update(int $tenantId, array $data, ?int $actingUserId)` | `service->update` |
| `delete(int $tenantId, array $data)` | `service->delete` |

---

## 6. Frontend — PHP Fragment

**File**: `admin/fragments/tenant_users.php`

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
| `$csrf` | string | CSRF token for form submission |
| `$tenantId` | int | Current session tenant ID |
| `$canView` | bool | Combined view permission |
| `$canCreate` | bool | Create permission |
| `$canEdit` | bool | Edit permission |
| `$canDelete` | bool | Delete permission |
| `$canViewAll/Own/Tenant` | bool | Granular view scopes |
| `$canEditAll/Own` | bool | Granular edit scopes |
| `$canDeleteAll/Own` | bool | Granular delete scopes |
| `$apiBase` | string | API base path (`"/api"`) |

### 6.3 HTML element IDs

| Element ID | Purpose |
|---|---|
| `tenantUsersPageContainer` | Outermost container (CSS scope root) |
| `btnAddTenantUser` | Opens the add form |
| `tenantUserFormContainer` | The add/edit card (hidden by default) |
| `formTitle` | `<h3>` heading — updated to Add/Edit by JS |
| `btnCloseForm` | Closes / hides the form |
| `tenantUserForm` | The `<form>` element |
| `formId` | Hidden field — empty for create, numeric for edit |
| `formTenantId` | Tenant ID input |
| `formUserId` | User ID input |
| `formRoleId` | Role `<select>` — populated dynamically |
| `formEntityId` | Entity `<select>` — populated dynamically |
| `formIsActive` | Status `<select>` |
| `btnSubmitForm` | Save button |
| `btnCancelForm` | Cancel button |
| `btnDeleteTenantUser` | Delete button (hidden, shown in edit mode) |
| `entityInfo` | Entity info box (shown when entity selected) |
| `tenantInfo` | Tenant info box (shown when tenant ID verified) |
| `userInfo` | User info box (shown when user ID verified) |
| `entityName/Slug/Status` | Spans inside entityInfo |
| `tenantName/Domain/Status` | Spans inside tenantInfo |
| `userName/Email/Status` | Spans inside userInfo |
| `searchInput` | Search text filter |
| `tenantFilter` | Tenant ID numeric filter |
| `userFilter` | User ID numeric filter |
| `entityFilter` | Entity ID numeric filter |
| `statusFilter` | Active/Inactive `<select>` |
| `btnApplyFilters` | Apply filters button |
| `btnResetFilters` | Reset filters button |
| `btnExportExcel` | Export to Excel button |
| `tableLoading` | Loading spinner state |
| `tableContainer` | Visible when data loaded |
| `tenantUsersTable` | The `<table>` |
| `tableBody` | `<tbody>` — filled by JS |
| `paginationInfo` | "Showing X–Y of Z results" |
| `pagination` | Pagination button container |
| `emptyState` | Shown when no rows returned |
| `errorState` | Shown when API request fails |
| `errorMessage` | Error description `<p>` |
| `btnRetry` | Retry load button |

### 6.4 Client-side globals emitted by fragment

```js
window.APP_CONFIG.API_BASE        // '/api'
window.APP_CONFIG.TENANT_ID       // session tenant_id
window.APP_CONFIG.CSRF_TOKEN      // CSRF token
window.APP_CONFIG.IS_SUPER_ADMIN  // true/false

window.USER_LANGUAGE              // 'ar' | 'en' | ...
window.USER_DIRECTION             // 'rtl' | 'ltr'
window.CSRF_TOKEN                 // duplicate for legacy JS

window.PAGE_PERMISSIONS = {
    canCreate, canEdit, canDelete, canView,
    canViewAll, canViewOwn, canViewTenant,
    canEditAll, canEditOwn,
    canDeleteAll, canDeleteOwn,
    isSuperAdmin
}
```

---

## 7. Frontend — JavaScript Module

**File**: `admin/assets/js/pages/tenant_users.js`  
**Global**: `window.TenantUsers`  
**Version**: 4.0.0

### 7.1 Module state

```js
state = {
    page: 1,           // current page number
    perPage: 10,       // items per page
    filters: {},       // active filter values
    permissions: {},   // PAGE_PERMISSIONS
    translations: {},  // loaded i18n JSON
    language: '...',   // current lang code
    meta: null         // last pagination meta
}
```

### 7.2 Public API (`window.TenantUsers`)

| Method | Description |
|---|---|
| `init()` | Bootstrap: load translations, bind events, call `load()` |
| `load(page)` | Fetch data from API and render table |
| `add()` | Show empty form for creating a new membership |
| `edit(id)` | Fetch row by ID and populate form for editing |
| `remove(id)` | Confirm then DELETE the membership |
| `exportExcel()` | Export current filtered list as Excel |

### 7.3 API helpers (internal)

| Function | Endpoint | Description |
|---|---|---|
| `getUser(id)` | `GET /api/users_account/{id}` | Fetch user details for info-box |
| `getTenant(id)` | `GET /api/tenants/{id}` | Fetch tenant details |
| `getRoles(tenantId)` | `GET /api/roles?tenant_id={id}` | Fetch roles for dropdown |
| `getEntities(tenantId)` | `GET /api/entities?tenant_id={id}` | Fetch entities for dropdown |
| `getEntity(id)` | `GET /api/entities/{id}` | Fetch single entity details |

All helpers use `AF.Cache` (AdminFramework cache) to avoid redundant requests.

### 7.4 RTL direction

`setDirectionForLang(lang)` is called on init. RTL languages: `ar`, `he`, `fa`,
`ur`, `ps`. Sets `document.documentElement.dir`, `document.body` class, and the
container's `dir` attribute.

### 7.5 Translation system

Translations are loaded from `/languages/TenantUsers/{lang}.json` at module
init. Falls back to `en.json`, then to hard-coded English strings.

The `t(key)` helper resolves dot-notation keys:
`t('table.headers.username')` → `"Username"`.

### 7.6 Table rendering

`renderTable(items)` generates HTML for `<tbody>`. Each row uses CSS classes
exclusively — no inline styles:

| Element | CSS class(es) |
|---|---|
| Badge for role | `badge badge-info` |
| Badge for active status | `badge badge-success` |
| Badge for inactive status | `badge badge-danger` |
| Badge for warnings | `badge badge-warning` |
| Action edit button | `btn btn-sm btn-outline` |
| Action delete button | `btn btn-sm btn-danger` |
| Actions wrapper | `table-actions` |
| Muted secondary text | `text-muted` |

### 7.7 Save verification pattern

On `save()`, if the server throws an error the module **verifies** the operation
actually succeeded by querying `GET /api/tenant_users?tenant_id=X&user_id=Y`. If
the record is found, the UI shows success. This handles edge cases where the DB
write succeeded but the response serialization failed.

---

## 8. Frontend — CSS Stylesheet

**File**: `admin/assets/css/pages/tenant_users.css`  
**Scope root**: `#tenantUsersPageContainer`

### 8.1 Design principles

1. **All colours from DB** — every `color`, `background`, `border-color` uses
   `var(--xxx)` resolved at runtime from `theme_injector.php`. No hex values.
2. **`color-mix()` for transparency** — `color-mix(in srgb, var(--xxx) N%, transparent)` replaces raw `rgba()`.
3. **Fully scoped** — every selector prefixed with `#tenantUsersPageContainer`.
4. **RTL-ready** — `[dir="rtl"] #tenantUsersPageContainer ...` rules cover all layout reversals.
5. **Three responsive breakpoints**: tablet ≤1024 px, mobile ≤768 px, small phone ≤480 px.

### 8.2 CSS variables consumed

| Variable | DB source | Usage |
|---|---|---|
| `--primary-color` | `color_settings.primary_color` | Buttons, focus borders, active pagination, badge-info |
| `--primary-hover` | `color_settings.primary_hover` | btn-primary hover |
| `--secondary-color` | `color_settings.secondary_color` | btn-secondary |
| `--danger-color` | `color_settings.danger_color` | btn-danger, badge-danger, validation errors |
| `--success-color` | `color_settings.success_color` | btn-success, badge-success |
| `--warning-color` | `color_settings.warning_color` | badge-warning, entity icon |
| `--background-secondary` | `color_settings.background_secondary` | Page header background |
| `--background-tertiary` | `color_settings.background_tertiary` | Card header, info-box background |
| `--card-bg` | `color_settings.card_bg` | Card surfaces |
| `--input-bg` | `color_settings.input_bg` | Form inputs |
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
| `.page-header` | `#tenantUsersPageContainer .page-header` | Top header panel |
| `.page-title` | `… .page-title` | `<h1>` |
| `.page-subtitle` | `… .page-subtitle` | Subtitle paragraph |
| `.page-header-actions` | `… .page-header-actions` | Right-side action buttons |
| `.card` | `… .card` | Generic card container |
| `.card-header` | `… .card-header` | Card top bar |
| `.card-title` | `… .card-title` | Card heading |
| `.card-body` | `… .card-body` | Card content area |
| `.form-row` | `… .form-row` | Responsive form grid |
| `.form-row-mt` | `… .form-row-mt` | Form row with top margin |
| `.form-group` | `… .form-group` | Label + input column |
| `.form-control` | `… .form-control` | Input/select/textarea |
| `.invalid-feedback` | `… .invalid-feedback` | Validation error message |
| `.form-actions` | `… .form-actions` | Save/Cancel/Delete bar |
| `.input-with-button` | `… .input-with-button` | Input + verify button row |
| `.user-info-box` | `… .user-info-box` | Entity/tenant/user info panel |
| `.info-content` | `… .info-content` | Icon + text flex row |
| `.entity-icon` | `… .entity-icon` | Store icon (warning color) |
| `.tenant-icon` | `… .tenant-icon` | Tenant icon (primary color) |
| `.user-icon` | `… .user-icon` | User icon (success color) |
| `.filters-grid` | `… .filters-grid` | Filter controls grid |
| `.filter-group` | `… .filter-group` | Single filter label+input |
| `.filter-actions` | `… .filter-actions` | Apply/Reset/Export row |
| `.btn-export` | `… .btn-export` | Export button (pushed to end) |
| `.data-table` | `… .data-table` | Full-width table |
| `.table-responsive` | `… .table-responsive` | Horizontal scroll wrapper |
| `.table-actions` | `… .table-actions` | Per-row action flex row |
| `.icon-btn` | `… .icon-btn` | 32×32 icon-only action button |
| `.icon-btn.danger` | `… .icon-btn.danger` | Destructive icon button |
| `.badge` | `… .badge` | Pill-shaped status label |
| `.badge-success` | `… .badge-success` | Green — active, approved |
| `.badge-danger` | `… .badge-danger` | Red — inactive, rejected |
| `.badge-warning` | `… .badge-warning` | Amber — pending, warning |
| `.badge-info` | `… .badge-info` | Blue — role label |
| `.loading-state` | `… .loading-state` | Spinner + loading message |
| `.empty-state` | `… .empty-state` | No-results message |
| `.error-state` | `… .error-state` | Error message + retry |
| `.spinner` | `… .spinner` | CSS-only rotating ring |
| `.empty-icon` | `… .empty-icon` | Emoji / icon for empty state |
| `.error-icon` | `… .error-icon` | Emoji / icon for error state |
| `.pagination-wrapper` | `… .pagination-wrapper` | Pagination bar |
| `.pagination-info` | `… .pagination-info` | "Showing X–Y of Z" text |
| `.pagination` | `… .pagination` | Button container |
| `.page-ellipsis` | `… .page-ellipsis` | "..." span between pages |
| `.alert` | `… .alert` | Fixed-position toast |
| `.alert-success` | `… .alert-success` | Success toast |
| `.alert-error` | `… .alert-error` | Error toast |
| `.alert-info` | `… .alert-info` | Info toast |
| `.alert-warning` | `… .alert-warning` | Warning toast |
| `.text-muted` | `… .text-muted` | Secondary text colour |

### 8.4 Keyframe names (namespaced)

| Keyframe | Purpose |
|---|---|
| `tu-spin` | Loading spinner rotation |
| `tu-slide-in` | Alert toast entrance animation |

---

## 9. Translation System

**Translation files**: `/languages/TenantUsers/{lang}.json`  
e.g. `/languages/TenantUsers/ar.json`, `/languages/TenantUsers/en.json`

### 9.1 JSON key structure

```json
{
  "tenant_users": {
    "title": "...",
    "subtitle": "...",
    "add_new": "...",
    "loading": "...",
    "retry": "..."
  },
  "table": {
    "headers": { "id": "...", "username": "...", "email": "...", "tenant": "...",
                 "entity": "...", "role": "...", "joined_at": "...", "status": "...", "actions": "..." },
    "actions": { "edit": "...", "delete": "...", "export": "...", "confirm_delete": "..." },
    "status": { "active": "...", "inactive": "..." },
    "empty": { "title": "...", "message": "...", "add_first": "...", "no_entity": "..." }
  },
  "filters": {
    "search": "...", "search_placeholder": "...",
    "tenant_id": "...", "tenant_placeholder": "...",
    "user_id": "...", "user_placeholder": "...",
    "entity_id": "...", "entity_placeholder": "...",
    "status": "...",
    "status_options": { "all": "...", "active": "...", "inactive": "..." },
    "apply": "...", "reset": "..."
  },
  "form": {
    "add_title": "...", "edit_title": "...",
    "fields": {
      "tenant_id": { "label": "...", "placeholder": "...", "required": "..." },
      "user_id": { "label": "...", "placeholder": "...", "required": "..." },
      "role_id": { "label": "...", "required": "...", "enter_tenant_first": "...",
                   "loading": "...", "no_roles": "..." },
      "entity_id": { "label": "...", "enter_tenant_first": "...", "no_entities": "...",
                     "not_found": "..." },
      "status": { "label": "...", "active": "...", "inactive": "..." }
    },
    "tenant_info": { "title": "...", "name": "...", "domain": "...", "status": "..." },
    "user_info": { "title": "...", "name": "...", "email": "...", "status": "..." },
    "entity_info": { "name": "..." },
    "buttons": { "save": "...", "cancel": "...", "saving": "...", "updating": "..." }
  },
  "messages": {
    "success": { "created": "...", "updated": "...", "deleted": "..." },
    "error": { "load_failed": "...", "save_failed": "...", "delete_failed": "...", "not_found": "..." }
  },
  "pagination": { "showing": "...", "to": "...", "of": "...", "results": "...",
                  "previous": "...", "next": "..." },
  "validation": { "required": "..." },
  "accessibility": { "close": "..." }
}
```

### 9.2 Translation application

Translations are applied in two passes:
1. **PHP** — `__t('key', 'fallback')` renders default text in HTML.
2. **JS** — on DOMContentLoaded (or 50 ms after load in embedded mode) the
   `applyTranslations()` IIFE re-fetches the JSON and updates all
   `[data-i18n]` and `[data-i18n-placeholder]` elements.

---

## 10. Design Tokens (DB Theme)

All visual tokens are injected by `admin/includes/theme_injector.php` as CSS
custom properties on `:root`. They originate from these DB tables:

| DB table | Maps to |
|---|---|
| `color_settings` | `--primary-color`, `--danger-color`, `--success-color`, etc. |
| `design_settings` | `--border-radius`, `--container-width`, `--header-height` |
| `font_settings` | `--body-font-family`, `--heading-font-family` |

The injector emits a `<style id="theme-vars">` block and optional Google Fonts
`<link>` tags before any page CSS is linked, guaranteeing variables are defined
before the stylesheet parses them.

**Rule**: Never hard-code colour hex values in `tenant_users.css`. Use
`var(--xxx)` exclusively. Use `color-mix()` for transparency variations.

---

## 11. Error Reference

| HTTP | Scenario | Resolution |
|---|---|---|
| 401 | Admin session expired | Redirect to `/admin/login.php` |
| 403 | Permission system configured, user has no access | Grant `tenant_users.view` role permission |
| 404 | Row not found by ID | Verify ID exists in `tenant_users` table |
| 422 | Validation failed | See `errors` object in response body |
| 500 | DB error | Check PHP error log; run `SHOW PROCESSLIST` to diagnose DB |

---

## 12. File Map

```
admin/
├── fragments/
│   └── tenant_users.php              ← PHP template / HTML fragment
├── assets/
│   ├── css/pages/
│   │   └── tenant_users.css          ← Complete CSS stylesheet (this module)
│   └── js/pages/
│       └── tenant_users.js           ← Frontend JS module (window.TenantUsers)
└── includes/
    ├── admin_context.php             ← Helper functions (admin_user, can, …)
    ├── header.php                    ← Standalone page header
    └── theme_injector.php            ← DB → CSS variable emitter

api/
└── v1/
    ├── routes/
    │   └── tenant_users.php          ← HTTP router
    └── models/tenant_users/
        ├── tenant_users.php          ← Alternate simplified route (legacy)
        ├── controllers/
        │   └── Tenant_usersController.php
        ├── services/
        │   └── Tenant_usersService.php
        ├── repositories/
        │   └── PdoTenant_usersRepository.php
        ├── validators/
        │   └── Tenant_usersValidator.php
        └── fragments/                ← Alternative fragment copies (legacy)
            ├── tenant_users.php
            ├── tenant_users.css
            └── tenant_users.js

languages/
└── TenantUsers/
    ├── ar.json                       ← Arabic translations
    ├── en.json                       ← English translations (fallback)
    └── …

docs/
└── tenant_users_documentation.md    ← This file
```
