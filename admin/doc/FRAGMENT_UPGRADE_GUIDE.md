# دليل توحيد ملفات الـ Fragments
## النموذج المرجعي: `bad_words.php / .css / .js`

---

## 1. هيكل الملفات لكل صفحة

```
admin/fragments/page_name.php
admin/assets/css/pages/page_name.css
admin/assets/js/pages/page_name.js
```

---

## 2. قائمة التعديلات — PHP Fragment

### 2.1 رأس الملف (Context + Auth)

```php
<?php
declare(strict_types=1);

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}
```

> **ملاحظة:** لا تغيّر هذا القسم — هو موحّد في كل الصفحات.

---

### 2.2 ❌ → ✅  `time()` إلى `assetVer()`

```php
// ❌ قبل
<link href="/admin/assets/css/pages/page_name.css?v=<?= time() ?>">
<script src="/admin/assets/js/pages/page_name.js?v=<?= time() ?>">

// ✅ بعد
<link href="/admin/assets/css/pages/page_name.css?v=<?= assetVer('/admin/assets/css/pages/page_name.css') ?>">
<script src="/admin/assets/js/pages/page_name.js?v=<?= assetVer('/admin/assets/js/pages/page_name.js') ?>">
```

أضف هذا الكود بعد السطر الأخير من `require_once` وقبل أي HTML:

```php
if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
```

---

### 2.3 ❌ → ✅  هيكل الـ Filter Bar

```php
// ❌ قبل — كل عناصر الفلتر في سطر واحد
<div class="card-body filter-bar">
    <input type="text" id="filterSearch" class="form-control">
    <select id="filterType" class="form-control">...</select>
    <button id="btnFilter" class="btn btn-primary">Filter</button>
    <button id="btnClear"  class="btn btn-secondary">Clear</button>
</div>

// ✅ بعد — كل فلتر في filter-group خاص به
<div class="card-body">
    <div class="filters-grid">

        <div class="filter-group">
            <label class="filter-label" for="filterSearch">Search</label>
            <input type="text" id="filterSearch" class="form-control"
                   placeholder="..." data-i18n-placeholder="filter.search_placeholder">
        </div>

        <div class="filter-group">
            <label class="filter-label" for="filterType">Type</label>
            <select id="filterType" class="form-control">
                <option value="">All Types</option>
                ...
            </select>
        </div>

        <!-- كرّر filter-group لكل فلتر -->

        <div class="filter-group">
            <label class="filter-label" aria-hidden="true">&nbsp;</label>
            <div class="filter-buttons">
                <button id="btnFilter" class="btn btn-primary">Filter</button>
                <button id="btnClear"  class="btn btn-secondary">Clear</button>
            </div>
        </div>

    </div>
</div>
```

---

### 2.4 ✅ استخدام كلاسات الأزرار الديناميكية (لا للألوان الثابتة)

تأكد من أن **جميع الأزرار** في الصفحة (فلتر، تصفية، إلغاء، إضافة، تعديل، حذف، الخ) تستخدم كلاسات الأزرار المرتبطة بقاعدة البيانات والمولدة ديناميكياً، مثل:
- `.btn-primary` للحدث الأساسي (حفظ، فلتر، إضافة)
- `.btn-secondary` للحدث الثانوي (إلغاء، تصفية، إغلاق)
- `.btn-danger` للحذف أو التحذير
- `.btn-success` للنجاح
- `.btn-info` تأكد من تحويلها إلى `.btn-primary`
- `.btn-warning` للتنبيه

**يمنع منعاً باتاً** إعطاء أي زر لون خلفية أو نص ثابت (`hardcoded`) في ملفات الـ CSS الخاصة بالصفحة. كل ألوان الأزرار تأتي حصرياً من جدول `button_styles` في قاعدة البيانات.

---

### 2.5 ❌ → ✅  الجدول — أضف حالات Loading / Empty / Error

