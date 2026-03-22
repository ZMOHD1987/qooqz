<?php
declare(strict_types=1);

/**
 * /admin/fragments/brands.php
 * Brands Management — Production v2.0
 * ─ متوافق مع header.php v3.0 (single source of truth)
 * ─ لا hardcoded styles — كل التنسيق من CSS variables
 * ─ assetVer() بدل time() لـ cache-busting صحيح
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

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
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';
$imageTypeId = 12; // brand image type (from image_types table)

// ════════════════════════════════════════════════════════════
// TRANSLATIONS (server-side — injected via BRANDS_CONFIG.strings)
// ════════════════════════════════════════════════════════════
$_brStrings     = [];
$_brAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_brSafeLang = in_array($lang, $_brAllowedLangs, true) ? $lang : 'en';
$_brLangFile = __DIR__ . '/../../languages/Brands/' . $_brSafeLang . '.json';

if (file_exists($_brLangFile)) {
    $_brJson = json_decode(file_get_contents($_brLangFile), true);
    if (is_array($_brJson)) {
        $_brStrings = isset($_brJson['strings']) ? $_brJson['strings'] : $_brJson;
    }
}

/**
 * Translate dot-notation key — PHP fallback only.
 * Runtime translations handled by data-i18n attributes via admin_core.js.
 */
