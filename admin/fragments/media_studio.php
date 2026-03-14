<?php
declare(strict_types=1);

$isFragment = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
              isset($_GET['embedded']) ||
              isset($_POST['embedded']);

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

$payload   = $GLOBALS['ADMIN_UI'] ?? [];
$user      = $payload['user']     ?? [];
$tenantId  = (int)($user['tenant_id'] ?? ($_GET['tenant_id'] ?? 1));
$lang      = $payload['lang']     ?? ($user['preferred_language'] ?? 'en');
$dir       = ($lang === 'ar') ? 'rtl' : 'ltr';
$apiBase   = $payload['api_base'] ?? '/api';
$csrf      = $payload['csrf_token'] ?? bin2hex(random_bytes(32));

// Permissions
$permissions  = $user['permissions'] ?? [];
$roles        = $user['roles']       ?? [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$canCreate    = $isSuperAdmin || in_array('manage_media', $permissions, true);
$canEdit      = $canCreate;
$canDelete    = $canCreate;

// Get params for auto-fill
$autoFill = [
    'owner_id'      => isset($_GET['owner_id'])      ? (int)$_GET['owner_id']      : null,
    'image_type_id' => isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : null,
    'tenant_id'     => isset($_GET['tenant_id'])     ? (int)$_GET['tenant_id']     : $tenantId,
    'user_id'       => isset($_GET['user_id'])       ? (int)$_GET['user_id']       : ($user['id'] ?? null),
];

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        $emitted = [];
        $lines   = [':root {'];

        $emit = function(string $k, string $v) use (&$emitted, &$lines): void {
            $ke = htmlspecialchars($k, ENT_QUOTES);
            $ve = htmlspecialchars($v, ENT_QUOTES);
            $lines[]           = "    --{$ke}: {$ve};";
            $emitted["--{$k}"] = $v;
        };

        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = $c['setting_key'];
            $h = str_replace('_', '-', $k);
            $v = $c['color_value'];
            $emit($k, $v);
            if ($h !== $k) $emit($h, $v);
        }
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
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = $d['setting_key'];
            $dh = str_replace('_', '-', $dk);
            $emit($dk, $d['setting_value']);
            if ($dh !== $dk) $emit($dh, $d['setting_value']);
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) $emit("btn-{$slug}-bg",     $b['background_color']);
            if (!empty($b['text_color']))        $emit("btn-{$slug}-color",  $b['text_color']);
            if (!empty($b['border_color']))      $emit("btn-{$slug}-border", $b['border_color']);
            if (!empty($b['border_radius']))     $emit("btn-{$slug}-radius", $b['border_radius'] . 'px');
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) $emit("card-{$slug}-bg",      $cs['background_color']);
            if (!empty($cs['border_color']))      $emit("card-{$slug}-border",  $cs['border_color']);
            if (!empty($cs['border_radius']))     $emit("card-{$slug}-radius",  $cs['border_radius'] . 'px');
            if (!empty($cs['shadow_style']))      $emit("card-{$slug}-shadow",  $cs['shadow_style']);
            if (!empty($cs['padding']))           $emit("card-{$slug}-padding", $cs['padding']);
        }

        $bgSec = $emitted['--background-secondary'] ?? $emitted['--background_secondary'] ?? null;
        $aliasDefaults = [
            '--card-bg'       => $emitted['--card-bg']      ?? $emitted['--card_bg']      ?? $bgSec ?? '#081127',
            '--input-bg'      => $emitted['--input-bg']     ?? $emitted['--input_bg']     ?? $bgSec ?? '#0b1220',
            '--thead-bg'      => $emitted['--thead-bg']     ?? $emitted['--thead_bg']     ?? $bgSec ?? '#061021',
            '--danger-color'  => $emitted['--danger-color'] ?? $emitted['--danger_color'] ?? $emitted['--error-color'] ?? '#ef4444',
            '--success-color' => $emitted['--success-color'] ?? $emitted['--success_color'] ?? '#22c55e',
        ];
        foreach ($aliasDefaults as $cssVar => $val) {
            if (!isset($emitted[$cssVar])) {
                $lines[]          = '    ' . htmlspecialchars($cssVar, ENT_QUOTES) . ': ' . htmlspecialchars($val, ENT_QUOTES) . ';';
                $emitted[$cssVar] = $val;
            }
        }

        $lines[] = '}';
        echo implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
