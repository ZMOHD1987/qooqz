<?php
declare(strict_types=1);

/**
 * /admin/fragments/certificates_products.php
 * Production Version - Certificates Products Management
 *
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ No deprecated fields
 * ✅ Added origin_country_id field (بلد المنشأ)
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// TENANT VERIFICATION ENDPOINT (for super admin)
// ════════════════════════════════════════════════════════════
if ($isFragment && isset($_GET['verify_tenant'])) {
    // Must be logged in
    require_once __DIR__ . '/../includes/admin_context.php';
    if (!is_admin_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $tenantId = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;
    if (!$tenantId) {
        echo json_encode(['valid' => false, 'error' => 'No tenant ID']);
        exit;
    }
    // Only super admin can verify arbitrary tenants
    if (!is_super_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    // Get database connection (from admin_context or global)
    $pdo = $GLOBALS['ADMIN_DB'] ?? null;
    if (!$pdo) {
        echo json_encode(['valid' => false, 'error' => 'Database error']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tenant) {
        echo json_encode(['valid' => true, 'data' => $tenant]);
    } else {
        echo json_encode(['valid' => false, 'error' => 'Tenant not found']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    // Load admin_context to provide helper functions in fragment mode
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user = admin_user();
$lang = admin_lang();
$dir = admin_dir();
$csrf = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManage = can('certificates_products.manage') || can('certificates_products.create');

$canViewAll = can_view_all('certificates_products');
$canViewOwn = can_view_own('certificates_products');
$canViewTenant = can_view_tenant('certificates_products');
$canCreate = can_create('certificates_products');
$canEditAll = can_edit_all('certificates_products');
$canEditOwn = can_edit_own('certificates_products');
$canDeleteAll = can_delete_all('certificates_products');
$canDeleteOwn = can_delete_own('certificates_products');

$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;
$canDuplicate = $canCreate; // optional

// If user has no view permission at all, deny access
if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view certificates products');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace("{" . $ph . "}", (string)$val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/certificates_products.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta – ✅ المسار الصحيح لملفات الترجمة -->
<meta data-page="certificates_products"
      data-i18n-files="/languages/CertificatesProducts/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="certificatesProductsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="certificates_products.title"><?= __t('certificates_products.title', 'Certificates Products') ?></h1>
            <p class="page-subtitle" data-i18n="certificates_products.subtitle"><?= __t('certificates_products.subtitle', 'Manage products associated with certificates') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate || is_super_admin()): ?>
            <button id="btnAddProduct" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="certificates_products.add_new"><?= __t('certificates_products.add_new', 'Add Product') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="productFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Product') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="productForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <!-- Tenant (Read-only for non-super-admin) -->
                    <div class="form-group">
                        <label for="tenantId" data-i18n="form.fields.tenant_id.label">Tenant ID</label>
                        <input type="number" 
                               id="tenantId" 
                               name="tenant_id" 
                               class="form-control" 
                               value="<?= $tenantId ?>" 
                               <?= is_super_admin() ? '' : 'readonly' ?>
                               required>
                        <div id="tenantInfo" style="margin-top:5px; font-size:12px;"></div>
                    </div>

                    <!-- Entity -->
                    <div class="form-group">
                        <label for="entityId" class="required" data-i18n="form.fields.entity.label">Entity</label>
                        <select id="entityId" name="entity_id" class="form-control" required>
                            <option value="" data-i18n="form.fields.entity.select"><?= __t('form.fields.entity.select', 'Select entity') ?></option>
                        </select>
                    </div>

                    <!-- Brand -->
                    <div class="form-group">
                        <label for="brandId" data-i18n="form.fields.brand.label">Brand</label>
                        <select id="brandId" name="brand_id" class="form-control">
                            <option value="" data-i18n="form.fields.brand.select"><?= __t('form.fields.brand.select', 'Select brand') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Product Code -->
                    <div class="form-group">
                        <label for="productCode" data-i18n="form.fields.product_code.label">Product Code</label>
                        <input type="text" id="productCode" name="entity_product_code" class="form-control" 
                               data-i18n-placeholder="form.fields.product_code.placeholder"
                               placeholder="<?= __t('form.fields.product_code.placeholder', 'Enter product code') ?>">
                    </div>

                    <!-- Net Weight -->
                    <div class="form-group">
                        <label for="netWeight" data-i18n="form.fields.net_weight.label">Net Weight</label>
                        <input type="number" step="0.001" id="netWeight" name="net_weight" class="form-control" 
                               data-i18n-placeholder="form.fields.net_weight.placeholder"
                               placeholder="<?= __t('form.fields.net_weight.placeholder', '0.000') ?>">
                    </div>

                    <!-- Weight Unit -->
                    <div class="form-group">
                        <label for="weightUnit" data-i18n="form.fields.weight_unit.label">Weight Unit</label>
                        <select id="weightUnit" name="weight_unit" class="form-control"></select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Sample Status -->
                    <div class="form-group">
                        <label for="sampleStatus" data-i18n="form.fields.sample_status.label">Sample Status</label>
                        <select id="sampleStatus" name="sample_status" class="form-control">
                            <option value="normal" data-i18n="form.fields.sample_status.normal"><?= __t('form.fields.sample_status.normal', 'Normal') ?></option>
                            <option value="tested" data-i18n="form.fields.sample_status.tested"><?= __t('form.fields.sample_status.tested', 'Tested') ?></option>
                            <option value="rejected" data-i18n="form.fields.sample_status.rejected"><?= __t('form.fields.sample_status.rejected', 'Rejected') ?></option>
                        </select>
                    </div>

                    <!-- Product Condition -->
                    <div class="form-group">
                        <label for="productCondition" data-i18n="form.fields.product_condition.label">Product Condition</label>
                        <select id="productCondition" name="product_condition" class="form-control">
                            <option value="chilled" data-i18n="form.fields.product_condition.chilled"><?= __t('form.fields.product_condition.chilled', 'Chilled') ?></option>
                            <option value="frozen" data-i18n="form.fields.product_condition.frozen"><?= __t('form.fields.product_condition.frozen', 'Frozen') ?></option>
                            <option value="dry" data-i18n="form.fields.product_condition.dry"><?= __t('form.fields.product_condition.dry', 'Dry') ?></option>
                        </select>
                    </div>

                    <!-- Origin Country (بلد المنشأ) -->
                    <div class="form-group">
                        <label for="originCountryId" class="required" data-i18n="form.fields.origin_country.label">Origin Country</label>
                        <select id="originCountryId" name="origin_country_id" class="form-control" required>
                            <option value="" data-i18n="form.fields.origin_country.select"><?= __t('form.fields.origin_country.select', 'Select country') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Translations Section – تم إزالة حقل brand -->
                <div class="translations-section">
                    <h4><i class="fas fa-language"></i> <?= __t('form.translations.title', 'Translations') ?></h4>
                    <div id="productTranslations" class="translation-panels"></div>
                    <div class="form-group" style="margin-top:12px;">
                        <label for="langSelect" data-i18n="form.translations.select_lang">Select Language</label>
                        <div style="display:flex; gap:8px;">
                            <select id="langSelect" class="form-control" style="flex:1;">
                                <option value="" data-i18n="form.translations.choose_lang"><?= __t('form.translations.choose_lang', 'Choose language') ?></option>
                            </select>
                            <button type="button" id="addLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i> <?= __t('form.translations.add', 'Add Translation') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete || is_super_admin()): ?>
                    <button type="button" id="btnDeleteProduct" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search">Search</label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id">Tenant ID</label>
                    <input type="number"
                           id="tenantFilter"
                           class="form-control"
                           value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="entityFilter" data-i18n="filters.entity">Entity</label>
                    <select id="entityFilter" class="form-control">
                        <option value="" data-i18n="filters.all_entities"><?= __t('filters.all_entities', 'All Entities') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="brandFilter" data-i18n="filters.brand">Brand</label>
                    <select id="brandFilter" class="form-control">
                        <option value="" data-i18n="filters.all_brands"><?= __t('filters.all_brands', 'All Brands') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sampleStatusFilter" data-i18n="filters.sample_status">Sample Status</label>
                    <select id="sampleStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_status"><?= __t('filters.all_status', 'All') ?></option>
                        <option value="normal" data-i18n="filters.sample_status.normal">Normal</option>
                        <option value="tested" data-i18n="filters.sample_status.tested">Tested</option>
                        <option value="rejected" data-i18n="filters.sample_status.rejected">Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="conditionFilter" data-i18n="filters.product_condition">Condition</label>
                    <select id="conditionFilter" class="form-control">
                        <option value="" data-i18n="filters.all_conditions"><?= __t('filters.all_conditions', 'All') ?></option>
                        <option value="chilled" data-i18n="filters.condition.chilled">Chilled</option>
                        <option value="frozen" data-i18n="filters.condition.frozen">Frozen</option>
                        <option value="dry" data-i18n="filters.condition.dry">Dry</option>
                    </select>
                </div>

                <!-- Origin Country Filter -->
                <div class="filter-group">
                    <label for="originCountryFilter" data-i18n="filters.origin_country">Origin Country</label>
                    <select id="originCountryFilter" class="form-control">
                        <option value="" data-i18n="filters.all_countries"><?= __t('filters.all_countries', 'All Countries') ?></option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="display:none;">
        <span id="resultsCountText"></span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="certificates_products.loading"><?= __t('certificates_products.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="productsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant">Tenant</th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.entity">Entity</th>
                                <th data-i18n="table.headers.brand">Brand</th>
                                <th data-i18n="table.headers.name">Name</th>
                                <th data-i18n="table.headers.code">Code</th>
                                <th data-i18n="table.headers.net_weight">Net Weight</th>
                                <th data-i18n="table.headers.unit">Unit</th>
                                <th data-i18n="table.headers.sample_status">Sample Status</th>
                                <th data-i18n="table.headers.condition">Condition</th>
                                <th data-i18n="table.headers.origin_country">Origin Country</th> <!-- جديد -->
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">📦</div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Products Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'Start by adding a product') ?></p>
                <?php if ($canCreate || is_super_admin()): ?>
                <button class="btn btn-primary" onclick="window.CertificatesProducts.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first"><?= __t('table.empty.add_first', 'Add First Product') ?></span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="certificates_products.retry">
                    <?= __t('certificates_products.retry', 'Retry') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Expose client-side globals -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID = window.APP_CONFIG.USER_ID || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN = window.CSRF_TOKEN || '<?= addslashes($csrf) ?>';

// Page permissions available to JS
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canDuplicate' => $canDuplicate,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script type="text/javascript">
window.CERTIFICATES_PRODUCTS_CONFIG = {
    apiUrl: '<?= $apiBase ?>/certificates_products',
    translationsApi: '<?= $apiBase ?>/certificates_products_translations',
    entitiesApi: '<?= $apiBase ?>/entities',
    brandsApi: '<?= $apiBase ?>/brands',
    unitsApi: '<?= $apiBase ?>/units',
    languagesApi: '<?= $apiBase ?>/languages',
    tenantsApi: '<?= $apiBase ?>/tenants',
    countriesApi: '<?= $apiBase ?>/countries',   // جديد: نقطة نهاية الدول
    csrfToken: '<?= addslashes($csrf) ?>',
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<!-- Translation loader (runs early) – ✅ المسار الصحيح -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url = `/languages/CertificatesProducts/${encodeURIComponent(lang)}.json`;
            console.log('[CertificatesProducts] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.CERTIFICATES_PRODUCTS_TRANSLATIONS = translations;
            // apply translations to elements with data-i18n
            const container = document.getElementById('certificatesProductsPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            // placeholders
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[CertificatesProducts] Translations applied');
        } catch (err) {
            console.warn('[CertificatesProducts] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Page Permissions JSON for scripts that prefer it in DOM -->
<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canDuplicate' => $canDuplicate,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script id="CERTIFICATES_PRODUCTS_INITIAL_PAYLOAD" type="application/json">
<?= json_encode(['items' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]]) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/certificates_products.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[CertificatesProducts] Embedded mode - waiting for framework & module...');
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.CertificatesProducts && typeof window.CertificatesProducts.init === 'function') {
            clearInterval(interval);
            console.log('[CertificatesProducts] Module ready - initializing...');
            try {
                const maybePromise = window.CertificatesProducts.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(()=>console.log('[CertificatesProducts] Initialized')).catch(e=>console.error('[CertificatesProducts] Init failed', e));
                } else {
                    console.log('[CertificatesProducts] Initialized (sync)');
                }
            } catch (e) {
                console.error('[CertificatesProducts] Init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[CertificatesProducts] Timeout waiting for module. Framework present:', !!window.AdminFramework, 'Module present:', !!window.CertificatesProducts);
        } else if (attempts % 10 === 0) {
            console.log('[CertificatesProducts] waiting...', attempts, '/', maxAttempts);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/certificates_products.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>