<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_working_hours.php
 * UI لإدارة ساعات عمل التجار
 * يعمل Standalone أو داخل Dashboard تلقائيًا
 * يدعم جميع لغات العالم عبر نظام الترجمة الخاص بك
 */

/* =======================
   اكتشاف بيئة التشغيل
======================= */
$isInDashboard = false;
$standaloneMode = true;

// القيم الافتراضية
$userLang = 'ar';
$direction = 'rtl';
$csrfToken = '';
$apiUrl = '/api/routes/vendor_working_hours.php';
$vendorsApi = '/api/routes/vendors.php';

if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD) || function_exists('is_in_admin_scope')) {
    $isInDashboard = true;
    $standaloneMode = false;

    if (isset($ADMIN_UI_PAYLOAD)) {
        // تحديد اللغة من الـ payload
        if (isset($ADMIN_UI_PAYLOAD['user_lang'])) {
            $userLang = $ADMIN_UI_PAYLOAD['user_lang'];
        } elseif (isset($ADMIN_UI_PAYLOAD['user_info']['preferred_language'])) {
            $userLang = $ADMIN_UI_PAYLOAD['user_info']['preferred_language'];
        } elseif (isset($ADMIN_UI_PAYLOAD['lang'])) {
            $userLang = $ADMIN_UI_PAYLOAD['lang'];
        }
        
        // تحديد الاتجاه من اللغة
        $rtlLangs = ['ar', 'fa', 'he', 'ur']; // أضف اللغات RTL الأخرى التي تدعمها
        $direction = in_array($userLang, $rtlLangs) ? 'rtl' : 'ltr';
        
        $csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? '';
        $apiUrls = $ADMIN_UI_PAYLOAD['apiUrls'] ?? [];
        $apiUrl = $apiUrls['vendorWorkingHours'] ?? '/api/routes/vendor_working_hours.php';
        $vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
    }
}

/* =======================
   Standalone Mode
======================= */
if ($standaloneMode) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // من الجلسة أو القيمة الافتراضية
    if (isset($_SESSION['user_lang'])) {
        $userLang = $_SESSION['user_lang'];
        $rtlLangs = ['ar', 'fa', 'he', 'ur'];
        $direction = in_array($userLang, $rtlLangs) ? 'rtl' : 'ltr';
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];

    $apiUrl = '/api/routes/vendor_working_hours.php';
    $vendorsApi = '/api/routes/vendors.php';
}

/* =======================
   محاولة تحميل الترجمة
======================= */
$translations = [];
$daysTranslations = [];

if ($standaloneMode) {
    // في الوضع المنفصل: حاول تحميل ملف الترجمة
    $langFile = $_SERVER['DOCUMENT_ROOT'] . '/languages/vendors/' . $userLang . '.json';
    
    if (file_exists($langFile)) {
        $langContent = file_get_contents($langFile);
        $langData = json_decode($langContent, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($langData['strings'])) {
            $translations = $langData['strings'];
        }
    }
} elseif ($isInDashboard && isset($ADMIN_UI_PAYLOAD['translations'])) {
    // في الداشبورد: استخدم الترجمات من الـ payload
    $translations = $ADMIN_UI_PAYLOAD['translations'];
}

// استخراج نصوص ساعات العمل وأيام الأسبوع من الترجمات
$texts = [];
$days = [];

if (!empty($translations)) {
    // البحث عن النصوص باستخدام المفاتيح المتوقعة
    $textKeys = [
        'title' => ['vendor_working_hours_title', 'title'],
        'filter_vendor' => ['filter_by_vendor', 'filter_vendor'],
        'filter_day' => ['filter_by_day', 'filter_day'],
        'reset_filters' => ['reset_filters', 'reset'],
        'refresh' => ['refresh', 'reload'],
        'add_new' => ['add_new', 'new'],
        'id' => ['id', 'ID'],
        'vendor' => ['vendor', 'merchant'],
        'day' => ['day', 'Day'],
        'open' => ['open', 'Open'],
        'close' => ['close', 'Close'],
        'closed' => ['closed', 'Closed'],
        'actions' => ['actions', 'Actions'],
        'all_days' => ['all_days', 'All Days'],
        'add_hours' => ['add_working_hours', 'Add Working Hours'],
        'edit_hours' => ['edit_working_hours', 'Edit Working Hours'],
        'select_vendor' => ['select_vendor', 'Select Vendor'],
        'open_time' => ['open_time', 'Open Time'],
        'close_time' => ['close_time', 'Close Time'],
        'cancel' => ['cancel', 'Cancel'],
        'save_data' => ['save_data', 'Save Data'],
        'loading' => ['loading', 'Loading'],
        'no_data' => ['no_data', 'No data'],
        'error_loading' => ['error_loading', 'Error loading'],
        'confirm_delete' => ['confirm_delete', 'Confirm delete'],
        'all_vendors' => ['all_vendors', 'All Vendors'],
        'edit' => ['edit', 'Edit'],
        'delete' => ['delete', 'Delete']
    ];
    
    foreach ($textKeys as $key => $possibleKeys) {
        foreach ($possibleKeys as $possibleKey) {
            if (isset($translations[$possibleKey])) {
                $texts[$key] = $translations[$possibleKey];
                break;
            }
        }
    }
    
    // أيام الأسبوع
    $daysKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    foreach ($daysKeys as $index => $dayKey) {
        if (isset($translations[$dayKey])) {
            $days[$index] = $translations[$dayKey];
        }
    }
}

