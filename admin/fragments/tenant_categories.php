<?php
declare(strict_types=1);

/**
 * /admin/fragments/tenant_categories.php
 * Tenant Categories Management — Production v2.0
 *
 * ─ المبادئ ────────────────────────────────────────────────────
 * • لا إعادة حقن :root — header.php هو المصدر الوحيد للـ CSS vars
 * • assetVer() بدل time()
 * • admin_context helpers بدل $payload مباشرة
 * • filters-grid / filter-group / filter-buttons
 * • loading / empty / error states موحّدة
 * • لا inline styles مكرّرة
 * ─────────────────────────────────────────────────────────────
 */

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

// ── Context ──────────────────────────────────────────────────
$user         = admin_user();
$isSuperAdmin = is_super_admin();
$lang         = admin_lang();
$dir          = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf         = admin_csrf();
$tenantId     = admin_tenant_id();
$apiBase      = '/api';

// ── Permissions ──────────────────────────────────────────────
$canCreate = $isSuperAdmin || can('manage_tenant_categories');
$canEdit   = $canCreate;
$canDelete = $canCreate;

// ── Tenant name for non-super-admin ──────────────────────────
$tenantName = '';
if (!$isSuperAdmin && $tenantId) {
    try {
        $pdo = admin_db();
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $tenantName = $stmt->fetchColumn() ?: '';
        }
    } catch (Throwable) {}
    if (empty($tenantName)) {
        $tenantName = $lang === 'ar' ? "المستأجر #{$tenantId}" : "Tenant #{$tenantId}";
    }
}

// ── Translations ─────────────────────────────────────────────
$_tcStrings     = [];
$_tcAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
];
$_tcSafeLang = in_array($lang, $_tcAllowedLangs, true) ? $lang : 'en';
$_tcLangFile = __DIR__ . '/../../languages/Tenant_categories/' . $_tcSafeLang . '.json';

if (file_exists($_tcLangFile)) {
    $_tcJson = json_decode(file_get_contents($_tcLangFile), true);
    $_tcStrings = $_tcJson['strings'] ?? $_tcJson ?? [];
}

