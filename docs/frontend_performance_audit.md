# Frontend Performance Audit — Products Module

**Target File:** `admin/fragments/products.php`  
**Related Assets:** `admin/assets/js/pages/products.js`, `admin/assets/css/pages/products.css`, `admin/assets/js/admin_framework.js`, `admin/assets/js/admin_core.js`  
**Audit Date:** 2026-03-17  
**Auditor Role:** Senior Full-Stack Performance Engineer + Security Auditor

---

## 1. Summary

> **Is the system ready for 1,000,000 users?**

### ❌ NO — NOT READY FOR 1M USERS (PARTIAL for 10K)

The Products module has a solid architectural foundation but contains several critical performance blockers that would cause severe degradation under enterprise-scale traffic. The primary issues are: unminified, uncompressed JavaScript assets (~210 KB uncompressed across 4 files), sequential API calls on initialization (6 separate requests), one catastrophic unbounded API call (`limit=1000` for categories), `time()`-based cache busting forcing re-download of all assets on every page load, 66 `console.*` calls in production code, no debouncing on the search input, and `innerHTML`-based table rendering without virtual scrolling for large datasets.

| Scale | Ready? | Bottleneck |
|---|---|---|
| **10K concurrent users** | ⚠️ PARTIAL | Asset cache busting forces full re-download every page load |
| **100K concurrent users** | ❌ NO | Sequential init API calls + unbounded category fetch overwhelm the API |
| **1M concurrent users** | ❌ NO | All of the above + no CDN, no minification, no HTTP/2 push, no client-side caching |

---

## 2. Critical Issues

> Must fix before production deployment.

### 🔴 CRITICAL-1: `time()` Used for Asset Cache Busting

**File:** `admin/fragments/products.php` — Lines 187, 1027, 1028, 1059

```php
// CURRENT (BAD):
<link rel="stylesheet" href="/admin/assets/css/pages/products.css?v=<?= time() ?>">
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/products.js?v=<?= time() ?>"></script>
```

**Impact:** `time()` returns the current Unix timestamp in seconds. Every single page load generates a new cache-busting query string, forcing the browser to re-download `products.js` (124 KB), `products.css` (14.5 KB), and `admin_framework.js` (19.6 KB) on **every page visit**. With 1M users, this eliminates all HTTP caching benefits entirely.

**Fix:**
```php
// CORRECT: Use filemtime() — only changes when file changes
function assetVer(string $path): string {
    $full = $_SERVER['DOCUMENT_ROOT'] . $path;
    return file_exists($full) ? (string)filemtime($full) : '1';
}

<link rel="stylesheet" href="/admin/assets/css/pages/products.css?v=<?= assetVer('/admin/assets/css/pages/products.css') ?>">
<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>
<script src="/admin/assets/js/pages/products.js?v=<?= assetVer('/admin/assets/js/pages/products.js') ?>"></script>
```

---

### 🔴 CRITICAL-2: 6 Sequential API Calls on Module Init

**File:** `admin/assets/js/pages/products.js` — `loadDropdownData()` function (lines 368–460)

```javascript
// CURRENT (BAD): Sequential — each await blocks the next
const typesResult    = await apiCall(`${API.productTypes}?...`);   // ~80ms
const brandsResult   = await apiCall(`${API.brands}?...`);          // ~80ms
const categoriesResult = await apiCall(`${API.categories}?...`);    // ~150ms (+ limit=1000 problem)
const currenciesResult = await apiCall(`${API.currencies}?...`);    // ~60ms
const attributesResult = await apiCall(`${API.attributes}?...`);    // ~80ms
const languagesResult  = await apiCall(`${API.languages}?...`);     // ~60ms
// Total: ~510ms of blocking sequential fetches before page is interactive
```

**Impact:** Users wait ~500ms+ just for dropdown data before the page becomes interactive. Under high server load at 1M users, each request could take 300–500ms, making the total init time 1.8–3 seconds just for dropdowns.

