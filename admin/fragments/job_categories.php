<?php
declare(strict_types=1);

/**
 * /admin/fragments/job_categories.php
 * Complete Job Categories Management System
 * 
 * ✅ Multi-language support
 * ✅ Hierarchical category structure (parent-child)
 * ✅ Image type integration (image_types.id=11 for job categories)
 * ✅ Translation management interface
 * ✅ Advanced filtering and search
 */

// ════════════════════════════════════════════════════════════
// CONFIGURATION
// ════════════════════════════════════════════════════════════
define('JOB_CATEGORY_IMAGE_TYPE_ID', 11);

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
$dir = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$userId = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
// Support both job_categories.manage and job_categories_manage for backwards compatibility
$canManageJobCategories = can('job_categories.manage') || can('job_categories_manage') || can('job_categories.create');
$canViewAll = can_view_all('job_categories');
$canViewOwn = can_view_own('job_categories');
$canViewTenant = can_view_tenant('job_categories');
$canCreate = can_create('job_categories');
$canEditAll = can_edit_all('job_categories');
$canEditOwn = can_edit_own('job_categories');
$canDeleteAll = can_delete_all('job_categories');
$canDeleteOwn = can_delete_own('job_categories');

$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageJobCategories;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageJobCategories;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view job categories');
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
<link rel="stylesheet" href="/admin/assets/css/pages/job_categories.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Set User Language for JavaScript -->
<script>
window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIR = window.USER_DIR || '<?= addslashes($dir) ?>';
</script>

<!-- Page Meta -->
<meta data-page="job_categories"
      data-i18n-files="/languages/JobCategories/<?= rawurlencode($lang) ?>.json"
      data-image-type-id="<?= JOB_CATEGORY_IMAGE_TYPE_ID ?>">

