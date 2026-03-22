<?php
declare(strict_types=1);

/**
 * /admin/fragments/media_studio.php — Production v2.0
 *
 * ─ المبادئ ────────────────────────────────────────────────────
 * • لا إعادة حقن :root — header.php هو المصدر الوحيد للـ CSS vars
 * • لا تحميل admin_framework.js/css مكرّر — موجودان في header.php
 * • assetVer() بدل time()
 * • admin_context helpers بدل $GLOBALS['ADMIN_UI'] مباشرة
 * • الترجمات مُحقَنة في CONFIG.strings — لا fetch منفصل
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
$canCreate = $isSuperAdmin || can('manage_media');
$canEdit   = $canCreate;
$canDelete = $canCreate;

// ── Auto-fill params ──────────────────────────────────────────
$autoFill = [
    'owner_id'      => isset($_GET['owner_id'])      ? (int)$_GET['owner_id']      : null,
    'image_type_id' => isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : null,
    'tenant_id'     => isset($_GET['tenant_id'])     ? (int)$_GET['tenant_id']     : $tenantId,
    'user_id'       => isset($_GET['user_id'])       ? (int)$_GET['user_id']       : ($user['id'] ?? null),
];

// ── Translations ─────────────────────────────────────────────
$_msStrings     = [];
$_msAllowedLangs = ['ar','en','fr','tr','ur','de','es','fa','he','hi','zh','ja','ko','pt','ru','it','nl'];
$_msSafeLang = in_array($lang, $_msAllowedLangs, true) ? $lang : 'en';
$_msLangFile = __DIR__ . '/../../languages/Media_studio/' . $_msSafeLang . '.json';
if (file_exists($_msLangFile)) {
    $_msJson = json_decode(file_get_contents($_msLangFile), true);
    $_msStrings = is_array($_msJson) ? $_msJson : [];
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

function _ms(string $key, string $fallback = ''): string {
    global $_msStrings;
    return is_string($_msStrings[$key] ?? null)
        ? $_msStrings[$key]
        : ($fallback ?: $key);
}

/*
 * ══════════════════════════════════════════════════════════
 * حالة iframe مستقلة:
 * عندما تُفتح الصفحة كـ iframe مباشر (لا AJAX) تحتاج إلى
 * CSS vars لأن header.php لا يُحمَّل. نُحقن style مرة واحدة
 * بأسلوب مبسّط يقرأ من $GLOBALS['ADMIN_UI']['theme'].
 * هذا الكود لا يتعارض مع header.php لأنه فقط في iframe mode.
 * ══════════════════════════════════════════════════════════
 */
$needsThemeVars = $isEmbedded && !$isAjax;
if ($needsThemeVars):
    $theme = $GLOBALS['ADMIN_UI']['theme'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"
      dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet"
          href="/admin/assets/css/admin_framework.css?v=<?= assetVer('/admin/assets/css/admin_framework.css') ?>">
    <style id="iframe-theme-vars">
<?php
    // نُحقن generated_css إذا متوفر — هو المصدر الأكثر اكتمالاً
    if (!empty($theme['generated_css'])) {
        echo $theme['generated_css'];
    } else {
        // fallback: بناء :root من color_settings فقط
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES, 'UTF-8');
            $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']), ENT_QUOTES, 'UTF-8');
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES, 'UTF-8');
            echo "    --{$k}: {$v};\n";
            if ($h !== $k) echo "    --{$h}: {$v};\n";
        }
        echo '}' . PHP_EOL;
    }
