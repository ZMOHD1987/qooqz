<?php
/**
 * admin/fragments/DeliveryCompany.php
 * إدارة شركات التوصيل - نسخة كاملة محسنة
 *
 * يعتمد على:
 * 1. ADMIN_UI_PAYLOAD من bootstrap.php (إذا متوفر)
 * 2. ملفات الترجمة في /languages/DeliveryCompany/{lang}.json
 * 3. جلسة PHP للتحقق من المستخدم وCSRF token
 *
 * Save as UTF-8 without BOM.
 */

declare(strict_types=1);

// ==================== تسجيل الأخطاء عند الإغلاق ====================
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log = __DIR__ . '/../../api/error_debug.log';
        $msg = "[" . date('c') . "] SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}" . PHP_EOL;
        @file_put_contents($log, $msg, FILE_APPEND);
    }
});

// ==================== المسارات الأساسية ====================
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../'), '/\\');
$projectBaseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$baseApiDir = realpath(__DIR__ . '/../../api') ?: (__DIR__ . '/../../api');
$logFile = $baseApiDir . '/error_debug.log';

// ==================== دوال مساعدة ====================
function safe_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) { 
            if (!is_string($item)) return; 
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8'); 
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}

// ==================== الجلسة والـ CSRF Token ====================
if (session_status() === PHP_SESSION_NONE) @session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ==================== بيانات المستخدم الحالي ====================
function safe_get_current_user() {
    $user = [];
    try {
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];
        } elseif (!empty($_SESSION['user_id'])) {
            $user = [
                'id' => (int)$_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0,
                'preferred_language' => $_SESSION['preferred_language'] ?? ($_SESSION['lang'] ?? 'en'),
                'permissions' => $_SESSION['permissions'] ?? []
            ];
        }
    } catch (Throwable $e) {
        $user = [];
    }
    return is_array($user) ? $user : ['id'=>0, 'username'=>'guest', 'role_id'=>0, 'permissions'=>[]];
}

$safeUser = safe_get_current_user();

// ==================== إعدادات ADMIN_UI والترجمات ====================
// الأفضلية لـ ADMIN_UI_PAYLOAD إذا تم حقنه من bootstrap.php
$final_admin_ui = null;
if (isset($ADMIN_UI_PAYLOAD) && is_array($ADMIN_UI_PAYLOAD)) {
    $final_admin_ui = $ADMIN_UI_PAYLOAD;
} else {
    $final_admin_ui = [
        'lang' => $safeUser['preferred_language'] ?? 'en',
        'direction' => in_array($safeUser['preferred_language'] ?? 'en', ['ar','he','fa','ur'], true) ? 'rtl' : 'ltr',
        'strings' => [],
        'user' => [
            'id' => (int)$safeUser['id'],
            'username' => $safeUser['username'] ?? 'guest',
            'permissions' => $safeUser['permissions'] ?? []
        ],
        'csrf_token' => $csrfToken
    ];
}

// الترجمات الاحتياطية: /languages/DeliveryCompany/{lang}.json
$translations = [];
$independentLangPath = $docRoot . '/languages/DeliveryCompany';
$lang = $final_admin_ui['lang'] ?? 'en';
$pageFile = $independentLangPath . '/' . $lang . '.json';

if (is_readable($pageFile)) {
    $content = @file_get_contents($pageFile);
    $decoded = @json_decode($content, true);
    if (is_array($decoded)) {
        $strings = isset($decoded['strings']) && is_array($decoded['strings']) ? $decoded['strings'] : $decoded;
        // فقط إذا لم توفر bootstrap الترجمات
        if (empty($final_admin_ui['strings'])) {
            $final_admin_ui['strings'] = $strings;
        }
        $translations = $decoded;
    } else {
        @file_put_contents($logFile, "[".date('c')."] DeliveryCompany lang file JSON decode failed: ".$pageFile.PHP_EOL, FILE_APPEND);
    }
}

// تأكيد القيم الافتراضية
if (!isset($final_admin_ui['lang'])) $final_admin_ui['lang'] = $lang;
if (!isset($final_admin_ui['direction'])) {
    $final_admin_ui['direction'] = in_array($final_admin_ui['lang'], ['ar','he','fa','ur'], true) ? 'rtl' : 'ltr';
}
if (!isset($final_admin_ui['csrf_token'])) $final_admin_ui['csrf_token'] = $csrfToken;
if (!isset($final_admin_ui['strings']) || !is_array($final_admin_ui['strings'])) {
    $final_admin_ui['strings'] = [];
}