function _brt(string $key, string $fallback = ''): string
{
    global $_brStrings;
    $parts = explode('.', $key);
    $val   = $_brStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

// assetVer() مُعرَّفة في header.php — نُعرِّفها هنا فقط عند fragment مستقل
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
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/brands.css?v=<?= assetVer('/admin/assets/css/pages/brands.css') ?>">

<meta data-page="brands"
      data-assets-css="/admin/assets/css/pages/brands.css"
      data-assets-js="/admin/assets/js/pages/brands.js"
      data-i18n-files="/languages/Brands/<?= rawurlencode($_brSafeLang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="brandsPageContainer" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══════════════════════════════════════════
         PAGE HEADER
    ════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="brands.title">
                <?= htmlspecialchars(_brt('brands.title', 'Brands'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="brands.subtitle">
                <?= htmlspecialchars(_brt('brands.subtitle', 'Manage brands and their translations'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddBrand" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="brands.add_new"><?= htmlspecialchars(_brt('brands.add_new', 'Add Brand'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FORM CONTAINER
    ════════════════════════════════════════════ -->
    <div id="brandFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title">
                <?= htmlspecialchars(_brt('form.add_title', 'Add Brand'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm"
                    aria-label="<?= htmlspecialchars(_brt('accessibility.close', 'Close'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="brandForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="brandImageId" name="image_id">
                <input type="hidden" id="brandImageTypeId" value="<?= $imageTypeId ?>">

                <div class="form-row">
                    <!-- Tenant -->
                    <div class="form-group">
                        <label for="brandTenantId" data-i18n="form.fields.tenant_id.label">Tenant ID</label>
                        <input type="number"
                               id="brandTenantId"
                               name="tenant_id"
                               class="form-control"
                               value="<?= $tenantId ?>"
                               <?= is_super_admin() ? '' : 'readonly' ?>
                               required>
                        <div id="tenantInfo" class="br-tenant-info"></div>
                    </div>

                    <!-- Entity ID -->
                    <div class="form-group">
                        <label for="brandEntityId" class="required" data-i18n="form.fields.entity_id.label">Entity ID</label>
                        <input type="number"
                               id="brandEntityId"
                               name="entity_id"
                               class="form-control"
                               value="0"
                               required
                               data-i18n-placeholder="form.fields.entity_id.placeholder"
                               placeholder="<?= htmlspecialchars(_brt('form.fields.entity_id.placeholder', 'Entity ID'), ENT_QUOTES, 'UTF-8') ?>">
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
                               placeholder="<?= htmlspecialchars(_brt('form.fields.slug.placeholder', 'Enter slug'), ENT_QUOTES, 'UTF-8') ?>">
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
                               placeholder="<?= htmlspecialchars(_brt('form.fields.website_url.placeholder', 'https://example.com'), ENT_QUOTES, 'UTF-8') ?>">
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
                               placeholder="<?= htmlspecialchars(_brt('form.fields.sort_order.placeholder', 'Sort order'), ENT_QUOTES, 'UTF-8') ?>">
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
                        <div class="br-image-row">
                            <img id="brandImagePreview"
                                 src="/assets/images/no-image.png"
                                 class="br-image-preview"
                                 alt="Brand image">
                            <div class="br-image-actions">
                                <button type="button" id="brandSelectImageBtn" class="btn btn-secondary br-image-btn"
                                        data-i18n="common.select_image">
                                    <?= htmlspecialchars(_brt('common.select_image', 'Select Image'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button type="button" id="brandRemoveImageBtn" class="btn btn-outline-danger br-image-btn"
                                        data-i18n="common.remove_image">
                                    <?= htmlspecialchars(_brt('common.remove_image', 'Remove Image'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <select id="brandImageType" class="form-control" style="display:none;">
                                    <option value="<?= $imageTypeId ?>">Brand Image (600x600)</option>
                                </select>
                                <small id="brandImageTypeDesc" style="display:none;">Image will be cropped to 600x600, fit</small>
                                <div id="brandImageLinks" class="br-image-links"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div class="translations-section">
                    <h4 class="section-heading">
                        <i class="fas fa-language" aria-hidden="true"></i> Translations
                    </h4>
                    <div id="brandTranslations" class="translation-panels"></div>
                    <div class="form-group br-lang-add-row-wrapper">
                        <label for="brandLangSelect" data-i18n="form.translations.select_lang">Select Language</label>
                        <div class="br-lang-add-row">
                            <select id="brandLangSelect" class="form-control">
                                <option value="">Choose language</option>
                            </select>
                            <button type="button" id="brandAddLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus" aria-hidden="true"></i> Add Translation
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save"><?= htmlspecialchars(_brt('form.buttons.save', 'Save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= htmlspecialchars(_brt('form.buttons.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteBrand" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span data-i18n="table.actions.delete"><?= htmlspecialchars(_brt('table.actions.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FILTERS
    ════════════════════════════════════════════ -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="searchInput" data-i18n="filters.search">Search</label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= htmlspecialchars(_brt('filters.search_placeholder', 'Search...'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label class="filter-label" for="tenantFilter" data-i18n="filters.tenant_id">Tenant ID</label>
                    <input type="number"
                           id="tenantFilter"
                           class="form-control"
                           value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= htmlspecialchars(_brt('filters.tenant_placeholder', 'Filter by tenant'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label class="filter-label" for="statusFilter" data-i18n="filters.status">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">All Status</option>
                        <option value="1" data-i18n="filters.status_options.active">Active</option>
                        <option value="0" data-i18n="filters.status_options.inactive">Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="featuredFilter" data-i18n="filters.featured">Featured</label>
                    <select id="featuredFilter" class="form-control">
                        <option value="" data-i18n="filters.featured_options.all">All</option>
                        <option value="1" data-i18n="filters.featured_options.yes">Featured</option>
                        <option value="0" data-i18n="filters.featured_options.no">Not Featured</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                            <?= htmlspecialchars(_brt('filters.apply', 'Apply'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                            <?= htmlspecialchars(_brt('filters.reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="display:none;">
        <span>
            <i class="fas fa-list" aria-hidden="true"></i>
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- ═══════════════════════════════════════════
         DATA TABLE
    ════════════════════════════════════════════ -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner" role="status"></div>
                <p data-i18n="brands.loading"><?= htmlspecialchars(_brt('brands.loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="brandsTable" aria-label="Brands">
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
                    <div class="pagination" id="pagination" role="navigation" aria-label="Pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon"><i class="fas fa-tags" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title">No Brands Found</h3>
                <p data-i18n="table.empty.message">Start by adding brands</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Brands)window.Brands.add()">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span data-i18n="table.empty.add_first">Add First Brand</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="brands.retry">Retry</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MODAL: MEDIA STUDIO
    ════════════════════════════════════════════ -->
    <div id="brandMediaStudioModal"
         class="br-modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="brandMediaStudioTitle"
         style="display:none">
        <div class="br-modal-panel br-modal-panel--wide br-modal-panel--studio">
            <div class="br-modal-header">
                <h3 id="brandMediaStudioTitle">Media Studio</h3>
                <button type="button"
                        class="btn-close-modal icon-btn"
                        id="brandMediaStudioClose"
                        data-modal="brandMediaStudioModal"
                        aria-label="<?= htmlspecialchars(_brt('accessibility.close', 'Close'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="br-modal-body br-modal-body--studio">
                <iframe id="brandMediaStudioFrame"
                        class="media-studio-iframe"
                        src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= rawurlencode($lang) ?>&image_type_id=<?= $imageTypeId ?>&mode=select"></iframe>
            </div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.BRANDS_CONFIG = {
    apiBase:       <?= json_encode($apiBase,    JSON_UNESCAPED_SLASHES) ?>,
    apiUrl:        <?= json_encode($apiBase . '/brands',    JSON_UNESCAPED_SLASHES) ?>,
    languagesApi:  <?= json_encode($apiBase . '/languages', JSON_UNESCAPED_SLASHES) ?>,
    tenantsApi:    <?= json_encode($apiBase . '/tenants',   JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:     <?= json_encode($csrf) ?>,
    lang:          <?= json_encode($_brSafeLang) ?>,
    dir:           <?= json_encode($dir) ?>,
    tenantId:      <?= (int) $tenantId ?>,
    userId:        <?= (int) $userId ?>,
    strings:       <?= json_encode($_brStrings, JSON_UNESCAPED_UNICODE) ?>,
    imageTypeId:   <?= (int) $imageTypeId ?>,
    canCreate:     <?= json_encode($canCreate) ?>,
    canEdit:       <?= json_encode($canEdit) ?>,
    canDelete:     <?= json_encode($canDelete) ?>,
    isSuperAdmin:  <?= json_encode(is_super_admin()) ?>,
    permissions: {
        canCreate:     <?= json_encode($canCreate) ?>,
        canEdit:       <?= json_encode($canEdit) ?>,
        canDelete:     <?= json_encode($canDelete) ?>,
        canDuplicate:  <?= json_encode($canDuplicate) ?>,
        canViewAll:    <?= json_encode($canViewAll) ?>,
        canViewOwn:    <?= json_encode($canViewOwn) ?>,
        canViewTenant: <?= json_encode($canViewTenant) ?>,
        canEditAll:    <?= json_encode($canEditAll) ?>,
        canEditOwn:    <?= json_encode($canEditOwn) ?>,
        canDeleteAll:  <?= json_encode($canDeleteAll) ?>,
        canDeleteOwn:  <?= json_encode($canDeleteOwn) ?>,
        isSuperAdmin:  <?= json_encode(is_super_admin()) ?>
    },
    itemsPerPage: 25
};
</script>
<script src="/admin/assets/js/pages/brands.js?v=<?= assetVer('/admin/assets/js/pages/brands.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>