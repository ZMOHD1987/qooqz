<?php
declare(strict_types=1);

/**
 * /admin/fragments/categories.php — Production v2.0
 * ─ لا inline styles
 * ─ لا إعادة حقن ADMIN_UI
 * ─ لا translation script مكرّر — admin_core.js يتولى الترجمة
 * ─ config موحّد في CATEGORIES_CONFIG فقط
 * ─ assetVer() بدل time()
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
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ── Permissions ──────────────────────────────────────────────
$isSA          = is_super_admin();
$canCreate     = $isSA || can('categories.manage') || can_create('categories');
$canEdit       = $isSA || can('categories.manage') || can_edit_all('categories')   || can_edit_own('categories');
$canDelete     = $isSA || can('categories.manage') || can_delete_all('categories') || can_delete_own('categories');
$canView       = $isSA || can_view_all('categories') || can_view_own('categories') || can_view_tenant('categories');

if (!$canView) {
    http_response_code(403);
    exit($isFragment ? json_encode(['error' => 'Access denied']) : 'Access denied');
}

$apiBase = '/api';

// ── Translations ─────────────────────────────────────────────
$_catStrings     = [];
$_catAllowedLangs = ['ar','en','fr','tr','ur','de','es','fa','he','hi','zh','ja','ko','pt','ru','it','nl'];
$_catSafeLang = in_array($lang, $_catAllowedLangs, true) ? $lang : 'en';
$_catLangFile = __DIR__ . '/../../languages/Categories/' . $_catSafeLang . '.json';
if (file_exists($_catLangFile)) {
    $_catJson = json_decode(file_get_contents($_catLangFile), true);
    $_catStrings = $_catJson ?? [];
}

function _cat(string $key, string $fallback = ''): string
{
    global $_catStrings;
    $parts = explode('.', $key);
    $val   = $_catStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) { $val = $val[$k]; } else { return $fallback ?: $key; }
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}

if (!function_exists('assetVer')) {
    function assetVer(string $path): string {
        static $cache = [];
        if (!isset($cache[$path])) {
            $f = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($f) ? (string)filemtime($f) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/categories.css?v=<?= assetVer('/admin/assets/css/pages/categories.css') ?>">

<meta data-page="categories"
      data-i18n-files="/languages/Categories/<?= rawurlencode($_catSafeLang) ?>.json">

<div class="page-container" id="categoriesPageContainer" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══ PAGE HEADER ════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="categories.title">
                <?= htmlspecialchars(_cat('categories.title', 'Categories'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="categories.subtitle">
                <?= htmlspecialchars(_cat('categories.subtitle', 'Manage product and content categories'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnImportExcel" class="btn btn-secondary">
                <i class="fas fa-file-excel" aria-hidden="true"></i>
                <span data-i18n="categories.import_excel">
                    <?= htmlspecialchars(_cat('categories.import_excel', 'Import Excel'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </button>
            <button id="btnAddCategory" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="categories.add_new">
                    <?= htmlspecialchars(_cat('categories.add_new', 'Add Category'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ FORM CARD ══════════════════════════════════════ -->
    <div id="categoryFormContainer" class="card cat-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title">
                <?= htmlspecialchars(_cat('form.add_title', 'Add Category'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="categoryForm" novalidate>
                <input type="hidden" id="formId"       name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="catImageId"   name="image_id">

                <div class="form-row">
                    <!-- Tenant ID -->
                    <div class="form-group">
                        <label for="catTenantId" data-i18n="form.fields.tenant_id.label">Tenant ID</label>
                        <input type="number" id="catTenantId" name="tenant_id"
                               class="form-control" value="<?= (int)$tenantId ?>"
                               <?= $isSA ? '' : 'readonly' ?> required>
                        <div id="tenantInfo" class="cat-field-hint"></div>
                    </div>
                    <!-- Name -->
                    <div class="form-group">
                        <label class="required" for="catName" data-i18n="form.fields.name.label">
                            <?= htmlspecialchars(_cat('form.fields.name.label', 'Name'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text" id="catName" name="name" class="form-control" required
                               data-i18n-placeholder="form.fields.name.placeholder"
                               placeholder="<?= htmlspecialchars(_cat('form.fields.name.placeholder', 'Enter category name'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.name.required">
                            <?= htmlspecialchars(_cat('form.fields.name.required', 'Name is required'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <!-- Slug -->
                    <div class="form-group">
                        <label class="required" for="catSlug" data-i18n="form.fields.slug.label">
                            <?= htmlspecialchars(_cat('form.fields.slug.label', 'Slug'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text" id="catSlug" name="slug" class="form-control" required
                               data-i18n-placeholder="form.fields.slug.placeholder"
                               placeholder="<?= htmlspecialchars(_cat('form.fields.slug.placeholder', 'Enter slug'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <!-- Parent -->
                    <div class="form-group">
                        <label for="catParentId" data-i18n="form.fields.parent_id.label">
                            <?= htmlspecialchars(_cat('form.fields.parent_id.label', 'Parent Category'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="catParentId" name="parent_id" class="form-control">
                            <option value="" data-i18n="form.fields.parent_id.none">
                                <?= htmlspecialchars(_cat('form.fields.parent_id.none', 'None (Root)'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Sort Order -->
                    <div class="form-group">
                        <label for="catSortOrder" data-i18n="form.fields.sort_order.label">
                            <?= htmlspecialchars(_cat('form.fields.sort_order.label', 'Sort Order'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="number" id="catSortOrder" name="sort_order" class="form-control" value="0">
                    </div>
                    <!-- Status -->
                    <div class="form-group">
                        <label for="catIsActive" data-i18n="form.fields.status.label">
                            <?= htmlspecialchars(_cat('form.fields.status.label', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="catIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active">
                                <?= htmlspecialchars(_cat('form.fields.status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="0" data-i18n="form.fields.status.inactive">
                                <?= htmlspecialchars(_cat('form.fields.status.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                    <!-- Featured -->
                    <div class="form-group">
                        <label for="catIsFeatured" data-i18n="form.fields.featured.label">
                            <?= htmlspecialchars(_cat('form.fields.featured.label', 'Featured'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="catIsFeatured" name="is_featured" class="form-control">
                            <option value="0" data-i18n="form.fields.featured.no">
                                <?= htmlspecialchars(_cat('form.fields.featured.no', 'No'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="1" data-i18n="form.fields.featured.yes">
                                <?= htmlspecialchars(_cat('form.fields.featured.yes', 'Yes'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="catDescription" data-i18n="form.fields.description.label">
                        <?= htmlspecialchars(_cat('form.fields.description.label', 'Description'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <textarea id="catDescription" name="description" class="form-control" rows="3"
                              data-i18n-placeholder="form.fields.description.placeholder"
                              placeholder="<?= htmlspecialchars(_cat('form.fields.description.placeholder', 'Enter description'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                </div>

                <!-- Image -->
                <div class="form-group">
                    <label data-i18n="form.fields.image.label">
                        <?= htmlspecialchars(_cat('form.fields.image.label', 'Image'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <div class="cat-image-row">
                        <img id="catImagePreview" src="/assets/images/no-image.png"
                             class="cat-image-preview" alt="Category image">
                        <div class="cat-image-controls">
                            <button type="button" id="catSelectImageBtn" class="btn btn-secondary">
                                <i class="fas fa-image" aria-hidden="true"></i>
                                <span data-i18n="common.select_image">
                                    <?= htmlspecialchars(_cat('common.select_image', 'Select Image'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </button>
                            <select id="catImageType" class="form-control" style="display:none;"></select>
                            <small id="catImageTypeDesc" class="cat-image-type-desc"></small>
                            <div id="catImageLinks" class="cat-image-links"></div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div class="cat-translations-section">
                    <h4 class="cat-translations-title">
                        <i class="fas fa-language" aria-hidden="true"></i>
                        <span data-i18n="form.translations.translations_title">
                            <?= htmlspecialchars(_cat('form.translations.translations_title', 'Translations'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </h4>
                    <div id="catTranslations" class="cat-translation-panels"></div>
                    <div class="form-group cat-add-lang-row">
                        <label for="catLangSelect" data-i18n="form.translations.select_lang">
                            <?= htmlspecialchars(_cat('form.translations.select_lang', 'Select Language'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <div class="cat-lang-picker">
                            <select id="catLangSelect" class="form-control">
                                <option value="" data-i18n="form.translations.choose_lang">
                                    <?= htmlspecialchars(_cat('form.translations.choose_lang', 'Choose language'), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            </select>
                            <button type="button" id="catAddLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span data-i18n="form.translations.add_translation">
                                    <?= htmlspecialchars(_cat('form.translations.add_translation', 'Add Translation'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="form.buttons.save">
                            <?= htmlspecialchars(_cat('form.buttons.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                    <button type="button" id="btnCancelForm" class="btn btn-secondary" data-i18n="form.buttons.cancel">
                        <?= htmlspecialchars(_cat('form.buttons.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteCategory"
                            class="btn btn-danger cat-delete-btn" style="display:none;">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span data-i18n="table.actions.delete">
                            <?= htmlspecialchars(_cat('table.actions.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ FILTERS ════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="searchInput" data-i18n="filters.search">
                        <?= htmlspecialchars(_cat('filters.search', 'Search'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= htmlspecialchars(_cat('filters.search_placeholder', 'Search...'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php if ($isSA): ?>
                <div class="filter-group">
                    <label class="filter-label" for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= htmlspecialchars(_cat('filters.tenant_id', 'Tenant ID'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="tenantFilter" class="form-control"
                           value="<?= (int)$tenantId ?>">
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label class="filter-label" for="parentFilter" data-i18n="filters.parent_id">
                        <?= htmlspecialchars(_cat('filters.parent_id', 'Parent'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="parentFilter" class="form-control">
                        <option value="" data-i18n="filters.parent_options.all">
                            <?= htmlspecialchars(_cat('filters.parent_options.all', 'All Parents'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="statusFilter" data-i18n="filters.status">
                        <?= htmlspecialchars(_cat('filters.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="">
                            <?= htmlspecialchars(_cat('filters.status_options.all', 'All'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="1">
                            <?= htmlspecialchars(_cat('filters.status_options.active', 'Active'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="0">
                            <?= htmlspecialchars(_cat('filters.status_options.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="featuredFilter" data-i18n="filters.featured">
                        <?= htmlspecialchars(_cat('filters.featured', 'Featured'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="featuredFilter" class="form-control">
                        <option value="">
                            <?= htmlspecialchars(_cat('filters.featured_options.all', 'All'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="1">
                            <?= htmlspecialchars(_cat('filters.featured_options.yes', 'Featured'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="0">
                            <?= htmlspecialchars(_cat('filters.featured_options.no', 'Not Featured'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyFilters" class="btn btn-primary" data-i18n="filters.apply">
                            <?= htmlspecialchars(_cat('filters.apply', 'Apply'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnResetFilters" class="btn btn-secondary" data-i18n="filters.reset">
                            <?= htmlspecialchars(_cat('filters.reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ DATA TABLE ═════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div id="catLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="categories.loading">
                    <?= htmlspecialchars(_cat('categories.loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div id="catEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
                <h3 data-i18n="table.empty.title">
                    <?= htmlspecialchars(_cat('table.empty.title', 'No Categories Found'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p data-i18n="table.empty.message">
                    <?= htmlspecialchars(_cat('table.empty.message', 'Start by adding categories'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($canCreate): ?>
                <button id="btnAddCategoryEmpty" class="btn btn-primary">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span data-i18n="table.empty.add_first">
                        <?= htmlspecialchars(_cat('table.empty.add_first', 'Add First Category'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>
                <?php endif; ?>
            </div>
            <div id="catError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="messages.error.load_failed">
                    <?= htmlspecialchars(_cat('messages.error.load_failed', 'Error Loading Data'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p id="catErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="categories.retry">
                    <?= htmlspecialchars(_cat('categories.retry', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <div id="catTableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="categoriesTable" aria-label="Categories">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">ID</th>
                            <?php if ($isSA): ?>
                            <th data-i18n="table.headers.tenant">
                                <?= htmlspecialchars(_cat('table.headers.tenant', 'Tenant'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <?php endif; ?>
                            <th data-i18n="table.headers.image">
                                <?= htmlspecialchars(_cat('table.headers.image', 'Image'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.name">
                                <?= htmlspecialchars(_cat('table.headers.name', 'Name'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.slug">
                                <?= htmlspecialchars(_cat('table.headers.slug', 'Slug'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.parent">
                                <?= htmlspecialchars(_cat('table.headers.parent', 'Parent'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.sort_order">
                                <?= htmlspecialchars(_cat('table.headers.sort_order', 'Sort'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.status">
                                <?= htmlspecialchars(_cat('table.headers.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.featured">
                                <?= htmlspecialchars(_cat('table.headers.featured', 'Featured'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.headers.actions">
                                <?= htmlspecialchars(_cat('table.headers.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="catTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="catPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="catPagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

    <!-- ═══ MEDIA STUDIO MODAL ═════════════════════════════ -->
    <div id="catMediaModal" class="cat-modal-backdrop" style="display:none;"
         role="dialog" aria-modal="true">
        <div class="cat-modal-panel cat-modal-panel--xl">
            <div class="cat-modal-header">
                <h3 data-i18n="common.select_image">Select Image</h3>
                <button type="button" id="catMediaClose" class="icon-btn" aria-label="Close">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="cat-modal-frame">
                <iframe id="catMediaFrame"
                        src=""
                        title="Media Studio"
                        loading="lazy"></iframe>
            </div>
        </div>
    </div>

    <!-- ═══ EXCEL IMPORT MODAL ═════════════════════════════ -->
    <div id="catExcelModal" class="cat-modal-backdrop" style="display:none;"
         role="dialog" aria-modal="true">
        <div class="cat-modal-panel">
            <div class="cat-modal-header">
                <h3>
                    <i class="fas fa-file-excel" aria-hidden="true"></i>
                    <span data-i18n="excel.title">
                        <?= htmlspecialchars(_cat('excel.title', 'Import Categories from Excel / CSV'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </h3>
                <button type="button" id="catExcelClose" class="icon-btn" aria-label="Close">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="cat-modal-body">
                <div class="info-hint-box">
                    <strong><i class="fas fa-info-circle"></i>
                        <span data-i18n="excel.columns_info_label">Excel Column Format:</span>
                    </strong><br>
                    <code>name</code> (required) &nbsp;|&nbsp;
                    <code>parent_name</code> &nbsp;|&nbsp;
                    <code>level</code> &nbsp;|&nbsp;
                    <code>slug</code> &nbsp;|&nbsp;
                    <code>description</code> &nbsp;|&nbsp;
                    <code>sort_order</code> &nbsp;|&nbsp;
                    <code>is_active</code> &nbsp;|&nbsp;
                    <code>is_featured</code><br>
                    <small data-i18n="excel.columns_info">
                        Plus language columns: en_name, en_slug, ar_name, ar_slug …
                    </small>
                </div>

                <div class="form-group">
                    <label class="filter-label" data-i18n="excel.choose_file">
                        <?= htmlspecialchars(_cat('excel.choose_file', 'Choose File (CSV or XLSX)'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <div class="cat-excel-file-row">
                        <input type="file" id="catExcelFileInput" accept=".xlsx,.xls,.csv"
                               class="form-control">
                        <button type="button" id="catExcelDownloadSample" class="btn btn-secondary">
                            <i class="fas fa-download" aria-hidden="true"></i>
                            <span data-i18n="excel.download_sample">Sample</span>
                        </button>
                    </div>
                </div>

                <div id="catExcelPreviewInfo" class="success-hint-box" style="display:none;">
                    <span id="catExcelPreviewText"></span>
                </div>

                <div id="catExcelProgressArea" style="display:none;">
                    <div class="cat-excel-progress-header">
                        <span id="catExcelProgressLabel" data-i18n="excel.importing">Importing…</span>
                        <span id="catExcelProgressPct">0%</span>
                    </div>
                    <div class="cat-progress-track">
                        <div id="catExcelProgressBar" class="cat-progress-bar"></div>
                    </div>
                    <pre id="catExcelProgressLog" class="cat-excel-log"></pre>
                </div>

                <div id="catExcelResultSummary" style="display:none;" class="cat-excel-result"></div>

                <div class="form-actions">
                    <button type="button" id="catExcelImportStart" class="btn btn-primary" disabled>
                        <i class="fas fa-upload" aria-hidden="true"></i>
                        <span data-i18n="excel.import">Start Import</span>
                    </button>
                    <button type="button" id="catExcelImportCancel" class="btn btn-secondary" data-i18n="excel.cancel">
                        <?= htmlspecialchars(_cat('excel.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.page-container -->

<!-- ══ Single unified config ════════════════════════════════ -->
<script>
window.CATEGORIES_CONFIG = {
    apiUrl:          <?= json_encode($apiBase . '/categories',   JSON_UNESCAPED_SLASHES) ?>,
    languagesApi:    <?= json_encode($apiBase . '/languages',    JSON_UNESCAPED_SLASHES) ?>,
    tenantsApi:      <?= json_encode($apiBase . '/tenants',      JSON_UNESCAPED_SLASHES) ?>,
    imageTypesApi:   <?= json_encode($apiBase . '/image-types',  JSON_UNESCAPED_SLASHES) ?>,
    imagesApi:       <?= json_encode($apiBase . '/images',       JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:       <?= json_encode($csrf) ?>,
    tenantId:        <?= (int)$tenantId ?>,
    lang:            <?= json_encode($_catSafeLang) ?>,
    dir:             <?= json_encode($dir) ?>,
    strings:         <?= json_encode($_catStrings, JSON_UNESCAPED_UNICODE) ?>,
    isSuperAdmin:    <?= json_encode($isSA) ?>,
    permissions: {
        canCreate:    <?= json_encode($canCreate) ?>,
        canEdit:      <?= json_encode($canEdit) ?>,
        canDelete:    <?= json_encode($canDelete) ?>
    }
};
</script>
<script src="/admin/assets/js/pages/categories.js?v=<?= assetVer('/admin/assets/js/pages/categories.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>