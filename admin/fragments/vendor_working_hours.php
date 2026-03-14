<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_working_hours.php
 * UI لإدارة ساعات عمل التجار
 * يعتمد 100% على الترجمات من ملفات JSON وإعدادات القوالب من الجداول
 */

/* =======================
   اكتشاف بيئة التشغيل
======================= */
$isInDashboard = false;
$standaloneMode = true;

// القيم الافتراضية
$userLang = 'en';
$direction = 'ltr';
$csrfToken = '';
$apiUrl = '/api/routes/vendor_working_hours.php';
$vendorsApi = '/api/routes/vendors.php';
$translations = [];
$theme = [];
$themeMap = [];

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
        $rtlLangs = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];
        $direction = in_array(strtolower(substr($userLang, 0, 2)), $rtlLangs) ? 'rtl' : 'ltr';
        
        $csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? '';
        $apiUrls = $ADMIN_UI_PAYLOAD['apiUrls'] ?? [];
        $apiUrl = $apiUrls['vendorWorkingHours'] ?? '/api/routes/vendor_working_hours.php';
        $vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
        
        // الحصول على الترجمات
        $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
        
        // الحصول على إعدادات القالب
        $theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
        if (isset($theme['colors_map']) && is_array($theme['colors_map'])) {
            $themeMap = $theme['colors_map'];
        }
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
        $rtlLangs = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];
        $direction = in_array(strtolower(substr($userLang, 0, 2)), $rtlLangs) ? 'rtl' : 'ltr';
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];

    $apiUrl = '/api/routes/vendor_working_hours.php';
    $vendorsApi = '/api/routes/vendors.php';
    
    // محاولة تحميل الترجمات من ملف JSON
    $langFile = $_SERVER['DOCUMENT_ROOT'] . '/languages/vendors/' . $userLang . '.json';
    if (file_exists($langFile)) {
        $langContent = file_get_contents($langFile);
        $langData = json_decode($langContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $translations = isset($langData['strings']) ? $langData['strings'] : $langData;
        }
    }
    
    // تحميل إعدادات القالب الافتراضية
    $theme = [
        'colors_map' => [
            'primary' => '#2563eb',
            'secondary' => '#3b82f6',
            'background' => '#0f0f0f',
            'card-background' => '#1a1a1a',
            'text-primary' => '#ffffff',
            'text-secondary' => '#cccccc',
            'border' => '#333333',
            'danger' => '#450a0a',
            'danger-text' => '#f87171'
        ]
    ];
    $themeMap = $theme['colors_map'] ?? [];
}

/* =======================
   استخراج النصوص من الترجمات
======================= */
$texts = [
    'title' => $translations['vendor_working_hours_title'] ?? $translations['title'] ?? 'Vendor Working Hours',
    'filter_vendor' => $translations['filter_by_vendor'] ?? $translations['filter_vendor'] ?? 'Filter by Vendor',
    'filter_day' => $translations['filter_by_day'] ?? $translations['filter_day'] ?? 'Filter by Day',
    'reset_filters' => $translations['reset_filters'] ?? $translations['reset'] ?? 'Reset Filters',
    'refresh' => $translations['refresh'] ?? $translations['reload'] ?? 'Refresh',
    'add_new' => $translations['add_new'] ?? $translations['new'] ?? 'Add New',
    'id' => $translations['id'] ?? 'ID',
    'vendor' => $translations['vendor'] ?? $translations['merchant'] ?? 'Vendor',
    'day' => $translations['day'] ?? 'Day',
    'open' => $translations['open'] ?? 'Open',
    'close' => $translations['close'] ?? 'Close',
    'closed' => $translations['closed'] ?? 'Closed',
    'actions' => $translations['actions'] ?? 'Actions',
    'all_days' => $translations['all_days'] ?? 'All Days',
    'add_hours' => $translations['add_working_hours'] ?? 'Add Working Hours',
    'edit_hours' => $translations['edit_working_hours'] ?? 'Edit Working Hours',
    'select_vendor' => $translations['select_vendor'] ?? 'Select Vendor',
    'open_time' => $translations['open_time'] ?? 'Open Time',
    'close_time' => $translations['close_time'] ?? 'Close Time',
    'cancel' => $translations['cancel'] ?? 'Cancel',
    'save_data' => $translations['save_data'] ?? 'Save Data',
    'loading' => $translations['loading'] ?? 'Loading',
    'no_data' => $translations['no_data'] ?? 'No data',
    'error_loading' => $translations['error_loading'] ?? 'Error loading',
    'confirm_delete' => $translations['confirm_delete'] ?? 'Confirm delete',
    'all_vendors' => $translations['all_vendors'] ?? 'All Vendors',
    'edit' => $translations['edit'] ?? 'Edit',
    'delete' => $translations['delete'] ?? 'Delete'
];

