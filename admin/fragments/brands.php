<?php
declare(strict_types=1);

/**
 * /admin/fragments/brands.php
 * Production Version – Brands Management Fragment
 * 
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ No deprecated fields
 * ✅ Production-ready
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
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
// CHECK PERMISSIONS (resource = 'brands')
// ════════════════════════════════════════════════════════════
$canManage = can('brands.manage') || can('brands.create');
$canViewAll   = can_view_all('brands');
$canViewOwn    = can_view_own('brands');
$canViewTenant = can_view_tenant('brands');
$canCreate     = can_create('brands');
$canEditAll    = can_edit_all('brands');
$canEditOwn    = can_edit_own('brands');
$canDeleteAll  = can_delete_all('brands');
$canDeleteOwn  = can_delete_own('brands');

$canView   = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit   = $canEditAll || $canEditOwn || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;
$canDuplicate = $canCreate;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view brands');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS (same as categories)
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    return (function_exists('i18n_get') && ($v = i18n_get($key))) ? $v : ($fallback ?: $key);
}
function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) $text = str_replace("{" . $ph . "}", (string)$val, $text);
    return $text;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';
$imageTypeId = 12; // brand image type (from image_types table)

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/brands.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="brands"
      data-i18n-files="/languages/Brands/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="brandsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="brands.title"><?= __t('brands.title', 'Brands') ?></h1>
            <p class="page-subtitle" data-i18n="brands.subtitle"><?= __t('brands.subtitle', 'Manage brands and their translations') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddBrand" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="brands.add_new"><?= __t('brands.add_new', 'Add Brand') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="brandFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Brand') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="brandForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="brandImageId" name="image_id">
                <input type="hidden" id="brandImageTypeId" value="<?= $imageTypeId ?>">

                <div class="form-row">
                    <!-- Tenant (Read-only for non-super-admin) -->
                    <div class="form-group">
                        <label for="brandTenantId" data-i18n="form.fields.tenant_id.label">Tenant ID</label>
                        <input type="number" 
                               id="brandTenantId" 
                               name="tenant_id" 
                               class="form-control" 
                               value="<?= $tenantId ?>" 
                               <?= is_super_admin() ? '' : 'readonly' ?>
                               required>
                        <div id="tenantInfo" style="margin-top:5px; font-size:12px;"></div>
                    </div>

                    <!-- Entity ID (foreign key to entities, required) -->
                    <div class="form-group">
                        <label for="brandEntityId" class="required" data-i18n="form.fields.entity_id.label">Entity ID</label>
                        <input type="number"
                               id="brandEntityId"
                               name="entity_id"
                               class="form-control"
                               value="0"
                               required
                               placeholder="<?= __t('form.fields.entity_id.placeholder', 'Entity ID') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.entity_id.required">Entity ID is required</div>
                    </div>

                    <!-- Slug -->
                    <div class="form-group">
                        <label for="brandSlug" class="required" data-i18n="form.fields.slug.label">Slug</label>
                        <input type="text"
                               id="brandSlug"
                               name="slug"
                               class="form-control"
                               required
                               data-i18n-placeholder="form.fields.slug.placeholder"
                               placeholder="<?= __t('form.fields.slug.placeholder', 'Enter slug') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.slug.required">Slug is required</div>
                    </div>

                    <!-- Website URL -->
                    <div class="form-group">
                        <label for="brandWebsiteUrl" data-i18n="form.fields.website_url.label">Website URL</label>
                        <input type="url"
                               id="brandWebsiteUrl"
                               name="website_url"
                               class="form-control"
                               data-i18n-placeholder="form.fields.website_url.placeholder"
                               placeholder="<?= __t('form.fields.website_url.placeholder', 'https://example.com') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <!-- Sort Order -->
                    <div class="form-group">
                        <label for="brandSortOrder" data-i18n="form.fields.sort_order.label">Sort Order</label>
                        <input type="number"
                               id="brandSortOrder"
                               name="sort_order"
                               class="form-control"
                               value="0"
                               placeholder="<?= __t('form.fields.sort_order.placeholder', 'Sort order') ?>">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="brandIsActive" data-i18n="form.fields.status.label">Status</label>
                        <select id="brandIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active">Active</option>
                            <option value="0" data-i18n="form.fields.status.inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- Featured -->
                    <div class="form-group">
                        <label for="brandIsFeatured" data-i18n="form.fields.featured.label">Featured</label>
                        <select id="brandIsFeatured" name="is_featured" class="form-control">
                            <option value="0" data-i18n="form.fields.featured.no">No</option>
                            <option value="1" data-i18n="form.fields.featured.yes">Yes</option>
                        </select>
                    </div>
                </div>

                <!-- Image with Type (brand = 12) -->
                <div class="form-group">
                    <label data-i18n="form.fields.image.label">Image</label>
                    <div class="image-upload-section">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                            <img id="brandImagePreview" src="/assets/images/no-image.png" style="width:100px; height:100px; object-fit:contain; border-radius:4px; background:#1a2332;">
                            <div style="flex:1;">
                                <button type="button" id="brandSelectImageBtn" class="btn btn-secondary" data-i18n="common.select_image" style="width:100%; margin-bottom:8px;">
                                    <?= __t('common.select_image', 'Select Image') ?>
                                </button>
                                <button type="button" id="brandRemoveImageBtn" class="btn btn-outline-danger" data-i18n="common.remove_image" style="width:100%;">
                                    <?= __t('common.remove_image', 'Remove Image') ?>
                                </button>
                                <select id="brandImageType" class="form-control" style="font-size:0.85rem; display:none;">
                                    <option value="<?= $imageTypeId ?>">Brand Image (600x600)</option>
                                </select>
                                <small id="brandImageTypeDesc" style="color:#94a3b8; display:none;">Image will be cropped to 600x600, fit</small>
                                <div id="brandImageLinks" style="margin-top:5px; font-size:0.8rem; display:flex; gap:10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div class="translations-section" style="margin-top:20px;">
                    <h4 style="margin-bottom:12px; color:var(--text-primary,#fff); border-bottom:1px solid var(--border-color,#263044); padding-bottom:8px;">
                        <i class="fas fa-language"></i> Translations
                    </h4>
                    <div id="brandTranslations" class="translation-panels"></div>
                    <div class="form-group" style="margin-top:12px;">
                        <label for="brandLangSelect" data-i18n="form.translations.select_lang">Select Language</label>
                        <div style="display:flex; gap:8px; align-items:flex-end;">
                            <select id="brandLangSelect" class="form-control" style="flex:1;">
                                <option value="">Choose language</option>
                            </select>
                            <button type="button" id="brandAddLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Translation
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
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteBrand" class="btn btn-danger" style="display:none">
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
                    <label for="statusFilter" data-i18n="filters.status">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">All Status</option>
                        <option value="1" data-i18n="filters.status_options.active">Active</option>
                        <option value="0" data-i18n="filters.status_options.inactive">Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="featuredFilter" data-i18n="filters.featured">Featured</label>
                    <select id="featuredFilter" class="form-control">
                        <option value="" data-i18n="filters.featured_options.all">All</option>
                        <option value="1" data-i18n="filters.featured_options.yes">Featured</option>
                        <option value="0" data-i18n="filters.featured_options.no">Not Featured</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
            <i class="fas fa-list"></i> 
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="brands.loading"><?= __t('brands.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="brandsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant">Tenant</th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.entity">Entity ID</th>
                                <th data-i18n="table.headers.image">Image</th>
                                <th data-i18n="table.headers.name">Name</th>
                                <th data-i18n="table.headers.slug">Slug</th>
                                <th data-i18n="table.headers.website">Website</th>
                                <th data-i18n="table.headers.sort_order">Sort</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.featured">Featured</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing">Showing</span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🏷️</div>
                <h3 data-i18n="table.empty.title">No Brands Found</h3>
                <p data-i18n="table.empty.message">Start by adding brands</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Brands)window.Brands.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first">Add First Brand</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="brands.retry">Retry</button>
            </div>
        </div>
    </div>

    <!-- Media Studio Modal -->
    <div id="brandMediaStudioModal" class="modal" style="display:none">
        <div class="modal-content">
            <span class="close" id="brandMediaStudioClose">&times;</span>
            <iframe id="brandMediaStudioFrame" src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= $lang ?>&image_type_id=<?= $imageTypeId ?>&mode=select" style="width:100%; height:500px; border:none;"></iframe>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
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
window.BRANDS_CONFIG = {
    apiUrl: '<?= $apiBase ?>/brands',
    languagesApi: '<?= $apiBase ?>/languages',
    tenantsApi: '<?= $apiBase ?>/tenants',
    csrfToken: '<?= addslashes($csrf) ?>',
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 25,
    imageTypeId: <?= $imageTypeId ?>
};
</script>

<!-- Translation loader -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url = `/languages/Brands/${encodeURIComponent(lang)}.json`;
            console.log('[Brands] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.BRANDS_TRANSLATIONS = translations;
            const container = document.getElementById('brandsPageContainer');
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
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[Brands] Translations applied');
        } catch (err) {
            console.warn('[Brands] Translation load/apply failed:', err);
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

<script id="BRANDS_INITIAL_PAYLOAD" type="application/json">
<?= json_encode(['items' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]]) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/brands.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[Brands] Embedded mode - waiting for framework & module...');
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.Brands && typeof window.Brands.init === 'function') {
            clearInterval(interval);
            console.log('[Brands] Module ready - initializing...');
            try {
                const maybePromise = window.Brands.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(()=>console.log('[Brands] Initialized')).catch(e=>console.error('[Brands] Init failed', e));
                } else {
                    console.log('[Brands] Initialized (sync)');
                }
            } catch (e) {
                console.error('[Brands] Init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Brands] Timeout waiting for module.');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/brands.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>