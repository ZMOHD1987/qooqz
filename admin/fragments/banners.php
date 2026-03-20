<?php
declare(strict_types=1);

/**
 * /admin/fragments/banners.php
 * Banners Management Fragment — rebuilt following products.php / brands.php pattern
 *
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ Images stored in unified images table (image_type_id = 9)
 * ✅ Translations: EN required, AR optional — stored in banner_translations
 * ✅ DB-driven theme CSS vars
 * ✅ Production-ready
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$canManage    = can('banners.manage') || can('banners.create');
$canViewAll   = can_view_all('banners');
$canViewOwn   = can_view_own('banners');
$canViewTenant= can_view_tenant('banners');
$canCreate    = can_create('banners');
$canEditAll   = can_edit_all('banners');
$canEditOwn   = can_edit_own('banners');
$canDeleteAll = can_delete_all('banners');
$canDeleteOwn = can_delete_own('banners');

$canView   = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit   = $canEditAll || $canEditOwn || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view banners');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
if (!function_exists('__t_banners')) {
    function __t_banners($key, $fallback = '') {
        return (function_exists('i18n_get') && ($v = i18n_get($key))) ? $v : ($fallback ?: $key);
    }
}
// Use non-conflicting alias for this fragment
function __t($key, $fallback = '') {
    return __t_banners($key, $fallback);
}

// ════════════════════════════════════════════════════════════
// API BASE + IMAGE TYPE
// ════════════════════════════════════════════════════════════
$apiBase     = '/api';
$imageTypeId = 9; // banner image type (from image_types table)

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS
// ════════════════════════════════════════════════════════════
if (!function_exists('renderBannerThemeVars')) {
    function renderBannerThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']), ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
            if ($h !== $k) echo "    --{$h}: {$v};" . PHP_EOL;
        }
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = htmlspecialchars($f['setting_key'], ENT_QUOTES);
            $sh = htmlspecialchars(str_replace('_', '-', $f['setting_key']), ENT_QUOTES);
            if (!empty($f['font_family'])) {
                $ff = htmlspecialchars($f['font_family'], ENT_QUOTES);
                echo "    --{$sk}-family: {$ff};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-family: {$ff};" . PHP_EOL;
            }
        }
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = htmlspecialchars($d['setting_key'], ENT_QUOTES);
            $dh = htmlspecialchars(str_replace('_', '-', $d['setting_key']), ENT_QUOTES);
            $dv = htmlspecialchars($d['setting_value'], ENT_QUOTES);
            echo "    --{$dk}: {$dv};" . PHP_EOL;
            if ($dh !== $dk) echo "    --{$dh}: {$dv};" . PHP_EOL;
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) echo "    --btn-{$slug}-bg: " . htmlspecialchars($b['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['text_color']))       echo "    --btn-{$slug}-color: " . htmlspecialchars($b['text_color'], ENT_QUOTES) . ';' . PHP_EOL;
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) echo "    --card-{$slug}-bg: " . htmlspecialchars($cs['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_color']))     echo "    --card-{$slug}-border: " . htmlspecialchars($cs['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_radius']))    echo "    --card-{$slug}-radius: " . htmlspecialchars((string)$cs['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}
?>
<!-- DB-driven CSS vars -->
<style id="db-theme-vars-banners">
<?php renderBannerThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/banners.css?v=<?= time() ?>">

<!-- Page Meta -->
<meta data-page="banners"
      data-i18n-files="/languages/Banners/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="bannersPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="banners.title"><?= __t('banners.title', 'Banners') ?></h1>
            <p class="page-subtitle" data-i18n="banners.subtitle"><?= __t('banners.subtitle', 'Manage banners and their translations') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddBanner" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="banners.add_new"><?= __t('banners.add_new', 'Add Banner') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="bannerFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="bannerFormTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Banner') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="bannerForm" novalidate>
                <input type="hidden" id="formId"   name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="bannerImageId"     name="image_id">
                <input type="hidden" id="bannerImageTypeId" value="<?= $imageTypeId ?>">

                <!-- Row 1: Title + Subtitle -->
                <div class="form-row">
                    <div class="form-group form-group-wide">
                        <label for="bannerTitle" class="required" data-i18n="form.fields.title.label">
                            <?= __t('form.fields.title.label', 'Title') ?>
                        </label>
                        <input type="text"
                               id="bannerTitle"
                               name="title"
                               class="form-control"
                               required
                               data-i18n-placeholder="form.fields.title.placeholder"
                               placeholder="<?= __t('form.fields.title.placeholder', 'Enter banner title') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.title.required">
                            <?= __t('form.fields.title.required', 'Title is required') ?>
                        </div>
                    </div>

                    <div class="form-group form-group-wide">
                        <label for="bannerSubtitle" data-i18n="form.fields.subtitle.label">
                            <?= __t('form.fields.subtitle.label', 'Subtitle') ?>
                        </label>
                        <input type="text"
                               id="bannerSubtitle"
                               name="subtitle"
                               class="form-control"
                               data-i18n-placeholder="form.fields.subtitle.placeholder"
                               placeholder="<?= __t('form.fields.subtitle.placeholder', 'Enter subtitle (optional)') ?>">
                    </div>
                </div>

                <!-- Row 2: Link URL + Link Text -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerLinkUrl" data-i18n="form.fields.link_url.label">
                            <?= __t('form.fields.link_url.label', 'Link URL') ?>
                        </label>
                        <input type="url"
                               id="bannerLinkUrl"
                               name="link_url"
                               class="form-control"
                               data-i18n-placeholder="form.fields.link_url.placeholder"
                               placeholder="<?= __t('form.fields.link_url.placeholder', 'https://example.com') ?>">
                    </div>

                    <div class="form-group">
                        <label for="bannerLinkText" data-i18n="form.fields.link_text.label">
                            <?= __t('form.fields.link_text.label', 'Button Text') ?>
                        </label>
                        <input type="text"
                               id="bannerLinkText"
                               name="link_text"
                               class="form-control"
                               data-i18n-placeholder="form.fields.link_text.placeholder"
                               placeholder="<?= __t('form.fields.link_text.placeholder', 'e.g. Shop Now') ?>">
                    </div>
                </div>

                <!-- Row 3: Position + Status + Sort Order -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerPosition" data-i18n="form.fields.position.label">
                            <?= __t('form.fields.position.label', 'Position') ?>
                        </label>
                        <select id="bannerPosition" name="position" class="form-control">
                            <option value="homepage_main"      data-i18n="form.fields.position.options.homepage_main"><?=      __t('form.fields.position.options.homepage_main', 'Homepage – Main') ?></option>
                            <option value="homepage_secondary" data-i18n="form.fields.position.options.homepage_secondary"><?= __t('form.fields.position.options.homepage_secondary', 'Homepage – Secondary') ?></option>
                            <option value="category_top"       data-i18n="form.fields.position.options.category_top"><?=       __t('form.fields.position.options.category_top', 'Category Top') ?></option>
                            <option value="product_sidebar"    data-i18n="form.fields.position.options.product_sidebar"><?=    __t('form.fields.position.options.product_sidebar', 'Product Sidebar') ?></option>
                            <option value="footer"             data-i18n="form.fields.position.options.footer"><?=             __t('form.fields.position.options.footer', 'Footer') ?></option>
                            <option value="popup"              data-i18n="form.fields.position.options.popup"><?=              __t('form.fields.position.options.popup', 'Popup') ?></option>
                            <option value="other"              data-i18n="form.fields.position.options.other"><?=              __t('form.fields.position.options.other', 'Other') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bannerIsActive" data-i18n="form.fields.status.label">
                            <?= __t('form.fields.status.label', 'Status') ?>
                        </label>
                        <select id="bannerIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active"><?=   __t('form.fields.status.active', 'Active') ?></option>
                            <option value="0" data-i18n="form.fields.status.inactive"><?= __t('form.fields.status.inactive', 'Inactive') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bannerSortOrder" data-i18n="form.fields.sort_order.label">
                            <?= __t('form.fields.sort_order.label', 'Sort Order') ?>
                        </label>
                        <input type="number"
                               id="bannerSortOrder"
                               name="sort_order"
                               class="form-control"
                               value="0"
                               placeholder="<?= __t('form.fields.sort_order.placeholder', '0') ?>">
                    </div>
                </div>

                <!-- Row 4: Colors + Button Style -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerBgColor" data-i18n="form.fields.background_color.label">
                            <?= __t('form.fields.background_color.label', 'Background Color') ?>
                        </label>
                        <input type="color" id="bannerBgColor" name="background_color" class="form-control form-control-color" value="#FFFFFF">
                    </div>

                    <div class="form-group">
                        <label for="bannerTextColor" data-i18n="form.fields.text_color.label">
                            <?= __t('form.fields.text_color.label', 'Text Color') ?>
                        </label>
                        <input type="color" id="bannerTextColor" name="text_color" class="form-control form-control-color" value="#000000">
                    </div>

                    <div class="form-group">
                        <label for="bannerButtonStyle" data-i18n="form.fields.button_style.label">
                            <?= __t('form.fields.button_style.label', 'Button Style') ?>
                        </label>
                        <select id="bannerButtonStyle" name="button_style" class="form-control">
                            <option value="" data-i18n="form.fields.button_style.none"><?= __t('form.fields.button_style.none', '— None —') ?></option>
                            <?php foreach ($GLOBALS['ADMIN_UI']['theme']['button_styles'] ?? [] as $bs): ?>
                            <?php
                                $bsSlug = htmlspecialchars($bs['slug'] ?? '', ENT_QUOTES);
                                $bsName = htmlspecialchars($bs['name'] ?? $bs['slug'] ?? '', ENT_QUOTES);
                            ?>
                            <option value="<?= $bsSlug ?>"><?= $bsName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 5: Dates -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="bannerStartDate" data-i18n="form.fields.start_date.label">
                            <?= __t('form.fields.start_date.label', 'Start Date') ?>
                        </label>
                        <input type="datetime-local" id="bannerStartDate" name="start_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="bannerEndDate" data-i18n="form.fields.end_date.label">
                            <?= __t('form.fields.end_date.label', 'End Date') ?>
                        </label>
                        <input type="datetime-local" id="bannerEndDate" name="end_date" class="form-control">
                    </div>
                </div>

                <!-- Image (via unified images table, image_type_id=9) -->
                <div class="form-group">
                    <label data-i18n="form.fields.image.label"><?= __t('form.fields.image.label', 'Banner Image') ?></label>
                    <div class="image-upload-section">
                        <div style="display:flex; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                            <img id="bannerImagePreview"
                                 src=""
                                 alt=""
                                 style="display:none; max-width:200px; max-height:80px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color,#263044);">
                            <div style="display:flex; flex-direction:column; gap:8px; min-width:140px;">
                                <button type="button" id="bannerSelectImageBtn" class="btn btn-secondary" data-i18n="common.select_image">
                                    <i class="fas fa-images"></i>
                                    <?= __t('common.select_image', 'Select Image') ?>
                                </button>
                                <button type="button" id="bannerRemoveImageBtn" class="btn btn-outline-danger" data-i18n="common.remove_image">
                                    <i class="fas fa-times"></i>
                                    <?= __t('common.remove_image', 'Remove Image') ?>
                                </button>
                            </div>
                            <div id="bannerImageLinks" style="font-size:0.8rem; display:flex; gap:10px; align-items:center;"></div>
                        </div>
                    </div>
                </div>

                <!-- ═══ TRANSLATIONS SECTION ═══ -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h4 class="form-section-title" data-i18n="form.translations.section_title">
                            <?= __t('form.translations.section_title', 'Translations') ?>
                        </h4>
                        <p class="form-section-desc" data-i18n="form.translations.section_desc">
                            <?= __t('form.translations.section_desc', 'English is required. Add other languages as needed.') ?>
                        </p>
                    </div>

                    <!-- English (default, required) -->
                    <div class="translation-panel" data-lang="en">
                        <div class="translation-panel-header">
                            <span class="lang-badge">EN</span>
                            <span data-i18n="form.translations.lang_en"><?= __t('form.translations.lang_en', 'English') ?></span>
                            <span class="badge badge-required">Required</span>
                        </div>
                        <div class="translation-panel-body">
                            <div class="form-group">
                                <label data-i18n-replace="form.translations.title_in_lang" data-i18n-replace-lang="English">
                                    <?= __t('form.translations.title_in_lang', 'Title in English') ?>
                                </label>
                                <input type="text"
                                       id="trans_en_title"
                                       name="trans[en][title]"
                                       class="form-control"
                                       required
                                       placeholder="<?= __t('form.fields.title.placeholder', 'Enter banner title') ?>">
                                <div class="invalid-feedback" data-i18n="messages.error.en_required">
                                    <?= __t('messages.error.en_required', 'English title is required') ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label data-i18n-replace="form.translations.subtitle_in_lang" data-i18n-replace-lang="English">
                                    <?= __t('form.translations.subtitle_in_lang', 'Subtitle in English') ?>
                                </label>
                                <input type="text"
                                       id="trans_en_subtitle"
                                       name="trans[en][subtitle]"
                                       class="form-control"
                                       placeholder="<?= __t('form.fields.subtitle.placeholder', 'Enter subtitle (optional)') ?>">
                            </div>
                            <div class="form-group">
                                <label data-i18n-replace="form.translations.link_text_in_lang" data-i18n-replace-lang="English">
                                    <?= __t('form.translations.link_text_in_lang', 'Button Text in English') ?>
                                </label>
                                <input type="text"
                                       id="trans_en_link_text"
                                       name="trans[en][link_text]"
                                       class="form-control"
                                       placeholder="<?= __t('form.fields.link_text.placeholder', 'e.g. Shop Now') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic language panels rendered by JS -->
                    <div id="bannerTranslations" class="translation-panels"></div>

                    <!-- Add language -->
                    <div class="form-group" style="margin-top:12px;">
                        <label for="bannerLangSelect" data-i18n="form.translations.select_lang">
                            <?= __t('form.translations.select_lang', 'Select Language') ?>
                        </label>
                        <div style="display:flex; gap:8px; align-items:flex-end;">
                            <select id="bannerLangSelect" class="form-control" style="flex:1;">
                                <option value=""><?= __t('form.translations.choose_language', 'Choose language') ?></option>
                            </select>
                            <button type="button" id="bannerAddLangBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                <?= __t('form.translations.add_translation', 'Add Translation') ?>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- end translations -->

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit" id="bannerSaveBtn" class="btn btn-primary">
                        <span id="bannerSaveBtnText" data-i18n="form.buttons.save">
                            <?= __t('form.buttons.save', 'Save') ?>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filters-card">
        <div class="card-body" style="padding:12px 16px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="search"
                       id="bannerSearch"
                       class="form-control"
                       style="max-width:260px;"
                       data-i18n-placeholder="filters.search_placeholder"
                       placeholder="<?= __t('filters.search_placeholder', 'Search banners...') ?>">

                <select id="bannerFilterPosition" class="form-control" style="max-width:200px;">
                    <option value="" data-i18n="filters.position_all"><?= __t('filters.position_all', 'All Positions') ?></option>
                    <option value="homepage_main"><?=      __t('form.fields.position.options.homepage_main', 'Homepage – Main') ?></option>
                    <option value="homepage_secondary"><?= __t('form.fields.position.options.homepage_secondary', 'Homepage – Secondary') ?></option>
                    <option value="category_top"><?=       __t('form.fields.position.options.category_top', 'Category Top') ?></option>
                    <option value="product_sidebar"><?=    __t('form.fields.position.options.product_sidebar', 'Product Sidebar') ?></option>
                    <option value="footer"><?=             __t('form.fields.position.options.footer', 'Footer') ?></option>
                    <option value="popup"><?=              __t('form.fields.position.options.popup', 'Popup') ?></option>
                    <option value="other"><?=              __t('form.fields.position.options.other', 'Other') ?></option>
                </select>

                <select id="bannerFilterStatus" class="form-control" style="max-width:160px;">
                    <option value=""  data-i18n="filters.status_options.all"><?=      __t('filters.status_options.all', 'All Status') ?></option>
                    <option value="1" data-i18n="filters.status_options.active"><?=   __t('filters.status_options.active', 'Active') ?></option>
                    <option value="0" data-i18n="filters.status_options.inactive"><?= __t('filters.status_options.inactive', 'Inactive') ?></option>
                </select>

                <button id="btnRefresh" class="btn btn-outline" title="<?= __t('banners.retry', 'Retry') ?>">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card table-card">
        <div class="card-body" style="padding:0; overflow:auto;">
            <table id="bannersTable" class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th data-i18n="table.headers.id"><?=         __t('table.headers.id', 'ID') ?></th>
                        <th data-i18n="table.headers.image"><?=       __t('table.headers.image', 'Image') ?></th>
                        <th data-i18n="table.headers.title"><?=       __t('table.headers.title', 'Title') ?></th>
                        <th data-i18n="table.headers.position"><?=    __t('table.headers.position', 'Position') ?></th>
                        <th data-i18n="table.headers.sort_order"><?=  __t('table.headers.sort_order', 'Sort') ?></th>
                        <th data-i18n="table.headers.status"><?=      __t('table.headers.status', 'Status') ?></th>
                        <th data-i18n="table.headers.dates"><?=       __t('table.headers.dates', 'Dates') ?></th>
                        <th data-i18n="table.headers.actions"><?=     __t('table.headers.actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody id="bannersTbody">
                    <tr>
                        <td colspan="8" style="text-align:center; padding:24px;">
                            <span data-i18n="banners.loading"><?= __t('banners.loading', 'Loading...') ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Status/toast area -->
    <div id="bannersToast" style="position:fixed; bottom:20px; inset-inline-end:20px; z-index:9999; display:none;"></div>

</div><!-- /bannersPageContainer -->

<!-- Inline Media Studio Modal -->
<div id="bannerMediaStudioOverlay" class="media-studio-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="<?= __t('common.select_image', 'Select Image') ?>">
    <div class="media-studio-container">
        <div class="media-studio-header">
            <h4><i class="fas fa-images" style="margin-inline-end:8px;color:var(--primary-color,#3b82f6);"></i><?= __t('common.select_image', 'Select Image') ?></h4>
            <button type="button" id="bannerMediaStudioClose" class="btn btn-outline btn-sm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <iframe id="bannerMediaStudioFrame"
                class="media-studio-frame"
                src="about:blank"
                title="<?= __t('common.select_image', 'Select Image') ?>">
        </iframe>
    </div>
</div>

<!-- Expose globals -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE    = '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID   = <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN  = '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID     = <?= admin_user_id() ?>;

window.USER_LANGUAGE  = '<?= addslashes($lang) ?>';
window.USER_DIRECTION = '<?= addslashes($dir) ?>';
window.CSRF_TOKEN     = '<?= addslashes($csrf) ?>';

if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'    => $canCreate,
    'canEdit'      => $canEdit,
    'canDelete'    => $canDelete,
    'canViewAll'   => $canViewAll,
    'canViewOwn'   => $canViewOwn,
    'canViewTenant'=> $canViewTenant,
    'canEditAll'   => $canEditAll,
    'canEditOwn'   => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;

window.BANNERS_CONFIG = {
    apiUrl:          '<?= $apiBase ?>/banners',
    imagesApi:       '<?= $apiBase ?>/images',
    languagesApi:    '<?= $apiBase ?>/languages',
    buttonStylesApi: '<?= $apiBase ?>/button_styles',
    imageTypeId:  <?= $imageTypeId ?>,
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<!-- Translation loader -->
<script type="text/javascript">
(function(){
    async function applyBannerTranslations() {
        try {
            const lang = window.USER_LANGUAGE || '<?= $lang ?>';
            const url  = `/languages/Banners/${encodeURIComponent(lang)}.json`;
            const res  = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const data = await res.json();
            const translations = data.strings || data;
            window.BANNERS_TRANSLATIONS = translations;
            const container = document.getElementById('bannersPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
        } catch (err) {
            console.warn('[Banners] Translation load failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyBannerTranslations);
    } else {
        setTimeout(applyBannerTranslations, 50);
    }
})();
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/banners.js?v=<?= time() ?>"></script>
<script>
(function(){
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.Banners && typeof window.Banners.init === 'function') {
            clearInterval(interval);
            try {
                var p = window.Banners.init();
                if (p && typeof p.then === 'function') p.catch(function(e){ console.error('[Banners] Init failed:', e); });
            } catch(e) { console.error('[Banners] Init threw:', e); }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Banners] Timeout waiting for module');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/banners.js?v=<?= time() ?>"></script>
<script>
(function(){
    function tryInit() {
        if (window.Banners && typeof window.Banners.init === 'function') {
            window.Banners.init().catch(function(e){ console.error('[Banners] Init failed', e); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>