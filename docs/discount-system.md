# Discount System

## Overview

The discount system provides a flexible, multi-tenant promotion engine for managing discounts, coupons, and special offers. It supports multiple discount types, conditional rules, scope-based targeting, multi-language translations, and usage tracking. Discounts can be applied automatically or via coupon codes, with configurable stacking, redemption limits, and date-based scheduling.

## Database Schema

The system uses 7 related tables:

### `discounts`

Core discount records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `entity_id` | string | Tenant/entity identifier for multi-tenant isolation |
| `type` | string | Discount type: `percentage`, `fixed`, `free_shipping`, `buy_x_get_y`, `free_item` |
| `code` | string (nullable) | Coupon code (null for auto-apply discounts) |
| `auto_apply` | tinyint | Whether the discount applies automatically without a code (`0`/`1`) |
| `priority` | int | Evaluation priority (higher = applied first) |
| `is_stackable` | tinyint | Whether the discount can combine with others (`0`/`1`) |
| `currency_code` | string | Currency for fixed-amount discounts (e.g., `SAR`, `USD`) |
| `max_redemptions` | int (nullable) | Total redemption limit across all users |
| `max_redemptions_per_user` | int (nullable) | Per-user redemption limit |
| `current_redemptions` | int | Running count of total redemptions |
| `starts_at` | datetime (nullable) | Scheduled start date |
| `ends_at` | datetime (nullable) | Expiration date |
| `status` | string | Database status: `active` or `inactive` |
| `created_by` | string (nullable) | User/admin who created the discount |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

### `discount_translations`

Multi-language content for discounts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `language_code` | string | Language identifier (e.g., `en`, `ar`) |
| `name` | string | Localized discount name |
| `description` | text (nullable) | Localized description |
| `terms_conditions` | text (nullable) | Localized terms and conditions |
| `marketing_badge` | string (nullable) | Badge text for storefront display (e.g., "20% OFF") |

### `discount_scopes`