?>
<!-- DB-driven CSS vars (colors, fonts, buttons from database) -->
<style id="db-theme-vars-media-studio">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<!-- Framework CSS (only needed in embedded/fragment mode; header.php loads it in standalone mode) -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/admin/assets/css/admin_framework.css?v=<?= time() ?>">
<?php endif; ?>
<!-- Page CSS -->
<link rel="stylesheet" href="/admin/assets/css/pages/media_studio.css?v=<?= time() ?>">

<div id="notificationsContainer" class="notifications-container"></div>

<!-- Selection Bar (embedded select mode) -->
<div id="selectionBar" class="selection-bar">
    <div class="selection-info">
        <i class="fas fa-images"></i>
        <span id="selectionCount">0</span>
        <span data-i18n="selected_label">selected</span>
    </div>
    <button id="btnConfirmSelectionBar" class="btn btn-success">
        <i class="fas fa-check"></i>
        <span data-i18n="confirm_select">Confirm Selection</span>
    </button>
</div>

<!-- Studio Copy Mode Bar (select-from-library inside Add form) -->
<div id="studioCopyBar" class="studio-copy-bar" style="display:none;">
    <div class="studio-copy-info">
        <i class="fas fa-hand-pointer"></i>
        <span data-i18n="copy_mode_hint">Click an image below to use it</span>
    </div>
    <div class="studio-copy-actions">
        <button id="btnConfirmCopy" class="btn btn-success" disabled>
            <i class="fas fa-check"></i>
            <span data-i18n="use_image">Use This Image</span>
        </button>
        <button id="btnCancelCopy" class="btn btn-outline">
            <i class="fas fa-times"></i>
            <span data-i18n="cancel_button">Cancel</span>
        </button>
    </div>
</div>