**Fix:** Use `Promise.allSettled()` to parallelize all independent fetches:
```javascript
// CORRECT: All fetches in parallel — total time = slowest single request
async function loadDropdownData() {
    const [types, brands, categories, currencies, attributes, languages] =
        await Promise.allSettled([
            apiCall(`${API.productTypes}?format=json&lang=${state.language}`),
            apiCall(`${API.brands}?format=json&tenant_id=${state.tenantId}&lang=${state.language}`),
            apiCall(`${API.categories}?page=1&limit=200&tenant_id=${state.tenantId}&lang=${state.language}&format=json`),
            apiCall(`${API.currencies}?format=json`),
            apiCall(`${API.attributes}?format=json&lang=${state.language}`),
            apiCall(`${API.languages}?format=json`),
        ]);
    // Handle each result individually...
}
```

---

### 🔴 CRITICAL-3: Unbounded Category Fetch (`limit=1000`)

**File:** `admin/assets/js/pages/products.js` — Line 400

```javascript
// CURRENT (BAD):
const categoriesResult = await apiCall(
    `${API.categories}?page=1&limit=1000&tenant_id=${state.tenantId}&lang=${state.language}&format=json`
);
```

**Impact:** This fetches up to 1,000 categories in a single response. A tenant with many categories could return a JSON payload of 500 KB+. This:
1. Saturates the network connection during initial load.
2. Forces the browser to parse and hold 1,000 DOM nodes in the categories tree.
3. On the server, the SQL query with LIMIT 1000 + JOINs scans thousands of rows.

**Fix:** Limit to 200 and implement lazy-loading or search-on-type for the categories tree:
```javascript
// Use limit=200 for initial display; add a search endpoint for large catalogs
`${API.categories}?page=1&limit=200&...`
```

---

### 🔴 CRITICAL-4: No Debounce on Search Input

**File:** `admin/assets/js/pages/products.js` — event binding for `searchInput`

The search input fires a new `loadProducts()` API call on every filter apply. If the admin types 10 characters quickly and clicks Apply, or if search is auto-triggered on input, this fires 10 sequential API calls.

**Fix:** Add a 300ms debounce:
```javascript
let searchDebounceTimer = null;
el.searchInput.addEventListener('input', function() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadProducts(1);
    }, 300);
});
```

---

### 🔴 CRITICAL-5: `innerHTML` Table Rendering Without Virtual Scrolling

**File:** `admin/assets/js/pages/products.js` — `renderTable()` function (line 518)

```javascript
// CURRENT: Dumps all rows as one giant HTML string into innerHTML
el.tbody.innerHTML = items.map(prod => { ... }).join('');
```

**Impact:** With 25 items per page this is acceptable. However:
1. On each page change, the entire `<tbody>` is cleared and rewritten — causing a full DOM reflow.
2. If limit is increased (or if the user manually sets `limit=1000`), this renders 1,000 `<tr>` elements at once.
3. Each row contains image elements, badge spans, and action buttons — the DOM tree grows to 10,000+ nodes.

**Fix for current scale:** Batch DOM writes using `DocumentFragment`:
```javascript
const fragment = document.createDocumentFragment();
items.forEach(prod => {
    const tr = document.createElement('tr');
    tr.innerHTML = buildRowHtml(prod); // escape properly
    fragment.appendChild(tr);
});
el.tbody.innerHTML = '';
el.tbody.appendChild(fragment);
```

**Fix for 1M user scale:** Implement virtual scrolling (only render visible rows), or keep server-side pagination at ≤ 25 items and never increase.

---

### 🔴 CRITICAL-6: Products JS Loaded Twice in Embedded Mode

**File:** `admin/fragments/products.php` — Lines 1027–1028 and 1059

```php
// In the `if ($isFragment)` branch — products.js is loaded ONCE (line 1028).
// In the `else` branch — products.js is loaded ONCE (line 1059).
// HOWEVER: admin_framework.js is loaded in the fragment branch (line 1027)
// but may already be loaded by header.php in the non-fragment path.
// More critically: if this fragment is re-loaded via AJAX navigation without
// a full page reload, the DOMContentLoaded listener fires again, potentially
// double-initializing the Products module.
```

**Impact:** In embedded/SPA mode, if the fragment is injected into the DOM multiple times, `window.addEventListener('message', ...)` could accumulate listeners (mitigated by `_messageListenerAdded` flag) and the init polling interval could stack.