function _tc(string $key, string $fallback = ''): string
{
    global $_tcStrings;
    $parts = explode('.', $key);
    $val   = $_tcStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

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
      href="/admin/assets/css/pages/tenant_categories.css?v=<?= assetVer('/admin/assets/css/pages/tenant_categories.css') ?>">

<meta data-page="tenant_categories"
      data-i18n-files="/languages/Tenant_categories/<?= rawurlencode($_tcSafeLang) ?>.json">

<div class="page-container" id="tenantCategoriesPage" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══════════════════════════════════════════
         PAGE HEADER
    ════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="page_title">
                <?= htmlspecialchars(_tc('page_title', 'Tenant Categories'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="page_subtitle">
                <?= htmlspecialchars(_tc('page_subtitle', 'Manage categories assigned to tenants'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="btnAddTenantCategory" class="btn btn-primary" data-i18n="add_button">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?= htmlspecialchars(_tc('add_button', 'Add Tenant Category'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════
         FORM CARD
    ════════════════════════════════════════════ -->
    <div id="tenantCategoryFormContainer" class="card tc-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form_add_title">
                <?= htmlspecialchars(_tc('form_add_title', 'Add Tenant Category'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseTenantCategoryForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantCategoryForm" novalidate>
                <input type="hidden" name="id"         id="tenantCategoryId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">

                    <!-- Tenant field -->
                    <div class="form-group">
                        <label class="required" for="tenantCategoryTenantId" data-i18n="label_tenant">
                            <?= htmlspecialchars(_tc('label_tenant', 'Tenant'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <?php if ($isSuperAdmin): ?>
                        <input type="text"
                               id="tenantCategoryTenantId"
                               name="tenant_display"
                               class="form-control"
                               list="tenantsList"
                               placeholder="<?= htmlspecialchars(_tc('placeholder_tenant', 'Search tenant...'), ENT_QUOTES, 'UTF-8') ?>"
                               data-i18n-placeholder="placeholder_tenant"
                               autocomplete="off">
                        <datalist id="tenantsList"></datalist>
                        <input type="hidden" name="tenant_id" id="tenantCategoryTenantIdHidden">
                        <?php else: ?>
                        <input type="hidden" name="tenant_id" id="tenantCategoryTenantIdHidden"
                               value="<?= (int)$tenantId ?>">
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>"
                               readonly disabled>
                        <?php endif; ?>
                    </div>

                    <!-- Category field -->
                    <div class="form-group">
                        <label class="required" for="tenantCategoryCategoryId" data-i18n="label_category">
                            <?= htmlspecialchars(_tc('label_category', 'Category'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="tenantCategoryCategoryId" name="category_id" class="form-control" required>
                            <option value="">
                                <?= htmlspecialchars(_tc('placeholder_category', '-- Select Category --'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>

                    <!-- Sort Order -->
                    <div class="form-group">
                        <label for="tenantCategorySortOrder" data-i18n="label_sort_order">
                            <?= htmlspecialchars(_tc('label_sort_order', 'Sort Order'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="number" id="tenantCategorySortOrder" name="sort_order"
                               class="form-control" value="0" min="0">
                    </div>

                    <!-- Status (super admin only) -->
                    <?php if ($isSuperAdmin): ?>
                    <div class="form-group">
                        <label for="tenantCategoryIsActive" data-i18n="label_status">
                            <?= htmlspecialchars(_tc('label_status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="tenantCategoryIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="active_option">
                                <?= htmlspecialchars(_tc('active_option', 'Active'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="0" data-i18n="inactive_option">
                                <?= htmlspecialchars(_tc('inactive_option', 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" id="btnSaveTenantCategory" class="btn btn-primary" data-i18n="save_button">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <?= htmlspecialchars(_tc('save_button', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" id="btnCancelTenantCategoryForm" class="btn btn-secondary" data-i18n="cancel_button">
                        <?= htmlspecialchars(_tc('cancel_button', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTenantCategory"
                            class="btn btn-danger tc-delete-btn" style="display:none;" data-i18n="delete_button">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <?= htmlspecialchars(_tc('delete_button', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FILTER BAR
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div class="filters-grid">
                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label class="filter-label" for="tenantCategoryFilterTenant" data-i18n="filter_tenant_label">
                        <?= htmlspecialchars(_tc('filter_tenant_label', 'Tenant'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text"
                           id="tenantCategoryFilterTenant"
                           class="form-control"
                           list="filterTenantsList"
                           placeholder="<?= htmlspecialchars(_tc('placeholder_filter_tenant', 'All Tenants'), ENT_QUOTES, 'UTF-8') ?>"
                           data-i18n-placeholder="placeholder_filter_tenant"
                           autocomplete="off">
                    <datalist id="filterTenantsList"></datalist>
                    <input type="hidden" id="tenantCategoryFilterTenantHidden">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label class="filter-label" for="tenantCategoryFilterCategory" data-i18n="filter_category_label">
                        <?= htmlspecialchars(_tc('filter_category_label', 'Category'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text"
                           id="tenantCategoryFilterCategory"
                           class="form-control"
                           list="filterCategoriesList"
                           placeholder="<?= htmlspecialchars(_tc('placeholder_filter_category', 'All Categories'), ENT_QUOTES, 'UTF-8') ?>"
                           data-i18n-placeholder="placeholder_filter_category"
                           autocomplete="off">
                    <datalist id="filterCategoriesList"></datalist>
                    <input type="hidden" id="tenantCategoryFilterCategoryHidden">
                </div>

                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label class="filter-label" for="tenantCategoryFilterStatus" data-i18n="filter_status_label">
                        <?= htmlspecialchars(_tc('filter_status_label', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="tenantCategoryFilterStatus" class="form-control">
                        <option value=""  data-i18n="all_status">
                            <?= htmlspecialchars(_tc('all_status', 'All Status'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="1" data-i18n="active_option">
                            <?= htmlspecialchars(_tc('active_option',   'Active'),   ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="0" data-i18n="inactive_option">
                            <?= htmlspecialchars(_tc('inactive_option', 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyTenantCategoryFilters" class="btn btn-primary" data-i18n="filter_apply">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <?= htmlspecialchars(_tc('filter_apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnResetTenantCategoryFilters" class="btn btn-secondary" data-i18n="filter_reset">
                            <?= htmlspecialchars(_tc('filter_reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         DATA TABLE
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">

            <div id="tcLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading">
                    <?= htmlspecialchars(_tc('loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div id="tcEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-tags" aria-hidden="true"></i></div>
                <h3 data-i18n="empty_title">
                    <?= htmlspecialchars(_tc('empty_title', 'No Tenant Categories Found'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p data-i18n="empty_description">
                    <?= htmlspecialchars(_tc('empty_description', 'Start by adding tenant categories'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($canCreate): ?>
                <button id="btnAddTenantCategoryEmpty" class="btn btn-primary" data-i18n="add_button">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <?= htmlspecialchars(_tc('add_button', 'Add Tenant Category'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="tcError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error_title">
                    <?= htmlspecialchars(_tc('error_title', 'Error Loading Data'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p id="tcErrorMessage"></p>
                <button id="btnRetryTenantCategories" class="btn btn-primary" data-i18n="retry_button">
                    <i class="fas fa-redo" aria-hidden="true"></i>
                    <?= htmlspecialchars(_tc('retry_button', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div id="tcTableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="tenantCategoriesTable" aria-label="Tenant Categories">
                    <thead>
                        <tr>
                            <th data-i18n="table_id">ID</th>
                            <?php if ($isSuperAdmin): ?>
                            <th data-i18n="table_tenant_id">
                                <?= htmlspecialchars(_tc('table_tenant_id', 'Tenant ID'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <?php endif; ?>
                            <th data-i18n="table_tenant_name">
                                <?= htmlspecialchars(_tc('table_tenant_name', 'Tenant'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table_category_id">
                                <?= htmlspecialchars(_tc('table_category_id', 'Cat. ID'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table_category_name">
                                <?= htmlspecialchars(_tc('table_category_name', 'Category'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table_sort_order">
                                <?= htmlspecialchars(_tc('table_sort_order', 'Sort'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <?php if ($isSuperAdmin): ?>
                            <th data-i18n="table_status">
                                <?= htmlspecialchars(_tc('table_status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <?php endif; ?>
                            <th data-i18n="table_created_at">
                                <?= htmlspecialchars(_tc('table_created_at', 'Created'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table_actions">
                                <?= htmlspecialchars(_tc('table_actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tenantCategoryTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-wrapper">
            <div class="pagination-info" id="tcPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="tcPagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.TENANT_CATEGORIES_CONFIG = {
    apiUrl:          <?= json_encode($apiBase . '/categories-tenants', JSON_UNESCAPED_SLASHES) ?>,
    tenantsUrl:      <?= json_encode($apiBase . '/tenants',            JSON_UNESCAPED_SLASHES) ?>,
    categoriesUrl:   <?= json_encode($apiBase . '/categories',         JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:       <?= json_encode($csrf) ?>,
    tenantId:        <?= (int)$tenantId ?>,
    lang:            <?= json_encode($_tcSafeLang) ?>,
    dir:             <?= json_encode($dir) ?>,
    isSuperAdmin:    <?= json_encode($isSuperAdmin) ?>,
    strings:         <?= json_encode($_tcStrings, JSON_UNESCAPED_UNICODE) ?>,
    permissions: {
        canCreate: <?= json_encode($canCreate) ?>,
        canEdit:   <?= json_encode($canEdit) ?>,
        canDelete: <?= json_encode($canDelete) ?>
    }
};
</script>
<script src="/admin/assets/js/pages/tenant_categories.js?v=<?= assetVer('/admin/assets/js/pages/tenant_categories.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>