```php
<div class="card-body">

    <!-- Loading -->
    <div id="pageLoading" class="loading-state" style="display:none;">
        <div class="spinner" role="status"></div>
        <p data-i18n="loading">Loading...</p>
    </div>

    <!-- Empty -->
    <div id="pageEmpty" class="empty-state" style="display:none;">
        <div class="empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
        <h3 data-i18n="table.no_records">No records found</h3>
        <!-- زر إضافة اختياري هنا -->
    </div>

    <!-- Error -->
    <div id="pageError" class="error-state" style="display:none;">
        <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
        <h3 data-i18n="error.title">Something went wrong</h3>
        <p id="pageErrorMessage"></p>
        <button id="btnRetry" class="btn btn-primary" data-i18n="retry">Retry</button>
    </div>

    <!-- Table -->
    <div id="pageTableContainer" class="table-responsive">
        <table class="data-table" id="pageTable" aria-label="...">
            <thead>
                <tr>
                    <th data-i18n="table.id">ID</th>
                    <!-- بقية الأعمدة -->
                    <th data-i18n="table.actions">Actions</th>
                </tr>
            </thead>
            <tbody id="pageBody"></tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="pagination-wrapper">
    <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
    <div class="pagination" id="pagination" role="navigation"></div>
</div>
```

---

### 2.5 ❌ → ✅  الـ Modals — غيّر class النظام

```php
// ❌ قبل — يتعارض مع AdminModal framework
<div id="myModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Title</h3>
        <div class="modal-body">...</div>
    </div>
</div>

// ✅ بعد — namespace خاص بكل صفحة (استبدل bw بـ prefix خاص بصفحتك)
<div id="myModal"
     class="bw-modal-backdrop"     ← غيّر bw إلى اختصار الصفحة مثل prd/usr/ord
     role="dialog"
     aria-modal="true"
     aria-labelledby="myModalTitle"
     style="display:none;">
    <div class="bw-modal-panel">
        <div class="bw-modal-header">
            <h3 id="myModalTitle">Title</h3>
            <button type="button"
                    class="btn-close-modal icon-btn"
                    data-modal="myModal"
                    aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="bw-modal-body">
            <!-- المحتوى -->
        </div>
    </div>
</div>
```

**قاعدة الـ prefix:** استخدم اختصار اسم الصفحة لتجنب التعارض بين الصفحات:

| الصفحة | الـ prefix | مثال |
|---|---|---|
| bad_words | `bw` | `bw-modal-backdrop` |
| products | `prd` | `prd-modal-backdrop` |
| users | `usr` | `usr-modal-backdrop` |
| orders | `ord` | `ord-modal-backdrop` |
| vendors | `vnd` | `vnd-modal-backdrop` |
| categories | `cat` | `cat-modal-backdrop` |

---

### 2.6 ✅  window.PAGE_CONFIG — النموذج الموحّد

```php
<script>
window.PAGE_NAME_CONFIG = {
    apiBase:   <?= json_encode($apiBase,    JSON_UNESCAPED_SLASHES) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    lang:      <?= json_encode($lang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_strings,   JSON_UNESCAPED_UNICODE) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
```

---

### 2.7 ✅  سطر الإغلاق — آخر سطر في الملف

```php
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
```

---

## 3. قائمة التعديلات — CSS

### 3.1 ❌ → ✅  غيّر `.modal` إلى namespace خاص

```css
/* ❌ قبل */
.modal { position: fixed; ... }
.modal-content { background: ...; }

/* ✅ بعد — استبدل bw بـ prefix صفحتك */
.bw-modal-backdrop {
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, var(--background-main, #000) 70%, transparent);
    z-index: 12000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(8px, 2vw, 16px);
    box-sizing: border-box;
    overflow-y: auto;
}
.bw-modal-panel {
    background: var(--card-bg, var(--background-secondary, #1e293b));
    border: 1px solid var(--border-color, #334155);
    border-radius: var(--card-border-radius, 12px);
    box-shadow: 0 12px 40px color-mix(in srgb, #000 40%, transparent);
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-sizing: border-box;
}
.bw-modal-panel--wide { max-width: 760px; }
.bw-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: clamp(12px, 1.5vw, 16px) clamp(14px, 2vw, 20px);
    border-bottom: 1px solid var(--border-color, #334155);
    background: var(--surface-color, var(--background-secondary, #1e293b));
    flex-shrink: 0;
}
.bw-modal-header h3 {
    margin: 0;
    font-size: clamp(1rem, 1.3vw, 1.125rem);
    font-weight: 600;
    color: var(--text-primary, #fff);
    font-family: var(--body-font-family, inherit);
}
.bw-modal-body {
    padding: clamp(14px, 2vw, 20px);
    overflow-y: auto;
    flex: 1;
}
```

