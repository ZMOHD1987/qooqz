<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_working_hours.php
 * Vendor Working Hours Management
 */

$isInDashboard = false;
$standaloneMode = true;
$translations = [];
$themeColors = [];

if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD)) {
    $isInDashboard = true;
    $standaloneMode = false;

    if (isset($ADMIN_UI_PAYLOAD)) {
        $userLang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
        $direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
        $csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? '';
        $apiUrls = $ADMIN_UI_PAYLOAD['apiUrls'] ?? [];
        $apiUrl = $apiUrls['vendorWorkingHours'] ?? '/api/routes/vendor_working_hours.php';
        $vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
        $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
        $themeColors = $ADMIN_UI_PAYLOAD['theme']['colors_map'] ?? [];
    }
}

if ($standaloneMode) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['user_lang'] ?? 'ar';
    $rtlLangs = ['ar', 'fa', 'he', 'ur'];
    $direction = in_array($userLang, $rtlLangs) ? 'rtl' : 'ltr';
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];
    $apiUrl = '/api/routes/vendor_working_hours.php';
    $vendorsApi = '/api/routes/vendors.php';
}

// Translation helper function
function t($key, $default = '') {
    global $translations;
    if (empty($translations)) return $default ?: $key;
    $keys = explode('.', $key);
    $value = $translations;
    foreach ($keys as $k) {
        if (!is_array($value) || !isset($value[$k])) {
            return $default ?: $key;
        }
        $value = $value[$k];
    }
    return is_string($value) ? $value : ($default ?: $key);
}

// Days of week
$days = [
    0 => t('days.sunday', 'Sunday'),
    1 => t('days.monday', 'Monday'),
    2 => t('days.tuesday', 'Tuesday'),
    3 => t('days.wednesday', 'Wednesday'),
    4 => t('days.thursday', 'Thursday'),
    5 => t('days.friday', 'Friday'),
    6 => t('days.saturday', 'Saturday')
];

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= htmlspecialchars($userLang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('vendor_working_hours_title', 'Vendor Working Hours') ?></title>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.vwh-scope { background:#0f0f0f;color:#eee;padding:20px;font-family:'Segoe UI',sans-serif;min-height:100vh; }
.vwh-card { background:#1a1a1a;border:1px solid #333;border-radius:8px;padding:20px; }
.vwh-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #333;padding-bottom:15px; }
.vwh-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px;align-items:end; }
.vwh-table { width:100%;border-collapse:collapse;margin-top:10px; }
.vwh-table th { background:#252525;color:#aaa;padding:12px;text-align:start;border-bottom:2px solid #333; }
.vwh-table td { padding:12px;border-bottom:1px solid #222; }
.vwh-btn { padding:8px 16px;border-radius:4px;cursor:pointer;border:none;font-weight:600;display:inline-flex;align-items:center;justify-content:center; }
.btn-blue { background:#2563eb;color:#fff; }
.btn-gray { background:#333;color:#ccc; }
.vwh-btn:hover { opacity:0.8; }
.vwh-input { width:100%;padding:10px;background:#000;border:1px solid #333;color:#fff;border-radius:4px;height:42px;box-sizing:border-box; }
.select2-container--default .select2-selection--single { background:#000 !important;border:1px solid #333 !important;height:42px !important; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color:#fff !important;line-height:42px !important; }
.select2-dropdown { background:#1a1a1a !important;color:#fff !important;border:1px solid #2563eb !important; }
#vwhFormWrap { display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:15px; }
.vwh-modal { background:#1a1a1a;padding:25px;border-radius:10px;border:1px solid #2563eb;width:100%;max-width:420px; }
</style>
</head>
<body class="vwh-scope" dir="<?= $direction ?>">
<?php endif; ?>

<div class="vwh-card">
    <div class="vwh-header">
        <h2 style="color:#2563eb;margin:0;">
            <?= t('vendor_working_hours_title', 'Vendor Working Hours') ?>
        </h2>
        <div style="display:flex;gap:10px;">
            <button id="vwhRefresh" class="vwh-btn btn-gray"><?= t('refresh', 'Refresh') ?></button>
            <button id="vwhNew" class="vwh-btn btn-blue"><?= t('add_new', 'Add +') ?></button>
        </div>
    </div>

    <div class="vwh-grid">
        <div>
            <label style="display:block;font-size:0.75rem;color:#888;margin-bottom:5px;">
                <?= t('filter_by_vendor', 'Filter by Vendor') ?>
            </label>
            <select id="vwhVendorFilter" class="vwh-input"></select>
        </div>
        <div>
            <label style="display:block;font-size:0.75rem;color:#888;margin-bottom:5px;">
                <?= t('filter_by_day', 'Filter by Day') ?>
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
                <?= t('reset_filters', 'Reset Filters') ?>
            </button>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="vwh-table">
            <thead>
                <tr>
                    <th style="width:60px;"><?= t('id', 'ID') ?></th>
                    <th><?= t('vendor', 'Vendor') ?></th>
                    <th><?= t('day', 'Day') ?></th>
                    <th><?= t('open', 'Open') ?></th>
                    <th><?= t('close', 'Close') ?></th>
                    <th style="text-align:center;"><?= t('closed', 'Closed') ?></th>
                    <th style="text-align:center;width:160px;"><?= t('actions', 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vwhTbody">
                <tr><td colspan="7" style="text-align:center;color:#666;padding:40px;">
                    <?= t('loading', 'Loading...') ?>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vwhFormWrap">
    <div class="vwh-modal">
        <h3 id="vwhFormTitle" style="color:#2563eb;margin-top:0;margin-bottom:20px;border-bottom:1px solid #333;padding-bottom:10px;"></h3>
        <form id="vwhForm">
            <input type="hidden" name="id" id="vwhId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= t('vendor', 'Vendor') ?>
                </label>
                <select name="vendor_id" id="vwhVendor" class="vwh-input" required style="width:100%;">
                    <option value=""><?= t('select_vendor', 'Select Vendor') ?></option>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= t('day', 'Day') ?>
                </label>
                <select name="day_of_week" id="vwhDay" class="vwh-input" required style="width:100%;">
                    <?php foreach ($days as $k => $dayName): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= t('open_time', 'Open Time') ?>
                </label>
                <input type="time" name="open_time" id="vwhOpen" class="vwh-input">
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= t('close_time', 'Close Time') ?>
                </label>
                <input type="time" name="close_time" id="vwhClose" class="vwh-input">
            </div>

            <div style="margin-bottom:25px;display:flex;align-items:center;">
                <input type="checkbox" name="is_closed" id="vwhClosed" value="1" style="margin-inline-end:8px;">
                <label for="vwhClosed" style="color:#aaa;font-size:0.85rem;cursor:pointer;">
                    <?= t('closed', 'Closed') ?>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="vwhCancel" class="vwh-btn btn-gray">
                    <?= t('cancel', 'Cancel') ?>
                </button>
                <button type="submit" class="vwh-btn btn-blue">
                    <?= t('save_data', 'Save Data') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =======================
     JS CONFIG
======================= -->
<script>
window.VWH_CONFIG = {
    apiUrl: "<?= $apiUrl ?>",
    vendorsUrl: "<?= $vendorsApi ?>",
    csrfToken: "<?= $csrfToken ?>",
    lang: "<?= $userLang ?>",
    direction: "<?= $direction ?>",
    translations: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
    themeColors: <?= json_encode($themeColors, JSON_UNESCAPED_UNICODE) ?>,
    days: <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/pages/vendor_working_hours.js"></script>
</body>
</html>
<?php endif; ?>
