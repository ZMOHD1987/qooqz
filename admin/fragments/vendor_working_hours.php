<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_working_hours.php
 * Vendor Working Hours Management - Respects translations and theme from bootstrap_admin_ui.php
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
    if (empty($csrfToken) && session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($csrfToken)) {
        $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrfToken;
    }
    $apiUrl = '/api/routes/vendor_working_hours.php';
    $vendorsApi = '/api/routes/vendors.php';
    $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    $theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['lang'] ?? $_SESSION['preferred_language'] ?? 'en';
    $direction = in_array(strtolower(substr($userLang, 0, 2)), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr';
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];
    $apiUrl = '/api/routes/vendor_working_hours.php';
    $vendorsApi = '/api/routes/vendors.php';
    $translations = [];
    $theme = [];
}

// Helper function to get translation with fallback
function t($key, $default = '') {
    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    // Try nested keys like 'table.vendor'
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

// Days of week - use translations or default to English
$days = [
    0 => t('sunday', 'Sunday'),
    1 => t('monday', 'Monday'),
    2 => t('tuesday', 'Tuesday'),
    3 => t('wednesday', 'Wednesday'),
    4 => t('thursday', 'Thursday'),
    5 => t('friday', 'Friday'),
    6 => t('saturday', 'Saturday')
];

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('vendor_working_hours_title', 'Vendor Working Hours') ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/admin/assets/css/pages/vendor_working_hours.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
<?php endif; ?>

<style>
    /* Component-specific styles using theme CSS variables */
    .vwh-card {
        background: var(--theme-background-secondary, #1a1a1a);
        border: 1px solid var(--theme-border, #333);
        border-radius: var(--theme-card-radius, 8px);
        padding: 20px;
    }
    .vwh-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--theme-border, #333);
        padding-bottom: 15px;
    }
    .vwh-header h2 {
        color: var(--theme-primary, #2563eb);
        margin: 0;
    }
    .vwh-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
        align-items: end;
    }
    .vwh-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .vwh-table th {
        background: var(--theme-background, #252525);
        color: var(--theme-text-secondary, #aaa);
        padding: 12px;
        text-align: start;
        border-bottom: 2px solid var(--theme-border, #333);
    }
    .vwh-table td {
        padding: 12px;
        border-bottom: 1px solid var(--theme-border, #222);
        color: var(--theme-text-primary, #fff);
    }
    .vwh-btn {
        padding: 8px 16px;
        border-radius: var(--theme-btn-radius, 4px);
        cursor: pointer;
        border: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
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
    .vwh-input {
        width: 100%;
        padding: 10px;
        background: var(--theme-background, #000);
        border: 1px solid var(--theme-border, #333);
        color: var(--theme-text-primary, #fff);
        border-radius: var(--theme-btn-radius, 4px);
        height: 42px;
        box-sizing: border-box;
    }
    .vwh-input:focus {
        border-color: var(--theme-primary, #2563eb);
        outline: none;
    }
    #vwhFormWrap {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.85);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 15px;
        backdrop-filter: blur(8px);
    }
    .vwh-modal {
        background: var(--theme-background-secondary, #1a1a1a);
        padding: 25px;
        border-radius: var(--theme-card-radius, 10px);
        border: 1px solid var(--theme-primary, #2563eb);
        width: 100%;
        max-width: 420px;
    }
    .select2-container--default .select2-selection--single {
        background: var(--theme-background, #000) !important;
        border: 1px solid var(--theme-border, #333) !important;
        height: 42px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--theme-text-primary, #fff) !important;
        line-height: 42px !important;
    }
    .select2-dropdown {
        background: var(--theme-background-secondary, #1a1a1a) !important;
        color: var(--theme-text-primary, #fff) !important;
        border: 1px solid var(--theme-primary, #2563eb) !important;
    }
</style>

<div class="vwh-card">
    <div class="vwh-header">
        <h2 data-i18n="vendor_working_hours_title"><?= t('vendor_working_hours_title', 'Vendor Working Hours') ?></h2>
        <div style="display:flex;gap:10px;">
            <button id="vwhRefresh" class="vwh-btn btn-gray">
                <span data-i18n="refresh"><?= t('refresh', 'Refresh') ?></span>
            </button>
            <button id="vwhNew" class="vwh-btn btn-blue">
                <span data-i18n="add_new"><?= t('add_new', 'Add New') ?></span>
            </button>
        </div>
    </div>

    <div class="vwh-grid">
        <div>
            <label style="display:block;font-size:0.75rem;color: var(--theme-text-secondary);margin-bottom:5px;" data-i18n="filter_vendor">
                <?= t('filter_vendor', 'Filter by Vendor') ?>
            </label>
            <select id="vwhVendorFilter" class="vwh-input"></select>
        </div>
        <div>
            <label style="display:block;font-size:0.75rem;color: var(--theme-text-secondary);margin-bottom:5px;" data-i18n="filter_day">
                <?= t('filter_day', 'Filter by Day') ?>
            </label>
            <select id="vwhDayFilter" class="vwh-input">
                <option value=""><?= t('all_days', 'All Days') ?></option>
                <?php foreach ($days as $k => $dayName): ?>
                <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="button" id="vwhResetFilters" class="vwh-btn btn-gray" style="height:42px;width:100%;">
                <span data-i18n="reset_filters"><?= t('reset_filters', 'Reset Filters') ?></span>
            </button>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="vwh-table">
            <thead>
                <tr>
                    <th style="width:60px;" data-i18n="id"><?= t('id', 'ID') ?></th>
                    <th data-i18n="vendor"><?= t('vendor', 'Vendor') ?></th>
                    <th data-i18n="day"><?= t('day', 'Day') ?></th>
                    <th data-i18n="open"><?= t('open', 'Open') ?></th>
                    <th data-i18n="close"><?= t('close', 'Close') ?></th>
                    <th style="text-align:center;" data-i18n="closed"><?= t('closed', 'Closed') ?></th>
                    <th style="text-align:center;width:160px;" data-i18n="actions"><?= t('actions', 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vwhTbody">
                <tr><td colspan="7" style="text-align:center;color: var(--theme-text-secondary);padding:40px;">
                    <span data-i18n="loading"><?= t('loading', 'Loading...') ?></span>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vwhFormWrap">
    <div class="vwh-modal">
        <h3 id="vwhFormTitle" style="color: var(--theme-primary);margin-top:0;margin-bottom:20px;border-bottom:1px solid var(--theme-border);padding-bottom:10px;" data-i18n="form_title"></h3>
        <form id="vwhForm">
            <input type="hidden" name="id" id="vwhId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary);font-size:0.85rem;display:block;margin-bottom:5px;" data-i18n="vendor">
                    <?= t('vendor', 'Vendor') ?>
                </label>
                <select name="vendor_id" id="vwhVendor" class="vwh-input" required style="width:100%;">
                    <option value=""><?= t('select_vendor', 'Select Vendor') ?></option>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary);font-size:0.85rem;display:block;margin-bottom:5px;" data-i18n="day">
                    <?= t('day', 'Day') ?>
                </label>
                <select name="day_of_week" id="vwhDay" class="vwh-input" required style="width:100%;">
                    <?php foreach ($days as $k => $dayName): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary);font-size:0.85rem;display:block;margin-bottom:5px;" data-i18n="open_time">
                    <?= t('open_time', 'Open Time') ?>
                </label>
                <input type="time" name="open_time" id="vwhOpen" class="vwh-input">
            </div>

            <div style="margin-bottom:15px;">
                <label style="color: var(--theme-text-secondary);font-size:0.85rem;display:block;margin-bottom:5px;" data-i18n="close_time">
                    <?= t('close_time', 'Close Time') ?>
                </label>
                <input type="time" name="close_time" id="vwhClose" class="vwh-input">
            </div>

            <div style="margin-bottom:25px;display:flex;align-items:center;">
                <input type="checkbox" name="is_closed" id="vwhClosed" value="1" style="margin-inline-end:8px;">
                <label for="vwhClosed" style="color: var(--theme-text-secondary);font-size:0.85rem;cursor:pointer;" data-i18n="closed">
                    <?= t('closed', 'Closed') ?>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="vwhCancel" class="vwh-btn btn-gray">
                    <span data-i18n="cancel"><?= t('cancel', 'Cancel') ?></span>
                </button>
                <button type="submit" class="vwh-btn btn-blue">
                    <span data-i18n="save_data"><?= t('save_data', 'Save Data') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.VWH_CONFIG = {
        apiUrl: "<?= $apiUrl ?>",
        vendorsUrl: "<?= $vendorsApi ?>",
        csrfToken: "<?= $csrfToken ?>",
        lang: "<?= $userLang ?>",
        direction: "<?= $direction ?>",
        translations: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
        theme: <?= json_encode($theme, JSON_UNESCAPED_UNICODE) ?>,
        days: <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/admin_core.js"></script>
<?php endif; ?>
<script src="/admin/assets/js/pages/vendor_working_hours.js"></script>

<?php if ($standaloneMode): ?>
</body>
</html>
<?php endif; ?>
