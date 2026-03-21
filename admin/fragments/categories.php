<?php
declare(strict_types=1);

/**
 * /admin/fragments/categories.php
 * Production Version - Updated for new admin_context.php
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

// Method 1: Using role-based permissions
$canManageCategories = can('categories.manage') || can('categories.create');

// Method 2: Using resource-based permissions (recommended for granular control)
$canViewAll = can_view_all('categories');
$canViewOwn = can_view_own('categories');
$canViewTenant = can_view_tenant('categories');
$canCreate = can_create('categories');
$canEditAll = can_edit_all('categories');
$canEditOwn = can_edit_own('categories');
$canDeleteAll = can_delete_all('categories');
$canDeleteOwn = can_delete_own('categories');

// Combined permissions for UI
$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageCategories;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageCategories;
$canDuplicate = $canCreate;

// If user has no view permission at all, deny access
if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view categories');
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

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// Generates :root CSS vars from all DB theme settings so no
// hardcoded fallback colors are ever used.
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        // Collect every emitted variable so we can fill in aliases at the end
        $emitted = [];
        $lines   = [':root {'];

        $emit = function(string $k, string $v) use (&$emitted, &$lines): void {
            $ke = htmlspecialchars($k, ENT_QUOTES);
            $ve = htmlspecialchars($v, ENT_QUOTES);
            $lines[]         = "    --{$ke}: {$ve};";
            $emitted["--{$k}"] = $v;
        };

        // Color settings
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = $c['setting_key'];
            $h = str_replace('_', '-', $k);
            $v = $c['color_value'];
            $emit($k, $v);
            if ($h !== $k) $emit($h, $v);
        }
        // Font settings
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = $f['setting_key'];
            $sh = str_replace('_', '-', $sk);
            if (!empty($f['font_family'])) {
                $emit("{$sk}-family", $f['font_family']);
                if ($sh !== $sk) $emit("{$sh}-family", $f['font_family']);
            }
            if (!empty($f['font_size'])) {
                $emit("{$sk}-size", $f['font_size']);
                if ($sh !== $sk) $emit("{$sh}-size", $f['font_size']);
            }
            if (!empty($f['font_weight'])) {
                $emit("{$sk}-weight", $f['font_weight']);
                if ($sh !== $sk) $emit("{$sh}-weight", $f['font_weight']);
            }
        }
        // Design settings (border-radius, padding, spacing, shadows…)
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = $d['setting_key'];
            $dh = str_replace('_', '-', $dk);
            $emit($dk, $d['setting_value']);
            if ($dh !== $dk) $emit($dh, $d['setting_value']);
        }
        // Button styles exposed as CSS vars
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) $emit("btn-{$slug}-bg",     $b['background_color']);
            if (!empty($b['text_color']))        $emit("btn-{$slug}-color",  $b['text_color']);
            if (!empty($b['border_color']))      $emit("btn-{$slug}-border", $b['border_color']);
            if (!empty($b['border_radius']))     $emit("btn-{$slug}-radius", $b['border_radius'] . 'px');
        }
        // Card styles exposed as CSS vars
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) $emit("card-{$slug}-bg",      $cs['background_color']);
            if (!empty($cs['border_color']))      $emit("card-{$slug}-border",  $cs['border_color']);
            if (!empty($cs['border_radius']))     $emit("card-{$slug}-radius",  $cs['border_radius'] . 'px');
            if (!empty($cs['shadow_style']))      $emit("card-{$slug}-shadow",  $cs['shadow_style']);
            if (!empty($cs['padding']))           $emit("card-{$slug}-padding", $cs['padding']);
        }

        // Shorthand alias defaults — ensure that --input-bg, --card-bg and --thead-bg
        // are always present even when the DB theme doesn't have explicit entries for
        // them.  We derive from the nearest available variable or use a safe fallback.
        $bgSec = $emitted['--background-secondary'] ?? $emitted['--background_secondary'] ?? null;
        $aliasDefaults = [
            '--card-bg'       => $emitted['--card-bg']       ?? $emitted['--card_bg']       ?? $bgSec ?? '#081127',
            '--input-bg'      => $emitted['--input-bg']      ?? $emitted['--input_bg']       ?? $bgSec ?? '#0b1220',
            '--thead-bg'      => $emitted['--thead-bg']      ?? $emitted['--thead_bg']       ?? $bgSec ?? '#061021',
            '--danger-color'  => $emitted['--danger-color']  ?? $emitted['--danger_color']   ?? $emitted['--error-color']  ?? '#ef4444',
            '--success-color' => $emitted['--success-color'] ?? $emitted['--success_color']  ?? '#22c55e',
        ];
        foreach ($aliasDefaults as $cssVar => $val) {
            if (!isset($emitted[$cssVar])) {
                $lines[]        = '    ' . htmlspecialchars($cssVar, ENT_QUOTES) . ': ' . htmlspecialchars($val, ENT_QUOTES) . ';';
                $emitted[$cssVar] = $val;
            }
        }

        $lines[] = '}';
        echo implode(PHP_EOL, $lines) . PHP_EOL;
    }
}

?>
<!-- DB-driven CSS vars (all settings, colors, fonts, cards, buttons from database) -->
<style id="db-theme-vars-categories">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<!-- Structural layout CSS (uses only var() for all visual properties) -->
<link rel="stylesheet" href="/admin/assets/css/pages/categories.css?v=<?= time() ?>">

<!-- Page Meta -->
<meta data-page="categories"
      data-assets-css="/admin/assets/css/pages/categories.css"
      data-i18n-files="/languages/Categories/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="categoriesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="categories.title"><?= __t('categories.title', 'Categories') ?></h1>
            <p class="page-subtitle" data-i18n="categories.subtitle"><?= __t('categories.subtitle', 'Manage product and content categories') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnImportExcel" class="btn btn-secondary">
                <i class="fas fa-file-excel"></i>
                <span data-i18n="categories.import_excel"><?= __t('categories.import_excel', 'Import Excel') ?></span>
            </button>
            <button id="btnAddCategory" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="categories.add_new"><?= __t('categories.add_new', 'Add Category') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="categoryFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Category') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="categoryForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="catImageId" name="image_id">

                <div class="form-row">
                    <!-- Tenant (Read-only for non-super-admin) -->
                    <div class="form-group">
                        <label for="catTenantId" data-i18n="form.fields.tenant_id.label">Tenant ID</label>
                        <input type="number" 
                               id="catTenantId" 
                               name="tenant_id" 
                               class="form-control" 
                               value="<?= $tenantId ?>" 
                               <?= is_super_admin() ? '' : 'readonly' ?>
                               required>
                        <div id="tenantInfo" style="margin-top:5px; font-size:12px;"></div>
                    </div>

                    <!-- Name -->
                    <div class="form-group">
                        <label for="catName" class="required" data-i18n="form.fields.name.label">
                            <?= __t('form.fields.name.label', 'Name') ?>
                        </label>
                        <input type="text"
                               id="catName"
                               name="name"
                               class="form-control"
                               required
                               data-i18n-placeholder="form.fields.name.placeholder"
                               placeholder="<?= __t('form.fields.name.placeholder', 'Enter category name') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.name.required">
                            <?= __t('form.fields.name.required', 'Name is required') ?>
                        </div>
                    </div>

                    <!-- Slug -->
                    <div class="form-group">
                        <label for="catSlug" class="required" data-i18n="form.fields.slug.label">
                            <?= __t('form.fields.slug.label', 'Slug') ?>
                        </label>
                        <input type="text"
                               id="catSlug"
                               name="slug"
                               class="form-control"
                               required
                               data-i18n-placeholder="form.fields.slug.placeholder"
                               placeholder="<?= __t('form.fields.slug.placeholder', 'Enter slug') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.slug.required">
                            <?= __t('form.fields.slug.required', 'Slug is required') ?>
                        </div>
                    </div>

                    <!-- Parent -->
                    <div class="form-group">
                        <label for="catParentId" data-i18n="form.fields.parent_id.label">
                            <?= __t('form.fields.parent_id.label', 'Parent Category') ?>
                        </label>
                        <select id="catParentId" name="parent_id" class="form-control">
                            <option value="" data-i18n="form.fields.parent_id.none">
                                <?= __t('form.fields.parent_id.none', 'None (Root)') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Sort Order -->
                    <div class="form-group">
                        <label for="catSortOrder" data-i18n="form.fields.sort_order.label">
                            <?= __t('form.fields.sort_order.label', 'Sort Order') ?>
                        </label>
                        <input type="number"
                               id="catSortOrder"
                               name="sort_order"
                               class="form-control"
                               value="0"
                               data-i18n-placeholder="form.fields.sort_order.placeholder"
                               placeholder="<?= __t('form.fields.sort_order.placeholder', 'Sort order') ?>">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="catIsActive" data-i18n="form.fields.status.label">
                            <?= __t('form.fields.status.label', 'Status') ?>
                        </label>
                        <select id="catIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active">
                                <?= __t('form.fields.status.active', 'Active') ?>
                            </option>
                            <option value="0" data-i18n="form.fields.status.inactive">
                                <?= __t('form.fields.status.inactive', 'Inactive') ?>
                            </option>
                        </select>
                    </div>

                    <!-- Featured -->
                    <div class="form-group">
                        <label for="catIsFeatured" data-i18n="form.fields.featured.label">
                            <?= __t('form.fields.featured.label', 'Featured') ?>
                        </label>
                        <select id="catIsFeatured" name="is_featured" class="form-control">
                            <option value="0" data-i18n="form.fields.featured.no">
                                <?= __t('form.fields.featured.no', 'No') ?>
                            </option>
                            <option value="1" data-i18n="form.fields.featured.yes">
                                <?= __t('form.fields.featured.yes', 'Yes') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="catDescription" data-i18n="form.fields.description.label">
                        <?= __t('form.fields.description.label', 'Description') ?>
                    </label>
                    <textarea id="catDescription"
                              name="description"
                              class="form-control"
                              rows="3"
                              data-i18n-placeholder="form.fields.description.placeholder"
                              placeholder="<?= __t('form.fields.description.placeholder', 'Enter description') ?>"></textarea>
                </div>

                <!-- Image with Type -->
                <div class="form-group">
                    <label data-i18n="form.fields.image.label">
                        <?= __t('form.fields.image.label', 'Image') ?>
                    </label>
                    <div class="image-upload-section">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                            <img id="catImagePreview" src="/assets/images/no-image.png" style="width:100px; height:100px; object-fit:cover; border-radius:4px;">
                            <div style="flex:1;">
                                <button type="button" id="catSelectImageBtn" class="btn btn-secondary" data-i18n="common.select_image" style="width:100%; margin-bottom:8px;">
                                    <?= __t('common.select_image', 'Select Image') ?>
                                </button>
                                <select id="catImageType" class="form-control" style="font-size:0.85rem; display:none;">
                                    <option value="">Loading image types...</option>
                                </select>
                                <small id="catImageTypeDesc" style="color:var(--text-secondary,#94a3b8); display:none; margin-top:4px;"></small>
                                <div id="catImageLinks" style="margin-top:5px; font-size:0.8rem; display:flex; gap:10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div class="translations-section" style="margin-top:20px;">
                    <h4 style="margin-bottom:12px; color:var(--text-primary,#fff); border-bottom:1px solid var(--border-color,#263044); padding-bottom:8px;">
                        <i class="fas fa-language"></i>
                        <span data-i18n="form.translations.translations_title"><?= __t('form.translations.translations_title', 'Translations') ?></span>
                    </h4>
                    <div id="catTranslations" class="translation-panels"></div>
                    <div class="form-group" style="margin-top:12px;">
                        <label for="catLangSelect" data-i18n="form.translations.select_lang"><?= __t('form.translations.select_lang', 'Select Language') ?></label>
                        <div style="display:flex; gap:8px; align-items:flex-end;">
                            <select id="catLangSelect" class="form-control" style="flex:1;">
                                <option value="" data-i18n="form.translations.choose_lang"><?= __t('form.translations.choose_lang', 'Choose language') ?></option>
                            </select>
                            <button type="button" id="catAddLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                <span data-i18n="form.translations.add_translation"><?= __t('form.translations.add_translation', 'Add Translation') ?></span>
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
                    <button type="button" id="btnDeleteCategory" class="btn btn-danger" style="display:none">
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
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= __t('filters.tenant_id', 'Tenant ID') ?>
                    </label>
                    <input type="number"
                           id="tenantFilter"
                           class="form-control"
                           value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="parentFilter" data-i18n="filters.parent_id">
                        <?= __t('filters.parent_id', 'Parent ID') ?>
                    </label>
                    <select id="parentFilter" class="form-control">
                        <option value="" data-i18n="filters.parent_options.all">
                            <?= __t('filters.parent_options.all', 'All Parents') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">
                            <?= __t('filters.status_options.all', 'All Status') ?>
                        </option>
                        <option value="1" data-i18n="filters.status_options.active">
                            <?= __t('filters.status_options.active', 'Active') ?>
                        </option>
                        <option value="0" data-i18n="filters.status_options.inactive">
                            <?= __t('filters.status_options.inactive', 'Inactive') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="featuredFilter" data-i18n="filters.featured">
                        <?= __t('filters.featured', 'Featured') ?>
                    </label>
                    <select id="featuredFilter" class="form-control">
                        <option value="" data-i18n="filters.featured_options.all">
                            <?= __t('filters.featured_options.all', 'All') ?>
                        </option>
                        <option value="1" data-i18n="filters.featured_options.yes">
                            <?= __t('filters.featured_options.yes', 'Featured') ?>
                        </option>
                        <option value="0" data-i18n="filters.featured_options.no">
                            <?= __t('filters.featured_options.no', 'Not Featured') ?>
                        </option>
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
    <div id="resultsCount" class="results-count" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
            <i class="fas fa-list"></i> 
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- Table (loading / container / empty / error) -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="categories.loading"><?= __t('categories.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= __t('table.headers.id', 'ID') ?></th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant"><?= __t('table.headers.tenant', 'Tenant') ?></th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.image"><?= __t('table.headers.image', 'Image') ?></th>
                                <th data-i18n="table.headers.name"><?= __t('table.headers.name', 'Name') ?></th>
                                <th data-i18n="table.headers.slug"><?= __t('table.headers.slug', 'Slug') ?></th>
                                <th data-i18n="table.headers.parent"><?= __t('table.headers.parent', 'Parent') ?></th>
                                <th data-i18n="table.headers.sort_order"><?= __t('table.headers.sort_order', 'Sort Order') ?></th>
                                <th data-i18n="table.headers.status"><?= __t('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.featured"><?= __t('table.headers.featured', 'Featured') ?></th>
                                <th data-i18n="table.headers.actions"><?= __t('table.headers.actions', 'Actions') ?></th>
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
                <div class="empty-icon">📁</div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Categories Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'Start by adding categories') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Categories)window.Categories.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first"><?= __t('table.empty.add_first', 'Add First Category') ?></span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="categories.retry">
                    <?= __t('categories.retry', 'Retry') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Media Studio Modal -->
    <div id="catMediaStudioModal" class="modal" style="display:none">
        <div class="modal-content">
            <span class="close" id="catMediaStudioClose">&times;</span>
            <iframe id="catMediaStudioFrame" src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= $lang ?>" style="width:100%; height:500px; border:none;"></iframe>
        </div>
    </div>

    <!-- Excel Import Modal -->
    <div id="catExcelImportModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; width:min(700px,95vw); max-height:90vh; overflow-y:auto; padding:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border-color,#263044);">
                <h3 style="margin:0; color:var(--text-primary,#fff); font-size:1.1rem;">
                    <i class="fas fa-file-excel" style="color:#22c55e; margin-right:8px;"></i>
                    <span data-i18n="excel.title"><?= __t('excel.title', 'Import Categories from Excel / CSV') ?></span>
                </h3>
                <button type="button" id="catExcelImportClose" style="background:none; border:none; color:var(--text-secondary,#94a3b8); cursor:pointer; font-size:1.2rem;">&times;</button>
            </div>
            <div style="padding:20px;">
                <!-- Instructions -->
                <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.3); border-radius:8px; padding:12px; margin-bottom:16px; font-size:0.85rem; color:var(--text-secondary,#94a3b8);">
                    <strong style="color:var(--text-primary,#fff);"><i class="fas fa-info-circle"></i> <span data-i18n="excel.columns_info_label"><?= __t('excel.columns_info_label', 'Excel Column Format:') ?></span></strong><br>
                    <code style="color:#60a5fa;">name</code> (required) &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">parent_name</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">level</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">slug</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">description</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">sort_order</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">is_active</code> &nbsp;|&nbsp;
                    <code style="color:#60a5fa;">is_featured</code><br>
                    <small data-i18n="excel.columns_info"><?= __t('excel.columns_info', 'Supported columns: name, parent_name, level, slug, description, sort_order, is_active, is_featured — plus language columns: en_name, en_slug, en_description, en_meta_title, en_meta_description, en_meta_keywords, ar_name, ar_slug, ar_description, ar_meta_title, ar_meta_description, ar_meta_keywords') ?></small>
                </div>

                <!-- File Upload -->
                <div style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:6px; color:var(--text-secondary,#94a3b8); font-size:0.85rem;" data-i18n="excel.choose_file"><?= __t('excel.choose_file', 'Choose File (CSV or XLSX)') ?></label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="file" id="catExcelFileInput" accept=".xlsx,.xls,.csv" style="flex:1; padding:8px; background:var(--input-bg,#0b1220); border:1px solid var(--border-color,#263044); border-radius:8px; color:var(--text-primary,#fff);">
                        <button type="button" id="catExcelDownloadSample" class="btn btn-outline" style="white-space:nowrap;">
                            <i class="fas fa-download"></i>
                            <span data-i18n="excel.download_sample"><?= __t('excel.download_sample', 'Download Sample') ?></span>
                        </button>
                    </div>
                </div>

                <!-- Preview Info -->
                <div id="catExcelPreviewInfo" style="display:none; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.3); border-radius:8px; padding:10px; margin-bottom:12px; font-size:0.85rem; color:var(--text-secondary,#94a3b8);">
                    <span id="catExcelPreviewText"></span>
                </div>

                <!-- Progress -->
                <div id="catExcelProgressArea" style="display:none; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:0.85rem; color:var(--text-secondary,#94a3b8);">
                        <span id="catExcelProgressLabel" data-i18n="excel.importing"><?= __t('excel.importing', 'Importing…') ?></span>
                        <span id="catExcelProgressPct">0%</span>
                    </div>
                    <div style="background:var(--border-color,#263044); border-radius:4px; height:8px; overflow:hidden;">
                        <div id="catExcelProgressBar" style="height:100%; background:var(--primary-color,#3b82f6); width:0%; transition:width 0.3s;"></div>
                    </div>
                    <pre id="catExcelProgressLog" style="margin-top:10px; max-height:200px; overflow-y:auto; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; font-size:0.75rem; color:var(--text-secondary,#94a3b8); white-space:pre-wrap;"></pre>
                </div>

                <!-- Result Summary -->
                <div id="catExcelResultSummary" style="display:none; padding:12px; border-radius:8px; font-size:0.9rem;"></div>

                <!-- Actions -->
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                    <button type="button" id="catExcelImportCancel" class="btn btn-outline">
                        <span data-i18n="excel.cancel"><?= __t('excel.cancel', 'Cancel') ?></span>
                    </button>
                    <button type="button" id="catExcelImportStart" class="btn btn-primary" disabled>
                        <i class="fas fa-upload"></i>
                        <span data-i18n="excel.import"><?= __t('excel.import', 'Start Import') ?></span>
                    </button>
                </div>
            </div>
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

// Inject ADMIN_UI with DB theme data (colors, fonts, cards, buttons, design settings)
// so admin_core.js and category JS can apply all vars without any static fallbacks.
if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

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
window.CATEGORIES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/categories',
    languagesApi: '<?= $apiBase ?>/languages',
    tenantsApi: '<?= $apiBase ?>/tenants',
    csrfToken: '<?= addslashes($csrf) ?>',
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<!-- Translation loader (runs early) -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url = `/languages/Categories/${encodeURIComponent(lang)}.json`;
            console.log('[Categories] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.CATEGORIES_TRANSLATIONS = translations;
            // apply translations to elements with data-i18n
            const container = document.getElementById('categoriesPageContainer');
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
            console.log('[Categories] Translations applied');
        } catch (err) {
            console.warn('[Categories] Translation load/apply failed:', err);
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

<script id="CATEGORIES_INITIAL_PAYLOAD" type="application/json">
<?= json_encode(['items' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]]) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/categories.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[Categories] Embedded mode - waiting for framework & module...');
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.Categories && typeof window.Categories.init === 'function') {
            clearInterval(interval);
            console.log('[Categories] Module ready - initializing...');
            try {
                const maybePromise = window.Categories.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(()=>console.log('[Categories] Initialized')).catch(e=>console.error('[Categories] Init failed', e));
                } else {
                    console.log('[Categories] Initialized (sync)');
                }
            } catch (e) {
                console.error('[Categories] Init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Categories] Timeout waiting for module. Framework present:', !!window.AdminFramework, 'Module present:', !!window.Categories);
        } else if (attempts % 10 === 0) {
            console.log('[Categories] waiting...', attempts, '/', maxAttempts);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/categories.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>