# Delivery Management — Audit, Optimization & Documentation

> **File scope:** `admin/fragments/delivery.php`, `admin/assets/js/pages/delivery.js`, `admin/assets/css/pages/delivery.css`  
> **Last updated:** 2026-03-20

---

## Table of Contents

1. [Overview](#1-overview)
2. [Structure](#2-structure)
3. [Logic Flow](#3-logic-flow)
4. [UI Explanation](#4-ui-explanation)
5. [Performance Optimizations](#5-performance-optimizations)
6. [Cleanup Summary](#6-cleanup-summary)
7. [Responsiveness](#7-responsiveness)
8. [Security Notes](#8-security-notes)
9. [Audit Report](#9-audit-report)
10. [Future Improvements](#10-future-improvements)

---

## 1. Overview

`admin/fragments/delivery.php` is the **Delivery Management Workspace** — a multi-tab admin panel rendered as either a standalone page or an AJAX-injected fragment. It provides full CRUD management for six delivery sub-domains:

| Domain | Description |
|---|---|
| **Zones** | Geographic delivery boundaries (city, district, radius, polygon/GeoJSON) with fees and time estimates |
| **Providers** | Couriers/drivers linked to tenant users and entities |
| **Orders** | Individual delivery assignments tied to a customer order |
| **Locations** | Real-time GPS pings from providers |
| **Tracking** | Full event log (coordinates + status notes) per delivery order |
| **Provider Zones** | Many-to-many assignment of providers to zones |

All data is **tenant-scoped** and **permission-gated** using the admin RBAC system.

---

## 2. Structure

### 2.1 PHP Fragment (`delivery.php`)

```
delivery.php
├── Bootstrap block
│   ├── Detect context ($isAjax / $isEmbedded / $isFragment)
│   ├── Require context (admin_context.php or header.php)
│   ├── Auth + permission gate
│   └── __t() i18n helper (inline fallback if i18n_get() not available)
├── CSS link (fragment mode only)
├── Page header (title + subtitle)
├── Workspace tabs (6 tabs)
├── Tab panels (6 × .ws-panel)
│   ├── Zones:         form card + sidebar list + Leaflet map
│   ├── Providers:     form card + filter card + table card
│   ├── Orders:        form card + filter card + table card
│   ├── Locations:     form card + filter card + table card
│   ├── Tracking:      form card + filter card + table card
│   └── Provider Zones: form card + filter card + table card
├── Coordinate Picker Modal
├── Toast notification
└── Script block
    ├── window.APP_CONFIG  (tenant ID, CSRF token)
    ├── window.DELIVERY_CONFIG  (full runtime config + API URLs)
    ├── window.PAGE_PERMISSIONS
    ├── <script src="delivery.js">
    └── Reinit call (fragment mode only)
```

### 2.2 JavaScript (`delivery.js`)

Implemented as a self-executing IIFE using `window.Delivery` as the public API.

```
delivery.js
├── State object (per-tab page/items/filters/loaded/saving)
├── Leaflet loader (loadScript / ensureLeafletJs / ensureLeafletCss)
├── Helpers ($ / esc / notify / showTableError / api / badge / pagination)
├── API response normalizers (extractItems / extractMeta / extractItem)
├── ID lookup binders (bindIdLookup / bindProviderLookup / bindEntityLookup / bindTenantUserLookup)
├── Country→City cascade (loadCountries / loadCitiesForCountry)
├── Coordinate Picker Modal (initCoordPicker / openCoordPicker / placePickerMarker / etc.)
├── Module factory (createModule) — generic load/save/delete/form logic
├── Leaflet map logic (initZonesMap / renderZonesOnMap / drawGeoOnMap / etc.)
├── Per-tab module instances (zonesMod / providersMod / ordersMod / locationsMod / trackingMod / pzonesMod)
├── Dropdown loader (loadDrops) — parallelized
├── Tab switcher (initTabs)
├── Cascade + zone-type change binders
├── init() / reinit()
└── window.Delivery public API
```

### 2.3 CSS (`delivery.css`)

Scoped stylesheet using CSS custom properties (`--card-bg`, `--border-color`, `--primary-color`, etc.) for dark/light theme compatibility. Organized into sections:

- Page header · Workspace tabs · Zones workspace · Cards · Filters · Forms · Buttons · Badges · Tables · Loading/Empty states · Pagination · RTL support · Responsive breakpoints · Provider lookup · Coordinate input · Coordinate picker modal · Leaflet overrides · Toast · RTL adjustments

---

## 3. Logic Flow

### 3.1 Initial Load

```
PHP renders HTML (context: fragment or full page)
    ↓
APP_CONFIG / DELIVERY_CONFIG / PAGE_PERMISSIONS injected as JS globals
    ↓
delivery.js IIFE runs → init()
    ├── initTabs()          — wire tab-btn click handlers
    ├── bindZoneTypeChange() — show/hide radius fields
    ├── bindCascade()        — country→city cascade
    ├── initCoordPicker()    — map picker modal events
    ├── bindProviderLookup() × 5 — live ID→name lookups
    ├── bindEntityLookup()
    ├── bindTenantUserLookup()
    ├── bindEvents() × 6    — add/edit/close/submit/filter per module
    ├── bind orderStatus change (show/hide cancel fields)
    ├── ensureLeafletJs()
    │   └── ensureLeafletCss()
    │       └── initZonesMap()    — Leaflet map + Draw controls
    ├── loadDrops() [parallel]
    │   ├── zones fetch      → populate zone selects
    │   ├── countries fetch  → loadCitiesForCountry()
    │   ├── orders fetch     → populate order selects
    │   └── providers fetch  → populate provider filter selects
    └── zonesMod.load(1)    — load first page of zones + render sidebar + map
```

### 3.2 Tab Switch

```
Tab button click
    ↓
Hide all .ws-panel → show target panel
    ↓
If zones tab → invalidateSize (Leaflet needs resize after display:none→visible)
    ↓
If tab data not yet loaded → mod.load(1)
```

### 3.3 CRUD Flow (per module)

```
Add button click → showForm({})     — empty form
Edit button click → api GET /id     — pre-fill form with existing data
Form submit → save()
    ├── id present → PUT /api/{resource}/{id}
    └── no id     → POST /api/{resource}
Delete button → confirm() → api DELETE /api/{resource}/{id} → reload
```

### 3.4 AJAX Re-Navigation (reinit)

When the fragment is re-injected via AJAX navigation, `delivery.php` calls `window.Delivery.reinit()` (if available). `reinit()` destroys existing Leaflet map instances (whose DOM containers no longer exist), resets the `state.initialized` flag, and calls `init()` again on the new DOM.

---

## 4. UI Explanation

### 4.1 Layout

```
┌──────────────────────────────────────────────┐
│  Page Header (title + subtitle)               │
├──────────────────────────────────────────────┤
│  Workspace Tabs  [Zones][Providers][Orders]… │
├──────────────────────────────────────────────┤
│                                              │
│  Active Tab Panel                            │
│  ┌───────────────────────────────────────┐  │
│  │  (Optional) Form Card  (slide-in)     │  │
│  ├───────────────────────────────────────┤  │
│  │  Zones: Sidebar  │  Leaflet Map       │  │
│  │  Others: Filter Card + Table Card     │  │
│  └───────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

### 4.2 Zones Tab

The Zones tab has a unique split layout:

- **Left sidebar (300 px):** search/filter controls, scrollable zone list, pagination, Add button.
- **Right panel (flex-1):** full-height Leaflet map (OpenStreetMap tiles) with Leaflet.Draw for polygon/rectangle/circle drawing.

Clicking a zone in the sidebar flies the map to its boundary. Clicking a map layer opens the edit form.

### 4.3 Other Tabs

Standard CRUD pattern: collapsible form card at top → filter bar card → paginated data table card.

### 4.4 Coordinate Picker Modal

A fixed-position modal with a secondary Leaflet map, a Nominatim place search input, drag-to-reposition marker, and a GPS "Use my location" button. On confirm, the selected lat/lng is written back to the target form fields.

### 4.5 Provider ID Lookup

Numeric inputs for `provider_id`, `entity_id`, and `tenant_user_id` include a live debounced lookup (400 ms). A small badge next to each input shows `found` (green), `not-found` (red), or `loading` (yellow) state.

---

## 5. Performance Optimizations

The following improvements were applied during this audit:

### 5.1 CSS Cache Busting (HIGH impact)

| Before | After |
|---|---|
| `?v=<?= time() ?>` — new URL every page load, browser cannot cache | `?v=3` — static version, full HTTP caching |

**Impact:** The browser was re-downloading `delivery.css` on every single page load because `time()` generated a unique query string each time. Switching to a static version allows the browser to serve the file from its cache after the first load.

### 5.2 Parallel Dropdown Fetches (HIGH impact)

| Before | After |
|---|---|
| 4 sequential `await` calls in `loadDrops()` — each waits for the previous | `Promise.all([...])` runs zones, countries+cities, orders, and providers concurrently |

**Impact:** On a typical 50–150 ms per-request latency, sequential loading took ~400–600 ms total. Parallel loading brings this down to the duration of the single slowest request (~100–150 ms). The country→city internal sequence is preserved.

### 5.3 Reduced `invalidateSize` Timeouts (LOW impact)

| Before | After |
|---|---|
| 5 timeouts: `[100, 300, 700, 1500, 3000]` ms | 3 timeouts: `[200, 600, 1500]` ms |

**Impact:** Eliminates 2 unnecessary timer callbacks. The map correctly resizes within the first 200 ms in all observed cases.

### 5.4 JavaScript Escaping (Security + Correctness)

| Before | After |
|---|---|
| `addslashes($csrf)` / `addslashes($lang)` in JS string literals | `json_encode($csrf)` / `json_encode($lang)` producing self-quoted values |

`json_encode` is the PHP-idiomatic and safe way to embed arbitrary PHP values inside JavaScript. It handles all edge cases (quotes, Unicode, newlines) that `addslashes` does not.

### 5.5 Simplified Reinit Script (LOW impact)

| Before | After |
|---|---|
| IIFE with polling `setInterval` that only clears itself — no functional effect | Plain `if (window.Delivery?.reinit)` call |

**Impact:** Removes 1 IIFE wrapper + a polling interval (120 ticks × 100 ms = 12 s potential lifetime) that provided no functional value. The comment stated "delivery.js will self-init" but the interval was still running for up to 12 seconds waiting for a no-op.

---

## 6. Cleanup Summary

### Removed / Simplified JS

| Item | Action | Reason |
|---|---|---|
| Top-level `orderStatusEl` event binding (outside `init()`) | Moved inside `init()` | Was not re-applied after `reinit()`, causing the cancel-fields toggle to break on AJAX re-navigation |
| Polling `setInterval` in reinit inline script | Removed | Served no purpose; delivery.js auto-inits via its IIFE |
| 2 excess `invalidateSize` setTimeout calls | Removed | Redundant; map resizes correctly within first 200 ms |

### Removed PHP

| Item | Action | Reason |
|---|---|---|
| `time()` in CSS version query string | Replaced with static `3` | Prevented HTTP caching of delivery.css |
| `addslashes()` for JS output (×4) | Replaced with `json_encode()` | Unsafe for arbitrary string values in JS context |

### Refactored

| Item | Action | Reason |
|---|---|---|
| `loadDrops()` sequential awaits | Refactored to `Promise.all` with 4 parallel branches | Performance — reduces total dropdown load time by ~70% |

---

## 7. Responsiveness

The UI is fully responsive across all screen sizes using CSS Flexbox + wrapping.

| Breakpoint | Behaviour |
|---|---|
| **> 900 px (Desktop/Large)** | Zones workspace: sidebar (300 px) + map (flex-1) side-by-side. Form rows: 2–4 columns. Filters: flex-row. |
| **≤ 900 px (Tablet)** | Zones workspace stacks vertically (sidebar above map). Map height reduced to 360 px. All form groups become full-width (100%). Filters stack vertically. |
| **≤ 600 px (Mobile)** | Tab button labels hidden (icons only). Tab padding reduced. Horizontal scroll on tab bar via `overflow-x: auto`. |

### RTL Support

All layouts reverse correctly under `[dir="rtl"]`:

- `flex-direction: row-reverse` on card headers, workspace, modal header/footer, provider lookup, coord input
- `text-align: right` on table cells
- Pagination wrapper reverses order
- Arabic font stack applied to tab buttons

---

## 8. Security Notes

### Fixed in this audit

| Issue | Severity | Fix |
|---|---|---|
| `addslashes($csrf)` used for JavaScript string output | **Medium** | Replaced with `json_encode($csrf)`. While CSRF tokens are alphanumeric in practice, `json_encode` is the correct approach for embedding PHP values into JavaScript; it handles all characters safely. |

### Existing good practices

| Item | Status |
|---|---|
| `htmlspecialchars()` used for all HTML attribute output (`$csrf`, `$dir`) | ✅ Correct |
| `rawurlencode($lang)` used in URL context | ✅ Correct |
| `json_encode()` used for `PAGE_PERMISSIONS` object | ✅ Correct |
| CSRF token sent as `X-CSRF-Token` header on all API requests | ✅ Correct |
| `declare(strict_types=1)` at file top | ✅ Correct |
| Auth + permission check before any output | ✅ Correct |
| `novalidate` + JS-side validation on forms | ✅ Acceptable (API validates server-side) |
| JS `esc()` helper used in all innerHTML rendering | ✅ Prevents XSS from API data |
| Coordinates output via `.toFixed()` — always numeric | ✅ Safe |
| `parseInt()` / `(int)` casts on numeric IDs | ✅ Safe |
| API calls use `credentials: 'same-origin'` | ✅ Correct |

### Remaining low-priority notes

| Issue | Severity | Note |
|---|---|---|
| `confirm('Delete this item?')` is hardcoded English | Low | Should use `__t()` equivalent in JS i18n. No security impact. |
| `notify('Saved successfully', 'success')` etc. are hardcoded English | Low | Cosmetic i18n gap. No security impact. |
| Nominatim geocoding uses a direct `fetch` without rate-limiting | Low | Nominatim has a 1 req/s policy. The coord picker is admin-only and manually triggered, so abuse risk is minimal. |

---

## 9. Audit Report

### Issues Found

| # | File | Issue | Severity | Status |
|---|---|---|---|---|
| 1 | `delivery.php:65` | `?v=<?= time() ?>` prevents HTTP caching of delivery.css — re-downloaded on every page load | **High** | ✅ Fixed |
| 2 | `delivery.php:800,804,807` | `addslashes($csrf/$lang/$dir)` used for JavaScript string embedding — incorrect escaping function | **Medium** | ✅ Fixed |
| 3 | `delivery.js:890` | `orderStatusEl` event binding runs at module-load time (outside `init()`), not re-applied after `reinit()` — cancel-fields toggle breaks on AJAX re-navigation | **Medium** | ✅ Fixed |
| 4 | `delivery.js:1065` | `loadDrops()` makes 4 API calls sequentially — total wait time ≈ 400–600 ms when calls could run in parallel | **Medium** | ✅ Fixed |
| 5 | `delivery.php:844` | Polling `setInterval` (120 × 100 ms) in reinit inline script does nothing — creates a 12 s live timer for no functional purpose | **Low** | ✅ Fixed |
| 6 | `delivery.js:1179` | 5 `invalidateSize` timeouts (100/300/700/1500/3000 ms) — 2 are redundant | **Low** | ✅ Fixed |
| 7 | `delivery.js:*` | `confirm('Delete this item?')` and success/error messages are hardcoded English, not using i18n system | **Low** | ⚠️ Noted (out of scope for this audit) |
| 8 | `delivery.js:searchCoordPlace` | Nominatim geocoding has no rate limiting or debounce | **Low** | ⚠️ Noted (admin-only, manually triggered) |

### Before vs. After

| Metric | Before | After |
|---|---|---|
| CSS HTTP cache | ❌ Busted every page load | ✅ Fully cached (same version) |
| JS output escaping | ⚠️ `addslashes()` (incomplete) | ✅ `json_encode()` (correct) |
| Dropdown load time | ~400–600 ms (sequential) | ~100–150 ms (parallel) |
| `orderStatus` binding on reinit | ❌ Not reapplied after AJAX nav | ✅ Inside `init()`, reapplied |
| Idle timer in reinit script | ❌ 12 s `setInterval` | ✅ Removed |
| `invalidateSize` timers | 5 callbacks | 3 callbacks |

---

## 10. Future Improvements

### Performance

| Suggestion | Detail |
|---|---|
| **Lazy-load inactive tab content** | Currently all 6 tab panels are rendered in HTML even though only the Zones tab is active. Consider rendering inactive panels on first tab-click (already done for API data via `state[tab].loaded`; extend to DOM rendering). |
| **Pagination for large datasets** | `loadDrops()` fetches up to 500 zones, 500 orders, and 500 providers for dropdown population. For large tenants, replace with server-side search (autocomplete/select2 pattern) to avoid large payloads. |
| **HTTP caching headers on API** | Add `Cache-Control: private, max-age=60` to `/api/delivery_zones` and `/api/delivery_providers` responses (data changes infrequently). The JS layer could cache the last response in `sessionStorage` and only re-fetch when the tab is explicitly refreshed. |
| **Virtualize long zone list** | The zones sidebar renders all items at once. For tenants with many zones, implement virtual scrolling or increase pagination granularity. |

### Architecture

| Suggestion | Detail |
|---|---|
| **Extract `createModule` to shared admin utility** | The generic module factory pattern is useful across many admin fragments. Consider moving it to `admin_framework.js` as `AdminFramework.createCRUDModule()`. |
| **Replace `confirm()` with modal dialog** | Native `confirm()` blocks the UI thread and cannot be styled. Replace with the existing admin modal system (`AdminFramework` or `modal.js`). |
| **i18n for JS notifications** | `notify('Saved successfully', 'success')` and similar strings should pass through the JS i18n layer (already available via `window.i18n` or `data-i18n` approach) to support the multilingual admin. |

### UX

| Suggestion | Detail |
|---|---|
| **Optimistic UI updates** | After a successful save/delete, update the in-memory `state[tab].items` directly instead of refetching the full page, for a snappier feel. |
| **Status badge for Zones sidebar** | Add an active/inactive colored dot to zone list items to improve scannability. |
| **Keyboard navigation on tabs** | Add `role="tablist"` / `role="tab"` ARIA attributes and arrow-key navigation to the workspace tabs for accessibility compliance. |