// أيام الأسبوع من الترجمات
$days = [];
$dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
foreach ($dayKeys as $index => $key) {
    $days[$index] = $translations[$key] ?? ucfirst($key);
}

/* =======================
   استخراج ألوان القالب
======================= */
$colors = [
    'primary' => $themeMap['primary'] ?? '#2563eb',
    'secondary' => $themeMap['secondary'] ?? '#3b82f6',
    'background' => $themeMap['background'] ?? '#0f0f0f',
    'cardBg' => $themeMap['card-background'] ?? $themeMap['background-card'] ?? '#1a1a1a',
    'textPrimary' => $themeMap['text-primary'] ?? $themeMap['text'] ?? '#ffffff',
    'textSecondary' => $themeMap['text-secondary'] ?? '#cccccc',
    'border' => $themeMap['border'] ?? '#333333',
    'danger' => $themeMap['danger'] ?? '#450a0a',
    'dangerText' => $themeMap['danger-text'] ?? '#f87171'
];

/* =======================
   HTML Header (Standalone)
======================= */
if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= htmlspecialchars($userLang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($texts['title']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.vwh-scope {
    background: <?= $colors['background'] ?>;
    color: <?= $colors['textPrimary'] ?>;
    padding: 20px;
    font-family: 'Segoe UI', sans-serif;
    min-height: 100vh;
}
.vwh-card {
    background: <?= $colors['cardBg'] ?>;
    border: 1px solid <?= $colors['border'] ?>;
    border-radius: 8px;
    padding: 20px;
}
.vwh-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid <?= $colors['border'] ?>;
    padding-bottom: 15px;
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
    background: <?= $colors['cardBg'] ?>;
    color: <?= $colors['textSecondary'] ?>;
    padding: 12px;
    text-align: start;
    border-bottom: 2px solid <?= $colors['border'] ?>;
}
.vwh-table td {
    padding: 12px;
    border-bottom: 1px solid <?= $colors['border'] ?>;
}
.vwh-btn {
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    border: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}
.vwh-btn:hover {
    opacity: 0.8;
}
.btn-primary {
    background: <?= $colors['primary'] ?>;
    color: white;
}
.btn-secondary {
    background: <?= $colors['secondary'] ?>;
    color: white;
}
.btn-gray {
    background: <?= $colors['border'] ?>;
    color: <?= $colors['textSecondary'] ?>;
}
.btn-danger {
    background: <?= $colors['danger'] ?>;
    color: <?= $colors['dangerText'] ?>;
}
.vwh-input {
    width: 100%;
    padding: 10px;
    background: <?= $colors['background'] ?>;
    border: 1px solid <?= $colors['border'] ?>;
    color: <?= $colors['textPrimary'] ?>;
    border-radius: 4px;
    height: 42px;
    box-sizing: border-box;
}
.select2-container--default .select2-selection--single {
    background: <?= $colors['background'] ?> !important;
    border: 1px solid <?= $colors['border'] ?> !important;
    height: 42px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: <?= $colors['textPrimary'] ?> !important;
    line-height: 42px !important;
}
.select2-dropdown {
    background: <?= $colors['cardBg'] ?> !important;
    color: <?= $colors['textPrimary'] ?> !important;
    border: 1px solid <?= $colors['primary'] ?> !important;
}
#vwhFormWrap {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 15px;
}
.vwh-modal {
    background: <?= $colors['cardBg'] ?>;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid <?= $colors['primary'] ?>;
    width: 100%;
    max-width: 420px;
}
</style>
</head>
<body class="vwh-scope" dir="<?= $direction ?>">
<?php endif; ?>

<!-- =======================
     CONTENT
