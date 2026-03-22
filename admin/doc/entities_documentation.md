# Entities Management — Documentation

## Overview

The Entities system manages the business entities (stores, branches, vendors) within the multi-tenant platform. Each entity belongs to a tenant and can optionally belong to a parent entity (branch relationship). The admin interface at `/admin/fragments/entities.php` provides full CRUD operations, attribute management, working-hours configuration, media uploads, address management, and multi-language translations.

---

## Features

### CRUD Operations

| Operation | Endpoint | Notes |
|-----------|----------|-------|
| List      | `GET /api/entities` | Paginated, filterable, searchable |
| Create    | `POST /api/entities` | Validates required fields |
| Update    | `PUT /api/entities?id=<id>` | Partial updates supported |
| Delete    | `DELETE /api/entities?id=<id>` | Hard delete; cascades handled at DB level |

### Parent–Child Hierarchy

Entities support a single level of parent–child relationship:

- **Main entity** — `parent_id IS NULL`
- **Branch** — `parent_id = <main entity id>`

A branch cannot be assigned as the parent of another entity (self-referential cycles are rejected).

### AJAX Parent Entity Search (Scalable)

The parent entity selector uses a debounced AJAX search to support datasets of any size:

- The user types at least **2 characters** into the search box.
- A request is sent to `GET /api/entities?search=<query>&limit=20` after a **300 ms** debounce.
- Results are displayed in a scrollable `<select>` list.
- Selecting an item populates the hidden `parent_id` field and triggers validation.
- When editing an existing branch, the current parent is pre-loaded by its ID.

This replaces the previous approach of loading up to 500 entities at page load, making the feature fully scalable.

### Working Hours

Each entity can define opening hours per day of the week. Working hours are managed via the **Working Hours** tab and saved through `POST /api/entities_working_hours`.

### Attributes

Custom attributes can be attached to an entity via the **Attributes** tab. Attribute values are saved through `POST /api/entities_attribute_values`. Attribute types include: text, number, boolean, select, and date.

### Entity Settings

Key–value settings (e.g. minimum order amount, delivery fee) can be configured via the **Settings** tab and persisted through `POST /api/entity_settings`.

### Media

Three media slots are available per entity:

- **Logo** — square/portrait image
- **Cover** — banner/wide image
- **License** — document image

All media is selected via the embedded Media Studio (`/admin/fragments/media_studio.php`).

### Address Management

Each entity has an address record managed via the embedded address fragment (`/admin/fragments/address.php?entity_id=<id>`). Communication between the iframe and the parent page is done via `postMessage`.

### Multi-Language Translations

Entities support translated `store_name`, `description`, `meta_title`, `meta_description` for every active language configured in the tenant. Translations are stored in the `entity_translations` table.

---

## Technical Details

### Data Structure

**`entities` table — key columns**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT PK | Auto-increment primary key |
| `tenant_id` | INT | Tenant isolation key |
| `user_id` | INT | Owner/creator user |
| `parent_id` | INT NULL | Parent entity id (NULL = main entity) |
| `store_name` | VARCHAR | Default/original name |
| `branch_code` | VARCHAR | Short code for the branch |
| `vendor_type` | ENUM | `product_seller`, `service_provider`, `both` |
| `store_type` | ENUM | `individual`, `company`, `brand` |
| `status` | ENUM | `active`, `inactive`, `pending`, `suspended` |
| `is_verified` | TINYINT | 0 = unverified, 1 = verified |
| `entity_logo` | VARCHAR | Logo URL |
| `entity_cover` | VARCHAR | Cover image URL |
| `entity_license` | VARCHAR | License image URL |
| `timezone_id` | INT FK | Reference to `timezones` |

### Parent Relationship Handling

1. Entity type is determined client-side: `entity_type = "main" | "branch"`.
2. When `entity_type === "branch"`, the `parent_id` field is shown and required.
3. Before saving, the UI calls `GET /api/entities?validate_parent=<id>` to confirm the parent exists and belongs to the same tenant.
4. The API rejects a `parent_id` that equals the entity's own `id`.

### Performance Strategy — AJAX Search

**Before (problematic):**
```
GET /api/entities?limit=500  → 500 rows transferred on every form open
Client-side JS filter on keyup
```

**After (scalable):**
```
User types ≥2 chars
  → debounce 300ms
  → GET /api/entities?search=<query>&limit=20
  → Server-side LIKE filter on store_name
  → 20 rows maximum returned
```

The `search` filter is implemented as `store_name LIKE '%<query>%'` in `PdoEntitiesRepository`, applied to both the `all()` (list) and `count()` methods. The minimum 2-character requirement is enforced in both the API route and the UI.

---

## UI / UX

### Responsive Layout

- `.page-container` uses `width: 100%; max-width: 100%` — expands to fill the admin sidebar's available space on all screen sizes.
- Form rows collapse to a single column below 768 px.
- Tab navigation horizontally scrolls on mobile.
- The Media Studio modal stretches to full viewport on mobile.

### Parent Entity Selector

| State | Behaviour |
|-------|-----------|
| < 2 chars typed | Placeholder shown, no request sent |
| Searching | "Searching…" option shown during fetch |
| Results returned | Up to 20 matching entities listed |
| No results | "No results" option shown |
| Error | "Search failed" option shown |
| Edit existing branch | Current parent is pre-loaded via ID search |

---

## Usage Guide

### Creating a Main Entity

1. Click **Add Entity**.
2. Fill **Store Name** (required).
3. Set **Entity Type** → `Main`.
4. Complete the remaining tabs (Address, Working Hours, Media, etc.).
5. Click **Save**.

### Creating a Branch

1. Click **Add Entity**.
2. Fill **Store Name**.
3. Set **Entity Type** → `Branch`.
4. In the **Search Parent Entity** box, type at least 2 characters of the parent store name.
5. Select the correct parent from the dropdown. The parent ID is populated automatically.
6. Optionally enter a **Branch Code**.
7. Complete the remaining tabs.
8. Click **Save**.

### Editing an Entity

1. Locate the entity in the table.
2. Click the **Edit** (pencil) button.
3. Modify the required fields.
4. Click **Save**.

### Assigning / Changing a Parent

1. Open the entity for editing.
2. Change **Entity Type** to `Branch` if it is currently `Main`.
3. Use the **Search Parent Entity** field to find and select the new parent.
4. Click **Save**.

---

## Troubleshooting

### Parent search returns no results

- Ensure the query is at least 2 characters.
- Confirm the parent entity belongs to the same tenant.
- Check the browser Network tab for the request to `/api/entities?search=...` and inspect the response.

### Parent validation fails on save

- The entered Parent ID does not exist or belongs to a different tenant.
- Use the **Validate** button to check the ID before saving.

### Form does not take full width

- Ensure `entities.css` is loaded correctly. The `.page-container` rule must not have a `max-width` restriction.
- Check that no parent wrapper in `admin.css` or a theme override is constraining the width.

### Translations not showing

- Confirm the language files at `/languages/Entity/<lang>.json` exist and contain all required keys.
- Check the browser Console for i18n load errors.

### Entity logo / cover not uploading

- Media Studio must be reachable at `/admin/fragments/media_studio.php`.
- Confirm the Media Studio iframe is not blocked by a CSP header.
- Check the `images` API response in the Network tab.