// الترميز للجافاسكربت
$ADMIN_UI_JSON = safe_json_encode($final_admin_ui);
$currentUserJson = safe_json_encode($safeUser);
$jsTranslations = !empty($translations) ? safe_json_encode($translations) : 'null';

// ==================== استخراج النصوص للعرض في HTML ====================
$strings = $final_admin_ui['strings'];

$module_title = htmlspecialchars($strings['delivery_company']['module_title'] ?? 'Delivery Companies', ENT_QUOTES);
$search_placeholder = htmlspecialchars($strings['placeholders']['search'] ?? 'Search name, email, phone...', ENT_QUOTES);
$phone_placeholder = htmlspecialchars($strings['placeholders']['phone'] ?? 'Phone', ENT_QUOTES);
$email_placeholder = htmlspecialchars($strings['placeholders']['email'] ?? 'Email', ENT_QUOTES);
$all_countries = htmlspecialchars($strings['placeholders']['all_countries'] ?? 'All countries', ENT_QUOTES);
$all_cities = htmlspecialchars($strings['placeholders']['all_cities'] ?? 'All cities', ENT_QUOTES);
$all_status = htmlspecialchars($strings['placeholders']['all_status'] ?? 'All status', ENT_QUOTES);
$active_status = htmlspecialchars($strings['statuses']['active'] ?? 'Active', ENT_QUOTES);
$inactive_status = htmlspecialchars($strings['statuses']['inactive'] ?? 'Inactive', ENT_QUOTES);
$refresh_text = htmlspecialchars($strings['actions']['refresh'] ?? 'Refresh', ENT_QUOTES);
$new_company_text = htmlspecialchars($strings['delivery_company']['new'] ?? 'New Company', ENT_QUOTES);
$total_text = htmlspecialchars($strings['messages']['total'] ?? 'Total:', ENT_QUOTES);
$loading_text = htmlspecialchars($strings['messages']['loading'] ?? 'Loading...', ENT_QUOTES);

$table_id = htmlspecialchars($strings['table']['id'] ?? 'ID', ENT_QUOTES);
$table_name = htmlspecialchars($strings['table']['name'] ?? 'Name', ENT_QUOTES);
$table_email = htmlspecialchars($strings['table']['email'] ?? 'Email', ENT_QUOTES);
$table_phone = htmlspecialchars($strings['table']['phone'] ?? 'Phone', ENT_QUOTES);
$table_country_city = htmlspecialchars($strings['table']['country_city'] ?? 'Country / City', ENT_QUOTES);
$table_active = htmlspecialchars($strings['table']['active'] ?? 'Active', ENT_QUOTES);
$table_actions = htmlspecialchars($strings['table']['actions'] ?? 'Actions', ENT_QUOTES);

$form_title = htmlspecialchars($strings['delivery_company']['form_title'] ?? 'Create / Edit Delivery Company', ENT_QUOTES);
$save_text = htmlspecialchars($strings['actions']['save'] ?? 'Save', ENT_QUOTES);
$reset_text = htmlspecialchars($strings['actions']['reset'] ?? 'Reset', ENT_QUOTES);

$field_name = htmlspecialchars($strings['fields']['name'] ?? 'Name', ENT_QUOTES);
$field_slug = htmlspecialchars($strings['fields']['slug'] ?? 'Slug', ENT_QUOTES);
$field_phone = htmlspecialchars($strings['fields']['phone'] ?? 'Phone', ENT_QUOTES);
$field_email = htmlspecialchars($strings['fields']['email'] ?? 'Email', ENT_QUOTES);
$field_website = htmlspecialchars($strings['fields']['website_url'] ?? 'Website', ENT_QUOTES);
$field_api_url = htmlspecialchars($strings['fields']['api_url'] ?? 'API URL', ENT_QUOTES);
$field_api_key = htmlspecialchars($strings['fields']['api_key'] ?? 'API Key', ENT_QUOTES);
$field_tracking = htmlspecialchars($strings['fields']['tracking_url'] ?? 'Tracking URL', ENT_QUOTES);
$field_country = htmlspecialchars($strings['fields']['country'] ?? 'Country', ENT_QUOTES);
$field_city = htmlspecialchars($strings['fields']['city'] ?? 'City', ENT_QUOTES);
$field_logo = htmlspecialchars($strings['fields']['logo'] ?? 'Logo', ENT_QUOTES);
$field_active = htmlspecialchars($strings['fields']['is_active'] ?? 'Active', ENT_QUOTES);
$field_rating = htmlspecialchars($strings['fields']['rating_average'] ?? 'Rating average', ENT_QUOTES);