======================= -->
<div class="vwh-card">
    <div class="vwh-header">
        <h2 style="color: <?= $colors['primary'] ?>; margin: 0;">
            <?= htmlspecialchars($texts['title']) ?>
        </h2>
        <div style="display: flex; gap: 10px;">
            <button id="vwhRefresh" class="vwh-btn btn-gray"><?= htmlspecialchars($texts['refresh']) ?></button>
            <button id="vwhNew" class="vwh-btn btn-primary"><?= htmlspecialchars($texts['add_new']) ?></button>
        </div>
    </div>

    <div class="vwh-grid">
        <div>
            <label style="display: block; font-size: 0.75rem; color: <?= $colors['textSecondary'] ?>; margin-bottom: 5px;">
                <?= htmlspecialchars($texts['filter_vendor']) ?>
            </label>
            <select id="vwhVendorFilter" class="vwh-input"></select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: <?= $colors['textSecondary'] ?>; margin-bottom: 5px;">
                <?= htmlspecialchars($texts['filter_day']) ?>
            </label>
            <select id="vwhDayFilter" class="vwh-input">
                <option value=""><?= htmlspecialchars($texts['all_days']) ?></option>
                <?php foreach ($days as $k => $dayName): ?>
                <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="button" id="vwhResetFilters" class="vwh-btn btn-gray" style="height: 42px; width: 100%;">
                <?= htmlspecialchars($texts['reset_filters']) ?>
            </button>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table class="vwh-table">
            <thead>
                <tr>
                    <th style="width: 60px;"><?= htmlspecialchars($texts['id']) ?></th>
                    <th><?= htmlspecialchars($texts['vendor']) ?></th>
                    <th><?= htmlspecialchars($texts['day']) ?></th>
                    <th><?= htmlspecialchars($texts['open']) ?></th>
                    <th><?= htmlspecialchars($texts['close']) ?></th>
                    <th style="text-align: center;"><?= htmlspecialchars($texts['closed']) ?></th>
                    <th style="text-align: center; width: 160px;"><?= htmlspecialchars($texts['actions']) ?></th>
                </tr>
            </thead>
            <tbody id="vwhTbody">
                <tr><td colspan="7" style="text-align: center; color: <?= $colors['textSecondary'] ?>; padding: 40px;">
                    <?= htmlspecialchars($texts['loading']) ?>
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
        <h3 id="vwhFormTitle" style="color: <?= $colors['primary'] ?>; margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid <?= $colors['border'] ?>; padding-bottom: 10px;"></h3>
        <form id="vwhForm">
            <input type="hidden" name="id" id="vwhId">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="margin-bottom: 15px;">
                <label style="color: <?= $colors['textSecondary'] ?>; font-size: 0.85rem; display: block; margin-bottom: 5px;">
                    <?= htmlspecialchars($texts['vendor']) ?>
                </label>
                <select name="vendor_id" id="vwhVendor" class="vwh-input" required style="width: 100%;">
                    <option value=""><?= htmlspecialchars($texts['select_vendor']) ?></option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="color: <?= $colors['textSecondary'] ?>; font-size: 0.85rem; display: block; margin-bottom: 5px;">
                    <?= htmlspecialchars($texts['day']) ?>
                </label>
                <select name="day_of_week" id="vwhDay" class="vwh-input" required style="width: 100%;">
                    <?php foreach ($days as $k => $dayName): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($dayName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="color: <?= $colors['textSecondary'] ?>; font-size: 0.85rem; display: block; margin-bottom: 5px;">
                    <?= htmlspecialchars($texts['open_time']) ?>
                </label>
                <input type="time" name="open_time" id="vwhOpen" class="vwh-input">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="color: <?= $colors['textSecondary'] ?>; font-size: 0.85rem; display: block; margin-bottom: 5px;">
                    <?= htmlspecialchars($texts['close_time']) ?>
                </label>
                <input type="time" name="close_time" id="vwhClose" class="vwh-input">
            </div>

            <div style="margin-bottom: 25px; display: flex; align-items: center;">
                <input type="checkbox" name="is_closed" id="vwhClosed" value="1" style="margin-inline-end: 8px;">
                <label style="color: <?= $colors['textSecondary'] ?>; font-size: 0.85rem; cursor: pointer;">
                    <?= htmlspecialchars($texts['closed']) ?>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="vwhCancel" class="vwh-btn btn-gray">
                    <?= htmlspecialchars($texts['cancel']) ?>
                </button>
                <button type="submit" class="vwh-btn btn-primary">
                    <?= htmlspecialchars($texts['save_data']) ?>
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
    translations: <?= json_encode($texts, JSON_UNESCAPED_UNICODE) ?>,
    theme: <?= json_encode($colors, JSON_UNESCAPED_UNICODE) ?>
};

<?php if ($isInDashboard && isset($ADMIN_UI_PAYLOAD)): ?>
// في حالة الداشبورد، نضيف التنسيقات الإضافية
window.THEME = <?= json_encode($theme, JSON_UNESCAPED_UNICODE) ?>;
window.I18N_FLAT = <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>;
window.DIRECTION = "<?= $direction ?>";
<?php endif; ?>
</script>

<script src="/admin/assets/js/pages/vendor_working_hours.js"></script>

<?php if ($standaloneMode): ?>
</body>
</html>
<?php endif; ?>