<!-- Page Container -->
<div class="page-container" id="jobCategoriesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="job_categories.title"><?= __t('job_categories.title', 'Job Categories') ?></h1>
            <p class="page-subtitle" data-i18n="job_categories.subtitle"><?= __t('job_categories.subtitle', 'Manage job categories and classifications') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddJobCategory" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="job_categories.add_new"><?= __t('job_categories.add_new', 'Add Job Category') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="jobCategoryFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Job Category') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="jobCategoryForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="categoryTenantId" name="tenant_id" value="<?= $tenantId ?>">
                <input type="hidden" id="categoryImageUrl" name="image_url">
                <input type="hidden" id="categoryIconUrl" name="icon_url">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.basic"><?= __t('tabs.basic', 'Basic Info') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= __t('tabs.translations', 'Translations') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="media">
                        <i class="fas fa-images"></i>
                        <span data-i18n="tabs.media"><?= __t('tabs.media', 'Media') ?></span>
                    </button>
                </div>

                <!-- Tab: Basic Info -->
                <div class="tab-content active" id="tab-basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoryParent" data-i18n="form.fields.parent.label">
                                <?= __t('form.fields.parent.label', 'Parent Category') ?>
                            </label>
                            <select id="categoryParent" name="parent_id" class="form-control">
                                <option value="" data-i18n="form.fields.parent.none"><?= __t('form.fields.parent.none', 'None (Top Level)') ?></option>
                            </select>
                            <small class="form-text" data-i18n="form.fields.parent.help">
                                <?= __t('form.fields.parent.help', 'Select a parent for hierarchical structure') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="categorySlug" data-i18n="form.fields.slug.label">
                                <?= __t('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="categorySlug" name="slug" class="form-control"
                                   data-i18n-placeholder="form.fields.slug.placeholder"
                                   placeholder="<?= __t('form.fields.slug.placeholder', 'category-slug') ?>">
                            <small class="form-text" data-i18n="form.fields.slug.help">
                                <?= __t('form.fields.slug.help', 'Leave blank for auto-generation') ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="categorySortOrder" data-i18n="form.fields.sort_order.label">
                                <?= __t('form.fields.sort_order.label', 'Sort Order') ?>
                            </label>
                            <input type="number" id="categorySortOrder" name="sort_order" class="form-control" 
                                   value="0" min="0"
                                   data-i18n-placeholder="form.fields.sort_order.placeholder"
                                   placeholder="<?= __t('form.fields.sort_order.placeholder', '0') ?>">
                            <small class="form-text" data-i18n="form.fields.sort_order.help">
                                <?= __t('form.fields.sort_order.help', 'Lower numbers appear first') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="categoryIsActive" data-i18n="form.fields.is_active.label">
                                <?= __t('form.fields.is_active.label', 'Status') ?>
                            </label>
                            <select id="categoryIsActive" name="is_active" class="form-control">
                                <option value="1" data-i18n="form.fields.is_active.active"><?= __t('form.fields.is_active.active', 'Active') ?></option>
                                <option value="0" data-i18n="form.fields.is_active.inactive"><?= __t('form.fields.is_active.inactive', 'Inactive') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations">
                    <div class="translations-section">
                        <h4>
                            <i class="fas fa-language"></i>
                            <span data-i18n="form.translations.title"><?= __t('form.translations.title', 'Translations') ?></span>
                        </h4>
                        <p data-i18n="form.translations.description">
                            <?= __t('form.translations.description', 'Add translations for different languages') ?>
                        </p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="langSelect" data-i18n="form.translations.language">
                                    <?= __t('form.translations.language', 'Language') ?>
                                </label>
                                <select id="langSelect" class="form-control">
                                    <option value="" data-i18n="form.translations.choose_lang"><?= __t('form.translations.choose_lang', 'Choose language') ?></option>
                                </select>
                            </div>
                            <div class="form-group" style="display:flex; align-items:flex-end;">
                                <button type="button" id="btnAddTranslation" class="btn btn-primary" style="width:100%;">
                                    <i class="fas fa-plus"></i>
                                    <span data-i18n="form.translations.add"><?= __t('form.translations.add', 'Add Translation') ?></span>
                                </button>
                            </div>
                        </div>

                        <div id="translationsContainer" class="translation-panels"></div>
                    </div>
                </div>

                <!-- Tab: Media -->
                <div class="tab-content" id="tab-media">
                    <div class="form-group">
                        <label data-i18n="form.media.category_image"><?= __t('form.media.category_image', 'Category Image') ?></label>
                        <div class="image-upload-section">
                            <button type="button" class="btn btn-outline" id="btnSelectCategoryImage">
                                <i class="fas fa-image"></i>
                                <span data-i18n="form.media.select_image"><?= __t('form.media.select_image', 'Select Image') ?></span>
                            </button>
                            <div id="categoryImagePreview" class="image-preview"></div>
                            <small class="form-text" data-i18n="form.media.image_help">
                                <?= __t('form.media.image_help', 'Select an image from Media Studio (image_types.id=' . JOB_CATEGORY_IMAGE_TYPE_ID . ')') ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="form.media.category_icon"><?= __t('form.media.category_icon', 'Category Icon') ?></label>
                        <div class="image-upload-section">
                            <button type="button" class="btn btn-outline" id="btnSelectCategoryIcon">
                                <i class="fas fa-icons"></i>
                                <span data-i18n="form.media.select_icon"><?= __t('form.media.select_icon', 'Select Icon') ?></span>
                            </button>
                            <div id="categoryIconPreview" class="image-preview"></div>
                            <small class="form-text" data-i18n="form.media.icon_help">
                                <?= __t('form.media.icon_help', 'Select an icon from Media Studio') ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="imageTypeSelect" data-i18n="form.media.image_type">
                            <?= __t('form.media.image_type', 'Image Type') ?>
                        </label>
                        <select id="imageTypeSelect" class="form-control" disabled>
                            <option value="<?= JOB_CATEGORY_IMAGE_TYPE_ID ?>">Job Category</option>
                        </select>
                        <small id="imageTypeDesc" class="form-text" data-i18n="form.media.image_type_help">
                            <?= __t('form.media.image_type_help', 'This is automatically set to Job Category (ID: ' . JOB_CATEGORY_IMAGE_TYPE_ID . ')') ?>
                        </small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.actions.save"><?= __t('form.actions.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnCancel">
                        <i class="fas fa-times"></i>
                        <span data-i18n="form.actions.cancel"><?= __t('form.actions.cancel', 'Cancel') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Container -->
    <div id="jobCategoryListContainer" class="card">
        <div class="card-header">
            <h3 class="card-title" data-i18n="list.title"><?= __t('list.title', 'Job Categories List') ?></h3>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="filters-grid">
                <div class="form-group">
                    <label for="filterSearch" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="filterSearch" class="form-control" 
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search by name or slug...') ?>">
                </div>

                <div class="form-group">
                    <label for="filterParent" data-i18n="filters.parent">
                        <?= __t('filters.parent', 'Parent Category') ?>
                    </label>
                    <select id="filterParent" class="form-control">
                        <option value="" data-i18n="filters.all_parents"><?= __t('filters.all_parents', 'All Categories') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterStatus" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="filterStatus" class="form-control">
                        <option value="" data-i18n="filters.all_statuses"><?= __t('filters.all_statuses', 'All Statuses') ?></option>
                        <option value="1" data-i18n="filters.active"><?= __t('filters.active', 'Active') ?></option>
                        <option value="0" data-i18n="filters.inactive"><?= __t('filters.inactive', 'Inactive') ?></option>
                    </select>
                </div>

                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="button" id="btnApplyFilters" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-filter"></i>
                        <span data-i18n="filters.apply"><?= __t('filters.apply', 'Apply Filters') ?></span>
                    </button>
                </div>
            </div>

            <!-- Results Count -->
            <div id="resultsCount" class="results-count" style="margin-top:12px; display:none;">
                <i class="fas fa-info-circle"></i>
                <span></span>
            </div>

            <!-- Table -->
            <div class="table-responsive" id="catTableContainer">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id"><?= __t('table.headers.id', 'ID') ?></th>
                            <th data-i18n="table.headers.image"><?= __t('table.headers.image', 'Image') ?></th>
                            <th data-i18n="table.headers.name"><?= __t('table.headers.name', 'Name') ?></th>
                            <th data-i18n="table.headers.slug"><?= __t('table.headers.slug', 'Slug') ?></th>
                            <th data-i18n="table.headers.parent"><?= __t('table.headers.parent', 'Parent') ?></th>
                            <th data-i18n="table.headers.sort_order"><?= __t('table.headers.sort_order', 'Sort') ?></th>
                            <th data-i18n="table.headers.status"><?= __t('table.headers.status', 'Status') ?></th>
                            <?php if ($canEdit || $canDelete): ?>
                            <th data-i18n="table.headers.actions"><?= __t('table.headers.actions', 'Actions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="catTableBody">
                        <tr>
                            <td colspan="8" class="loading-state">
                                <div class="spinner"></div>
                                <p data-i18n="job_categories.loading"><?= __t('job_categories.loading', 'Loading...') ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="paginationWrapper" class="pagination-wrapper" style="display:none;">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                    <span id="paginationRange"></span>
                    <span data-i18n="pagination.of"><?= __t('pagination.of', 'of') ?></span>
                    <span id="paginationTotal"></span>
                </div>
                <div class="pagination" id="paginationButtons"></div>
            </div>
        </div>
    </div>

    <!-- Media Studio Modal -->
    <div id="mediaStudioModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:90%; height:90vh;">
            <span class="close" id="mediaStudioClose" style="font-size:2rem; cursor:pointer; float:right;">&times;</span>
            <iframe id="mediaStudioFrame" style="width:100%; height:calc(100% - 40px); border:none;"></iframe>
        </div>
    </div>

</div>

<!-- Load JavaScript -->
<script src="/admin/assets/js/pages/job_categories.js?v=<?= time() ?>"></script>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>