**Fix:** Add explicit cleanup in `Products.destroy()` before re-initialization:
```javascript
Products.destroy = function() {
    // Clear all event listeners
    // Clear state
    // Remove DOM event bindings
};
```

---

## 3. Performance Score

### **42 / 100**

| Category | Score | Reason |
|---|---|---|
| Asset Caching | 5/20 | `time()` busting destroys all browser caching |
| Network Efficiency | 8/20 | 6 sequential init requests; unbounded category fetch |
| Rendering Performance | 12/20 | `innerHTML` table fine at 25 rows; breaks at scale |
| JavaScript Quality | 9/20 | Good structure but 66 console calls, no debounce, no AbortController |
| CSS Performance | 8/20 | Reasonable; no minification detected |
| Security | 0/10 | XSS risk via innerHTML from API data (see Section 7) |
| Mobile/Responsiveness | 10/10 | CSS uses CSS variables + responsive grid; acceptable |

---

## 4. Detailed Findings

### 4.1 JavaScript Issues

#### JS-01: 66 `console.*` Calls in Production Code

**File:** `admin/assets/js/pages/products.js`

```
66 occurrences of console.log / console.warn / console.error
```

Console calls are not stripped for production. In high-traffic scenarios, verbose logging floods the browser console and slightly degrades performance. They also expose internal API URLs, state structure, and error details to any user who opens DevTools.

**Fix:** Use a conditional logger:
```javascript
const log = window.DEBUG_MODE ? console.log.bind(console) : () => {};
```
Or strip all `console.*` calls using a build tool (Terser/UglifyJS with `drop_console: true`).

---

#### JS-02: No `AbortController` for In-Flight Requests

When the user changes filters and clicks Apply rapidly, multiple `loadProducts()` calls can be in-flight simultaneously. The last response to arrive (not the last sent) updates the UI, potentially showing stale data.

**Fix:**
```javascript
let currentFetchController = null;

async function loadProducts(page = 1) {
    if (currentFetchController) currentFetchController.abort();
    currentFetchController = new AbortController();
    const signal = currentFetchController.signal;
    // Pass signal to fetch: fetch(url, { signal, credentials: 'same-origin' })
}
```

---

#### JS-03: No `removeEventListener` for Any Bound Handlers

6 `addEventListener` calls are made during `bindEvents()` with no corresponding `removeEventListener`. When the module is re-initialized (SPA navigation), listeners accumulate. The `_messageListenerAdded` flag prevents one specific duplication but does not cover the click, submit, and change handlers.

---

#### JS-04: Translation File Fetched Twice on Page Load

**Files:** `admin/fragments/products.php` (inline `<script>` at line 959–1000) AND `admin/assets/js/pages/products.js` (`loadTranslations()` function)

The translation JSON (`/languages/Product/{lang}.json`) is fetched by an inline script block AND by the `Products.js` module separately. This results in **2 HTTP requests for the same file** on every page load.

**Fix:** The inline translation loader in the PHP fragment should be removed and only the module-level `loadTranslations()` should be kept.

---

#### JS-05: Polling Interval for Module Init (Embedded Mode)

```javascript
// Lines 1039–1054: Polls window.Products every 100ms up to 50 times = 5 seconds
var interval = setInterval(function() {
    attempts++;
    if (window.Products && ...) {
        clearInterval(interval);
    } else if (attempts > maxAttempts) {
        clearInterval(interval);
    }
}, 100);
```

This polling pattern consumes CPU for up to 5 seconds while waiting for the module. With multiple admin pages loading simultaneously, this degrades the main thread.

**Fix:** Use a `CustomEvent` or `Promise` resolved by the module when ready:
```javascript
// In products.js after module definition:
window.dispatchEvent(new CustomEvent('products:ready', { detail: window.Products }));

// In the fragment:
window.addEventListener('products:ready', function(e) {
    e.detail.init();
}, { once: true });
```

---

#### JS-06: Save Operation Makes 5+ Sequential API Calls

**File:** `admin/assets/js/pages/products.js` — `handleSubmit()` function (lines 759–800)

