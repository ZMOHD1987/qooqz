<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_attributes_values.php
 * Vendor Attributes Values Management - Respects translations and theme from bootstrap_admin_ui.php
 */

// Load admin context and UI bootstrap
require_once __DIR__ . '/../../api/bootstrap_admin_context.php';
require_once __DIR__ . '/../../api/bootstrap_admin_ui.php';

$isInDashboard = false;
$standaloneMode = true;

// Check if running inside dashboard with ADMIN_UI_PAYLOAD
if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD)) {
    $isInDashboard = true;
    $standaloneMode = false;
}

// Initialize from ADMIN_UI_PAYLOAD or fallback to session
if (isset($ADMIN_UI_PAYLOAD)) {
    $userLang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
    $direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    $apiUrl = '/api/routes/vendor_attributes_values.php';
    $vendorsApi = '/api/routes/vendors.php';
    $attrApi = '/api/routes/attributes.php';
    $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    $theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['lang'] ?? $_SESSION['preferred_language'] ?? 'en';
    $direction = in_array(strtolower(substr($userLang, 0, 2)), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr';
    $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
    $apiUrl = '/api/routes/vendor_attributes_values.php';
    $vendorsApi = '/api/routes/vendors.php';
    $attrApi = '/api/routes/attributes.php';
    $translations = [];
    $theme = [];
}

// Helper function to get translation with fallback
function t($key, $default = '') {
    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    // Try nested keys
    $parts = explode('.', $key);
    $value = $translations;
    foreach ($parts as $part) {
        if (isset($value[$part])) {
            $value = $value[$part];
        } else {
            return $default ?: $key;
        }
    }
    return is_string($value) ? $value : $default ?: $key;
}

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('vendor_attributes_title', 'Vendor Attributes Values') ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/admin/assets/css/pages/vendor_attributes_values.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
<?php endif; ?>

<style>
    /* Component-specific styles using theme CSS variables */
    .vav-card {
        background: var(--theme-background-secondary, #1a1a1a);
        border: 1px solid var(--theme-border, #333);
        border-radius: var(--theme-card-radius, 8px);
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    }
    .vav-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--theme-border, #333);
        padding-bottom: 15px;
    }
    .vav-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--theme-primary, #3b82f6);
    }
    .vav-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
        align-items: end;
    }
    .vav-table-res {
        width: 100%;
        overflow-x: auto;
        border-radius: 4px;
    }
    .vav-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .vav-table th {
        background: var(--theme-background, #252525);
        color: var(--theme-text-secondary, #aaa);
        padding: 12px;
        text-align: start;
        border-bottom: 2px solid var(--theme-border, #333);
        font-size: 0.9rem;
    }
    .vav-table td {
        padding: 12px;
        border-bottom: 1px solid var(--theme-border, #222);
        font-size: 0.9rem;
        vertical-align: middle;
        color: var(--theme-text-primary, #fff);
    }
    .vav-table tr:hover {
        background: var(--theme-background, rgba(51, 65, 85, 0.3));
    }
    .vav-btn {
        padding: 8px 16px;
        border-radius: var(--theme-btn-radius, 4px);
        cursor: pointer;
        border: none;
        font-weight: 600;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-blue {
        background: var(--theme-primary, #2563eb);
        color: #fff;
    }
    .btn-blue:hover {
        background: var(--theme-primary-hover, #1d4ed8);
    }
    .btn-gray {
        background: var(--theme-border, #333);
        color: var(--theme-text-secondary, #ccc);
    }
    .btn-gray:hover {
        opacity: 0.8;
    }
    .vav-input {
        width: 100%;
        padding: 10px;
        background: var(--theme-background, #000);
        border: 1px solid var(--theme-border, #333);
        color: var(--theme-text-primary, #fff);
        border-radius: var(--theme-btn-radius, 4px);
        box-sizing: border-box;
        height: 42px;
    }
    .vav-input:focus {
        border-color: var(--theme-primary, #3b82f6);
        outline: none;
    }
    .select2-container--default .select2-selection--single {
        background: var(--theme-background, #000) !important;
        border: 1px solid var(--theme-border, #333) !important;
        height: 42px !important;
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--theme-text-primary, #fff) !important;
        line-height: 42px !important;
        padding-inline-start: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-dropdown {
        background: var(--theme-background-secondary, #1a1a1a) !important;
        color: var(--theme-text-primary, #fff) !important;
        border: 1px solid var(--theme-primary, #3b82f6) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    }
    .select2-search__field {
        background: var(--theme-background, #000) !important;
        color: var(--theme-text-primary, #fff) !important;
        border: 1px solid var(--theme-border, #333) !important;
        border-radius: 4px !important;
    }
    #vavFormWrap {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.85);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        padding: 15px;
        backdrop-filter: blur(4px);
    }
    .vav-modal {
        background: var(--theme-background-secondary, #1a1a1a);
        width: 100%;
        max-width: 450px;
        padding: 25px;
        border-radius: var(--theme-card-radius, 12px);
        border: 1px solid var(--theme-primary, #3b82f6);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
</style>

<div class="vav-card">
    <div class="vav-header">
        <h2 data-i18n="vendor_attributes_title"><?= t('vendor_attributes_title', 'Vendor Attributes Values') ?></h2>
        <div style="display:flex; gap:10px;">
            <button type="button" id="vavRefresh" class="vav-btn btn-gray">
                <span data-i18n="refresh"><?= t('refresh', 'Refresh') ?></span>
            </button>
            <button type="button" id="vavNew" class="vav-btn btn-blue">
                <span data-i18n="add_new"><?= t('add_new', 'Add New') ?></span>
            </button>
        </div>
    </div>

    <div class="vav-grid">
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color: var(--theme-text-secondary); margin-bottom:5px;" data-i18n="filter_vendor">
                <?= t('filter_vendor', 'Filter by Vendor') ?>
            </label>
            <select id="vavVendorFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color: var(--theme-text-secondary); margin-bottom:5px;" data-i18n="filter_attribute">
                <?= t('filter_attribute', 'Filter by Attribute') ?>
            </label>
            <select id="vavAttributeFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color: var(--theme-text-secondary); margin-bottom:5px;" data-i18n="search_values">
                <?= t('search_values', 'Search Values') ?>
            </label>
            <input type="text" id="vavSearch" class="vav-input" placeholder="<?= t('search_placeholder', 'Search...') ?>">
        </div>
        <div>
            <button type="button" id="vavResetFilters" class="vav-btn btn-gray" style="height:42px; width:100%;">
                <span data-i18n="reset_filters"><?= t('reset_filters', 'Reset') ?></span>
            </button>
        </div>
    </div>

    <div class="vav-table-res">
        <table class="vav-table">
            <thead>
                <tr>
                    <th style="width: 60px;" data-i18n="id">ID</th>
                    <th data-i18n="vendor"><?= t('vendor', 'Vendor') ?></th>
                    <th data-i18n="attribute"><?= t('attribute', 'Attribute') ?></th>
                    <th data-i18n="value"><?= t('value', 'Value') ?></th>
                    <th style="text-align:center; width: 160px;" data-i18n="actions"><?= t('actions', 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vavTbody">
                <tr><td colspan="5" style="text-align:center; padding:40px; color: var(--theme-text-secondary);">
                    <span data-i18n="loading"><?= t('loading', 'Loading...') ?></span>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vavFormWrap">
    <div class="vav-modal">
        <h3 id="vavFormTitle" style="margin-top:0; color: var(--theme-primary); margin-bottom:20px; border-bottom: 1px solid var(--theme-border); padding-bottom: 10px;" data-i18n="form_title"></h3>
        <form id="vavForm">
            <input type="hidden" name="id" id="vavId">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary); font-size:0.85rem; display:block; margin-bottom:5px;" data-i18n="vendor">
                    <?= t('vendor', 'Vendor') ?>
                </label>
                <select name="vendor_id" id="vavVendor" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary); font-size:0.85rem; display:block; margin-bottom:5px;" data-i18n="attribute">
                    <?= t('attribute', 'Attribute') ?>
                </label>
                <select name="attribute_id" id="vavAttribute" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:25px;">
                <label style="color: var(--theme-text-secondary); font-size:0.85rem; display:block; margin-bottom:5px;" data-i18n="value">
                    <?= t('value', 'Value') ?>
                </label>
                <input type="text" name="value" id="vavValue" class="vav-input" required placeholder="<?= t('value_placeholder', 'Ex: 10%, Red, Extra Large') ?>">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="vavCancel" class="vav-btn btn-gray">
                    <span data-i18n="cancel"><?= t('cancel', 'Cancel') ?></span>
                </button>
                <button type="submit" class="vav-btn btn-blue">
                    <span data-i18n="save_data"><?= t('save_data', 'Save Data') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.VAV_CONFIG = {
        apiUrl: "<?= $apiUrl ?>",
        vendorsUrl: "<?= $vendorsApi ?>",
        attrsUrl: "<?= $attrApi ?>",
        csrfToken: "<?= $csrfToken ?>",
        lang: "<?= $userLang ?>",
        direction: "<?= $direction ?>",
        translations: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
        theme: <?= json_encode($theme, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/admin_core.js"></script>
<?php endif; ?>
<script src="/admin/assets/js/pages/vendor_attributes_values.js"></script>

<?php if ($standaloneMode): ?>
</body>
</html>
<?php endif; ?>