?>
    html, body {
        background: var(--background-main, var(--background_main, #0a0a0a));
        color: var(--text-primary, #fff);
        font-family: var(--body-font-family, system-ui, sans-serif);
        margin: 0; padding: 0;
    }
    </style>
</head>
<body dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<?php
endif; // needsThemeVars
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/media_studio.css?v=<?= assetVer('/admin/assets/css/pages/media_studio.css') ?>">

<meta data-page="media_studio"
      data-i18n-files="/languages/Media_studio/<?= rawurlencode($_msSafeLang) ?>.json">

<!-- Notifications -->
<div id="msNotifications" class="ms-notifications" aria-live="polite"></div>

<!-- Selection Bar (select mode) -->
<div id="selectionBar" class="ms-selection-bar" role="status" aria-live="polite">
    <div class="ms-selection-info">
        <i class="fas fa-images" aria-hidden="true"></i>
        <span id="selectionCount">0</span>
        <span data-i18n="selected_label"><?= htmlspecialchars(_ms('selected_label', 'selected'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <button id="btnConfirmSelectionBar" class="btn btn-primary">
        <i class="fas fa-check" aria-hidden="true"></i>
        <span data-i18n="confirm_select"><?= htmlspecialchars(_ms('confirm_select', 'Confirm Selection'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>
</div>

<!-- Studio Copy Mode Bar -->
<div id="studioCopyBar" class="ms-copy-bar" style="display:none;">
    <div class="ms-copy-info">
        <i class="fas fa-hand-pointer" aria-hidden="true"></i>
        <span data-i18n="copy_mode_hint"><?= htmlspecialchars(_ms('copy_mode_hint', 'Click an image to use it'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="ms-copy-actions">
        <button id="btnConfirmCopy" class="btn btn-primary" disabled>
            <i class="fas fa-check" aria-hidden="true"></i>
            <span data-i18n="use_image"><?= htmlspecialchars(_ms('use_image', 'Use This Image'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button id="btnCancelCopy" class="btn btn-secondary" data-i18n="cancel_button">
            <?= htmlspecialchars(_ms('cancel_button', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</div>

<div class="page-container" id="mediaStudioPage" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══ PAGE HEADER ════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="page_title">
                <?= htmlspecialchars(_ms('page_title', 'Media Studio'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="page_subtitle">
                <?= htmlspecialchars(_ms('page_subtitle', 'Manage images and media files'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <button id="btnSelectConfirm" class="btn btn-primary" style="display:none;">
                <i class="fas fa-check" aria-hidden="true"></i>
                <span data-i18n="select_button"><?= htmlspecialchars(_ms('select_button', 'Select'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <?php if ($canCreate): ?>
            <button id="btnAddImage" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="add_button"><?= htmlspecialchars(_ms('add_button', 'Add Image'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ ADD IMAGE FORM ══════════════════════════════════ -->
    <div id="addImageContainer" class="card ms-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                <span data-i18n="add_image_title"><?= htmlspecialchars(_ms('add_image_title', 'Add Image'), ENT_QUOTES, 'UTF-8') ?></span>
            </h3>
            <button type="button" id="btnCloseAddForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <!-- Tabs -->
            <div class="ms-tabs">
                <button type="button" class="ms-tab-btn active" data-tab="upload">
                    <i class="fas fa-upload" aria-hidden="true"></i>
                    <span data-i18n="tab_upload"><?= htmlspecialchars(_ms('tab_upload', 'Upload'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <button type="button" class="ms-tab-btn" data-tab="studio">
                    <i class="fas fa-photo-video" aria-hidden="true"></i>
                    <span data-i18n="tab_from_studio"><?= htmlspecialchars(_ms('tab_from_studio', 'From Studio'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>

            <!-- Upload Tab -->
            <div id="addTabUpload" class="ms-tab-content active">
                <form id="uploadForm" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" id="uploadOwnerId"          name="owner_id"      value="<?= (int)($autoFill['owner_id'] ?? 0) ?>">
                    <input type="hidden" id="uploadImageTypeIdHidden" name="image_type_id" value="<?= (int)($autoFill['image_type_id'] ?? 0) ?>">
                    <input type="hidden" id="uploadTenantId"         name="tenant_id"     value="<?= (int)($autoFill['tenant_id'] ?? $tenantId) ?>">
                    <input type="hidden" id="uploadUserId"           name="user_id"       value="<?= (int)($autoFill['user_id'] ?? 0) ?>">

                    <div class="ms-drop-zone" id="uploadDropZone">
                        <div class="ms-drop-icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></div>
                        <p class="ms-drop-text" data-i18n="drop_zone_text">
                            <?= htmlspecialchars(_ms('drop_zone_text', 'Drag & drop images here, or click to browse'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <input type="file" id="uploadImages" name="images[]"
                               class="ms-file-input" accept="image/*" multiple required>
                        <button type="button" class="btn btn-secondary"
                                onclick="document.getElementById('uploadImages').click()">
                            <i class="fas fa-folder-open" aria-hidden="true"></i>
                            <span data-i18n="browse_files"><?= htmlspecialchars(_ms('browse_files', 'Browse Files'), ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </div>
                    <div id="uploadFileList" class="ms-file-list" style="display:none;"></div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="btnUploadSave">
                            <i class="fas fa-upload" aria-hidden="true"></i>
                            <span data-i18n="upload_button"><?= htmlspecialchars(_ms('upload_button', 'Upload'), ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                        <button type="button" id="btnCancelUploadForm" class="btn btn-secondary" data-i18n="cancel_button">
                            <?= htmlspecialchars(_ms('cancel_button', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- From Studio Tab -->
            <div id="addTabStudio" class="ms-tab-content" style="display:none;">
                <div class="ms-studio-hint">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <span data-i18n="from_studio_hint">
                        <?= htmlspecialchars(_ms('from_studio_hint', 'Click "Select from Library" then click any image to use it.'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="form-actions">
                    <button type="button" id="btnEnterStudioCopy" class="btn btn-primary">
                        <i class="fas fa-images" aria-hidden="true"></i>
                        <span data-i18n="select_from_library"><?= htmlspecialchars(_ms('select_from_library', 'Select from Library'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button type="button" id="btnCancelStudioTab" class="btn btn-secondary" data-i18n="cancel_button">
                        <?= htmlspecialchars(_ms('cancel_button', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ EDIT IMAGE FORM ══════════════════════════════════ -->
    <div id="imageFormContainer" class="card ms-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="imageFormTitle" data-i18n="form_edit_title">
                <?= htmlspecialchars(_ms('form_edit_title', 'Edit Image'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseImageForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="imageForm" novalidate>
                <input type="hidden" name="id"         id="imageId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="required" for="imageOwnerId" data-i18n="label_owner_id">Owner ID</label>
                        <input type="number" id="imageOwnerId" name="owner_id"
                               class="form-control" value="<?= (int)($autoFill['owner_id'] ?? 0) ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label class="required" for="imageTypeDisplay" data-i18n="label_image_type">Image Type</label>
                        <input type="text" id="imageTypeDisplay" name="image_type_display"
                               class="form-control" list="imageTypesList"
                               placeholder="<?= htmlspecialchars(_ms('placeholder_type', 'Select or search type'), ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="off" required>
                        <datalist id="imageTypesList"></datalist>
                        <input type="hidden" name="image_type_id" id="imageTypeIdHidden"
                               value="<?= (int)($autoFill['image_type_id'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="imageTenantId" data-i18n="label_tenant_id">Tenant ID</label>
                        <input type="number" id="imageTenantId" name="tenant_id"
                               class="form-control" value="<?= (int)($autoFill['tenant_id'] ?? $tenantId) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="imageUserId" data-i18n="label_user_id">User ID</label>
                        <input type="number" id="imageUserId" name="user_id"
                               class="form-control" value="<?= (int)($autoFill['user_id'] ?? 0) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="imageFilename" data-i18n="label_filename">Filename</label>
                        <input type="text" id="imageFilename" name="filename" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="required" for="imageUrl" data-i18n="label_url">Image URL</label>
                        <input type="url" id="imageUrl" name="url" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="imageThumbUrl" data-i18n="label_thumb_url">Thumb URL</label>
                        <input type="url" id="imageThumbUrl" name="thumb_url" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="imageMimeType" data-i18n="label_mime_type">MIME Type</label>
                        <input type="text" id="imageMimeType" name="mime_type"
                               class="form-control" value="image/jpeg">
                    </div>
                    <div class="form-group">
                        <label for="imageVisibility" data-i18n="label_visibility">Visibility</label>
                        <select id="imageVisibility" name="visibility" class="form-control">
                            <option value="private" data-i18n="private_option">Private</option>
                            <option value="public"  data-i18n="public_option">Public</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="imageIsMain" data-i18n="label_is_main">Is Main</label>
                        <select id="imageIsMain" name="is_main" class="form-control">
                            <option value="0" data-i18n="no_option">No</option>
                            <option value="1" data-i18n="yes_option">Yes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="imageSortOrder" data-i18n="label_sort_order">Sort Order</label>
                        <input type="number" id="imageSortOrder" name="sort_order"
                               class="form-control" value="0" min="0">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" id="btnSaveImage" class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="save_button"><?= htmlspecialchars(_ms('save_button', 'Save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button type="button" id="btnCancelImageForm" class="btn btn-secondary" data-i18n="cancel_button">
                        <?= htmlspecialchars(_ms('cancel_button', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteImage"
                            class="btn btn-danger ms-delete-btn" style="display:none;" data-i18n="delete_button">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <?= htmlspecialchars(_ms('delete_button', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
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
                    <label class="filter-label" for="imageFilterFilename" data-i18n="filter_filename_label">Filename</label>
                    <input type="text" id="imageFilterFilename" class="form-control"
                           placeholder="<?= htmlspecialchars(_ms('filter_filename_placeholder', 'Search by filename'), ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="imageFilterType" data-i18n="filter_type_label">Image Type</label>
                    <input type="text" id="imageFilterType" class="form-control"
                           list="filterImageTypesList"
                           placeholder="<?= htmlspecialchars(_ms('all_types', 'All Types'), ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                    <datalist id="filterImageTypesList"></datalist>
                    <input type="hidden" id="imageFilterTypeHidden">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="imageFilterOwnerId" data-i18n="filter_owner_label">Owner ID</label>
                    <input type="number" id="imageFilterOwnerId" class="form-control"
                           placeholder="<?= htmlspecialchars(_ms('filter_owner_placeholder', 'Owner ID'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="imageFilterVisibility" data-i18n="filter_visibility_label">Visibility</label>
                    <select id="imageFilterVisibility" class="form-control">
                        <option value="" data-i18n="all_visibility">
                            <?= htmlspecialchars(_ms('all_visibility', 'All'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="public"  data-i18n="public_option">Public</option>
                        <option value="private" data-i18n="private_option">Private</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnApplyImageFilters" class="btn btn-primary" data-i18n="filter_apply">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <?= htmlspecialchars(_ms('filter_apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnResetImageFilters" class="btn btn-secondary" data-i18n="filter_reset">
                            <?= htmlspecialchars(_ms('filter_reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <?php if ($canDelete): ?>
                        <button id="btnDeleteSelected" class="btn btn-danger" style="display:none;" data-i18n="delete_selected">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                            <?= htmlspecialchars(_ms('delete_selected', 'Delete Selected'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ DATA TABLE ═════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div id="msLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading"><?= htmlspecialchars(_ms('loading', 'Loading images…'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div id="msEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-images" aria-hidden="true"></i></div>
                <h3 data-i18n="empty_title"><?= htmlspecialchars(_ms('empty_title', 'No Images Found'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p data-i18n="empty_description"><?= htmlspecialchars(_ms('empty_description', 'Start by adding images'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($canCreate): ?>
                <button id="btnAddImageEmpty" class="btn btn-primary" data-i18n="add_button">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <?= htmlspecialchars(_ms('add_button', 'Add Image'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>
            <div id="msError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error_title"><?= htmlspecialchars(_ms('error_title', 'Error Loading Data'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p id="msErrorMessage"></p>
                <button id="btnRetryImages" class="btn btn-primary" data-i18n="retry_button">
                    <?= htmlspecialchars(_ms('retry_button', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <div id="msTableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="imagesTable" aria-label="Media Studio">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllImages" aria-label="Select all"></th>
                            <th data-i18n="table_image">Image</th>
                            <th data-i18n="table_id">ID</th>
                            <th data-i18n="table_filename">Filename</th>
                            <th data-i18n="table_owner">Owner ID</th>
                            <th data-i18n="table_type">Type</th>
                            <th data-i18n="table_visibility">Visibility</th>
                            <th data-i18n="table_main">Main</th>
                            <th data-i18n="table_sort_order">Sort</th>
                            <th data-i18n="table_created_at">Created</th>
                            <th data-i18n="table_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="imageTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="msPaginationInfo" aria-live="polite"></div>
            <div class="pagination" id="msPagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.MEDIA_STUDIO_CONFIG = {
    apiUrl:         <?= json_encode($apiBase . '/images',      JSON_UNESCAPED_SLASHES) ?>,
    imageTypesApi:  <?= json_encode($apiBase . '/image-types', JSON_UNESCAPED_SLASHES) ?>,
    setMainApi:     <?= json_encode($apiBase . '/images/set_main', JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:      <?= json_encode($csrf) ?>,
    tenantId:       <?= (int)$tenantId ?>,
    lang:           <?= json_encode($_msSafeLang) ?>,
    dir:            <?= json_encode($dir) ?>,
    strings:        <?= json_encode($_msStrings, JSON_UNESCAPED_UNICODE) ?>,
    isSuperAdmin:   <?= json_encode($isSuperAdmin) ?>,
    autoFill:       <?= json_encode($autoFill) ?>,
    embedded:       <?= json_encode($isEmbedded) ?>,
    mode:           <?= json_encode($_GET['mode']   ?? 'manage') ?>,
    action:         <?= json_encode($_GET['action'] ?? '') ?>,
    selectionLimit: <?= (int)($_GET['limit'] ?? 1) ?>,
    permissions: {
        canCreate: <?= json_encode($canCreate) ?>,
        canEdit:   <?= json_encode($canEdit) ?>,
        canDelete: <?= json_encode($canDelete) ?>
    }
};
</script>
<script src="/admin/assets/js/pages/media_studio.js?v=<?= assetVer('/admin/assets/js/pages/media_studio.js') ?>"></script>

<?php
if ($needsThemeVars): ?>
</body></html>
<?php
elseif (!$isFragment):
    require_once __DIR__ . '/../includes/footer.php';
endif;
?>