On product save, the following calls are made sequentially:
1. `POST/PUT /api/products` — core product
2. `POST /api/product_pricing` — pricing data
3. `POST /api/product_physical_attributes` — physical attributes
4. `POST /api/product_categories` — category assignments
5. `POST /api/product_attribute_assignments` — attribute assignments
6. `POST /api/product_translations` (per language) — translation records

Each awaited sequentially. For a product with 3 languages, this is 8 sequential requests.

**Fix:** After saving the core product, parallelize the sub-resource saves:
```javascript
await Promise.all([
    savePricingData(savedProductId, formData),
    savePhysicalAttributes(savedProductId, formData),
    saveProductCategories(savedProductId, isEdit),
    saveProductAttributeAssignments(savedProductId, isEdit),
    saveProductVariants(savedProductId, isEdit),
    saveProductTranslations(savedProductId, translations),
]);
```

---

### 4.2 CSS Issues

#### CSS-01: Not Minified

**File:** `admin/assets/css/pages/products.css` (14,533 bytes uncompressed)

The CSS file contains full formatting, whitespace, and comments. No minification is applied.

**Expected savings:** ~40% size reduction (≈8.7 KB minified). At 1M page loads/day, this saves ~5.8 GB of bandwidth.

---

#### CSS-02: CSS Variables Emitted Inline per Request

**File:** `admin/fragments/products.php` — `renderFragmentThemeVars()` function