Define which products/categories/brands the discount applies to.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `scope_type` | string | Target type (see [Scope Types](#scope-types)) |
| `scope_id` | string (nullable) | ID of the target entity (null when `scope_type` is `all`) |

### `discount_conditions`

Eligibility rules that must be met for the discount to apply.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `condition_type` | string | Type of condition (see [Condition Types](#condition-types)) |
| `operator` | string | Comparison operator: `=`, `>`, `<`, `>=`, `<=`, `<>`, `in`, `not_in`, `between`, `contains` |
| `condition_value` | string | Value to compare against |

### `discount_actions`

Define what the discount does when applied.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `action_type` | string | Type of action (see [Action Types](#action-types)) |
| `action_value` | string | Action parameter (e.g., percentage amount, item ID) |

### `discount_redemptions`

Track individual discount usage.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `user_id` | string | User who redeemed the discount |
| `order_id` | string | Order where the discount was applied |
| `amount_discounted` | decimal | Amount saved |
| `currency_code` | string | Currency of the discounted amount |
| `redeemed_at` | datetime | When the redemption occurred |

### `discount_exclusions`

Define mutually exclusive discounts that cannot be combined.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int (PK) | Auto-increment primary key |
| `discount_id` | int (FK) | References `discounts.id` |
| `excluded_discount_id` | int (FK) | References the discount that cannot be used alongside |

## API Endpoints

All endpoints accept JSON request bodies and return JSON responses. CORS headers are included for cross-origin access.

### Discounts (`api/routes/discounts.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?stats` | Get discount statistics (total, active, inactive, expired, scheduled) |
| `GET` | `?id={id}` | Get a single discount by ID |
| `GET` | `?entity_id=&status=&type=&search=&limit=&offset=` | List discounts with filters and pagination |
| `POST` | Body: `entity_id`, `type`, `currency_code` (required) + optional fields | Create a new discount |
| `PUT` | Body: `id` (required) + fields to update | Update an existing discount |
| `DELETE` | `?id={id}` | Delete a discount |

### Discount Translations (`api/routes/discount_translations.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?discount_id={id}` | List translations for a discount |
| `POST` | Body: `discount_id`, `language_code`, `name` + optional fields | Create or update (upsert) a translation |
| `DELETE` | `?id={id}` | Delete a translation |

### Discount Scopes (`api/routes/discount_scopes.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?discount_id={id}` | List scopes for a discount |
| `POST` | Body: `discount_id`, `scope_type`, `scope_id` | Add a scope rule |
| `DELETE` | `?id={id}` | Delete a scope rule |

### Discount Conditions (`api/routes/discount_conditions.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?discount_id={id}` | List conditions for a discount |
| `POST` | Body: `discount_id`, `condition_type`, `operator`, `condition_value` | Create a condition |
| `PUT` | Body: `id` + fields to update | Update a condition |
| `DELETE` | `?id={id}` | Delete a condition |

### Discount Actions (`api/routes/discount_actions.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?discount_id={id}` | List actions for a discount |
| `POST` | Body: `discount_id`, `action_type`, `action_value` | Create an action |
| `PUT` | Body: `id` + fields to update | Update an action |
| `DELETE` | `?id={id}` | Delete an action |

### Discount Redemptions (`api/routes/discount_redemptions.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?stats&discount_id={id}` | Get redemption statistics for a discount |
| `GET` | `?discount_id={id}&limit=&offset=` | List redemptions with pagination |
| `POST` | Body: `discount_id`, `user_id`, `order_id`, `amount_discounted`, `currency_code` | Record a redemption |

### Discount Exclusions (`api/routes/discount_exclusions.php`)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `GET` | `?discount_id={id}` | List exclusions for a discount |
| `POST` | Body: `discount_id`, `excluded_discount_id` | Add an exclusion rule |
| `DELETE` | `?id={id}` | Delete an exclusion rule |

## MVC Architecture

The discount system follows a layered MVC architecture:

```
Routes (api/routes/)
  └── Controller (api/v1/models/discounts/DiscountsController.php)
        └── Service (api/v1/models/discounts/DiscountsService.php)
              └── Repositories (api/v1/models/discounts/repositories/)
                    ├── PdoDiscountsRepository.php
                    ├── PdoDiscountTranslationsRepository.php
                    ├── PdoDiscountScopesRepository.php
                    ├── PdoDiscountConditionsRepository.php
                    ├── PdoDiscountActionsRepository.php
                    ├── PdoDiscountRedemptionsRepository.php
                    └── PdoDiscountExclusionsRepository.php
```

- **Routes** handle HTTP request/response, CORS, session management, and input validation.
- **Controller** is a thin pass-through layer that delegates to the service.
- **Service** orchestrates all 7 repositories from a single PDO connection, providing a unified interface.
- **Repositories** encapsulate raw SQL queries and enforce allowed column/enum validation.

## Scope Types

Scopes define what a discount applies to:

| Scope Type | Description |
|-----------|-------------|
| `product` | Applies to a specific product (identified by `scope_id`) |
| `category` | Applies to all products in a category |
| `brand` | Applies to all products of a brand |
| `collection` | Applies to products in a curated collection |
| `supplier` | Applies to all products from a supplier |
| `customer_group` | Applies only to customers in a specific group |
| `all` | Applies globally to all eligible items |

## Condition Types

Conditions define eligibility requirements:

| Condition Type | Description | Example |
|---------------|-------------|---------|
| `min_cart_total` | Minimum cart value required | `>= 100` |
| `min_items_count` | Minimum number of items in cart | `>= 3` |
| `first_order_only` | Only for customers placing their first order | `= true` |
| `weekend_only` | Active only on weekends | `= true` |
| `specific_payment_method` | Requires a specific payment method | `in credit_card,apple_pay` |
| `customer_segment` | Targets a specific customer segment | `= vip` |
| `geo_location` | Restricts by geographic location | `in SA,AE` |
| `time_window` | Active only during specific hours | `between 09:00,17:00` |
| `custom_rule` | Custom business logic rule | Application-specific |

### Operators

Conditions use comparison operators:

| Operator | Description |
|----------|-------------|
| `=` | Equal to |
| `>` | Greater than |
| `<` | Less than |
| `>=` | Greater than or equal to |
| `<=` | Less than or equal to |
| `<>` | Not equal to |
| `in` | Value is in a set |
| `not_in` | Value is not in a set |
| `between` | Value is within a range |
| `contains` | Value contains a substring |

## Action Types

Actions define what happens when a discount is applied:

| Action Type | Description | `action_value` Example |
|------------|-------------|----------------------|
| `percentage` | Percentage off the price | `15` (15% off) |
| `fixed` | Fixed amount off | `50` (50 currency units off) |
| `free_shipping` | Waive shipping fees | `true` |
| `buy_x_get_y` | Buy X items, get Y free | `{"buy": 2, "get": 1}` |
| `free_item` | Add a free item to the order | Product ID or SKU |

## Status Lifecycle

The discount system uses a computed status model:

```
              ┌─────────────────────────────────────────┐
              │           Database Status                │
              │     (stored in `status` column)          │
              │                                         │
              │   ┌──────────┐     ┌───────────┐       │
              │   │  active   │     │  inactive  │       │
              │   └──────────┘     └───────────┘       │
              └─────────────────────────────────────────┘
                       │
                       ▼ (computed at query time)
              ┌─────────────────────────────────────────┐
              │          Computed States                 │
              │                                         │
              │   ┌───────────┐   ┌──────────┐         │
              │   │ scheduled  │   │ expired   │         │
              │   │starts_at   │   │ends_at    │         │
              │   │> NOW()     │   │< NOW()    │         │
              │   └───────────┘   └──────────┘         │
              └─────────────────────────────────────────┘
```

- **`active`** (DB) + `ends_at IS NULL OR ends_at >= NOW()` → Discount is currently active and usable.
- **`inactive`** (DB) → Discount is manually disabled by an admin.
- **`expired`** (computed) → `ends_at IS NOT NULL AND ends_at < NOW()` — the end date has passed. This is not a stored status value; it is computed from the `ends_at` column.
- **`scheduled`** (computed) → `starts_at IS NOT NULL AND starts_at > NOW() AND status = 'active'` — the discount is active but its start date hasn't arrived yet.

> **Important:** The `expired` state is never stored in the database. It is derived at query time by checking `ends_at < NOW()`. The stats queries use `SUM(CASE WHEN ...)` expressions to compute these counts accurately.

## Admin UI

The admin interface (`admin/fragments/discounts.php`) provides:

- **Stats Dashboard** — cards showing total, active, expired, and total redemptions at a glance.
- **Entity/Tenant Selector** — cascade dropdown for multi-tenant environments.
- **Search & Filters** — filter discounts by status, type, and free-text search on code/name.
- **Discount CRUD** — create, edit, and delete discounts via modal forms.
- **Translations Management** — add/edit localized names, descriptions, terms, and marketing badges per language.
- **Scopes Configuration** — assign discounts to specific products, categories, brands, collections, suppliers, customer groups, or globally.
- **Conditions Builder** — define eligibility rules with configurable condition types and operators.
- **Actions Configuration** — set discount effects (percentage, fixed, free shipping, etc.).
- **Exclusions Management** — prevent specific discounts from being combined.
- **Redemptions View** — track usage history with user, order, amount, and date details.

Frontend assets:
- **JavaScript:** `admin/assets/js/pages/discounts.js`
- **CSS:** `admin/assets/css/pages/discounts.css` (theme-aware with CSS variables)

## Usage Examples

### Create a 15% Off Coupon

```bash
# 1. Create the discount
curl -X POST /api/routes/discounts.php \
  -H "Content-Type: application/json" \
  -d '{
    "entity_id": "store_1",
    "type": "percentage",
    "code": "SAVE15",
    "currency_code": "SAR",
    "max_redemptions": 100,
    "max_redemptions_per_user": 1,
    "starts_at": "2025-01-01 00:00:00",
    "ends_at": "2025-12-31 23:59:59",
    "status": "active"
  }'
# Response: {"data": {"id": 1}, "message": "Discount created"}

# 2. Add a translation
curl -X POST /api/routes/discount_translations.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 1,
    "language_code": "en",
    "name": "Save 15%",
    "description": "Get 15% off your order",
    "marketing_badge": "15% OFF"
  }'

# 3. Add the action
curl -X POST /api/routes/discount_actions.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 1,
    "action_type": "percentage",
    "action_value": "15"
  }'

# 4. Add a scope (apply to all products)
curl -X POST /api/routes/discount_scopes.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 1,
    "scope_type": "all",
    "scope_id": null
  }'

# 5. Add a condition (minimum cart total)
curl -X POST /api/routes/discount_conditions.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 1,
    "condition_type": "min_cart_total",
    "operator": ">=",
    "condition_value": "200"
  }'
```

### Create a Category-Specific Free Shipping Offer

```bash
# 1. Create an auto-apply discount
curl -X POST /api/routes/discounts.php \
  -H "Content-Type: application/json" \
  -d '{
    "entity_id": "store_1",
    "type": "free_shipping",
    "auto_apply": 1,
    "currency_code": "SAR",
    "status": "active"
  }'

# 2. Scope it to a specific category
curl -X POST /api/routes/discount_scopes.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 2,
    "scope_type": "category",
    "scope_id": "electronics"
  }'

# 3. Add the free shipping action
curl -X POST /api/routes/discount_actions.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 2,
    "action_type": "free_shipping",
    "action_value": "true"
  }'
```

### Get Discount Statistics

```bash
curl -X GET "/api/routes/discounts.php?stats"
# Response: {"total": 25, "active": 12, "inactive": 3, "expired": 8, "scheduled": 2}
```

### Record a Redemption

```bash
curl -X POST /api/routes/discount_redemptions.php \
  -H "Content-Type: application/json" \
  -d '{
    "discount_id": 1,
    "user_id": "user_123",
    "order_id": "order_456",
    "amount_discounted": 30.00,
    "currency_code": "SAR"
  }'
```
