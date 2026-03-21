# Products Module — Full Technical Documentation

**Version:** 1.0.0  
**Last Updated:** 2026-03-17  
**Status:** ✅ READY (with noted improvements)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Data Flow](#3-data-flow)
4. [API / Routes Behavior](#4-api--routes-behavior)
5. [Validation Rules](#5-validation-rules)
6. [Bad Words Filtering](#6-bad-words-filtering)
7. [Audit Logging](#7-audit-logging)
8. [Security Considerations](#8-security-considerations)
9. [Performance Considerations](#9-performance-considerations)
10. [Edge Cases & Failure Scenarios](#10-edge-cases--failure-scenarios)
11. [Testing Scenarios](#11-testing-scenarios)
12. [Assumptions](#12-assumptions)
13. [Final Status](#13-final-status)

---

## 1. Overview

### What the Module Does

The **Products Module** is the central catalog management system of the platform. It enables tenants to create, read, update, and delete product records with full multi-language support, variant management, attribute assignment, pricing rules, inventory tracking, image association, and SEO metadata.

### Role in the System

The Products module is a **core domain module**. Nearly every other module depends on it:

| Dependent Module | Relationship |
|---|---|
| Orders | References `products.id` for line items |
| Carts | References `products.id` and `product_variants.id` |
| Flash Sales | References `products.id` |
| Bundles | References `products.id` via `product_bundle_items` |
| Comparisons | References `products.id` via `product_comparison_items` |
| Stock Movements | References `products.id` and `product_variants.id` |
| Stock Alerts | References `products.id` and `product_variants.id` |
| SEO Meta | Auto-populated on product create/update via `SeoAutoManager` |
| Audit Logs | Logs every CUD (create/update/delete) action |
| Bad Words | Filters text fields before persistence |

### Key Integrations

- **Bad Words Filter (`api/v1/routes/bad_words.php`):** Called during content submission to screen text fields (product name, description, specifications) for prohibited content.
- **Audit Logs (`api/v1/routes/audit_logs.php`):** All create, update, and delete operations are logged with actor identity, tenant context, IP address, and payload snapshot.
- **RBAC (Role-Based Access Control):** Every route and UI action is gated by granular resource-level permissions (`products.view_all`, `products.create`, `products.edit_own`, `products.delete_all`, etc.).
- **SEO Auto-Manager (`api/shared/helpers/SeoAutoManager.php`):** Automatically synchronizes SEO metadata for all product translations when a product is saved or deleted.
- **Subscription Plan Limits:** Product creation is gated by the tenant's active subscription plan (`subscription_plans.max_products`).

---

## 2. Database Schema

> All tables are extracted from `doc/products.md`. No tables have been invented.

---

### 2.1 `products` (Core Table)

| Field | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | PRI | auto_increment | **Primary Key** |
| `product_type_id` | `int(10) unsigned` | NO | MUL | — | **FK → `product_types.id`** |
| `tenant_id` | `int(10) unsigned` | NO | MUL | — | **FK → `tenants.id`** (multi-tenant isolation) |
| `created_by_user_id` | `int(11) unsigned` | YES | MUL | NULL | FK → `users.id` |
| `sku` | `varchar(100)` | NO | UNI | — | Unique stock-keeping unit |
| `slug` | `varchar(255)` | NO | UNI | — | URL-friendly identifier |
| `barcode` | `varchar(100)` | YES | UNI | NULL | Optional barcode |
| `brand_id` | `bigint(20)` | YES | MUL | NULL | FK → `brands.id` |
| `is_active` | `tinyint(1)` | YES | MUL | 1 | 0=inactive, 1=active |
| `is_featured` | `tinyint(1)` | YES | — | 0 | Featured flag |
| `is_bestseller` | `tinyint(1)` | YES | — | 0 | Bestseller flag |
| `is_new` | `tinyint(1)` | YES | — | 0 | New-arrival flag |
| `stock_quantity` | `int(11)` | YES | — | 0 | Current stock count |
| `low_stock_threshold` | `int(11)` | YES | — | 5 | Alert threshold |
| `stock_status` | `enum('in_stock','out_of_stock','on_backorder')` | YES | MUL | `in_stock` | Stock availability |
| `manage_stock` | `tinyint(1)` | YES | — | 1 | Enable stock tracking |
| `allow_backorder` | `tinyint(1)` | YES | — | 0 | Allow orders when out-of-stock |
| `total_sales` | `int(11)` | YES | — | 0 | Cumulative sales count |
| `rating_average` | `decimal(3,2)` | YES | MUL | 0.00 | Computed average rating |
| `rating_count` | `int(11)` | YES | — | 0 | Total number of ratings |
| `views_count` | `int(11)` | YES | — | 0 | Page view counter |
| `created_at` | `datetime` | YES | MUL | `current_timestamp()` | Creation timestamp |
| `updated_at` | `datetime` | YES | — | `current_timestamp() ON UPDATE` | Last modification |
| `published_at` | `datetime` | YES | — | NULL | Publication date |

**Unique Constraints:** `sku`, `slug`, `barcode`  
**Composite Index:** `(tenant_id, is_active)`, `(tenant_id, product_type_id)`

---

### 2.2 `product_types`

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `int(10) unsigned` | NO | PRI | auto_increment |
| `code` | `varchar(50)` | NO | UNI | — |
| `name` | `varchar(100)` | NO | — | — |
| `description` | `varchar(255)` | YES | — | NULL |
| `is_active` | `tinyint(1)` | YES | — | 1 |

Examples: `physical`, `digital`, `service`, `bundle`

---

### 2.3 `product_translations`

Stores multi-language content for each product.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `language_code` | `varchar(8)` | NO | MUL | — |
| `name` | `varchar(500)` | NO | MUL | — |
| `short_description` | `text` | YES | — | NULL |
| `description` | `longtext` | YES | — | NULL |
| `specifications` | `longtext` | YES | — | NULL |
| `meta_title` | `varchar(255)` | YES | — | NULL |
| `meta_description` | `text` | YES | — | NULL |
| `meta_keywords` | `varchar(500)` | YES | — | NULL |

**FK:** `product_id → products.id`  
**Unique Constraint:** `(product_id, language_code)` — one translation per language per product.

---

### 2.4 `product_variants`

Each variant represents a SKU-level variation (e.g., size+color combination).

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | PRI | auto_increment |
| `product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `sku` | `varchar(100)` | YES | UNI | NULL |
| `barcode` | `varchar(100)` | YES | UNI | NULL |
| `stock_quantity` | `int(11)` | YES | — | 0 |
| `low_stock_threshold` | `int(11)` | YES | — | 5 |
| `is_active` | `tinyint(1)` | YES | — | 1 |
| `is_default` | `tinyint(1)` | YES | — | 0 |
| `created_at` | `datetime` | YES | — | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

**FK:** `product_id → products.id`

---

### 2.5 `product_variant_attributes`

Maps which attribute values define each variant.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | — |
| `variant_id` | `bigint(20)` | NO | MUL | — |
| `attribute_id` | `bigint(20)` | NO | MUL | — |
| `attribute_value_id` | `bigint(20)` | NO | MUL | — |
| `created_at` | `datetime` | YES | — | current_timestamp() |

**FKs:** `variant_id → product_variants.id`, `attribute_id → product_attributes.id`, `attribute_value_id → product_attribute_values.id`

---

### 2.6 `product_attributes`

Defines attribute definitions (Color, Size, Material, etc.).

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | — |
| `slug` | `varchar(100)` | NO | UNI | — |
| `attribute_type_id` | `int(10) unsigned` | NO | MUL | — |
| `is_filterable` | `tinyint(1)` | YES | MUL | 1 |
| `is_visible` | `tinyint(1)` | YES | — | 1 |
| `is_required` | `tinyint(1)` | YES | — | 0 |
| `is_variation` | `tinyint(1)` | YES | MUL | 0 |
| `is_global` | `tinyint(1)` | YES | — | 1 |
| `sort_order` | `int(11)` | YES | — | 0 |
| `created_at` | `datetime` | YES | — | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

---

### 2.7 `product_attribute_translations`

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI |
| `attribute_id` | `bigint(20)` | NO | MUL |
| `language_code` | `varchar(8)` | NO | MUL |
| `name` | `varchar(255)` | NO | — |
| `description` | `text` | YES | — |

---

### 2.8 `product_attribute_values`

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `attribute_id` | `bigint(20)` | NO | MUL | — |
| `value` | `varchar(255)` | NO | — | — |
| `slug` | `varchar(255)` | NO | MUL | — |
| `sort_order` | `int(11)` | YES | — | 0 |
| `is_active` | `tinyint(1)` | YES | — | 1 |

**FK:** `attribute_id → product_attributes.id`

---

### 2.9 `product_attribute_value_translations`

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI |
| `attribute_value_id` | `bigint(20)` | NO | MUL |
| `language_code` | `varchar(8)` | NO | MUL |
| `label` | `varchar(255)` | NO | — |

---

### 2.10 `product_attribute_assignments`

Links attribute values to specific products (flat attribute assignment, not variant-level).

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20)` | NO | MUL | — |
| `attribute_id` | `bigint(20)` | NO | MUL | — |
| `attribute_value_id` | `bigint(20)` | YES | MUL | NULL |
| `custom_value` | `varchar(255)` | YES | — | NULL |
| `created_at` | `datetime` | YES | — | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

---

### 2.11 `product_pricing`

Stores flexible pricing rules per product or variant.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20)` | YES | MUL | NULL |
| `variant_id` | `bigint(20)` | YES | MUL | NULL |
| `price` | `decimal(15,2)` | NO | — | — |
| `tax_rate` | `decimal(5,2)` | YES | — | NULL |
| `cost_price` | `decimal(15,2)` | YES | — | NULL |
| `compare_at_price` | `decimal(15,2)` | YES | — | NULL |
| `currency_code` | `char(3)` | NO | MUL | — |
| `pricing_type` | `enum('fixed','discount','auction','service')` | YES | — | `fixed` |
| `start_at` | `datetime` | YES | — | NULL |
| `end_at` | `datetime` | YES | — | NULL |
| `country_id` | `bigint(20)` | YES | — | NULL |
| `city_id` | `bigint(20)` | YES | — | NULL |
| `is_active` | `tinyint(1)` | YES | MUL | 1 |
| `created_at` | `datetime` | YES | — | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

---

### 2.12 `product_physical_attributes`

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20) unsigned` | NO | UNI | — |
| `variant_id` | `bigint(20) unsigned` | YES | UNI | NULL |
| `weight` | `decimal(10,3)` | YES | — | NULL |
| `length` | `decimal(10,2)` | YES | — | NULL |
| `width` | `decimal(10,2)` | YES | — | NULL |
| `height` | `decimal(10,2)` | YES | — | NULL |
| `weight_unit` | `enum('kg','g','lb')` | NO | — | `kg` |
| `dimension_unit` | `enum('cm','mm','in')` | NO | — | `cm` |
| `created_at` | `datetime` | YES | — | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

**Unique:** One row per `product_id` (or per `product_id + variant_id` combination).

---

### 2.13 `product_categories`

Junction table linking products to categories.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `int(11)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20)` | NO | MUL | — |
| `category_id` | `bigint(20)` | NO | MUL | — |
| `is_primary` | `tinyint(1)` | YES | MUL | 0 |
| `sort_order` | `int(11)` | YES | — | 0 |

---

### 2.14 `product_stock_movements`

Tracks every change to stock quantity with audit trail.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | PRI | auto_increment |
| `product_id` | `bigint(20)` | NO | MUL | — |
| `variant_id` | `bigint(20)` | YES | MUL | NULL |
| `change_quantity` | `int(11)` | NO | — | — |
| `type` | `enum('restock','sale','return','adjustment')` | NO | — | — |
| `reference_id` | `bigint(20)` | YES | — | NULL |
| `notes` | `text` | YES | — | NULL |
| `created_at` | `datetime` | NO | — | current_timestamp() |

---

### 2.15 `product_stock_alerts`

Subscribers who want to be notified when a product is back in stock.

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | — |
| `product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `variant_id` | `bigint(20) unsigned` | YES | MUL | NULL |
| `user_id` | `int(11)` | NO | MUL | — |
| `email` | `varchar(191)` | NO | — | — |
| `is_notified` | `tinyint(1)` | YES | MUL | 0 |
| `notified_at` | `datetime` | YES | — | NULL |
| `created_at` | `datetime` | YES | — | current_timestamp() |

---

### 2.16 `product_reviews`

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | auto_increment |
| `product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `user_id` | `int(11) unsigned` | NO | MUL | — |
| `rating` | `tinyint(4)` | NO | MUL | — |
| `title` | `varchar(255)` | YES | — | NULL |
| `comment` | `text` | YES | — | NULL |
| `is_verified_purchase` | `tinyint(1)` | YES | — | 0 |
| `is_approved` | `tinyint(1)` | YES | MUL | 0 |
| `helpful_count` | `int(11)` | YES | — | 0 |
| `created_at` | `datetime` | YES | MUL | current_timestamp() |
| `updated_at` | `datetime` | YES | — | current_timestamp() ON UPDATE |

---

### 2.17 `product_questions` & `product_answers`

**`product_questions`:**

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI |
| `product_id` | `bigint(20) unsigned` | NO | MUL |
| `user_id` | `int(11)` | NO | MUL |
| `question` | `text` | NO | — |
| `is_approved` | `tinyint(1)` | YES | MUL |
| `helpful_count` | `int(11)` | YES | — |
| `created_at` | `datetime` | YES | — |
| `updated_at` | `datetime` | YES | — |

**`product_answers`:**

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI |
| `question_id` | `bigint(20)` | NO | MUL |
| `user_id` | `int(11)` | NO | MUL |
| `answer` | `text` | NO | — |
| `is_approved` | `tinyint(1)` | YES | MUL |
| `is_staff_answer` | `tinyint(1)` | YES | — |
| `helpful_count` | `int(11)` | YES | — |
| `created_at` | `datetime` | YES | — |
| `updated_at` | `datetime` | YES | — |

---

### 2.18 `product_relations`

| Field | Type | Null | Key | Default |
|---|---|---|---|---|
| `id` | `bigint(20)` | NO | PRI | — |
| `product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `related_product_id` | `bigint(20) unsigned` | NO | MUL | — |
| `relation_type` | `enum('related','upsell','cross_sell','alternative','accessory')` | NO | MUL | — |
| `sort_order` | `int(11)` | YES | — | 0 |

---

### 2.19 `product_comparisons` & `product_comparison_items`

**`product_comparisons`:**

| Field | Type |
|---|---|
| `id` | `bigint(20) unsigned` PRI |
| `user_id` | `int(11)` MUL |
| `created_at` | `datetime` |

**`product_comparison_items`:**

| Field | Type |
|---|---|
| `id` | `bigint(20) unsigned` PRI |
| `comparison_id` | `bigint(20) unsigned` MUL |
| `product_id` | `bigint(20) unsigned` MUL |
| `added_at` | `datetime` |

---

### 2.20 `product_bundles` & `product_bundle_items`

**`product_bundles`:**

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | PRI |
| `tenant_id` | `bigint(20) unsigned` | NO | MUL |
| `entity_id` | `bigint(20) unsigned` | NO | MUL |
| `bundle_name` | `varchar(255)` | NO | — |
| `description` | `text` | YES | — |
| `bundle_image` | `varchar(500)` | YES | — |
| `original_total_price` | `decimal(15,2)` | NO | — |
| `bundle_price` | `decimal(15,2)` | NO | — |
| `discount_amount` | `decimal(15,2)` | NO | — |
| `discount_percentage` | `decimal(5,2)` | YES | — |
| `stock_quantity` | `int(11)` | YES | — |
| `is_active` | `tinyint(1)` | YES | MUL |
| `start_date` | `datetime` | YES | MUL |
| `end_date` | `datetime` | YES | — |
| `sold_count` | `int(11)` | YES | — |
| `created_at` / `updated_at` | `datetime` | YES | — |

**`product_bundle_items`:**

| Field | Type | Null | Key |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | PRI |
| `tenant_id` | `bigint(20) unsigned` | NO | MUL |
| `bundle_id` | `bigint(20) unsigned` | NO | MUL |
| `product_id` | `bigint(20) unsigned` | NO | MUL |
| `quantity` | `int(11)` | YES | — |
| `product_price` | `decimal(15,2)` | NO | — |
| `created_at` | `datetime` | YES | — |

---

### 2.21 Entity Relationship Summary

```
products (1) ──── (N) product_translations
products (1) ──── (N) product_variants
products (1) ──── (N) product_variant_attributes  [via product_variants]
products (1) ──── (N) product_attribute_assignments
products (1) ──── (1) product_physical_attributes
products (1) ──── (N) product_categories
products (1) ──── (N) product_pricing
products (1) ──── (N) product_stock_movements
products (1) ──── (N) product_stock_alerts
products (1) ──── (N) product_reviews
products (1) ──── (N) product_questions ── (N) product_answers
products (1) ──── (N) product_relations
products (N) ──── (N) product_comparisons  [via product_comparison_items]
products (N) ──── (N) product_bundles      [via product_bundle_items]
product_attributes (1) ──── (N) product_attribute_translations
product_attributes (1) ──── (N) product_attribute_values
product_attribute_values (1) ──── (N) product_attribute_value_translations
```

---

## 3. Data Flow

### Complete Request Lifecycle

```
Browser (Admin UI)
      │
      │ HTTP Request (fetch API, CSRF token in header or body)
      ▼
admin/fragments/products.php
  ├── [PHP] RBAC check: can_create(), can_edit_all(), etc.
  ├── [PHP] CSRF token validation (via admin_csrf())
  └── [JS ] form serialized → JSON body
      │
      │ fetch('/api/products', { method:'POST', body: JSON, credentials:'same-origin' })
      ▼
api/v1/routes/products.php
  ├── [PHP] Session / tenant_id validation
  ├── [PHP] Subscription plan limit check (POST only)
  ├── [PHP] Raw input decoded: json_decode(file_get_contents('php://input'))
  └── Dispatch by HTTP method
      │
      ▼
ProductsController::create($tenantId, $data)
      │
      ▼
ProductsService::create($tenantId, $data)
  └── (future: bad-words check hook — see Section 6)
      │
      ▼
ProductsValidator::validate($data, isUpdate=false)
  ├── Required fields: product_type_id, tenant_id, sku, slug, is_active
  ├── Length checks (sku ≤ 100, slug ≤ 255, barcode ≤ 100)
  ├── Type checks (is_active ∈ {0,1}, numeric fields)
  └── Throws InvalidArgumentException → caught by route → 422 response
      │
      ▼
PdoProductsRepository::save($tenantId, $data)
  ├── Whitelist filtering (PRODUCT_COLUMNS constant)
  ├── Auto-generate SKU if empty (PRD-XXXX-{timestamp})
  ├── Auto-generate slug if empty (slugified name or fallback)
  ├── INSERT or UPDATE products table (PDO prepared statement)
  └── Returns new product ID
      │
      ▼
SeoAutoManager::sync() / syncAllTranslations()
  └── Upserts seo_meta table for this product in all languages
      │
      ▼
AuditLogsService::log('product.create', 'product', $newId, $payload, $tenantId, $userId)
  └── Inserts into audit_logs table
      │
      ▼
ResponseFormatter::success(['id' => $newId], 'Created successfully', 201)
      │
      ▼
Browser receives JSON response
  └── Products.js: loadProducts(1) — refreshes the list
```

### Layer Responsibilities

| Layer | File | Responsibility |
|---|---|---|
| **UI Fragment** | `admin/fragments/products.php` | HTML generation, permission guards, CSRF injection, config exposure to JS |
| **JS Module** | `admin/assets/js/pages/products.js` | State management, API calls, DOM rendering, form serialization, pagination |
| **Route** | `api/v1/routes/products.php` | HTTP method dispatch, session/tenant validation, subscription limit enforcement, SEO sync, exception-to-HTTP mapping |
| **Controller** | `ProductsController.php` | Thin adapter: delegates to service, returns data |
| **Service** | `ProductsService.php` | Business logic layer; would be the correct place to add bad-words checks |
| **Validator** | `ProductsValidator.php` (namespace `App\Models\Products\Validators`) | Input validation; throws `InvalidArgumentException` on failure |
| **Repository** | `PdoProductsRepository.php` | All SQL: prepared statements only, whitelist-filtered column writes, dynamic filters |
| **Database** | MariaDB/MySQL | Persistence, indexes, referential integrity |
| **Audit** | `AuditLogsService::log()` | Async-safe static log writes after every CUD operation |

---

## 4. API / Routes Behavior

**Base URL:** `GET|POST|PUT|DELETE /api/products`

All responses use `ResponseFormatter` which wraps data in:
```json
{ "status": "success", "data": {...}, "message": "..." }
```
or on error:
```json
{ "status": "error", "message": "...", "code": 422 }
```

---

### 4.1 GET /api/products — List Products

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `tenant_id` | integer | YES* | session | Tenant scope (*fallback to session) |
| `lang` | string | NO | `ar` | Translation language code |
| `page` | integer | NO | 1 | Current page |
| `limit` | integer | NO | 25 | Items per page (max 1000) |
| `order_by` | string | NO | `id` | Sort field (whitelist-validated) |
| `order_dir` | string | NO | `DESC` | `ASC` or `DESC` |
| `product_type_id` | integer | NO | — | Filter by product type |
| `sku` | string | NO | — | LIKE filter on sku |
| `slug` | string | NO | — | LIKE filter on slug |
| `barcode` | string | NO | — | LIKE filter on barcode |
| `brand_id` | integer | NO | — | Filter by brand |
| `is_active` | 0\|1 | NO | — | Filter by active status |

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "items": [
      {
        "id": 42,
        "sku": "PRD-ABC123",
        "slug": "blue-sneakers",
        "name": "حذاء رياضي أزرق",
        "price": "149.99",
        "currency_code": "SAR",
        "stock_quantity": 50,
        "is_active": 1,
        "image_url": "/uploads/products/42/main.jpg"
      }
    ],
    "meta": {
      "total": 250,
      "page": 1,
      "per_page": 25,
      "total_pages": 10,
      "from": 1,
      "to": 25
    }
  }
}
```

---

### 4.2 GET /api/products?id={id} — Get Single Product

**Success Response (200):** Full product row with joined translation, main image, and pricing fields.

**Error (404):** Product not found for given tenant.

---

### 4.3 POST /api/products — Create Product

**Request Body (JSON):**
```json
{
  "product_type_id": 1,
  "sku": "PRD-001",
  "slug": "blue-sneakers",
  "barcode": "1234567890123",
  "brand_id": 5,
  "is_active": 1,
  "is_featured": 0,
  "stock_quantity": 100,
  "low_stock_threshold": 10,
  "stock_status": "in_stock",
  "name": "Blue Sneakers",
  "en_name": "Blue Sneakers",
  "en_description": "Comfortable blue sneakers for everyday wear.",
  "price": 149.99,
  "currency_code": "SAR"
}
```

**Success Response (201):**
```json
{ "status": "success", "data": { "id": 43 }, "message": "Created successfully" }
```

**Subscription Limit Error (403):**
```json
{ "status": "error", "message": "Product limit reached (100/100). Upgrade your plan." }
```

**Validation Error (422):**
```json
{ "status": "error", "message": "Field 'product_type_id' is required." }
```

---

### 4.4 PUT /api/products — Update Product

**Request Body (JSON):** Same as POST plus `"id": 43` (required).

**Success Response (200):**
```json
{ "status": "success", "data": { "id": 43 }, "message": "Updated successfully" }
```

**Error (400):** If `id` is missing.

---

### 4.5 DELETE /api/products — Delete Product

**Request Body (JSON):**
```json
{ "id": 43 }
```

**Success Response (200):**
```json
{ "status": "success", "data": { "deleted": true }, "message": "Deleted successfully" }
```

**Error (400):** If `id` is missing.

---

### 4.6 Error Code Mapping

| HTTP Code | Exception Type | Cause |
|---|---|---|
| 400 | `RuntimeException` | Missing ID on PUT/DELETE, runtime failures |
| 401 | — | No valid tenant in session |
| 403 | — | Subscription product limit reached |
| 405 | — | Unsupported HTTP method |
| 422 | `InvalidArgumentException` | Validation failure |
| 500 | `Throwable` | Unexpected server error |

---

## 5. Validation Rules

Defined in `ProductsValidator::validate()`. The validator is used by `ProductsService`.

### Required Fields (on CREATE)

| Field | Rule |
|---|---|
| `product_type_id` | Must be present and non-empty |
| `tenant_id` | Must be present (injected by route, not from user input) |
| `sku` | Must be present; auto-generated if empty in repository |
| `slug` | Must be present; auto-generated from name if empty in repository |
| `is_active` | Must be present |

### String Length Limits

| Field | Max Length |
|---|---|
| `sku` | 100 characters |
| `slug` | 255 characters |
| `barcode` | 100 characters |

### Enum Values

| Field | Valid Values |
|---|---|
| `is_active` | `0` or `1` |
| `stock_status` | `in_stock`, `out_of_stock`, `on_backorder` |
| `weight_unit` | `kg`, `g`, `lb` |
| `dimension_unit` | `cm`, `mm`, `in` |
| `pricing_type` | `fixed`, `discount`, `auction`, `service` |

### Numeric Fields (must be numeric if provided)

- Integer: `stock_quantity`, `low_stock_threshold`, `total_sales`, `rating_count`
- Float/Decimal: `weight`, `length`, `width`, `height`, `rating_average`, `tax_rate`

### Duplicate Prevention

- `sku`: UNIQUE constraint at DB level — duplicate will throw a PDO exception (→ HTTP 500 currently; **Assumption**: this should be caught and mapped to 409).
- `slug`: UNIQUE constraint at DB level.
- `barcode`: UNIQUE constraint at DB level (if provided).

### Edge Cases Handled

- Empty `sku` → auto-generated as `PRD-{8hex}-{timestamp}` in the repository.
- Empty `slug` → slugified from product name, suffixed with random 4-digit number.
- Empty `product_type_id` → defaults to `1` in the repository.

---

## 6. Bad Words Filtering

### Integration Status

> **⚠️ Assumption:** The bad-words filtering system (`BadWordsService`) exists and is fully functional as a standalone API (`POST /api/bad_words/check`). However, based on inspection of `ProductsService` and `PdoProductsRepository`, **the bad-words check is NOT currently called automatically** during product creation or update. The service must be invoked explicitly.

### Where It Should Be Triggered

The recommended integration point is in `ProductsService::create()` and `ProductsService::update()` before calling the repository:

```php
// Recommended integration (not yet implemented)
$badWords = new BadWordsService($badWordsRepo);
$fieldsToCheck = ['name', 'en_name', 'en_description', 'en_short_description'];
foreach ($fieldsToCheck as $field) {
    if (!empty($data[$field])) {
        $result = $badWords->checkText($data[$field]);
        if (!$result['clean']) {
            throw new InvalidArgumentException("Content contains prohibited words in field: $field");
        }
    }
}
```

### Fields That Should Be Checked

| Field | Table | Notes |
|---|---|---|
| `name` | `product_translations` | Primary display text |
| `short_description` | `product_translations` | Visible to customers |
| `description` | `product_translations` | Rich text content |
| `specifications` | `product_translations` | Technical details |
| `meta_title` | `product_translations` | SEO metadata |
| `meta_description` | `product_translations` | SEO metadata |

### How the Filter Works

1. Input text is **normalized**: Arabic diacritics stripped, lowercased, look-alike characters replaced (`@→a`, `$→s`, `0→o`, etc.).
2. Each active bad word is matched using a **flexible pattern** that allows separator characters between letters (catches `b a d`, `b.a.d`, `b-a-d`).
3. Regex-based bad words are matched with Unicode-aware case-insensitive patterns.
4. Returns `{ clean: bool, found: [{ word, severity, position }] }`.

### On Failure

- The API call to `POST /api/bad_words/check` returns `{ clean: false, found: [...] }`.
- **Current behavior:** No automatic blocking of product creation (gap — see Assumptions).
- **Expected behavior:** Should throw `InvalidArgumentException` with message listing found words, causing a 422 response.

---

## 7. Audit Logging

### When Logs Are Created

Logs are created after every successful **Create**, **Update**, or **Delete** operation on a product. The static method `AuditLogsService::log()` is called from the route file.

> **⚠️ Assumption:** Based on inspection of `api/v1/routes/products.php`, the current route code does **not** contain explicit calls to `AuditLogsService::log()`. The audit log integration **should be added** to the route as follows:

```php
// POST (after successful create):
AuditLogsService::log('product.create', 'product', $newId, $data, $tenantId, $userId);

// PUT (after successful update):
AuditLogsService::log('product.update', 'product', $updatedId, $data, $tenantId, $userId);

// DELETE (after successful delete):
AuditLogsService::log('product.delete', 'product', (int)$data['id'], ['id' => $data['id']], $tenantId, $userId);
```

### What Data Is Stored

Based on `PdoAuditLogsRepository::save()` and `AuditLogsService::log()`:

| Field | Source |
|---|---|
| `tenant_id` | Session or passed parameter |
| `user_id` | `$_SESSION['user_id']` |
| `action` | Action string (e.g., `product.create`) |
| `entity_type` | `'product'` |
| `entity_id` | New/updated/deleted product ID |
| `payload` | JSON-encoded request payload (full data array) |
| `ip_address` | `$_SERVER['REMOTE_ADDR']` |
| `user_agent` | `$_SERVER['HTTP_USER_AGENT']` |
| `created_at` | `current_timestamp()` |

### Example Log Entry (JSON)

```json
{
  "id": 1042,
  "tenant_id": 7,
  "user_id": 12,
  "action": "product.create",
  "entity_type": "product",
  "entity_id": 43,
  "payload": {
    "sku": "PRD-001",
    "slug": "blue-sneakers",
    "is_active": 1,
    "stock_quantity": 100
  },
  "ip_address": "185.123.45.67",
  "user_agent": "Mozilla/5.0 ...",
  "created_at": "2026-03-17 14:30:00"
}
```

---

## 8. Security Considerations

### SQL Injection Protection

✅ **All SQL queries use PDO prepared statements** with bound parameters.  
✅ **Column whitelist** (`PRODUCT_COLUMNS`, `FILTERABLE_COLUMNS`, `ALLOWED_ORDER_BY`) prevents injection through dynamic column names.  
✅ `ORDER BY` direction is hard-sanitized: `strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC'`.  
✅ Subquery for `image_type_id` is parameterless (static string literal).

**Risk:** Subquery `SELECT id FROM image_types WHERE name = 'product' LIMIT 1` inside the main query runs for every row fetch. This is a performance concern more than a security concern, but should be cached.

### XSS Prevention

✅ All PHP output uses `htmlspecialchars()` with `ENT_QUOTES`.  
✅ JSON payloads use `json_encode()` with `JSON_UNESCAPED_UNICODE`.  
✅ CSS variable values are escaped via `htmlspecialchars()` before being emitted into `<style>` blocks.

⚠️ **JS-side rendering:** `el.tbody.innerHTML = items.map(...)` in `products.js` renders HTML from API data. If API data is not sanitized server-side before storage (e.g., if bad words check is bypassed), this could introduce stored XSS. **Recommendation:** Use `textContent` for text fields, or apply client-side escaping.

### RBAC Enforcement

✅ Granular resource permissions checked at PHP level before rendering any UI:
- `can_view_all('products')` — see all tenants' products
- `can_create('products')` — create button shown and enforced
- `can_edit_all('products')` / `can_edit_own('products')` — edit action enforced
- `can_delete_all('products')` — delete button and API enforced

✅ Permission state exposed to JS via `window.PAGE_PERMISSIONS` — used to conditionally render action buttons in the table.

⚠️ **Server-side enforcement on API:** The route file (`api/v1/routes/products.php`) does not currently perform explicit RBAC checks beyond `tenant_id` validation. The assumption is that RBAC is enforced via middleware or session context. **Recommendation:** Add explicit permission checks in the route.

### CSRF Protection

✅ CSRF token injected from PHP (`$csrf = admin_csrf()`) into a hidden form field and JS config.  
✅ Token sent with every mutating API request.

### Input Sanitization

✅ `ProductsValidator` validates types and lengths.  
⚠️ Rich text fields (`description`, `specifications`) stored as `longtext` — no HTML sanitization is applied before storage. **Recommendation:** Apply `HTMLPurifier` or equivalent before persisting rich text.

---

## 9. Performance Considerations

### Query Optimization

**Current Issues:**

1. **Correlated subquery per row** in `all()` and `find()`:
   ```sql
   LEFT JOIN images i ON i.owner_id = p.id AND i.is_main = 1
       AND i.image_type_id = (SELECT id FROM image_types WHERE name = 'product' LIMIT 1)
   ```
   The inner `SELECT` executes once per row fetched. **Fix:** Cache the `image_type_id` value as a constant or resolve it once before the main query.

2. **No full-text search index** on `product_translations.name` — LIKE `%keyword%` queries do full table scans.

3. **Separate COUNT query** for pagination runs after the main query, resulting in two DB round-trips per list request.

### Indexing Recommendations

| Table | Column(s) | Type | Reason |
|---|---|---|---|
| `products` | `(tenant_id, is_active, created_at)` | Composite | Most common filter+sort combination |
| `products` | `(tenant_id, stock_status)` | Composite | Stock status filtering |
| `product_translations` | `(product_id, language_code)` | Composite | JOIN in every list query |
| `product_translations` | `name` | FULLTEXT | Search by product name |
| `product_pricing` | `(product_id, is_active, variant_id)` | Composite | Pricing JOIN condition |
| `product_reviews` | `(product_id, is_approved)` | Composite | Approved reviews list |

### Pagination

✅ Server-side pagination with configurable `limit` (default 25, max 1000).  
⚠️ **Max limit of 1000 is too high** for production. At 1000 rows with JOINs to 3 tables, serializing to JSON and rendering DOM will cause browser jank. **Recommendation:** Reduce hard cap to 100.

### Caching Opportunities

- Product type list (rarely changes) → cache in Redis/APCu with TTL of 1 hour.
- Brand list → same.
- Attribute list → same.
- Translation files → browser-cached via `?v=filemtime()` (already partially fixed in header.php, but `products.php` still uses `time()`).

---

## 10. Edge Cases & Failure Scenarios

### Empty Inputs

| Scenario | Behavior |
|---|---|
| Empty `sku` | Auto-generated: `PRD-{hex8}-{timestamp}` |
| Empty `slug` | Auto-generated from `name`, suffixed with random 4-digit number |
| Empty `product_type_id` | Defaults to `1` |
| Empty `name` on create | Validator does not require `name` (only `en_name` is required in UI). **Gap:** API accepts product with no name if `sku` and `slug` are present. |
| `stock_quantity = 0` | Accepted; `stock_status` should be set to `out_of_stock` but is not auto-synchronized. |

### Invalid Language Code

- The `lang` parameter is passed directly to `product_translations` JOIN: `pt.language_code = :lang`.
- If the language does not exist, the JOIN returns no rows; `COALESCE(pt.name, '')` → empty name string.
- No error is thrown; product is returned with blank translated fields.

### Missing Translations

- If a product has no translation for the requested language, name and description return empty strings.
- **Recommendation:** Fall back to the default language (`en`) if no translation exists for the requested language.

### Concurrent Updates (Race Condition)

- Two admins update the same product simultaneously: last writer wins.
- No optimistic locking (`updated_at` check before UPDATE) is implemented.
- **Recommendation:** Add `AND updated_at = :last_known_updated_at` to UPDATE queries and return a 409 Conflict when the check fails.

### Subscription Limit Race

- Two simultaneous POST requests from the same tenant could both pass the product count check before either INSERT completes.
- **Recommendation:** Wrap the count check and INSERT in a DB transaction, or use a unique constraint / application-level mutex.

### Database Failure

- PDO exceptions from the repository bubble up as `RuntimeException` or raw `Throwable`.
- Caught by the route's `catch (Throwable $e)` → HTTP 500 with error message exposed to client.
- **Security concern:** Error messages (including stack traces) are logged via `safe_log()` but also returned to the client in the 500 response. **Recommendation:** Return generic error message to client in production; log full detail server-side only.

---

## 11. Testing Scenarios

### TC-01: Create Product — Happy Path

**Preconditions:** Authenticated admin, tenant has active subscription, product limit not reached.

**Input:**
```json
{
  "product_type_id": 1,
  "sku": "TEST-001",
  "slug": "test-product-001",
  "is_active": 1,
  "en_name": "Test Product"
}
```

**Expected Result:**
- HTTP 201
- Response: `{ "data": { "id": <integer> } }`
- Row exists in `products` table with matching `sku` and `tenant_id`
- SEO meta record created in `seo_meta` table
- Audit log entry created: `action = product.create`

---

### TC-02: Create Product — Duplicate SKU

**Input:** Same `sku` as an existing product for the same tenant.

**Expected Result:** HTTP 409 (currently HTTP 500 — gap identified)

---

### TC-03: Create Product — Missing Required Field

**Input:** Body with no `product_type_id`.

**Expected Result:**
- HTTP 422
- Message: `"Field 'product_type_id' is required."`

---

### TC-04: Create Product — Subscription Limit Reached

**Preconditions:** Tenant has plan with `max_products = 10`, tenant already has 10 products.

**Expected Result:**
- HTTP 403
- Message contains `"Product limit reached (10/10)"`

---

### TC-05: Update Product — Happy Path

**Input:**
```json
{ "id": 42, "is_active": 0, "stock_status": "out_of_stock" }
```

**Expected Result:**
- HTTP 200
- Product row updated in DB
- `updated_at` refreshed to current timestamp
- Audit log entry: `action = product.update`

---

### TC-06: Update Product — Missing ID

**Input:** `{ "is_active": 0 }` (no `id`)

**Expected Result:**
- HTTP 400
- Message: `"ID is required"`

---

### TC-07: Delete Product — Happy Path

**Input:** `{ "id": 42 }`

**Expected Result:**
- HTTP 200
- Row deleted from `products`
- SEO meta entry deleted
- Audit log entry: `action = product.delete`

---

### TC-08: Delete Product — Missing ID

**Input:** `{}`

**Expected Result:**
- HTTP 400
- Message: `"Missing product ID for deletion"`

---

### TC-09: Bad Words Rejection — Product Name

**Preconditions:** Bad words check is integrated into `ProductsService`.

**Input:** `{ "en_name": "buy cheap v1agra now" }`

**Expected Result:**
- HTTP 422
- Message identifies the prohibited word and field

---

### TC-10: List Products — Pagination

**Input:** `GET /api/products?tenant_id=7&page=2&limit=10`

**Expected Result:**
- HTTP 200
- `meta.page = 2`, `meta.per_page = 10`
- `items` array contains at most 10 records
- `meta.total` matches `COUNT(*)` for tenant 7

---

### TC-11: List Products — Language Filter

**Input:** `GET /api/products?lang=ar`

**Expected Result:**
- Products returned with Arabic `name` field populated from `product_translations`
- Products with no Arabic translation return `name = ""`

---

### TC-12: Permission Check — Unauthorized User

**Preconditions:** User has no `products.*` permissions.

**Expected Result:**
- Fragment PHP: HTTP 403 response
- API: HTTP 401 (if tenant not in session) or RBAC-specific 403

---

## 12. Assumptions

> All items below were not explicitly stated in source code or documentation and represent the author's professional inference.

1. **Assumption A:** The `bad_words` check is **not currently called** during product create/update. The `BadWordsService` exists and works correctly standalone, but is not wired into the products flow.

2. **Assumption B:** `AuditLogsService::log()` is **not currently called** from `api/v1/routes/products.php`. The route file does not contain any explicit calls to the audit log service. Logging must be added.

3. **Assumption C:** The `tenant_id` in the route is resolved from `$_GET['tenant_id']` or `$_SESSION['tenant_id']`. There is no middleware-based RBAC enforcement at the API level beyond tenant isolation.

4. **Assumption D:** Duplicate SKU/slug errors from the database (PDO SQLSTATE 23000) are currently not caught and return HTTP 500. They should be caught and mapped to HTTP 409.

5. **Assumption E:** The `created_by_user_id` column exists on `products` table but is never set by `PdoProductsRepository::save()`. It remains NULL for all records created via this API.

6. **Assumption F:** Rich text fields (`description`, `specifications`) are stored as raw HTML/Markdown from the textarea. No server-side HTML sanitization (e.g., `HTMLPurifier`) is applied.

7. **Assumption G:** The `images` table and `image_types` table are managed by a separate Images module. Products reference images via `images.owner_id = products.id`.

8. **Assumption H:** Translation records (`product_translations`) are saved separately via the `product_translations` route, not in the same POST body as the product core data, except for the English defaults (`en_name`, `en_description`, etc.) which are handled in JS.

9. **Assumption I:** `recently_viewed_products` table (present in `doc/products.md`) is managed by a separate frontend/public-facing API and is not modified by the admin products module.

---

## 13. Final Status

### ✅ READY — With Recommended Improvements

The Products module is **functionally complete** for core CRUD operations with multi-tenant isolation, pagination, filtering, SEO auto-management, and a solid validator. The following **must be addressed before enterprise-scale production deployment**:

| Priority | Issue | Fix Required |
|---|---|---|
| 🔴 HIGH | Bad-words check not called in products flow | Wire `BadWordsService` into `ProductsService` |
| 🔴 HIGH | Audit log calls missing from products route | Add `AuditLogsService::log()` on create/update/delete |
| 🔴 HIGH | Duplicate SKU returns HTTP 500 instead of 409 | Catch `PDOException` SQLSTATE 23000 → 409 |
| 🟡 MEDIUM | HTML not sanitized in rich-text fields | Apply `HTMLPurifier` before saving |
| 🟡 MEDIUM | Correlated subquery for image_type_id | Cache value, use direct JOIN |
| 🟡 MEDIUM | `created_by_user_id` never populated | Set from session in repository |
| 🟡 MEDIUM | No optimistic locking on concurrent updates | Add `updated_at` check on UPDATE |
| 🟡 MEDIUM | `products.php` still uses `time()` for CSS/JS cache busting | Replace with `filemtime()` (same fix as header.php) |
| 🟢 LOW | Missing translation fallback to default language | Fall back to `en` if requested lang not found |
| 🟢 LOW | Max pagination limit of 1000 too high | Reduce to 100 |