// إذا لم تكن هناك أيام مترجمة، استخدم أسماء افتراضية بالإنجليزية
if (empty($days)) {
    $days = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    ];
}

/* =======================
   HTML Header (Standalone)
======================= */
if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= htmlspecialchars($userLang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($texts['title'] ?? 'Vendor Working Hours') ?></title>

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

<!-- =======================
     CONTENT
======================= -->
<div class="vwh-card">
    <div class="vwh-header">
        <h2 style="color:#2563eb;margin:0;">
            <?= htmlspecialchars($texts['title'] ?? 'Vendor Working Hours') ?>
        </h2>
        <div style="display:flex;gap:10px;">
            <button id="vwhRefresh" class="vwh-btn btn-gray"><?= htmlspecialchars($texts['refresh'] ?? 'Refresh') ?></button>
            <button id="vwhNew" class="vwh-btn btn-blue"><?= htmlspecialchars($texts['add_new'] ?? 'Add +') ?></button>
        </div>
    </div>

    <div class="vwh-grid">
        <div>
            <label style="display:block;font-size:0.75rem;color:#888;margin-bottom:5px;">
                <?= htmlspecialchars($texts['filter_vendor'] ?? 'Filter by Vendor') ?>
            </label>
            <select id="vwhVendorFilter" class="vwh-input"></select>
        </div>
        <div>
            <label style="display:block;font-size:0.75rem;color:#888;margin-bottom:5px;">
                <?= htmlspecialchars($texts['filter_day'] ?? 'Filter by Day') ?>
            </label>
            <select id="vwhDayFilter" class="vwh-input">
                <option value=""><?= htmlspecialchars($texts['all_days'] ?? 'All Days') ?></option>
                <?php foreach ($days as $k => $dayName): ?>
                <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="button" id="vwhResetFilters" class="vwh-btn btn-gray" style="height:42px;width:100%;">
                <?= htmlspecialchars($texts['reset_filters'] ?? 'Reset Filters') ?>
            </button>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="vwh-table">
            <thead>
                <tr>
                    <th style="width:60px;"><?= htmlspecialchars($texts['id'] ?? 'ID') ?></th>
                    <th><?= htmlspecialchars($texts['vendor'] ?? 'Vendor') ?></th>
                    <th><?= htmlspecialchars($texts['day'] ?? 'Day') ?></th>
                    <th><?= htmlspecialchars($texts['open'] ?? 'Open') ?></th>
                    <th><?= htmlspecialchars($texts['close'] ?? 'Close') ?></th>
                    <th style="text-align:center;"><?= htmlspecialchars($texts['closed'] ?? 'Closed') ?></th>
                    <th style="text-align:center;width:160px;"><?= htmlspecialchars($texts['actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vwhTbody">
                <tr><td colspan="7" style="text-align:center;color:#666;padding:40px;">
                    <?= htmlspecialchars($texts['loading'] ?? 'Loading...') ?>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- =======================
     MODAL FORM
======================= -->
<div id="vwhFormWrap">
    <div class="vwh-modal">
        <h3 id="vwhFormTitle" style="color:#2563eb;margin-top:0;margin-bottom:20px;border-bottom:1px solid #333;padding-bottom:10px;"></h3>
        <form id="vwhForm">
            <input type="hidden" name="id" id="vwhId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= htmlspecialchars($texts['vendor'] ?? 'Vendor') ?>
                </label>
                <select name="vendor_id" id="vwhVendor" class="vwh-input" required style="width:100%;">
                    <option value=""><?= htmlspecialchars($texts['select_vendor'] ?? 'Select Vendor') ?></option>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= htmlspecialchars($texts['day'] ?? 'Day') ?>
                </label>
                <select name="day_of_week" id="vwhDay" class="vwh-input" required style="width:100%;">
                    <?php foreach ($days as $k => $dayName): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= htmlspecialchars($texts['open_time'] ?? 'Open Time') ?>
                </label>
                <input type="time" name="open_time" id="vwhOpen" class="vwh-input">
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa;font-size:0.85rem;display:block;margin-bottom:5px;">
                    <?= htmlspecialchars($texts['close_time'] ?? 'Close Time') ?>
                </label>
                <input type="time" name="close_time" id="vwhClose" class="vwh-input">
            </div>

            <div style="margin-bottom:25px;display:flex;align-items:center;">
                <input type="checkbox" name="is_closed" id="vwhClosed" value="1" style="margin-inline-end:8px;">
                <label style="color:#aaa;font-size:0.85rem;cursor:pointer;">
                    <?= htmlspecialchars($texts['closed'] ?? 'Closed') ?>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="vwhCancel" class="vwh-btn btn-gray">
                    <?= htmlspecialchars($texts['cancel'] ?? 'Cancel') ?>
                </button>
                <button type="submit" class="vwh-btn btn-blue">
                    <?= htmlspecialchars($texts['save_data'] ?? 'Save Data') ?>
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
    isStandalone: <?= $standaloneMode ? 'true' : 'false' ?>,
    isRTL: <?= ($direction === 'rtl') ? 'true' : 'false' ?>,
    days: <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>,
    translations: <?= json_encode($texts, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/pages/vendor_working_hours.js"></script>
</body>
</html>
<?php endif; ?>