---

### 3.2 ❌ → ✅  الألوان — لا hardcoded، كل شيء من vars

```css
/* ❌ قبل */
.some-element { background: #1e293b; color: #ffffff; border: 1px solid #334155; }
.badge-custom { background: #10b981; color: #fff; }

/* ✅ بعد */
.some-element {
    background: var(--card-bg, var(--background-secondary, #1e293b));
    color: var(--text-primary, #fff);
    border: 1px solid var(--border-color, #334155);
}
.badge-custom {
    background: color-mix(in srgb, var(--success-color, #10b981) 15%, transparent);
    color: var(--success-color, #10b981);
    border: 1px solid color-mix(in srgb, var(--success-color, #10b981) 30%, transparent);
}
```

**جدول المتغيرات المتاحة:**

| الغرض | المتغير |
|---|---|
| خلفية البطاقات | `var(--card-bg)` |
| خلفية الـ inputs | `var(--input-bg)` |
| خلفية الصفحة | `var(--background-main)` |
| خلفية ثانوية | `var(--background-secondary)` |
| نص رئيسي | `var(--text-primary)` |
| نص ثانوي | `var(--text-secondary)` |
| لون الحدود | `var(--border-color)` |
| اللون الأساسي | `var(--primary-color)` |
| نجاح | `var(--success-color)` |
| تحذير | `var(--warning-color)` |
| خطر/حذف | `var(--danger-color)` |
| معلومات | `var(--info-color)` |
| الخط | `var(--body-font-family)` |
| border-radius | `var(--border-radius)` |
| card border-radius | `var(--card-border-radius)` |

---

### 3.3 ❌ → ✅  الـ badges الخاصة بالصفحة

```css
/* ✅ نموذج badge page-specific لأي حالة مخصصة */
.badge-YOUR_STATUS {
    background: color-mix(in srgb, var(--COLOR-color, FALLBACK) 15%, transparent);
    color:      var(--COLOR-color, FALLBACK);
    border: 1px solid color-mix(in srgb, var(--COLOR-color, FALLBACK) 30%, transparent);
}

/* أمثلة جاهزة */
.badge-featured  { /* استخدم --primary-color  */ }
.badge-archived  { /* استخدم --text-secondary  */ }
.badge-vip       { /* استخدم --warning-color   */ }
.badge-blocked   { /* استخدم --danger-color    */ }
```

> **ملاحظة:** الـ badges العامة (active, inactive, pending, completed...) موجودة بالفعل في `admin_framework.css` — لا تُعيد تعريفها.

---

### 3.4 ✅  قسم Toast — أضفه إذا لم يكن موجوداً

انسخ قسم **TOAST NOTIFICATIONS** كاملاً من `bad_words.css` وغيّر prefix `bw-` إلى prefix صفحتك.

---

### 3.5 ✅  RTL و Responsive — النموذج الموحّد

```css
/* RTL */
[dir="rtl"] .page-header-actions { flex-direction: row-reverse; }
[dir="rtl"] .filter-buttons      { flex-direction: row-reverse; }
[dir="rtl"] .PREFIX-notifications { right: auto; left: clamp(12px, 2vw, 20px); }
[dir="rtl"] .PREFIX-modal-backdrop { direction: rtl; }
[dir="rtl"] .PREFIX-modal-header   { flex-direction: row-reverse; }

/* Responsive */
@media (max-width: 768px) {
    .page-header            { flex-direction: column; align-items: stretch; }
    .page-header-actions    { width: 100%; }
    .page-header-actions .btn { flex: 1; justify-content: center; }
    .filter-buttons         { flex-direction: column; }
    .filter-buttons .btn    { width: 100%; justify-content: center; }

    /* Sheet من الأسفل على الموبايل */
    .PREFIX-modal-backdrop  { align-items: flex-end; padding: 0; }
    .PREFIX-modal-panel     {
        max-width: 100%;
        max-height: 92vh;
        border-radius: var(--card-border-radius, 12px) var(--card-border-radius, 12px) 0 0;
        border-bottom: none;
    }
}

/* Print */
@media print {
    .page-header-actions,
    .filter-buttons,
    .form-actions,
    .PREFIX-notifications,
    .PREFIX-modal-backdrop,
    .pagination-wrapper { display: none !important; }
}
```