$placeholder_name = htmlspecialchars($strings['placeholders']['name'] ?? 'Company name', ENT_QUOTES);
$placeholder_loading_countries = htmlspecialchars($strings['placeholders']['loading_countries'] ?? 'Loading countries...', ENT_QUOTES);
$placeholder_select_country = htmlspecialchars($strings['placeholders']['select_country_first'] ?? 'Select country first', ENT_QUOTES);

$translations_title = htmlspecialchars($strings['delivery_company']['translations'] ?? 'Translations', ENT_QUOTES);
$add_language_text = htmlspecialchars($strings['actions']['add_language'] ?? 'Add Language', ENT_QUOTES);

$username = htmlspecialchars($safeUser['username'] ?? 'guest', ENT_QUOTES);
$is_admin = (($safeUser['role_id'] ?? 0) === 1);
?>

<link rel="stylesheet" href="<?= htmlspecialchars($projectBaseUrl, ENT_QUOTES) ?>/admin/assets/css/pages/DeliveryCompany.css">

<script>
(function(){
    window.ADMIN_UI = window.ADMIN_UI || <?= $ADMIN_UI_JSON ?>;
    window.ADMIN_LANG = window.ADMIN_UI.lang;
    window.ADMIN_DIR = window.ADMIN_UI.direction;
    window.CSRF_TOKEN = window.ADMIN_UI.csrf_token;
    document.documentElement.lang = window.ADMIN_LANG;
    document.documentElement.dir = window.ADMIN_DIR;
    window.__DeliveryCompanyTranslations = <?= $jsTranslations ?>;
})();
</script>