<div class="page-container" id="mediaStudioPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="page_title">Media Studio</h1>
            <p class="page-subtitle" data-i18n="page_subtitle">Manage images and media files</p>
        </div>
        <div class="page-header-actions">
            <button id="btnSelectConfirm" class="btn btn-success" style="display:none;" data-i18n="select_button">
                <i class="fas fa-check"></i> <span data-i18n="select_button">Select</span>
            </button>
            <?php if ($canCreate): ?>
            <button id="btnAddImage" class="btn btn-primary" data-i18n="add_button">
                <i class="fas fa-plus"></i> <span data-i18n="add_button">Add Image</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         ADD IMAGE FORM (Upload OR Select from Studio)
         ═══════════════════════════════════════════════ -->
    <div id="addImageContainer" class="card form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" data-i18n="add_image_title">
                <i class="fas fa-plus-circle"></i> Add Image
            </h3>
            <button type="button" id="btnCloseAddForm" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <!-- Tab Buttons -->
            <div class="add-image-tabs">
                <button type="button" class="add-tab-btn active" id="tabBtnUpload" data-tab="upload">
                    <i class="fas fa-upload"></i>
                    <span data-i18n="tab_upload">Upload</span>
                </button>
                <button type="button" class="add-tab-btn" id="tabBtnStudio" data-tab="studio">
                    <i class="fas fa-photo-video"></i>
                    <span data-i18n="tab_from_studio">From Studio</span>
                </button>
            </div>

            <!-- Tab: Upload -->
            <div id="addTabUpload" class="add-tab-content active">
                <form id="uploadForm" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" id="uploadOwnerId"        name="owner_id"      value="<?= (int)($autoFill['owner_id'] ?? 0) ?>">
                    <input type="hidden" id="uploadImageTypeIdHidden" name="image_type_id" value="<?= (int)($autoFill['image_type_id'] ?? 0) ?>">
                    <input type="hidden" id="uploadTenantId"       name="tenant_id"     value="<?= $autoFill['tenant_id'] ?? $tenantId ?>">
                    <input type="hidden" id="uploadUserId"         name="user_id"       value="<?= (int)($autoFill['user_id'] ?? 0) ?>">

                    <div class="upload-drop-zone" id="uploadDropZone">
                        <div class="upload-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p class="upload-drop-text" data-i18n="drop_zone_text">Drag & drop images here, or click to browse</p>
                        <input type="file" id="uploadImages" name="images[]" class="upload-file-input" accept="image/*" multiple required>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('uploadImages').click()">
                            <i class="fas fa-folder-open"></i>
                            <span data-i18n="browse_files">Browse Files</span>
                        </button>
                    </div>
                    <div id="uploadFileList" class="upload-file-list" style="display:none;"></div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="btnUploadSave">
                            <i class="fas fa-upload"></i>
                            <span data-i18n="upload_button">Upload</span>
                        </button>
                        <button type="button" class="btn btn-outline" id="btnCancelUploadForm">
                            <span data-i18n="cancel_button">Cancel</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab: From Studio (select existing image) -->
            <div id="addTabStudio" class="add-tab-content" style="display:none;">
                <div class="from-studio-hint">
                    <i class="fas fa-info-circle"></i>
                    <span data-i18n="from_studio_hint">Click "Select from Library" then click on any image in the gallery to use its path.</span>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="btnEnterStudioCopy">
                        <i class="fas fa-images"></i>
                        <span data-i18n="select_from_library">Select from Library</span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelStudioTab">
                        <span data-i18n="cancel_button">Cancel</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         EDIT IMAGE FORM (full details for existing images)
         ═══════════════════════════════════════════════ -->
    <div id="imageFormContainer" class="card form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form_edit_title">Edit Image</h3>
            <button type="button" id="btnCloseImageForm" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="imageForm" novalidate>
                <input type="hidden" name="id" id="imageId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="imageOwnerId" class="required" data-i18n="label_owner_id">Owner ID</label>
                        <input type="number" id="imageOwnerId" name="owner_id" class="form-control" value="<?= $autoFill['owner_id'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="imageTypeId" class="required" data-i18n="label_image_type">Image Type</label>
                        <input type="text" id="imageTypeId" name="image_type_display"
                               class="form-control" list="imageTypesList" data-i18n-placeholder="placeholder_image_type" placeholder="Select or search type" autocomplete="off" required>
                        <datalist id="imageTypesList"></datalist>
                        <input type="hidden" name="image_type_id" id="imageTypeIdHidden" value="<?= $autoFill['image_type_id'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="imageTenantId" data-i18n="label_tenant_id">Tenant ID</label>
                        <input type="number" id="imageTenantId" name="tenant_id" class="form-control" value="<?= $autoFill['tenant_id'] ?? '' ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="imageUserId" data-i18n="label_user_id">User ID</label>
                        <input type="number" id="imageUserId" name="user_id" class="form-control" value="<?= $autoFill['user_id'] ?? '' ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="imageFilename" data-i18n="label_filename">Filename</label>
                        <input type="text" id="imageFilename" name="filename" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="imageUrl" class="required" data-i18n="label_url">Image URL</label>
                        <input type="url" id="imageUrl" name="url" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="imageThumbUrl" data-i18n="label_thumb_url">Thumb URL</label>
                        <input type="url" id="imageThumbUrl" name="thumb_url" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="imageMimeType" data-i18n="label_mime_type">MIME Type</label>
                        <input type="text" id="imageMimeType" name="mime_type" class="form-control" value="image/jpeg">
                    </div>
                    <div class="form-group">
                        <label for="imageSize" data-i18n="label_size">Size (bytes)</label>
                        <input type="number" id="imageSize" name="size" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label for="imageVisibility" data-i18n="label_visibility">Visibility</label>
                        <select id="imageVisibility" name="visibility" class="form-control">
                            <option value="private" data-i18n="private_option">Private</option>
                            <option value="public" data-i18n="public_option">Public</option>
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
                        <input type="number" id="imageSortOrder" name="sort_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSaveImage">
                        <i class="fas fa-save"></i>
                        <span data-i18n="save_button">Save</span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelImageForm">
                        <span data-i18n="cancel_button">Cancel</span>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" class="btn btn-danger" id="btnDeleteImage" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="delete_button">Delete</span>
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
                    <label for="imageFilterFilename" data-i18n="filter_filename_label">Filename</label>
                    <input type="text" id="imageFilterFilename" class="form-control" 
                           data-i18n-placeholder="placeholder_filter_filename" placeholder="Search by filename" autocomplete="off">
                </div>
                <div class="filter-group">
                    <label for="imageFilterType" data-i18n="filter_type_label">Image Type</label>
                    <input type="text" id="imageFilterType" class="form-control" 
                           list="filterImageTypesList" data-i18n-placeholder="placeholder_filter_type" placeholder="All Types" autocomplete="off">
                    <datalist id="filterImageTypesList"></datalist>
                    <input type="hidden" id="imageFilterTypeHidden">
                </div>
                <div class="filter-group">
                    <label for="imageFilterOwnerId" data-i18n="filter_owner_label">Owner ID</label>
                    <input type="number" id="imageFilterOwnerId" class="form-control" 
                           placeholder="Owner ID">
                </div>
                <div class="filter-group">
                    <label for="imageFilterVisibility" data-i18n="filter_visibility_label">Visibility</label>
                    <select id="imageFilterVisibility" class="form-control">
                        <option value="" data-i18n="all_visibility">All Visibility</option>
                        <option value="public" data-i18n="public_option">Public</option>
                        <option value="private" data-i18n="private_option">Private</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnApplyImageFilters" class="btn btn-secondary" data-i18n="filter_apply">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button id="btnResetImageFilters" class="btn btn-outline" data-i18n="filter_reset">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <?php if ($canDelete): ?>
                    <button id="btnDeleteSelected" class="btn btn-danger" style="display:none;" data-i18n="delete_selected">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="imageResultsCount" class="results-count" style="display:none;">
        <i class="fas fa-images"></i> 
        <span id="imageResultsCountText"></span>
    </div>

    <!-- Grid Container -->
    <div class="card table-card">
        <div class="card-body">
            <!-- Loading State -->
            <div id="imageGridLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="loading">Loading images...</p>
            </div>

            <!-- Grid -->
            <div id="imageGridContainer" style="display:none;">
                <table class="data-table" id="imagesTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllImages"></th>
                            <th data-i18n="table_image">Image</th>
                            <th data-i18n="table_id">ID</th>
                            <th data-i18n="table_filename">Filename</th>
                            <th data-i18n="table_owner">Owner ID</th>
                            <th data-i18n="table_type">Type</th>
                            <th data-i18n="table_visibility">Visibility</th>
                            <th data-i18n="table_main">Main</th>
                            <th data-i18n="table_sort_order">Sort Order</th>
                            <th data-i18n="table_created_at">Created At</th>
                            <th data-i18n="table_actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="imageTableBody"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-wrapper" id="imagePaginationWrapper">
                <div class="pagination-info">
                    <span id="imagePaginationInfo" data-i18n="showing_results">Showing 0 to 0 of 0 results</span>
                </div>
                <div class="pagination-buttons">
                    <button id="btnPrevImagePage" class="btn btn-outline" disabled data-i18n="previous">Previous</button>
                    <button id="btnNextImagePage" class="btn btn-outline" disabled data-i18n="next">Next</button>
                </div>
            </div>

            <!-- Empty State -->
            <div id="imageEmptyState" class="empty-state" style="display:none;">
                <div class="empty-icon">🖼️</div>
                <h3 data-i18n="empty_title">No Images Found</h3>
                <p data-i18n="empty_description">Start by adding images</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="MediaStudio.add()" data-i18n="empty_add">
                    <i class="fas fa-plus"></i> Add First Image
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="imageErrorState" class="error-state" style="display:none;">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="error_title">Error Loading Data</h3>
                <p id="imageErrorMessage" data-i18n="error_message"></p>
                <button id="btnRetryImages" class="btn btn-secondary" data-i18n="retry_button">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.MEDIA_STUDIO_CONFIG = {
    apiUrl: '<?= $apiBase ?>/images',
    translationsUrl: '/languages/Media_studio/<?= addslashes($lang) ?>.json',
    csrfToken: '<?= addslashes($csrf) ?>',
    tenantId: <?= $tenantId ?>,
    lang: '<?= addslashes($lang) ?>',
    isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>,
    autoFill: <?= json_encode($autoFill) ?>,
    embedded: <?= isset($_GET['embedded']) ? 'true' : 'false' ?>,
    mode: '<?= $_GET['mode'] ?? 'manage' ?>',
    action: '<?= $_GET['action'] ?? '' ?>',
    selectionLimit: <?= (int)($_GET['limit'] ?? 1) ?>, // 0 or >1 for multi
    permissions: {
        canCreate: <?= $canCreate ? 'true' : 'false' ?>,
        canEdit: <?= $canEdit ? 'true' : 'false' ?>,
        canDelete: <?= $canDelete ? 'true' : 'false' ?>
    }
};
</script>

<!-- Load AdminFramework if embedded -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js"></script>
<?php endif; ?>

<script src="/admin/assets/js/pages/media_studio.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>