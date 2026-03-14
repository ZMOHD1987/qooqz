# Delivery System — Complete Technical Documentation

> **Permanent reference** — kept up to date with every code review and change.
> Last updated: 2026-03-03

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [API Architecture](#3-api-architecture)
4. [API Endpoints](#4-api-endpoints)
5. [Validation Rules](#5-validation-rules)
6. [Frontend — Admin Delivery Fragment](#6-frontend--admin-delivery-fragment)
7. [DB Migration — zone_value Column](#7-db-migration--zone_value-column)
8. [Error Reference](#8-error-reference)

---

## 1. System Overview

The delivery sub-system is a **multi-tenant**, **role-protected** module that manages:

| Domain | Description |
|---|---|
| **Delivery Zones** | Geographic boundaries (city, district, radius, polygon/GeoJSON) with fees |
| **Delivery Providers** | Drivers/couriers linked to a tenant user |
| **Delivery Orders** | Individual delivery jobs tied to a customer order |
| **Driver Locations** | Real-time GPS pings from providers |
| **Delivery Tracking** | Full event log (coordinates + status notes) per delivery order |
| **Provider Zones** | Many-to-many link between providers and zones |

All operations are **scoped to `tenant_id`** derived from the authenticated session.

---

## 2. Database Schema

### 2.1 `delivery_zones`

Stores geographic delivery zones.  
**⚠️ Important:** The `zone_value` column must be added via the migration at `/admin/fix_delivery_zones_zone_value.php` if not yet present.

| Field | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI | — | auto_increment |
| `tenant_id` | int(10) unsigned | NO | MUL | — | Tenant scope |
| `provider_id` | bigint(20) unsigned | NO | MUL | — | FK → delivery_providers.id |
| `zone_name` | varchar(255) | NO | | — | Display name |
| `zone_type` | enum | NO | MUL | — | `city`, `district`, `radius`, `polygon` |
| `city_id` | int(11) | YES | MUL | NULL | FK → cities.id (required for zone_type=city) |
| `center_lat` | decimal(10,7) | YES | | NULL | Required for zone_type=radius |
| `center_lng` | decimal(11,7) | YES | | NULL | Required for zone_type=radius |
| `radius_km` | decimal(6,2) | YES | | NULL | Required for zone_type=radius |
| `zone_value` | TEXT | YES | | NULL | GeoJSON geometry string (required for zone_type=polygon) |
| `delivery_fee` | decimal(15,2) | NO | | — | Base delivery fee |
| `free_delivery_over` | decimal(15,2) | YES | | NULL | Order subtotal threshold for free delivery |
| `min_order_value` | decimal(15,2) | YES | | NULL | Minimum order value to use this zone |
| `estimated_minutes` | int(10) unsigned | NO | | 45 | Estimated delivery time |
| `is_active` | tinyint(1) | YES | MUL | 1 | 1 = active, 0 = inactive |
| `created_at` | datetime | YES | | current_timestamp() | |

**Zone type constraints:**
- `city` → `city_id` required
- `radius` → `center_lat`, `center_lng`, `radius_km` required
- `polygon` → `zone_value` (valid GeoJSON) required on create; optional on update (preserves existing)

---

### 2.2 `delivery_providers`

Stores delivery drivers/couriers.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | PK, auto_increment |
| `tenant_id` | int(10) unsigned | Tenant scope |
| `tenant_user_id` | bigint(20) unsigned | FK → tenant_users.id |
| `entity_id` | bigint(20) unsigned | FK → entities.id (nullable) |
| `provider_type` | enum | `individual`, `company` |
| `vehicle_type` | enum | `motorcycle`, `car`, `van`, `truck`, `bicycle`, `on_foot` |
| `license_number` | varchar(100) | Driver's license/ID |
| `is_online` | tinyint(1) | Real-time online status |
| `is_active` | tinyint(1) | Account active flag |
| `rating` | decimal(3,2) | Average rating (0.00–5.00) |
| `total_deliveries` | int unsigned | Completed deliveries counter |
| `created_at` | datetime | auto |

---

### 2.3 `delivery_orders`

Links a customer order to a delivery provider.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | PK |
| `tenant_id` | int(10) unsigned | Tenant scope |
| `order_id` | bigint(20) unsigned | FK → orders.id |
| `provider_id` | bigint(20) unsigned | FK → delivery_providers.id (nullable until assigned) |
| `zone_id` | bigint(20) unsigned | FK → delivery_zones.id |
| `pickup_address_id` | bigint(20) unsigned | FK → addresses.id |
| `dropoff_address_id` | bigint(20) unsigned | FK → addresses.id |
| `delivery_status` | enum | `pending`, `assigned`, `accepted`, `picked_up`, `on_the_way`, `delivered`, `cancelled` |
| `delivery_fee` | decimal(15,2) | Actual fee charged |
| `calculated_fee` | decimal(15,2) | System-calculated fee |
| `provider_payout` | decimal(15,2) | Amount paid to provider |
| `cancelled_by` | enum | `customer`, `provider`, `admin`, `system` |
| `cancellation_reason` | text | Free-text reason |
| `picked_up_at` | datetime | |
| `delivered_at` | datetime | |
| `created_at` | datetime | auto |

---

### 2.4 `driver_locations`

Real-time GPS positions (latest ping per provider).

| Field | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | PK |
| `provider_id` | bigint(20) unsigned | FK → delivery_providers.id |
| `tenant_id` | int(10) unsigned | Tenant scope |
| `latitude` | decimal(10,7) | -90 to 90 |
| `longitude` | decimal(11,7) | -180 to 180 |
| `heading` | decimal(5,2) | Compass heading in degrees |
| `speed_kmh` | decimal(6,2) | Speed |
| `accuracy_m` | decimal(8,2) | GPS accuracy in metres |
| `updated_at` | datetime | auto on update |

---

### 2.5 `delivery_tracking`

Full event log of coordinates + notes for each delivery.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | PK |
| `delivery_order_id` | bigint(20) unsigned | FK → delivery_orders.id |
| `provider_id` | bigint(20) unsigned | FK → delivery_providers.id |
| `latitude` | decimal(10,7) | -90 to 90 |
| `longitude` | decimal(11,7) | -180 to 180 |
| `status_note` | varchar(255) | Optional event description |
| `created_at` | datetime | auto |

---

### 2.6 `provider_zones`

Many-to-many relationship: which zones a provider covers.

| Field | Type | Notes |
|---|---|---|
| `id` | bigint(20) unsigned | PK |
| `provider_id` | bigint(20) unsigned | FK → delivery_providers.id |
| `zone_id` | bigint(20) unsigned | FK → delivery_zones.id |
| `tenant_id` | int(10) unsigned | Tenant scope |

---

### 2.7 Related Tables

**`cities`**  
`id`, `country_id`, `name`, `state`, `latitude`, `longitude`, `location` (POINT)

**`countries`**  
`id`, `iso2`, `iso3`, `name`, `currency_code`

**`currencies`**  
`id`, `code`, `name`, `symbol`, `symbol_position`, `decimal_places`

---

## 3. API Architecture

```
api/v1/models/delivery_zones/
├── Contracts/
│   ├── DeliveryZoneRepositoryInterface.php
│   ├── DeliveryProviderRepositoryInterface.php
│   ├── DeliveryOrderRepositoryInterface.php
│   └── ProviderZoneRepositoryInterface.php
├── controllers/
│   ├── DeliveryZoneController.php
│   ├── DeliveryProviderController.php
│   ├── DeliveryOrderController.php
│   ├── DeliveryTrackingController.php
│   ├── DriverLocationController.php
│   └── ProviderZoneController.php
├── repositories/
│   ├── PdoDeliveryZoneRepository.php
│   ├── PdoDeliveryProviderRepository.php
│   ├── PdoDeliveryTrackingRepository.php
│   ├── PdoDriverLocationRepository.php
│   └── PdoProviderZoneRepository.php
├── services/
│   ├── DeliveryZoneService.php
│   ├── DeliveryProviderService.php
│   └── DeliveryOrderService.php
└── validators/
    ├── DeliveryZoneValidator.php
    ├── DeliveryOrderValidator.php
    └── DeliveryTrackingValidator.php
```

**Pattern:** Controller → Service → Repository → PDO  
**Tenant isolation:** Every query includes `WHERE … tenant_id = :tenant_id`.  
**Authentication:** All endpoints require a valid admin session (`admin_context.php`).

---

## 4. API Endpoints

All endpoints share the base path `/api/v1/`.  
All responses follow:

```json
{
  "success": true,
  "data": { … },
  "meta": { "time": "ISO-8601", "request_id": "…" }
}
```

### 4.1 Delivery Zones

| Method | Path | Description |
|---|---|---|
| GET | `/delivery-zones` | List zones (pagination, filters) |
| GET | `/delivery-zones/{id}` | Get single zone |
| POST | `/delivery-zones` | Create zone |
| PUT | `/delivery-zones/{id}` | Update zone |
| DELETE | `/delivery-zones/{id}` | Delete zone |

**Query parameters (list):**

| Parameter | Type | Description |
|---|---|---|
| `limit` | int | Max rows (default 25) |
| `offset` | int | Rows to skip |
| `provider_id` | int | Filter by provider |
| `zone_type` | string | Filter by type |
| `city_id` | int | Filter by city |
| `is_active` | 0/1 | Filter by active status |
| `order_by` | string | Column: `dz.id`, `dz.zone_name`, `dz.delivery_fee`, `dz.created_at` |
| `order_dir` | ASC/DESC | Sort direction |

**POST/PUT body fields:**

| Field | Required (POST) | Required (PUT) | Notes |
|---|---|---|---|
| `provider_id` | ✅ | — | |
| `zone_name` | ✅ | — | |
| `zone_type` | ✅ | — | city / district / radius / polygon |
| `delivery_fee` | ✅ | — | Numeric |
| `city_id` | if zone_type=city | — | |
| `center_lat` | if zone_type=radius | — | |
| `center_lng` | if zone_type=radius | — | |
| `radius_km` | if zone_type=radius | — | |
| `zone_value` | if zone_type=polygon | if changing to polygon | Valid JSON string or object |
| `free_delivery_over` | — | — | Numeric, optional |
| `min_order_value` | — | — | Numeric, optional |
| `estimated_minutes` | — | — | Integer, default 45 |
| `is_active` | — | — | 0 or 1 |

---

### 4.2 Delivery Providers

| Method | Path | Description |
|---|---|---|
| GET | `/delivery-providers` | List providers |
| GET | `/delivery-providers/{id}` | Get single provider |
| POST | `/delivery-providers` | Create provider |
| PUT | `/delivery-providers/{id}` | Update provider |
| DELETE | `/delivery-providers/{id}` | Delete provider |

**Filters:** `provider_type`, `vehicle_type`, `is_online`, `is_active`, `entity_id`

**POST/PUT body fields:**

| Field | Type | Notes |
|---|---|---|
| `tenant_user_id` | int | Required on create |
| `entity_id` | int | Optional |
| `provider_type` | string | `individual`, `company` |
| `vehicle_type` | string | `motorcycle`, `car`, `van`, `truck`, `bicycle`, `on_foot` |
| `license_number` | string | |
| `is_online` | 0/1 | |
| `is_active` | 0/1 | |
| `rating` | decimal | 0.00–5.00 |
| `total_deliveries` | int | |

---

### 4.3 Delivery Orders

| Method | Path | Description |
|---|---|---|
| GET | `/delivery-orders` | List delivery orders |
| GET | `/delivery-orders/{id}` | Get single delivery order |
| POST | `/delivery-orders` | Create delivery order |
| PUT | `/delivery-orders/{id}` | Update delivery order |
| DELETE | `/delivery-orders/{id}` | Delete delivery order |

**Filters:** `order_id`, `provider_id`, `delivery_status`

**Delivery status lifecycle:**
```
pending → assigned → accepted → picked_up → on_the_way → delivered
                                                         ↘ cancelled
```

**POST required fields:** `order_id`, `pickup_address_id`, `dropoff_address_id`

---

### 4.4 Driver Locations

| Method | Path | Description |
|---|---|---|
| GET | `/driver-locations` | List latest driver GPS positions |
| POST | `/driver-locations` | Upsert driver location (GPS ping) |

**POST fields:** `provider_id`, `latitude`, `longitude`, `heading`?, `speed_kmh`?, `accuracy_m`?

---

### 4.5 Delivery Tracking

| Method | Path | Description |
|---|---|---|
| GET | `/delivery-tracking` | List tracking events |
| POST | `/delivery-tracking` | Add tracking event |

**POST required fields:** `delivery_order_id`, `latitude`, `longitude`  
**Optional:** `provider_id`, `status_note`

**Coordinate validation:**
- `latitude`: numeric, -90 to 90
- `longitude`: numeric, -180 to 180

---

### 4.6 Provider Zones

| Method | Path | Description |
|---|---|---|
| GET | `/provider-zones` | List provider↔zone assignments |
| POST | `/provider-zones` | Assign provider to zone |
| DELETE | `/provider-zones/{id}` | Remove assignment |

---

## 5. Validation Rules

### 5.1 DeliveryZoneValidator

| Field | Create | Update | Rule |
|---|---|---|---|
| `provider_id` | required | optional | |
| `zone_name` | required | optional | |
| `zone_type` | required | optional | must be: `city`, `district`, `radius`, `polygon` |
| `delivery_fee` | required | optional | must be numeric |
| `zone_value` | required if polygon | optional* | must be valid JSON if provided |
| `city_id` | required if city | — | |
| `center_lat/lng/radius_km` | required if radius | — | |
| `is_active` | optional | optional | must be 0 or 1 |

*On **update** with `zone_type=polygon`: if `zone_value` is present in the request it must be non-empty and valid JSON. Omitting it entirely preserves the existing DB value.

### 5.2 DeliveryOrderValidator

| Field | Create | Update | Rule |
|---|---|---|---|
| `order_id` | required | — | numeric |
| `pickup_address_id` | required | — | |
| `dropoff_address_id` | required | — | |
| `delivery_status` | optional | optional | see lifecycle above |
| `cancelled_by` | optional | optional | `customer`, `provider`, `admin`, `system` |
| `delivery_fee` / `calculated_fee` / `provider_payout` | optional | optional | numeric |

### 5.3 DeliveryTrackingValidator

| Field | Create | Rule |
|---|---|---|
| `delivery_order_id` | required | |
| `latitude` | required | numeric, -90 to 90 |
| `longitude` | required | numeric, -180 to 180 |

---

## 6. Frontend — Admin Delivery Fragment

### 6.1 Files

| File | Purpose |
|---|---|
| `admin/fragments/delivery.php` | Main workspace PHP (tabs: Zones, Providers, Orders, Tracking) |
| `admin/assets/js/pages/delivery.js` | All client-side logic (CRUD modals, Leaflet map, real-time tracking) |
| `admin/assets/css/pages/delivery.css` | Delivery-specific styles (loaded dynamically) |
| `languages/Delivery/ar.json` | Arabic translations |
| `languages/Delivery/en.json` | English translations |

### 6.2 Loading

The fragment is loaded via:
```
/admin/fragments/delivery.php
```
or as an embedded AJAX fragment via:
```
/admin/fragments/delivery.php?embedded=1
```

### 6.3 Permissions

| Permission key | Controls |
|---|---|
| `delivery.view` / `view_delivery` | Read access |
| `delivery.manage` | Full CRUD |
| `delivery.create` | Create zones/providers |
| `delivery.edit` | Edit zones/providers |
| `delivery.delete` | Delete zones/providers |

Super-admin bypasses all permission checks.

### 6.4 Map Integration (Leaflet)

- **Polygon zones**: drawn interactively on a Leaflet map; coordinates serialized as GeoJSON stored in `zone_value`.
- **Radius zones**: circle drawn on map; `center_lat`, `center_lng`, `radius_km` stored separately.
- On zone list load, existing polygon `zone_value` is parsed and rendered as a layer on the map.

---

## 7. DB Migration — zone_value Column

The `zone_value TEXT NULL` column is **not** part of the original `delivery_zones` DDL.  
It must be added via the included migration script.

### How to apply

1. Log in to the admin panel as a super-admin.
2. Visit: `/admin/fix_delivery_zones_zone_value.php`
3. The script checks `information_schema` first (idempotent — safe to run multiple times).
4. On success you will see: ✔ Column `zone_value` added successfully.

### SQL equivalent

```sql
ALTER TABLE delivery_zones
  ADD COLUMN zone_value TEXT NULL DEFAULT NULL;
```

---

## 8. Error Reference

| HTTP | `message` | Cause |
|---|---|---|
| 400 | `provider_id is required.` | Missing field on create |
| 400 | `Invalid zone_type.` | `zone_type` not in allowed enum |
| 400 | `delivery_fee must be numeric.` | Non-numeric fee value |
| 400 | `Polygon zones require zone_value (GeoJSON geometry).` | Polygon created/updated without geometry |
| 400 | `zone_value must be valid JSON.` | Malformed GeoJSON |
| 400 | `Radius zones require center_lat, center_lng, and radius_km.` | Radius zone missing coords |
| 400 | `City zones require city_id.` | City zone missing city |
| 400 | `is_active must be 0 or 1.` | Invalid boolean value |
| 422 | `A database error occurred.` | Usually a missing DB column — apply migration (§ 7) |
| 401 | `Unauthorized` | Session expired or not logged in |
| 403 | `Access denied` | Insufficient permissions |
| 404 | `Zone not found` | ID does not exist in tenant scope |