<div id="adminDeliveryCompanies" dir="<?= htmlspecialchars($final_admin_ui['direction'], ENT_QUOTES) ?>" 
     data-lang="<?= htmlspecialchars($final_admin_ui['lang'], ENT_QUOTES) ?>" 
     style="max-width:1200px; margin:18px auto;">

    <!-- رأس الصفحة -->
    <header style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
        <h2 data-i18n="delivery_company.module_title"><?= $module_title ?></h2>
        <div style="margin-left:auto; color:#6b7280;" id="dcHeaderUser"><?= $username ?></div>
    </header>

    <!-- أدوات التصفية -->
    <div class="filters" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
        <input id="deliveryCompanySearch" data-i18n-placeholder="placeholders.search" 
               placeholder="<?= $search_placeholder ?>" 
               style="padding:8px; border:1px solid #e6eef0; border-radius:6px; width:260px;">
        
        <input id="deliveryCompanyFilterPhone" data-i18n-placeholder="placeholders.phone" 
               placeholder="<?= $phone_placeholder ?>" 
               style="padding:8px; border:1px solid #e6eef0; border-radius:6px; width:160px;">
        
        <input id="deliveryCompanyFilterEmail" data-i18n-placeholder="placeholders.email" 
               placeholder="<?= $email_placeholder ?>" 
               style="padding:8px; border:1px solid #e6eef0; border-radius:6px; width:200px;">
        
        <select id="deliveryCompanyFilterCountry" style="padding:8px; border:1px solid #e6eef0; border-radius:6px;">
            <option value=""><?= $all_countries ?></option>
        </select>
        
        <select id="deliveryCompanyFilterCity" style="padding:8px; border:1px solid #e6eef0; border-radius:6px;">
            <option value=""><?= $all_cities ?></option>
        </select>
        
        <select id="deliveryCompanyFilterActive" style="padding:8px; border:1px solid #e6eef0; border-radius:6px;">
            <option value=""><?= $all_status ?></option>
            <option value="1"><?= $active_status ?></option>
            <option value="0"><?= $inactive_status ?></option>
        </select>
        
        <button id="deliveryCompanyRefresh" class="btn" type="button" data-i18n="actions.refresh">
            <?= $refresh_text ?>
        </button>
        
        <button id="deliveryCompanyNewBtn" class="btn primary" type="button" data-i18n="delivery_company.new">
            <?= $new_company_text ?>
        </button>
        
        <div style="margin-left:auto; color:#6b7280;">
            <?= $total_text ?> <span id="deliveryCompaniesCount">‑</span>
        </div>
    </div>

    <!-- الجدول -->
    <div class="table-wrap" style="margin-bottom:18px;">
        <table id="deliveryCompaniesTable" style="width:100%; border-collapse:collapse;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th data-i18n="table.id" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_id ?>
                    </th>
                    <th data-i18n="table.name" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_name ?>
                    </th>
                    <th data-i18n="table.email" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_email ?>
                    </th>
                    <th data-i18n="table.phone" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_phone ?>
                    </th>
                    <th data-i18n="table.country_city" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_country_city ?>
                    </th>
                    <th data-i18n="table.active" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_active ?>
                    </th>
                    <th data-i18n="table.actions" style="padding:8px; border-bottom:1px solid #eef2f7">
                        <?= $table_actions ?>
                    </th>
                </tr>
            </thead>
            <tbody id="deliveryCompaniesTbody">
                <tr>
                    <td colspan="7" style="text-align:center; color:#6b7280; padding:18px;">
                        <?= $loading_text ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- نموذج الإضافة/التعديل -->
    <section id="deliveryCompanyFormSection" class="embedded-form" 
             style="background:#fff; border:1px solid #eef2f7; padding:12px; border-radius:8px;">
        
        <header style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <h3 id="deliveryCompanyFormTitle" data-i18n="delivery_company.form_title">
                <?= $form_title ?>
            </h3>
            <div>
                <button id="deliveryCompanySaveBtn" class="btn primary" type="button" data-i18n="actions.save">
                    <?= $save_text ?>
                </button>
                <button id="deliveryCompanyResetBtn" class="btn" type="button" data-i18n="actions.reset">
                    <?= $reset_text ?>
                </button>
            </div>
        </header>

        <div id="deliveryCompanyFormErrors" class="errors" 
             style="display:none; color:#b91c1c; margin-bottom:8px;"></div>

        <form id="deliveryCompanyForm" enctype="multipart/form-data" autocomplete="off" onsubmit="return false;">
            <input type="hidden" id="delivery_company_id" name="id" value="0">
            <input type="hidden" id="delivery_company_user_id" name="user_id" value="<?= (int)$safeUser['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <div class="form-grid" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px;">
                
                <!-- الاسم -->
                <label data-i18n="fields.name">
                    <?= $field_name ?>
                    <input id="delivery_company_name" name="name" type="text" required 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;"
                           data-i18n-placeholder="placeholders.name" 
                           placeholder="<?= $placeholder_name ?>">
                </label>

                <!-- الرابط المختصر -->
                <label data-i18n="fields.slug">
                    <?= $field_slug ?>
                    <input id="delivery_company_slug" name="slug" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- الهاتف -->
                <label data-i18n="fields.phone">
                    <?= $field_phone ?>
                    <input id="delivery_company_phone" name="phone" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;"
                           data-i18n-placeholder="placeholders.phone">
                </label>

                <!-- البريد الإلكتروني -->
                <label data-i18n="fields.email">
                    <?= $field_email ?>
                    <input id="delivery_company_email" name="email" type="email" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- الموقع الإلكتروني -->
                <label data-i18n="fields.website_url">
                    <?= $field_website ?>
                    <input id="delivery_company_website" name="website_url" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- رابط API -->
                <label data-i18n="fields.api_url">
                    <?= $field_api_url ?>
                    <input id="delivery_company_api_url" name="api_url" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- مفتاح API -->
                <label data-i18n="fields.api_key">
                    <?= $field_api_key ?>
                    <input id="delivery_company_api_key" name="api_key" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- رابط التتبع -->
                <label data-i18n="fields.tracking_url">
                    <?= $field_tracking ?>
                    <input id="delivery_company_tracking" name="tracking_url" type="text" 
                           style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                </label>

                <!-- الدولة -->
                <label data-i18n="fields.country">
                    <?= $field_country ?>
                    <select id="delivery_company_country" name="country_id" 
                            style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                        <option value=""><?= $placeholder_loading_countries ?></option>
                    </select>
                </label>

                <!-- المدينة -->
                <label data-i18n="fields.city">
                    <?= $field_city ?>
                    <select id="delivery_company_city" name="city_id" 
                            style="width:100%; padding:8px; border:1px solid #e6eef0; border-radius:6px;">
                        <option value=""><?= $placeholder_select_country ?></option>
                    </select>
                </label>

                <!-- الشعار -->
                <label data-i18n="fields.logo">
                    <?= $field_logo ?>
                    <input id="delivery_company_logo" name="logo" type="file" accept="image/*">
                </label>
                
                <div class="img-preview" id="preview_delivery_logo" 
                     style="display:flex; align-items:center; gap:8px;"></div>

                <!-- حقوق المدير (تظهر للمسؤولين فقط) -->
                <?php if ($is_admin): ?>
                <div id="deliveryAdminFields" style="grid-column:1 / span 2; padding-top:8px; border-top:1px dashed #eef2f7;">
                    <label style="display:inline-block; margin-right:12px" data-i18n="fields.is_active">
                        <?= $field_active ?>
                        <input id="delivery_company_is_active" name="is_active" type="checkbox" value="1">
                    </label>
                    <label style="display:inline-block" data-i18n="fields.rating_average">
                        <?= $field_rating ?>
                        <input id="delivery_company_rating" name="rating_average" type="text" value="0.00" 
                               style="width:120px; padding:6px; border:1px solid #e6eef0; border-radius:6px;">
                    </label>
                </div>
                <?php else: ?>
                <div id="deliveryAdminFields" style="display:none; grid-column:1 / span 2;"></div>
                <?php endif; ?>
            </div>

            <hr style="margin:12px 0;">
            
            <!-- الترجمات -->
            <h4 data-i18n="delivery_company.translations"><?= $translations_title ?></h4>
            <div id="deliveryCompany_translations_area" 
                 style="max-height:260px; overflow:auto; border:1px dashed #e6eef0; padding:8px; border-radius:6px;">
            </div>
            
            <div style="margin-top:8px;">
                <button id="deliveryCompanyAddLangBtn" type="button" class="btn" data-i18n="actions.add_language">
                    <?= $add_language_text ?>
                </button>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var baseUrl = '<?= htmlspecialchars($projectBaseUrl, ENT_QUOTES) ?>';
    window.API_BASE = baseUrl + '/api/routes/DeliveryCompany.php';
    window.COUNTRIES_API = baseUrl + '/api/helpers/countries.php';
    window.CITIES_API = baseUrl + '/api/helpers/cities.php';
    window.PARENTS_API = window.API_BASE + '?action=parents';
    window.CURRENT_USER = <?= $currentUserJson ?>;
    window.DELIVERY_COMPANY_VIEW = {
        user_id: <?= (int)$safeUser['id'] ?>,
        is_admin: <?= $is_admin ? 'true' : 'false' ?>,
        permissions: <?= json_encode($safeUser['permissions'] ?? []) ?>
    };

    // دمج الترجمات في ADMIN_UI.strings
    function merge(dest, src){
        Object.keys(src || {}).forEach(function(k){
            if(src[k] && typeof src[k] === 'object' && !Array.isArray(src[k])){ 
                dest[k] = dest[k] || {}; 
                merge(dest[k], src[k]); 
            } else {
                dest[k] = src[k];
            }
        });
    }
    
    try {
        window.ADMIN_UI = window.ADMIN_UI || {};
        window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};
        if (window.__DeliveryCompanyTranslations && typeof window.__DeliveryCompanyTranslations === 'object') {
            var src = window.__DeliveryCompanyTranslations.strings ? 
                     window.__DeliveryCompanyTranslations.strings : 
                     window.__DeliveryCompanyTranslations;
            merge(window.ADMIN_UI.strings, src || {});
            if (window.__DeliveryCompanyTranslations.direction) {
                window.ADMIN_UI.direction = window.__DeliveryCompanyTranslations.direction;
            }
        }
    } catch (e) {
        console.warn('DeliveryCompany translations merge failed', e);
    }

    // تحميل ملف JavaScript الخاص بالصفحة
    var s = document.createElement('script');
    s.src = baseUrl + '/admin/assets/js/pages/DeliveryCompany.js';
    s.async = false;
    document.body.appendChild(s);
});
</script>