# Ads Module — Complete Technical Documentation

## Table of Contents

1. [Module Overview](#module-overview)
2. [Database Schema](#database-schema)
3. [Backend API](#backend-api)
   - [Ad Campaigns `/api/ad_campaigns`](#ad-campaigns-apiad_campaigns)
   - [Ad Units `/api/ads`](#ad-units-apiads)
   - [Ad Translations `/api/ad_translations`](#ad-translations-apiad_translations)
   - [Ad Placements `/api/ad_placements`](#ad-placements-apiad_placements)
   - [Ad Placement Items `/api/ad_placement_items`](#ad-placement-items-apiad_placement_items)
   - [Ad Payments `/api/ad_payments`](#ad-payments-apiad_payments)
4. [Backend File Structure](#backend-file-structure)
5. [Admin Frontend](#admin-frontend)
   - [Fragment: `admin/fragments/ads.php`](#fragment-adminfragmentsadsphp)
   - [JavaScript: `admin/assets/js/pages/ads.js`](#javascript-adminassetsjspagesadsjs)
   - [CSS: `admin/assets/css/pages/ads.css`](#css-adminassetscsspagesadscss)
   - [Language Files](#language-files)
6. [Architecture & Data Flow](#architecture--data-flow)
7. [Security & Tenant Isolation](#security--tenant-isolation)
8. [Important Technical Notes](#important-technical-notes)

---

## Module Overview

The Ads module is a full advertising management system integrated into the platform. It allows each tenant to:

- Create and manage **ad campaigns** with budgets, pricing models, and scheduling
- Create **ad units** (individual ads) that belong to campaigns and target URLs or entities
- Manage **multilingual translations** (title + description) per ad unit
- Upload **ad images** in 8 different sizes/types via the media studio
- Define **ad placements** (slots on pages: homepage banner, sidebar, etc.)
- Assign ad units to placements as **placement items** with priority, weight, and date scheduling
- Track **ad payments** linked to campaigns

**Tenant isolation:** Every API endpoint requires `tenant_id` (from query param or session). All SQL queries are scoped to `WHERE tenant_id = :tenant_id` (directly or via JOIN through `ad_campaigns` or `ad_placements`).

---

## Database Schema

### `ad_campaigns`

Stores advertising campaigns per tenant.

| Column         | Type                                       | Notes                                |
|----------------|--------------------------------------------|--------------------------------------|
| `id`           | BIGINT UNSIGNED AUTO_INCREMENT PK          |                                      |
| `tenant_id`    | INT UNSIGNED NOT NULL                      | FK → `tenants.id` ON DELETE CASCADE  |
| `entity_id`    | BIGINT UNSIGNED NULL                       | FK → `entities.id` ON DELETE CASCADE |
| `name`         | VARCHAR(255) NOT NULL                      |                                      |
| `budget`       | DECIMAL(12,2) DEFAULT 0.00                 |                                      |
| `currency_id`  | SMALLINT UNSIGNED NOT NULL                 | FK → `currencies.id`                 |
| `pricing_model`| ENUM('fixed','cpm','cpc') DEFAULT 'fixed'  |                                      |
| `start_date`   | DATETIME NULL                              |                                      |
| `end_date`     | DATETIME NULL                              |                                      |
| `status`       | ENUM('draft','active','paused','completed') DEFAULT 'draft' |              |
| `created_by`   | INT UNSIGNED NULL                          | FK → `users.id`                      |
| `created_at`   | DATETIME DEFAULT CURRENT_TIMESTAMP         |                                      |
| `updated_at`   | DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

---

### `ads`

Individual advertising units that belong to a campaign.

| Column         | Type                                        | Notes                               |
|----------------|---------------------------------------------|-------------------------------------|
| `id`           | BIGINT UNSIGNED AUTO_INCREMENT PK           |                                     |
| `campaign_id`  | BIGINT UNSIGNED NOT NULL                    | FK → `ad_campaigns.id` ON DELETE CASCADE |
| `target_type`  | ENUM('url','entity') DEFAULT 'url'          |                                     |
| `target_value` | VARCHAR(500) NULL                           | URL or entity identifier            |
| `status`       | ENUM('active','paused','rejected') DEFAULT 'active' |                             |
| `views_count`  | INT DEFAULT 0                               |                                     |
| `clicks_count` | INT DEFAULT 0                               |                                     |
| `created_at`   | DATETIME DEFAULT CURRENT_TIMESTAMP          |                                     |
| `updated_at`   | DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

> **Note:** The `ads` table has **NO `title` column**. Titles are stored in `ad_translations`.

---

### `ad_translations`

Multilingual title and description per ad unit.

| Column          | Type                               | Notes                              |
|-----------------|------------------------------------|------------------------------------|
| `id`            | BIGINT UNSIGNED AUTO_INCREMENT PK  |                                    |
| `ad_id`         | BIGINT UNSIGNED NOT NULL           | FK → `ads.id` ON DELETE CASCADE    |
| `language_code` | VARCHAR(8) NOT NULL                | FK → `languages.code` (e.g. 'en', 'ar') |
| `title`         | VARCHAR(255) NULL                  |                                    |
| `description`   | TEXT NULL                          |                                    |
| `created_at`    | DATETIME DEFAULT CURRENT_TIMESTAMP |                                    |
| UNIQUE KEY      | `(ad_id, language_code)`           | One translation per language per ad |

---

### `ad_placements`

Defines named slots/zones where ads can be displayed on pages.

| Column          | Type                                          | Notes                               |
|-----------------|-----------------------------------------------|-------------------------------------|
| `id`            | BIGINT UNSIGNED AUTO_INCREMENT PK             |                                     |
| `tenant_id`     | INT UNSIGNED NOT NULL                         | FK → `tenants.id` ON DELETE CASCADE |
| `code`          | VARCHAR(100) NULL UNIQUE                      | Optional unique code (alphanumeric, `_`, `-`) |
| `name`          | VARCHAR(255) NULL                             | Human-readable placement name      |
| `description`   | TEXT NULL                                     |                                     |
| `placement_key` | VARCHAR(100) NOT NULL                         | Machine-readable key (alphanumeric, `_`, `-`); indexed |
| `page`          | VARCHAR(100) NULL                             | Page identifier (e.g. 'homepage')  |
| `width`         | INT NULL                                      | Slot width in pixels                |
| `height`        | INT NULL                                      | Slot height in pixels               |
| `max_ads`       | INT DEFAULT 1                                 | Maximum ads shown in this slot      |
| `created_at`    | DATETIME DEFAULT CURRENT_TIMESTAMP            |                                     |
| `status`        | ENUM('active','inactive','draft') DEFAULT 'active' |                              |

> **Note:** There is **no `updated_at`** column on `ad_placements`.

---

### `ad_placement_items`

Links ad units to placement slots with scheduling and rotation controls.

| Column         | Type                               | Notes                                   |
|----------------|------------------------------------|-----------------------------------------|
| `id`           | BIGINT UNSIGNED AUTO_INCREMENT PK  |                                         |
| `placement_id` | BIGINT UNSIGNED NOT NULL           | FK → `ad_placements.id` ON DELETE CASCADE |
| `ad_id`        | BIGINT UNSIGNED NOT NULL           | FK → `ads.id` ON DELETE CASCADE         |
| `priority`     | INT DEFAULT 1                      | Display priority (lower = higher priority) |
| `weight`       | INT DEFAULT 1                      | Weight for rotation algorithms          |
| `start_date`   | DATETIME NULL                      | Scheduled start (NULL = no limit)       |
| `end_date`     | DATETIME NULL                      | Scheduled end (NULL = no limit)         |
| `created_at`   | DATETIME DEFAULT CURRENT_TIMESTAMP |                                         |

---

### `ad_payments`

Records payment transactions for campaigns.

| Column        | Type                                         | Notes                           |
|---------------|----------------------------------------------|---------------------------------|
| `id`          | BIGINT UNSIGNED AUTO_INCREMENT PK            |                                 |
| `campaign_id` | BIGINT UNSIGNED NOT NULL                     | FK → `ad_campaigns.id`          |
| `amount`      | DECIMAL(12,2) NULL                           |                                 |
| `currency_id` | SMALLINT UNSIGNED NOT NULL                   | FK → `currencies.id`            |
| `status`      | ENUM('pending','paid','failed') DEFAULT 'pending' |                           |
| `paid_at`     | DATETIME NULL                                |                                 |
| `created_at`  | DATETIME DEFAULT CURRENT_TIMESTAMP           |                                 |

---

## Backend API

All endpoints follow the pattern:
- **Authentication/Authorization:** `tenant_id` is extracted from `$_GET['tenant_id']` or `$_SESSION['tenant_id']`. Returns `401` if missing.
- **Pagination:** `page` (default 1), `limit` (default varies, max 100)
- **Ordering:** `order_by` + `order_dir` (ASC/DESC)
- **Response format:** `{ "success": true/false, "data": {...}, "message": "...", "meta": { "time": "...", "request_id": null } }`
- **Error handling:** `422` for validation errors, `400` for runtime errors, `500` for unexpected errors

---

### Ad Campaigns `/api/ad_campaigns`

**File:** `api/v1/routes/ad_campaigns.php`

#### `GET /api/ad_campaigns`

Returns paginated list of campaigns for the tenant.

**Query Parameters:**

| Param          | Type    | Default | Description                     |
|----------------|---------|---------|---------------------------------|
| `tenant_id`    | int     | session | Tenant identifier               |
| `page`         | int     | 1       | Page number                     |
| `limit`        | int     | 20      | Items per page (max 100)        |
| `order_by`     | string  | `id`    | Sort field: `id`, `name`, `budget`, `status`, `start_date`, `end_date`, `created_at` |
| `order_dir`    | string  | `DESC`  | `ASC` or `DESC`                 |
| `status`       | string  | –       | Filter: `draft`, `active`, `paused`, `completed` |
| `pricing_model`| string  | –       | Filter: `fixed`, `cpm`, `cpc`  |
| `entity_id`    | int     | –       | Filter by entity                |
| `currency_id`  | int     | –       | Filter by currency              |
| `search`       | string  | –       | Search in campaign name         |

**Response fields (per item):** All `ad_campaigns` columns + `currency_code`, `currency_name`, `currency_symbol`, `currency_symbol_position`, `currency_decimal_places`, `entity_store_name`, `created_by_name`

#### `GET /api/ad_campaigns?id={id}`

Returns a single campaign by ID (scoped to tenant).

#### `POST /api/ad_campaigns`

Creates a new campaign.

**Required fields:** `name`, `currency_id`

**Optional fields:** `entity_id`, `budget` (default 0), `pricing_model` (default `fixed`), `start_date`, `end_date`, `status` (default `draft`), `created_by`

#### `PUT /api/ad_campaigns`

Updates an existing campaign.

**Required fields:** `id`

**Updatable fields:** same as POST (all optional on update)

#### `DELETE /api/ad_campaigns`

Deletes a campaign (and cascades to linked ads).

**Body:** `{ "id": N }` or query param `?id=N`

---

### Ad Units `/api/ads`

**File:** `api/v1/routes/ads.php`

#### `GET /api/ads`

Returns paginated list of ad units for the tenant (scoped via `ad_campaigns.tenant_id`).

**Query Parameters:**

| Param        | Type   | Default | Description                              |
|--------------|--------|---------|------------------------------------------|
| `tenant_id`  | int    | session |                                          |
| `page`       | int    | 1       |                                          |
| `limit`      | int    | 20      | max 100                                  |
| `order_by`   | string | `id`    | `id`, `campaign_id`, `target_type`, `status`, `views_count`, `clicks_count`, `created_at` |
| `order_dir`  | string | `DESC`  |                                          |
| `status`     | string | –       | Filter: `active`, `paused`, `rejected`   |
| `target_type`| string | –       | Filter: `url`, `entity`                  |
| `campaign_id`| int    | –       | Filter by campaign                       |
| `search`     | string | –       | Search in `target_value`                 |

**Response fields:** All `ads` columns + `campaign_name`, `campaign_status`, `campaign_tenant_id`

#### `GET /api/ads?id={id}`

Returns a single ad unit.

#### `POST /api/ads`

Creates a new ad unit.

**Required fields:** `campaign_id`

**Optional fields:** `target_type` (default `url`), `target_value`, `status` (default `active`), `views_count` (default 0), `clicks_count` (default 0)

> The campaign must belong to the same tenant, otherwise returns `400`.

#### `PUT /api/ads`

Updates an existing ad unit. **Required:** `id`

#### `DELETE /api/ads`

Deletes an ad unit. **Body/param:** `id`

---

### Ad Translations `/api/ad_translations`

**File:** `api/v1/routes/ad_translations.php`

Manages per-language title and description for each ad unit.

#### `GET /api/ad_translations`

**Query Parameters:** `tenant_id`, `page`, `limit`, `order_by`, `order_dir`, `ad_id` (filter), `language_code` (filter)

**Response fields:** All `ad_translations` columns + `ad_target_value`, `campaign_name`

#### `GET /api/ad_translations?id={id}`

Single translation by ID.

#### `POST /api/ad_translations`

Creates or upserts a translation.

**Required fields:** `ad_id`, `language_code`

**Optional fields:** `title` (max 255), `description`

#### `PUT /api/ad_translations`

Updates a translation. **Required:** `id`

#### `DELETE /api/ad_translations`

Deletes a translation. **Body/param:** `id`

---

### Ad Placements `/api/ad_placements`

**File:** `api/v1/routes/ad_placements.php`

Manages named placement slots on pages.

#### `GET /api/ad_placements`

**Query Parameters:** `tenant_id`, `page`, `limit` (default 50), `order_by` (`id`, `name`, `placement_key`, `status`, `created_at`), `order_dir`, `status` (filter), `search` (in name/placement_key)

#### `GET /api/ad_placements?id={id}`

Single placement by ID.

#### `POST /api/ad_placements`

Creates a new placement.

**Required fields:** `name`, `placement_key`

**Optional fields:** `code`, `description`, `page`, `width`, `height`, `max_ads` (default 1), `status` (default `active`)

> `placement_key` and `code` must be alphanumeric with `_` or `-` only.

#### `PUT /api/ad_placements`

Updates a placement. **Required:** `id`

#### `DELETE /api/ad_placements`

Deletes a placement (cascades to `ad_placement_items`). **Body/param:** `id`

---

### Ad Placement Items `/api/ad_placement_items`

**File:** `api/v1/routes/ad_placement_items.php`

Assigns ad units to placement slots with scheduling.

#### `GET /api/ad_placement_items`

**Query Parameters:** `tenant_id`, `page`, `limit` (default 50), `order_by` (`id`, `placement_id`, `ad_id`, `priority`, `weight`, `start_date`, `end_date`, `created_at`), `order_dir`, `placement_id` (filter), `ad_id` (filter)

**Response fields:** All `ad_placement_items` columns + `ad_title` (from `ad_translations` with `language_code = 'en'`, falls back to empty string)

> **Important:** `ad_title` is fetched via `LEFT JOIN ad_translations atr ON a.id = atr.ad_id AND atr.language_code = 'en'`. The `ads` table has no `title` column — always use `ad_translations`.

**Tenant scoping:** Via `INNER JOIN ad_placements ap ON api.placement_id = ap.id WHERE ap.tenant_id = :tenant_id`

#### `GET /api/ad_placement_items?id={id}`

Single placement item.

#### `POST /api/ad_placement_items`

Creates a new placement item.

**Required fields:** `placement_id`, `ad_id`

**Optional fields:** `priority` (default 1), `weight` (default 1), `start_date`, `end_date`

#### `PUT /api/ad_placement_items`

Updates a placement item. **Required:** `id`

#### `DELETE /api/ad_placement_items`

Deletes a placement item. **Body/param:** `id`

---

### Ad Payments `/api/ad_payments`

**File:** `api/v1/routes/ad_payments.php`

Records financial transactions for campaigns.

#### `GET /api/ad_payments`

**Query Parameters:** `tenant_id`, `page`, `limit`, `order_by` (`id`, `campaign_id`, `amount`, `status`, `paid_at`, `created_at`), `order_dir`, `status` (filter: `pending`/`paid`/`failed`), `campaign_id` (filter)

**Response fields:** All `ad_payments` columns + `campaign_name`, `currency_code`, `currency_symbol`

#### `GET /api/ad_payments?id={id}`

Single payment.

#### `POST /api/ad_payments`

Creates a payment record.

**Required fields:** `campaign_id`, `currency_id`

**Optional fields:** `amount`, `status` (default `pending`), `paid_at`

#### `PUT /api/ad_payments`

Updates a payment. **Required:** `id`

#### `DELETE /api/ad_payments`

Deletes a payment. **Body/param:** `id`

---

## Backend File Structure

```
api/v1/
├── routes/
│   ├── ads.php                        # Ad Units endpoint
│   ├── ad_campaigns.php               # Campaigns endpoint
│   ├── ad_translations.php            # Translations endpoint
│   ├── ad_placements.php              # Placements endpoint
│   ├── ad_placement_items.php         # Placement Items endpoint
│   └── ad_payments.php               # Payments endpoint
│
└── models/ads/
    ├── Contracts/
    │   ├── AdsRepositoryInterface.php
    │   ├── AdCampaignsRepositoryInterface.php
    │   ├── AdTranslationsRepositoryInterface.php
    │   ├── AdPlacementsRepositoryInterface.php
    │   ├── AdPlacementItemsRepositoryInterface.php
    │   └── AdPaymentsRepositoryInterface.php
    │
    ├── repositories/
    │   ├── PdoAdsRepository.php               # all(), count(), find(), save(), delete()
    │   ├── PdoAdCampaignsRepository.php
    │   ├── PdoAdTranslationsRepository.php
    │   ├── PdoAdPlacementsRepository.php
    │   ├── PdoAdPlacementItemsRepository.php
    │   └── PdoAdPaymentsRepository.php
    │
    ├── services/
    │   ├── AdsService.php                     # Business logic layer
    │   ├── AdCampaignsService.php
    │   ├── AdTranslationsService.php
    │   ├── AdPlacementsService.php
    │   ├── AdPlacementItemsService.php
    │   └── AdPaymentsService.php
    │
    ├── controllers/
    │   ├── AdsController.php                  # Handles list(), get(), create(), update(), delete()
    │   ├── AdCampaignsController.php
    │   ├── AdTranslationsController.php
    │   ├── AdPlacementsController.php
    │   ├── AdPlacementItemsController.php
    │   └── AdPaymentsController.php
    │
    └── validators/
        ├── AdsValidator.php
        ├── AdCampaignsValidator.php
        ├── AdTranslationsValidator.php
        ├── AdPlacementsValidator.php
        ├── AdPlacementItemsValidator.php
        └── AdPaymentsValidator.php
```

Each module follows the same layered pattern: **Route → Controller → Service → Repository**. Validators are called inside the Service layer.

---

## Admin Frontend

### Fragment: `admin/fragments/ads.php`

**Location:** `admin/fragments/ads.php`

This PHP file renders the complete Ads management page HTML. It is included by the admin panel router.

**What it renders:**
- Page header with title and subtitle
- **Three main tabs:**
  1. **Campaigns** tab — table of campaigns with filters (search, status, pricing model)
  2. **Ad Units** tab — table of ads with filters (search, status, target type, campaign); ad rows include a thumbnail image column
  3. **Placements** tab — table of placement slots; clicking a row opens a nested table of placement items

**JavaScript configuration block:**
```php
<script>
window.ADS_CONFIG = {
    apiBase:          '/api',
    csrfToken:        '<?= $csrfToken ?>',
    tenantId:         <?= $tenantId ?>,
    currenciesApi:    '/api/currencies',
    translationsApi:  '/api/ad_translations',
    imagesApi:        '/api/media',
    adImageTypeId:    20,              // image_type_id for ad_thumb
    placementsApi:    '/api/ad_placements',
    placementItemsApi:'/api/ad_placement_items',
    canCreate:        <?= $canCreate ? 'true' : 'false' ?>,
    canEdit:          <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:        <?= $canDelete ? 'true' : 'false' ?>,
    strings:          <?= json_encode($strings) ?>
};
</script>
```

**Modals in the fragment:**
- **Campaign Modal** — Add/edit campaign fields: name, entity (optional), budget, currency, pricing model, start/end date, status
- **Ad Unit Modal** — Tabbed modal with 3 tabs:
  - **Basic** tab: campaign_id, target_type, target_value, status, views_count, clicks_count; also mandatory EN title/description fields that are auto-saved as the English translation
  - **Translations** tab: full CRUD for all language translations (language dropdown, title, description)
  - **Images** tab: displays all 8 image types (IDs 13–20) for the ad; "Open Image Studio" button opens media overlay
- **Placement Modal** — Add/edit placement: name, placement_key, code, description, page, width, height, max_ads, status
- **Placement Item Modal** — Add/edit item: ad_id (dropdown of ad units), priority, weight, start_date, end_date

---

### JavaScript: `admin/assets/js/pages/ads.js`

**Location:** `admin/assets/js/pages/ads.js`

A self-contained IIFE module. Exposed as `window.Ads.init()`.

**State variables:**

| Variable              | Purpose                                      |
|-----------------------|----------------------------------------------|
| `CFG`                 | Reference to `window.ADS_CONFIG`             |
| `campaignsPage`       | Current page for campaigns pagination        |
| `campaignsFilters`    | Active filters for campaigns list            |
| `campaignCache`       | Cached campaign list for ad unit dropdowns   |
| `currencyCache`       | Cached currencies list for campaign modal    |
| `adsPage`             | Current page for ad units pagination         |
| `adsFilters`          | Active filters for ad units list             |
| `adSelectedImages`    | Image selections in the ad unit modal        |
| `activeTab`           | Currently active tab (`campaigns`/`ads`/`placements`) |
| `placementsPage`      | Current page for placements list             |
| `placementsFilters`   | Active filters for placements list           |
| `currentPlacementId`  | Selected placement for placement items panel |
| `placementItemsPage`  | Current page for placement items pagination  |

**Key functions:**

| Function                    | Description                                              |
|-----------------------------|----------------------------------------------------------|
| `loadCampaigns()`           | Fetches and renders campaigns table with pagination      |
| `openCampaignModal(data?)`  | Opens add/edit campaign modal; populates dropdowns       |
| `saveCampaign()`            | POST/PUT to `/api/ad_campaigns`; refreshes list          |
| `deleteCampaign(id)`        | DELETE with confirmation                                 |
| `loadAds()`                 | Fetches and renders ad units table; includes thumbnail   |
| `openAdModal(data?)`        | Opens tabbed ad modal; loads translations & images       |
| `saveAd()`                  | POST/PUT to `/api/ads`; auto-saves EN translation        |
| `deleteAd(id)`              | DELETE with confirmation                                 |
| `loadAdTranslations(adId)`  | Fetches translations for Translations tab                |
| `saveTranslation()`         | POST/PUT to `/api/ad_translations`                       |
| `loadAllAdImages(adId)`     | Fetches all images (types 13–20) for Images tab          |
| `loadPlacements()`          | Fetches and renders placements table                     |
| `openPlacementModal(data?)` | Opens add/edit placement modal                           |
| `savePlacement()`           | POST/PUT to `/api/ad_placements`                         |
| `deletePlacement(id)`       | DELETE with confirmation                                 |
| `loadPlacementItems(pId)`   | Loads items for the selected placement                   |
| `openPlacementItemModal(data?)` | Opens add/edit item modal; populates ad dropdown     |
| `savePlacementItem()`       | POST/PUT to `/api/ad_placement_items`                    |
| `deletePlacementItem(id)`   | DELETE with confirmation                                 |

**Helper utilities:**
- `t(key, fallback)` — Translation lookup from `STRINGS` object
- `esc(str)` — XSS-safe HTML escaping via `createTextNode`
- `openModal(id)` / `closeModal(id)` — Modal visibility helpers
- `toast(msg, type)` — Toast notification (success/error/warning)
- `formatDate(str)` — Formats ISO date string for display

---

### CSS: `admin/assets/css/pages/ads.css`

**Location:** `admin/assets/css/pages/ads.css`

Styles specific to the Ads management page, including:
- Tab navigation styling
- Placement items nested panel
- Ad thumbnail column in the table
- Modal tab bar (Basic / Translations / Images)
- Image grid for the Images tab

---

### Language Files

**Location:** `languages/Ads/{lang}.json`

Structure:
```json
{
  "strings": {
    "title": "...",
    "subtitle": "...",
    "tab_campaigns": "...",
    "tab_ads": "...",
    "tab_placements": "...",
    "status": { "active": "...", "paused": "...", ... },
    "pricing_model": { "fixed": "...", "cpm": "...", "cpc": "..." },
    "target_type": { "url": "...", "entity": "..." },
    "table": { ... },
    "campaigns_table": { ... },
    "modal": { ... },
    "form": { ... },
    "placement_form": { ... },
    "placement_item_form": { ... },
    "placements_table": { ... },
    "placement_items_table": { ... },
    "tabs": { "basic": "...", "translations": "...", "images": "..." },
    "translations": { ... },
    "images": { "types": { "ad_homepage_banner": "...", ... } }
  }
}
```

The `images.types` keys map to image type names used by the media API. The 8 ad image types have IDs 13–20 in the `image_types` table:

| Key                | Label                    | Dimensions |
|--------------------|--------------------------|------------|
| `ad_homepage_banner` | Homepage Banner        | 1440×400   |
| `ad_section_banner`  | Section Banner         | 1200×300   |
| `ad_square`          | Square Ad              | 400×400    |
| `ad_store_banner`    | Store Banner           | 1200×300   |
| `ad_small`           | Small Ad               | 300×250    |
| `ad_search_banner`   | Search Banner          | 1200×200   |
| `ad_mobile_banner`   | Mobile Banner          | 768×250    |
| `ad_thumb`           | Thumbnail (default)    | 300×150    |

---

## Architecture & Data Flow

```
Browser (admin/fragments/ads.php)
    │
    ├─ window.ADS_CONFIG injected by PHP
    │
    └─ ads.js (IIFE)
          │
          ├── GET /api/ad_campaigns?tenant_id=X   → PdoAdCampaignsRepository::all()
          ├── GET /api/ads?tenant_id=X             → PdoAdsRepository::all()
          ├── GET /api/ad_translations?ad_id=Y    → PdoAdTranslationsRepository::all()
          ├── GET /api/ad_placements?tenant_id=X  → PdoAdPlacementsRepository::all()
          └── GET /api/ad_placement_items?placement_id=Z&tenant_id=X
                  → PdoAdPlacementItemsRepository::all()
                  → SQL: INNER JOIN ad_placements ap ON api.placement_id = ap.id
                         LEFT JOIN ads a ON api.ad_id = a.id
                         LEFT JOIN ad_translations atr ON a.id = atr.ad_id AND atr.language_code = 'en'
```

### Entity Relationships

```
tenants
  └── ad_campaigns (tenant_id)
        └── ads (campaign_id)
              ├── ad_translations (ad_id)  [multilingual title/description]
              ├── media (entity_type ads)   [8 image types]
              └── ad_placement_items (ad_id)
                    └── ad_placements (placement_id → tenant_id)

ad_campaigns
  └── ad_payments (campaign_id)
```

---

## Security & Tenant Isolation

- **All read queries** filter by `tenant_id` either directly (campaigns, placements) or via `INNER JOIN` (ads → ad_campaigns, placement items → ad_placements).
- **Writes (save/delete)** always include `tenant_id` or `AND tenant_id = :tenant_id` in the WHERE clause to prevent cross-tenant modification.
- **Ads create** verifies the target campaign belongs to the tenant before inserting.
- **Placement item delete** uses `DELETE api FROM ad_placement_items api INNER JOIN ad_placements ap ON api.placement_id = ap.id WHERE api.id = :id AND ap.tenant_id = :tenant_id`.
- **`tenant_id` source:** `$_GET['tenant_id']` or `$_SESSION['tenant_id']`. Missing tenant returns HTTP 401.

---

## Ad Stats & Tracking

### `ad_stats` Table

Stores daily aggregated statistics for each ad unit.

| Column   | Type                              | Notes                                          |
|----------|-----------------------------------|------------------------------------------------|
| `id`     | BIGINT UNSIGNED AUTO_INCREMENT PK |                                                |
| `ad_id`  | BIGINT UNSIGNED NOT NULL          | FK → `ads.id` ON DELETE CASCADE                |
| `views`  | INT DEFAULT 0                     | Total views recorded for this ad on this date  |
| `clicks` | INT DEFAULT 0                     | Total clicks recorded for this ad on this date |
| `date`   | DATE NOT NULL                     | The calendar date of the stats record          |

**Unique constraint:** `(ad_id, date)` — one row per ad per day.

> **Important:** The `ads` table no longer has `views_count` or `clicks_count` columns. All statistics must be sourced from `ad_stats`.

---

### Recording a View

```sql
INSERT INTO ad_stats (ad_id, date, views, clicks)
VALUES (?, CURDATE(), 1, 0)
ON DUPLICATE KEY UPDATE views = views + 1;
```

**Deduplication:** The tracking endpoint uses a session key (`adv_{ad_id}_{YYYYMMDD}`) to prevent the same user from being counted more than once per day.

---

### Recording a Click

```sql
INSERT INTO ad_stats (ad_id, date, views, clicks)
VALUES (?, CURDATE(), 0, 1)
ON DUPLICATE KEY UPDATE clicks = clicks + 1;
```

Clicks are not deduplicated — every click is counted.

---

### CTR Calculation

CTR (Click-Through Rate) = `clicks / views × 100`

```
CTR% = (total_clicks / total_views) × 100
```

- If `views = 0`, CTR is `0%`.
- Displayed to two decimal places (e.g., `3.70%`).

---

### Tracking API Endpoints

| Endpoint               | Method | Description                           |
|------------------------|--------|---------------------------------------|
| `/api/track_view.php`  | GET    | Record a view for `?id=AD_ID`         |
| `/api/track_click.php` | GET    | Record a click for `?id=AD_ID`        |
| `/api/get_ad_stats.php`| GET    | Get stats for `?ad_id=X` or `?ad_ids=1,2,3`. Optional `?days=N` for date range. |

**Frontend tracking pattern:**
```javascript
// When an ad is displayed
fetch('/api/track_view.php?id=' + adId);

// When an ad is clicked
fetch('/api/track_click.php?id=' + adId);
```

---

### Full Tracking Flow

```
User sees ad
    │
    └─► fetch('/api/track_view.php?id=AD_ID')
            │
            ├─ Check session key → already tracked today? Stop.
            ├─ INSERT INTO ad_stats ... ON DUPLICATE KEY UPDATE views = views + 1
            └─ Return { success: true }

User clicks ad
    │
    └─► fetch('/api/track_click.php?id=AD_ID')
            │
            ├─ INSERT INTO ad_stats ... ON DUPLICATE KEY UPDATE clicks = clicks + 1
            └─ Return { success: true }

Admin views stats
    │
    └─► GET /api/ads?tenant_id=X
            │
            └─ SQL: SELECT a.*, SUM(s.views), SUM(s.clicks) FROM ads a LEFT JOIN ad_stats s ON s.ad_id = a.id GROUP BY a.id
```

---

### Stats in Admin Table

The `/api/ads` endpoint now returns two additional aggregated fields per ad:
- `views_total` — lifetime total views from `ad_stats`
- `clicks_total` — lifetime total clicks from `ad_stats`

These replace the previously removed `views_count` and `clicks_count` columns on the `ads` table.

---

## Important Technical Notes

1. **`ads` table has no `title` column.** Titles are always in `ad_translations`. Any query that needs the ad title must `LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = 'en'` and use `COALESCE(atr.title, '')`.

2. **`ad_placements` has no `updated_at` column.** The repository's `ALLOWED_ORDER_BY` list includes `updated_at` for historical reasons but it will error if used. Only use the listed columns that match the actual schema.

3. **English translation is mandatory** when creating an ad unit from the admin UI. The Basic tab auto-saves the EN translation. The `ad_en_title` and `ad_en_description` fields in the modal are required.

4. **`placement_key` and `code`** must match `/^[a-z0-9_-]+$/i`. The `code` column is globally unique (`UNIQUE KEY`); the `placement_key` is indexed but not globally unique (it can repeat across tenants).

5. **Image type ID 20** (`ad_thumb`) is used as the primary thumbnail in the ad units table. The full image set for an ad unit uses image type IDs 13–20.

6. **Pagination offset** is computed from `page` parameter: `offset = (page - 1) * limit`. The `offset` query param sent directly is also accepted by `ad_placement_items` route.

7. **`ad_payments`** is a simple ledger table. There is no automatic payment flow — payments are created/updated manually via the API.