---

## 4. قائمة التعديلات — JavaScript

### 4.1 ❌ → ✅  هيكل الملف الموحّد

```js
(function () {
    'use strict';

    // 1. CONFIG
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;

    function reloadConfig() {
        CFG        = window.PAGE_NAME_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    // 2. HELPERS (t, esc, notify, showState)
    // 3. MODAL SYSTEM (openModal, closeModal, ESC listener)
    // 4. DATA LOADING (load + pagination)
    // 5. CRUD (add, edit, save, delete)
    // 6. FILTERS (apply, clear)
    // 7. INIT
    // 8. REGISTER

}());
```

---

### 4.2 ❌ → ✅  `btn-info` إلى `btn-primary`

```js
// ❌ قبل
`<button class="btn btn-sm btn-info edit-btn" data-id="${esc(item.id)}">Edit</button>`

// ✅ بعد
`<button class="btn btn-sm btn-primary edit-btn" data-id="${esc(item.id)}">
    <i class="fas fa-edit" aria-hidden="true"></i>
</button>`
```

---

### 4.3 ❌ → ✅  badge classes

```js
// ❌ قبل
function severityClass(level) {
    if (level === 'low') return 'badge-low';
    ...
}

// ✅ بعد — تتطابق مع CSS المُعرَّفة في ملف الصفحة
function severityClass(level) {
    const map = {
        low:    'badge-severity-low',
        medium: 'badge-severity-medium',
        high:   'badge-severity-high',
    };
    return map[String(level)] || 'badge-secondary';
}
```

---

### 4.4 ✅  دالة `showState()` — أضفها في كل صفحة

```js
function showState(state, errorMsg) {
    const loading   = document.getElementById('pageLoading');
    const empty     = document.getElementById('pageEmpty');
    const error     = document.getElementById('pageError');
    const container = document.getElementById('pageTableContainer');

    [loading, empty, error, container].forEach(el => {
        if (el) el.style.display = 'none';
    });

    switch (state) {
        case 'loading': if (loading)   loading.style.display   = 'flex';   break;
        case 'empty':   if (empty)     empty.style.display     = 'flex';   break;
        case 'error':
            if (error) error.style.display = 'flex';
            if (errorMsg) {
                const p = document.getElementById('pageErrorMessage');
                if (p) p.textContent = errorMsg;
            }
            break;
        default:        if (container) container.style.display = 'block';  break;
    }
}
```

> استبدل `page` بـ prefix صفحتك في أسماء الـ IDs مثل `bwLoading` / `bwEmpty`.

---

### 4.5 ❌ → ✅  دالة `openModal` — أضف focus

```js
// ❌ قبل
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}

// ✅ بعد
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    const first = el.querySelector('input:not([type="hidden"]), select, textarea, button');
    if (first) setTimeout(() => first.focus(), 50);
}
```

---

### 4.6 ✅  ESC يُغلق الـ Modal — أضفه في init

```js
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['myModal', 'mySecondModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.style.display !== 'none') closeModal(id);
    });
});
```

---

### 4.7 ✅  fetch — أضف `credentials: 'same-origin'`

```js
// ❌ قبل
fetch('/api/items').then(...)

// ✅ بعد
fetch('/api/items', { credentials: 'same-origin' }).then(...)

// ✅ POST/PUT/DELETE
fetch('/api/items', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
    credentials: 'same-origin',
    body: JSON.stringify(body),
})
```

---

### 4.8 ✅  سطر تسجيل الصفحة — آخر سطر قبل `}());`

```js
// يدعم fragment navigation وأيضاً التحميل المباشر
window.page = { run: init };

if (window.Admin?.page?.register) {
    window.Admin.page.register('page_name', init);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
```

---

## 5. Checklist — قبل commit أي صفحة

