<?php
declare(strict_types=1);

// التحقق مما إذا كان ملف الرأس تم تضمينه (في الداشبورد) أم لا
$isInDashboard = false;
$standaloneMode = true;

// محاولة اكتشاف ما إذا كنا في بيئة الداشبورد
if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD) || function_exists('is_in_admin_scope')) {
    $isInDashboard = true;
    $standaloneMode = false;
    
    // استخدام بيانات من الداشبورد إذا كانت متوفرة
    if (isset($ADMIN_UI_PAYLOAD)) {
        $userLang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
        $direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
        $csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? '';
        
        // مسارات API من الداشبورد إذا كانت متوفرة
        $apiUrls = $ADMIN_UI_PAYLOAD['apiUrls'] ?? [];
        $apiUrl = $apiUrls['vendorAttributes'] ?? '/api/routes/vendor_attributes_values.php';
        $vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
        $attrApi = $apiUrls['attributes'] ?? '/api/routes/attributes.php';
    }
}

// إذا لم نكن في الداشبورد، نستخدم الجلسة
if ($standaloneMode) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // إعدادات اللغة والاتجاه
    $userLang = $_SESSION['user_lang'] ?? 'ar';
    $direction = ($userLang === 'ar') ? 'rtl' : 'ltr';

    // إنشاء توكن الحماية CSRF
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    $csrfToken = $_SESSION['csrf_token'];

    // المسارات
    $apiUrl = '/api/routes/vendor_attributes_values.php';
    $vendorsApi = '/api/routes/vendors.php';
    $attrApi = '/api/routes/attributes.php';
}

// إذا كنا في وضع منفصل، نعرض HTML كامل
if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $userLang == 'ar' ? 'قيم خصائص الموردين' : 'Vendor Attributes' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        /* CSS يبقى كما هو */
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

<!-- المحتوى يبقى كما هو في كلا الحالتين -->
<div class="vav-card">
    <div class="vav-header">
        <h2 style="margin:0; font-size:1.5rem; font-weight: bold; color: #3b82f6;">
            <?= $userLang == 'ar' ? 'قيم خصائص الموردين' : 'Vendor Attributes' ?>
        </h2>
        <div style="display:flex; gap:10px;">
            <button type="button" id="vavRefresh" class="vav-btn btn-gray"><?= $userLang == 'ar' ? 'تحديث' : 'Refresh' ?></button>
            <button type="button" id="vavNew" class="vav-btn btn-blue"><?= $userLang == 'ar' ? 'إضافة جديد +' : 'Add New +' ?></button>
        </div>
    </div>

    <div class="vav-grid">
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= $userLang == 'ar' ? 'فلترة حسب المورد' : 'Filter by Vendor' ?></label>
            <select id="vavVendorFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= $userLang == 'ar' ? 'فلترة حسب الخاصية' : 'Filter by Attribute' ?></label>
            <select id="vavAttributeFilter" class="vav-input"><option></option></select>
        </div>
        <div style="flex: 1;">
            <label style="display:block; font-size:0.75rem; color:#888; margin-bottom:5px;"><?= $userLang == 'ar' ? 'البحث في القيم' : 'Search Values' ?></label>
            <input type="text" id="vavSearch" class="vav-input" placeholder="<?= $userLang == 'ar' ? 'ابحث هنا...' : 'Search...' ?>">
        </div>
        <div>
            <button type="button" id="vavResetFilters" class="vav-btn btn-gray" style="height:42px; width:100%;">
                <?= $userLang == 'ar' ? 'إلغاء الفلاتر' : 'Reset' ?>
            </button>
        </div>
    </div>

    <div class="vav-table-res">
        <table class="vav-table">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th><?= $userLang == 'ar' ? 'المورد' : 'Vendor' ?></th>
                    <th><?= $userLang == 'ar' ? 'الخاصية' : 'Attribute' ?></th>
                    <th><?= $userLang == 'ar' ? 'القيمة' : 'Value' ?></th>
                    <th style="text-align:center; width: 160px;"><?= $userLang == 'ar' ? 'الإجراءات' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody id="vavTbody">
                <tr><td colspan="5" style="text-align:center; padding:40px; color:#666;">...</td></tr>
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
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= $userLang == 'ar' ? 'المورد' : 'Vendor' ?></label>
                <select name="vendor_id" id="vavVendor" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= $userLang == 'ar' ? 'الخاصية' : 'Attribute' ?></label>
                <select name="attribute_id" id="vavAttribute" class="vav-input" required style="width:100%;"></select>
            </div>

            <div style="margin-bottom:25px;">
                <label style="color:#aaa; font-size:0.85rem; display:block; margin-bottom:5px;"><?= $userLang == 'ar' ? 'القيمة' : 'Value' ?></label>
                <input type="text" name="value" id="vavValue" class="vav-input" required placeholder="Ex: 10%, Red, Extra Large">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="vavCancel" class="vav-btn btn-gray"><?= $userLang == 'ar' ? 'إلغاء' : 'Cancel' ?></button>
                <button type="submit" class="vav-btn btn-blue"><?= $userLang == 'ar' ? 'حفظ البيانات' : 'Save Data' ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($standaloneMode): ?>
<script>
window.VAV_CONFIG = {
    apiUrl: "<?= $apiUrl ?>",
    vendorsUrl: "<?= $vendorsApi ?>",
    attrsUrl: "<?= $attrApi ?>",
    csrfToken: "<?= $csrfToken ?>",
    lang: "<?= $userLang ?>",
    isStandalone: true
};
</script>
<script src="/admin/assets/js/pages/vendor_attributes_values.js"></script>
</body>
</html>
<?php else: ?>
<!-- في الداشبورد، نحتاج فقط إلى إضافة التكوين -->
<script>
// التكوين للداشبورد
window.VAV_CONFIG = window.VAV_CONFIG || {
    apiUrl: "<?= $apiUrl ?>",
    vendorsUrl: "<?= $vendorsApi ?>",
    attrsUrl: "<?= $attrApi ?>",
    csrfToken: "<?= $csrfToken ?>",
    lang: "<?= $userLang ?>",
    isStandalone: false
};
</script>
<?php endif; ?>
