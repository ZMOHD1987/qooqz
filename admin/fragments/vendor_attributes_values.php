<?php
declare(strict_types=1);

/**
 * admin/fragments/vendor_attributes_values.php
 * Vendor Attributes Values Management
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
        $apiUrl = $apiUrls['vendorAttributes'] ?? '/api/routes/vendor_attributes_values.php';
        $vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
        $attrApi = $apiUrls['attributes'] ?? '/api/routes/attributes.php';
        $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
        $themeColors = $ADMIN_UI_PAYLOAD['theme']['colors_map'] ?? [];
    }
}

if ($standaloneMode) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['user_lang'] ?? 'ar';
    $direction = ($userLang === 'ar') ? 'rtl' : 'ltr';

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];

    $apiUrl = '/api/routes/vendor_attributes_values.php';
    $vendorsApi = '/api/routes/vendors.php';
    $attrApi = '/api/routes/attributes.php';
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

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('vendor_attributes_values_title', 'Vendor Attributes') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        .vav-scope { background: #0f0f0f; color: #eee; padding: 20px; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
        .vav-card { background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        .vav-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .vav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; align-items: end; }
        .vav-table-res { width: 100%; overflow-x: auto; border-radius: 4px; }
        .vav-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .vav-table th { background: #252525; color: #aaa; padding: 12px; text-align: start; border-bottom: 2px solid #333; font-size: 0.9rem; }
        .vav-table td { padding: 12px; border-bottom: 1px solid #222; font-size: 0.9rem; vertical-align: middle; }
        .vav-btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; }
        .btn-blue { background: #2563eb; color: #fff; }
        .btn-gray { background: #333; color: #ccc; }
        .vav-btn:hover { opacity: 0.8; transform: translateY(-1px); }
        .vav-input { width: 100%; padding: 10px; background: #000; border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box; height: 42px; }
        .select2-container--default .select2-selection--single { background: #000 !important; border: 1px solid #333 !important; height: 42px !important; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: #fff !important; line-height: 42px !important; padding-inline-start: 10px; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #666 !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
        .select2-container--default .select2-selection--single .select2-selection__clear { color: #ff4d4d !important; margin-inline-end: 10px; font-size: 1.2rem; }
        .select2-dropdown { background: #1a1a1a !important; color: #fff !important; border: 1px solid #3b82f6 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .select2-search__field { background: #000 !important; color: #fff !important; border: 1px solid #333 !important; border-radius: 4px !important; }
        .select2-results__option--highlighted[aria-selected] { background-color: #2563eb !important; }
        .select2-results__option[aria-selected="true"] { background-color: #1e3a8a !important; }
        #vavFormWrap { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 99999; justify-content: center; align-items: center; padding: 15px; backdrop-filter: blur(4px); }
        .vav-modal { background: #1a1a1a; width: 100%; max-width: 450px; padding: 25px; border-radius: 12px; border: 1px solid #3b82f6; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    </style>
</head>
<body class="vav-scope" dir="<?= $direction ?>">
<?php endif; ?>

<div class="vav-card">
    <div class="vav-header">
        <h2 style="margin:0; font-size:1.5rem; font-weight: bold; color: #3b82f6;">
            <?= t('vendor_attributes.title', 'Vendor Attributes') ?>
        </h2>
        <div style="display:flex; gap:10px;">
            <button type="button" id="vavRefresh" class="vav-btn btn-gray"><?= t('refresh', 'Refresh') ?></button>
            <button type="button" id="vavNew" class="vav-btn btn-blue"><?= t('add_new', 'Add New +') ?></button>
        </div>
    </div>

    <div class="vav-grid">
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= t('filter_by_vendor', 'Filter by Vendor') ?></label>
            <select id="vavVendorFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= t('filter_by_attribute', 'Filter by Attribute') ?></label>
            <select id="vavAttributeFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= t('search_values', 'Search Values') ?></label>
            <input type="text" id="vavSearch" class="vav-input" placeholder="<?= t('search_placeholder', 'Search...') ?>">
        </div>
        <div>
            <button type="button" id="vavResetFilters" class="vav-btn btn-gray" style="height:42px; width:100%;">
                <?= t('reset_filters', 'Reset') ?>
            </button>
        </div>
    </div>

    <div class="vav-table-res">
        <table class="vav-table">
            <thead>
                <tr>
                    <th style="width: 60px;"><?= t('id', 'ID') ?></th>
                    <th><?= t('vendor', 'Vendor') ?></th>
                    <th><?= t('attribute', 'Attribute') ?></th>
                    <th><?= t('value', 'Value') ?></th>
                    <th style="text-align:center; width: 160px;"><?= t('actions', 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vavTbody">
                <tr><td colspan="5" style="text-align:center; padding:40px; color:#666;"><?= t('loading', 'Loading...') ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vavFormWrap">
    <div class="vav-modal">
        <h3 id="vavFormTitle" style="margin-top:0; color:#3b82f6; margin-bottom:20px; border-bottom: 1px solid #333; padding-bottom: 10px;"></h3>
        <form id="vavForm">
            <input type="hidden" name="id" id="vavId">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div style="margin-bottom:15px;">
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= t('vendor', 'Vendor') ?></label>
                <select name="vendor_id" id="vavVendor" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= t('attribute', 'Attribute') ?></label>
                <select name="attribute_id" id="vavAttribute" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:25px;">
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= t('value', 'Value') ?></label>
                <input type="text" name="value" id="vavValue" class="vav-input" required placeholder="<?= t('value_placeholder', 'Ex: 10%, Red, Extra Large') ?>">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="vavCancel" class="vav-btn btn-gray"><?= t('cancel', 'Cancel') ?></button>
                <button type="submit" class="vav-btn btn-blue"><?= t('save_data', 'Save Data') ?></button>
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
    themeColors: <?= json_encode($themeColors, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/pages/vendor_attributes_values.js"></script>
</body>
</html>
<?php endif; ?>