```
PHP Fragment
  [ ] assetVer() بدل time() في كل link و script
  [ ] assetVer() مُعرَّفة locally إذا كان الملف يُحمَّل fragment
  [ ] هيكل filters-grid / filter-group / filter-buttons
  [ ] حالات loading / empty / error مُضافة للجدول
  [ ] Modals تستخدم PREFIX-modal-backdrop/panel/header/body
  [ ] window.PAGE_CONFIG يحتوي csrfToken + strings + can*
  [ ] السطر الأخير: if (!$isFragment) require footer

CSS
  [ ] لا hardcoded colors — كل شيء من var(--)
  [ ] PREFIX-modal-* موجودة مع z-index: 12000
  [ ] Badge classes خاصة بالصفحة مُعرَّفة بـ color-mix
  [ ] Toast notifications بـ PREFIX خاص
  [ ] RTL rules لكل عنصر
  [ ] @media 768px + @media print

JavaScript
  [ ] btn-info غير موجود — بدّله btn-primary
  [ ] badge classes تتطابق مع CSS (badge-severity-* إلخ)
  [ ] showState() موجودة وتُستخدم قبل كل fetch
  [ ] openModal() يُطبّق focus على أول عنصر
  [ ] ESC listener يُغلق كل الـ modals
  [ ] credentials: 'same-origin' على كل fetch
  [ ] آخر سطر: window.page + Admin.page.register + DOMContentLoaded
```

---

## 6. مثال سريع — تحويل صفحة products

### قبل (products.php)
```php
<link href="/admin/assets/css/pages/products.css?v=<?= time() ?>">

<div class="card-body filter-bar">
    <input id="filterSearch" class="form-control">
    <select id="filterCategory" class="form-control">...</select>
    <button id="btnFilter" class="btn btn-primary">Filter</button>
</div>

<div id="productsModal" class="modal" style="display:none;">
    <div class="modal-content">...</div>
</div>

<script src="/admin/assets/js/pages/products.js?v=<?= time() ?>"></script>
```

### بعد (products.php)
```php
<?php
if (!function_exists('assetVer')) {
    function assetVer(string $path): string {
        static $c = [];
        if (!isset($c[$path])) {
            $f = $_SERVER['DOCUMENT_ROOT'] . $path;
            $c[$path] = file_exists($f) ? (string)filemtime($f) : '0';
        }
        return $c[$path];
    }
}
?>
<link href="/admin/assets/css/pages/products.css?v=<?= assetVer('/admin/assets/css/pages/products.css') ?>">

<div class="card-body">
    <div class="filters-grid">
        <div class="filter-group">
            <label class="filter-label" for="filterSearch">Search</label>
            <input id="filterSearch" class="form-control">
        </div>
        <div class="filter-group">
            <label class="filter-label" for="filterCategory">Category</label>
            <select id="filterCategory" class="form-control">...</select>
        </div>
        <div class="filter-group">
            <label class="filter-label" aria-hidden="true">&nbsp;</label>
            <div class="filter-buttons">
                <button id="btnFilter" class="btn btn-primary">Filter</button>
                <button id="btnClear"  class="btn btn-secondary">Clear</button>
            </div>
        </div>
    </div>
</div>

<div id="productsModal"
     class="prd-modal-backdrop"
     role="dialog" aria-modal="true"
     style="display:none;">
    <div class="prd-modal-panel">
        <div class="prd-modal-header">
            <h3 id="productsModalTitle">Add Product</h3>
            <button type="button" class="btn-close-modal icon-btn" data-modal="productsModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="prd-modal-body">...</div>
    </div>
</div>

<script src="/admin/assets/js/pages/products.js?v=<?= assetVer('/admin/assets/js/pages/products.js') ?>"></script>
```

### products.css — أضف
```css
/* استبدل bw بـ prd */
.prd-modal-backdrop { /* انسخ من bad_words.css */ }
.prd-modal-panel    { /* انسخ من bad_words.css */ }
.prd-modal-header   { /* انسخ من bad_words.css */ }
.prd-modal-body     { /* انسخ من bad_words.css */ }

/* RTL */
[dir="rtl"] .prd-modal-backdrop { direction: rtl; }
[dir="rtl"] .prd-modal-header   { flex-direction: row-reverse; }

/* Mobile */
@media (max-width: 768px) {
    .prd-modal-backdrop { align-items: flex-end; padding: 0; }
    .prd-modal-panel    { max-width: 100%; border-radius: 12px 12px 0 0; border-bottom: none; }
}
```

### products.js — عدّل
```js
// غيّر
`<button class="btn btn-sm btn-info edit-btn"`
// إلى
`<button class="btn btn-sm btn-primary edit-btn"`

// أضف showState()
// أضف credentials: 'same-origin' على كل fetch
// أضف ESC listener
// أضف window.page + Admin.page.register
```