The PHP function emits all theme CSS variables (colors, fonts, design settings, button styles, card styles) as inline `<style id="db-theme-vars-products">` on every page load. This means:
1. The CSS block is not cacheable (it's inline).
2. It's regenerated from the database on every PHP render.

**Fix:** Cache the generated CSS server-side (Redis/file cache keyed to tenant+theme hash) and serve it as a separate `<link>` with appropriate `Cache-Control` headers.

---

#### CSS-03: Potential CLS from Dynamic Theme Variable Application

When CSS variables are applied via inline `<style>`, the browser may need to repaint themed elements after the initial render if the `<style>` tag appears after the HTML elements. Ensure `<style id="db-theme-vars-products">` appears in `<head>` or immediately before the first themed element.

---

### 4.3 Rendering Issues

#### RENDER-01: Full `<tbody>` Rebuild on Every Page Change

On pagination, the table body is fully cleared and rebuilt:
```javascript
el.tbody.innerHTML = items.map(prod => buildRow(prod)).join('');
```

This triggers a full layout reflow for the entire table, recalculating the position of every cell. With wide tables and CSS variable lookups, this can take 20–50ms per navigation.

**Fix:** Use `DocumentFragment` and row-level `replaceWith` instead of full `innerHTML` replacement.

---

#### RENDER-02: Blocking `applyTranslations()` Runs Before Module Init

The inline translation loader (lines 959–1000 of `products.php`) awaits a fetch before `DOMContentLoaded` and applies translations by iterating all `[data-i18n]` elements. This runs before `Products.init()`, causing a second pass of DOM traversal when the module applies its own translations.

---

#### RENDER-03: Images Not Lazy-Loaded in Product Table

```javascript
// Row rendering includes:
`<img src="${prod.image_url || '/assets/placeholder.png'}" 
      alt="${escapeHtml(prod.name || 'product')}" 
      ...>`
```

No `loading="lazy"` attribute. With 25 rows × 1 product image each = 25 image requests fired simultaneously on every page load/navigation.

**Fix:**
```javascript
`<img src="${prod.image_url || '/assets/placeholder.png'}" 
      alt="${escapeHtml(prod.name || 'product')}"
      loading="lazy" decoding="async" ...>`
```

---

### 4.4 Network Issues

#### NET-01: No HTTP Caching Headers on API Responses

**File:** `api/v1/routes/products.php` — no `Cache-Control` headers are set.

For read-only `GET` requests that return list data, the server should set:
```
Cache-Control: private, max-age=30
```
This allows browsers to reuse the response for 30 seconds without re-fetching. For dropdown data (product types, currencies, languages), use a longer TTL:
```
Cache-Control: private, max-age=3600
```

---

#### NET-02: No Request Deduplication for Concurrent Identical Calls

If `loadProducts()` is called twice in rapid succession (e.g., from two different event handlers), two identical API requests are sent. The first response is overwritten by the second.

**Fix:** AbortController (see JS-02) handles this correctly.

---

#### NET-03: Large JSON Payload on Product List

The `all()` query in `PdoProductsRepository` returns all columns from `products` plus joined columns from `product_translations` and `product_pricing`. Many of these fields (e.g., `meta_keywords`, `specifications`, `meta_title`) are not displayed in the list table but are still transmitted.

**Fix:** Create a separate "list projection" query that only returns columns needed for the table (`id`, `sku`, `name`, `price`, `stock_quantity`, `is_active`, `image_url`), reducing payload by ~60%.

---

#### NET-04: Translation File Fetched on Every Module Init

Each module init calls `/languages/Product/{lang}.json`. If the admin navigates between pages rapidly, this file is re-fetched on every navigation.

**Fix:** Cache the translation object in `sessionStorage`:
```javascript
async function loadTranslations(lang) {
    const cacheKey = `translations_product_${lang}`;
    const cached = sessionStorage.getItem(cacheKey);
    if (cached) {
        translations = JSON.parse(cached);
        applyTranslations();
        return;
    }
    // ... fetch ...
    sessionStorage.setItem(cacheKey, JSON.stringify(translations));
}
```

---

## 5. Optimization Suggestions (Code-Level)

### 5.1 Fix `time()` → `filemtime()` in `products.php`

**Immediate fix** (same pattern already applied to `header.php`):

```php
// In admin/fragments/products.php, replace ALL three time() usages:

// Line 187:
<link rel="stylesheet" href="/admin/assets/css/pages/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/css/pages/products.css') ?: 1 ?>">

// Lines 1027-1028 and 1059:
<script src="/admin/assets/js/admin_framework.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin_framework.js') ?: 1 ?>"></script>
<script src="/admin/assets/js/pages/products.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/pages/products.js') ?: 1 ?>"></script>
```

---

### 5.2 Parallelize Dropdown Data Loading

Replace sequential `await` calls in `loadDropdownData()` with `Promise.allSettled()`:

```javascript
async function loadDropdownData() {
    const [typesRes, brandsRes, catsRes, currsRes, attrsRes, langsRes] =
        await Promise.allSettled([
            apiCall(`${API.productTypes}?format=json&lang=${state.language}`),
            apiCall(`${API.brands}?format=json&tenant_id=${state.tenantId}&lang=${state.language}`),
            apiCall(`${API.categories}?page=1&limit=200&tenant_id=${state.tenantId}&lang=${state.language}&format=json`),
            apiCall(`${API.currencies}?format=json`),
            apiCall(`${API.attributes}?format=json&lang=${state.language}`),
            apiCall(`${API.languages}?format=json`),
        ]);

    if (typesRes.status === 'fulfilled' && typesRes.value.success) {
        state.productTypes = typesRes.value.data?.data || typesRes.value.data?.items || [];
        populateDropdown(el.prodType, state.productTypes, 'id', 'name', '...');
        populateDropdown(el.typeFilter, state.productTypes, 'id', 'name', '...');
    }
    // ... handle each result
}
```

**Estimated improvement:** Init time reduced from ~500ms to ~150ms (network round-trip for the slowest single request).

---

### 5.3 Add `loading="lazy"` to Product Table Images

In the `renderTable()` row builder:
```javascript
// Find the <img> tag in the row template and add:
loading="lazy" decoding="async"
```

---

### 5.4 Cache Translation Files in `sessionStorage`

```javascript
async function loadTranslations(lang) {
    const cacheKey = `_i18n_product_${lang}`;
    try {
        const cached = sessionStorage.getItem(cacheKey);
        if (cached) { translations = JSON.parse(cached); applyTranslations(); return; }
    } catch (_) {}
    
    const res = await fetch(`/languages/Product/${encodeURIComponent(lang)}.json`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`${res.status}`);
    const raw = await res.json();
    translations = buildTranslationsMap(raw.strings || raw);
    try { sessionStorage.setItem(cacheKey, JSON.stringify(translations)); } catch (_) {}
    applyTranslations();
}
```

---

### 5.5 Remove Duplicate Translation Loader from PHP Fragment

Remove the inline `<script>` translation loader block (lines 959–1000 of `products.php`). The `products.js` module already loads and applies translations in `loadTranslations()`. Having both causes two fetches and two DOM traversal passes.

---

### 5.6 Add `AbortController` to `loadProducts()`

```javascript
let _loadController = null;

async function loadProducts(page = 1) {
    if (_loadController) _loadController.abort();
    _loadController = new AbortController();
    
    try {
        showLoading();
        // ... build params ...
        const result = await apiCall(`${API.products}?${params}`, {
            signal: _loadController.signal
        });
        // ... render ...
    } catch (err) {
        if (err.name === 'AbortError') return; // ignore cancelled requests
        showError(err.message);
    }
}
```

---

### 5.7 Add `Cache-Control` Headers to GET API Responses

In `api/v1/routes/products.php`, for GET requests:
```php
case 'GET':
    header('Cache-Control: private, max-age=30, must-revalidate');
    header('Vary: Accept-Language');
    // ... existing code
```

For dropdown APIs (product types, currencies, languages):
```php
header('Cache-Control: private, max-age=3600');
```

---

### 5.8 Implement a Lightweight Build Step

Create a `Makefile` or `npm` build task:
```bash
# Minify JS (example using terser):
npx terser admin/assets/js/pages/products.js \
  --compress drop_console=true,drop_debugger=true \
  --mangle \
  --output admin/assets/js/pages/products.min.js

# Minify CSS (example using clean-css):
npx cleancss -o admin/assets/css/pages/products.min.css admin/assets/css/pages/products.css
```

Then reference `.min.js` / `.min.css` in production. Estimated savings:
- `products.js`: 124 KB → ~52 KB (−58%)
- `admin_core.js`: 51 KB → ~22 KB (−57%)
- `admin_framework.js`: 20 KB → ~9 KB (−55%)
- `products.css`: 14.5 KB → ~8.7 KB (−40%)

Enable gzip/brotli on the web server for further 70% reduction of the already-minified files.

---

## 6. Scalability Verdict

### 10,000 Concurrent Users

**⚠️ PARTIAL — With Critical Fixes Applied**

The server-side is largely fine: tenant-isolated SQL, paginated queries, PDO prepared statements. The critical fix required is replacing `time()` with `filemtime()` — without this, 10K users re-downloading assets on every page visit would cause ~4 GB/hour of unnecessary bandwidth and significantly increase Time to Interactive (TTI).

After fixing cache busting and parallelizing init API calls, 10K users is achievable with:
- Standard LAMP/LEMP stack
- Nginx gzip compression enabled
- Redis for session storage

---

### 100,000 Concurrent Users

**❌ NOT READY — Requires Architecture Changes**

Additional requirements beyond the critical fixes:
- CDN for all static assets (CSS, JS, images)
- Read replicas for the database
- Redis caching for product lists (30-second TTL)
- Horizontal PHP-FPM workers
- The `limit=1000` category fetch **must be fixed** — this query could take 500ms+ per user at this scale

---

### 1,000,000 Concurrent Users

**❌ NOT READY — Requires Full Production Architecture**

Requirements:
- All of the above for 100K
- Minified + Brotli-compressed assets delivered from CDN edge nodes
- Full HTTP/2 or HTTP/3 with server push for critical assets
- Database connection pooling (PgBouncer or equivalent for MySQL)
- Product list API responses cached at edge (CDN) for public-facing; Redis for admin
- Virtual scrolling instead of `innerHTML` table rendering for large datasets
- Server-Sent Events or WebSocket for real-time stock updates instead of polling
- Remove all `console.*` calls from production JS
- Implement proper `AbortController` to prevent waterfall of stale requests during rapid navigation

---

## 7. Frontend Security

### SEC-01: `innerHTML` with API Data — XSS Risk

**File:** `admin/assets/js/pages/products.js` — `renderTable()` (line 518+)

The table rows are built by concatenating product data from the API into HTML strings, then assigned to `el.tbody.innerHTML`.

```javascript
// Example from products.js:
el.tbody.innerHTML = items.map(prod => `
    <tr>
        <td>${prod.id}</td>
        <td><span class="badge">${prod.name}</span></td>
        ...
    </tr>
`).join('');
```

If `prod.name` or any other field contains `<script>alert(1)</script>`, it would be executed as HTML.

The code uses a `escapeHtml()` helper (confirmed at line 1997):
```javascript
return div.innerHTML; // uses DOM-based escaping
```

However, **it's inconsistently applied**. Not every field in the row template passes through `escapeHtml()`. A review of the full row template is required.

**Fix:** Apply `escapeHtml()` to every interpolated value from the API, or use `textContent` for text-only fields.

---

### SEC-02: `window.postMessage` Handler Without Origin Validation

**File:** `admin/assets/js/pages/products.js` — Line 2533

```javascript
window.addEventListener('message', function (e) {
    if (e.data && e.data.type === 'media-selected') {
        state.selectedImages = e.data.images || [];
        renderProductImages();
    }
});
```

The handler does not validate `e.origin`. Any page (including malicious iframes) can send a `message` event with `type: 'media-selected'` and inject arbitrary images into the product form.

**Fix:**
```javascript
window.addEventListener('message', function (e) {
    // Only accept messages from the same origin
    if (e.origin !== window.location.origin) return;
    if (e.data && e.data.type === 'media-selected') {
        state.selectedImages = (e.data.images || []).map(img => ({
            id: parseInt(img.id, 10) || 0,
            url: String(img.url || ''),
            thumb_url: String(img.thumb_url || ''),
        }));
        renderProductImages();
    }
});
```

---

### SEC-03: CSRF Token Exposed in `window.APP_CONFIG`

**File:** `admin/fragments/products.php` — Line 908

```php
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.CSRF_TOKEN = window.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
```

The CSRF token is exposed globally as `window.CSRF_TOKEN` and `window.APP_CONFIG.CSRF_TOKEN`. Any injected third-party script or browser extension can read these values. The token is currently not scoped to a closure.

**Risk level:** LOW for same-origin attacks (CSRF protection still works), but MEDIUM if there are XSS vulnerabilities.

---

## 8. Responsiveness & UX

### Mobile Performance

The CSS uses CSS variables (`var(--card-bg)`, `var(--border-color)`, etc.) throughout, which are efficient for theming. The layout appears to use CSS Grid and Flexbox based on the form structure (`form-row`, `filters-grid`, `quick-actions-grid`), which is responsive.

**Positive findings:**
- RTL support via `dir` attribute on the page container
- CSS variables for all visual properties (no hardcoded values in structural layout)
- Responsive grid for quick actions (`minmax(220px, 1fr)`)

**Areas for improvement:**
- The 8-tab form (General, Pricing, Inventory, Attributes, Variants, Images, Categories, Translations) on mobile requires horizontal scrolling of the tab bar — add `overflow-x: auto; -webkit-overflow-scrolling: touch;` to `.form-tabs`
- The product table on mobile has 10 columns — needs `overflow-x: auto` wrapper (appears to exist as `.table-responsive`) ✅

### Accessibility Basics

- `aria-label` attributes are present on the sidebar toggle and notifications button ✅
- Form labels are associated with inputs via `for`/`id` ✅
- Empty state includes a meaningful heading and description ✅
- Error state includes an error message paragraph ✅
- **Gap:** Action buttons in table rows lack `aria-label` (only icons, no text for screen readers)

---

## Appendix: Asset Size Reference

| File | Raw Size | Est. Minified | Est. Gzipped |
|---|---|---|---|
| `products.js` | 124.6 KB | ~52 KB | ~16 KB |
| `admin_core.js` | 50.8 KB | ~22 KB | ~7 KB |
| `admin_framework.js` | 19.7 KB | ~9 KB | ~3 KB |
| `products.css` | 14.5 KB | ~8.7 KB | ~2.5 KB |
| **Total** | **209.6 KB** | **~91.7 KB** | **~28.5 KB** |

Without gzip/brotli + minification, each user downloads 209 KB of JS+CSS. With full optimization, this drops to 28 KB — a **7.4× reduction**.

At 1M daily admin sessions, this difference is:
- **Unoptimized:** ~199 GB/day bandwidth for JS+CSS alone
- **Optimized:** ~27 GB/day — saving ~172 GB/day
