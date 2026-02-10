<?php
/**
 * admin/fragments/vendor_attributes_values.php
 * نسخة مطورة بالكامل - ديناميكية 100% من الجداول مع ترقيم الصفحات
 */
declare(strict_types=1);

/* =======================
   Bootstrap Admin UI
======================= */
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$user       = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang       = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction  = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings    = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme      = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// التأكد من وجود خرائط للقيم
if (!isset($theme['colors_map']) && isset($theme['colors'])) {
    $theme['colors_map'] = [];
    foreach ($theme['colors'] as $color) {
        if (isset($color['setting_key']) && isset($color['color_value'])) {
            $theme['colors_map'][$color['setting_key']] = $color['color_value'];
        }
    }
}

if (!isset($theme['buttons_map']) && isset($theme['buttons'])) {
    $theme['buttons_map'] = [];
    foreach ($theme['buttons'] as $button) {
        $slug = $button['slug'] ?? strtolower(str_replace(' ', '-', $button['name'] ?? ''));
        $theme['buttons_map'][$slug] = $button;
    }
}

if (!isset($theme['cards_map']) && isset($theme['cards'])) {
    $theme['cards_map'] = [];
    foreach ($theme['cards'] as $card) {
        $slug = $card['slug'] ?? strtolower(str_replace(' ', '-', $card['name'] ?? ''));
        $theme['cards_map'][$slug] = $card;
    }
}

if (!isset($theme['fonts_map']) && isset($theme['fonts'])) {
    $theme['fonts_map'] = [];
    foreach ($theme['fonts'] as $font) {
        if (isset($font['setting_key'])) {
            $theme['fonts_map'][$font['setting_key']] = $font;
        }
    }
}

/* =======================
   Helpers
======================= */
if (!function_exists('flatten_strings')) {
    function flatten_strings(array $src): array {
        $out = [];
        $stack = [['p'=>'','n'=>$src]];
        while ($stack) {
            $it = array_pop($stack);
            foreach ((array)$it['n'] as $k=>$v) {
                $key = $it['p'] === '' ? $k : $it['p'].'.'.$k;
                if (is_array($v)) $stack[] = ['p'=>$key,'n'=>$v];
                else {
                    $out[$key] = (string)$v;
                    $short = basename(str_replace('.','/',$key));
                    $out[$short] ??= (string)$v;
                }
            }
        }
        return $out;
    }
}
$flat = flatten_strings($strings);

if (!function_exists('gs')) {
    function gs(string $k, array $flat, string $d=''): string {
        if (!empty($flat[$k])) return $flat[$k];
        $s = basename(str_replace('.','/',$k));
        return $flat[$s] ?? ($d !== '' ? $d : $s);
    }
}

// تحميل ترجمات الموردين
$vendorTranslations = [];
try {
    $vendorTransFile = __DIR__ . '/../../languages/vendors/' . $lang . '.json';
    if (file_exists($vendorTransFile)) {
        $vendorTranslations = json_decode(file_get_contents($vendorTransFile), true);
        if (!empty($vendorTranslations)) {
            $vendorFlat = flatten_strings($vendorTranslations);
            $flat = array_merge($flat, $vendorFlat);
        }
    }
} catch (Throwable $e) {}

/* =======================
   Permissions Check
======================= */
$canManage = false;
if (!empty($user['role_id']) && (int)$user['role_id'] === 1) $canManage = true;
if (!$canManage && !empty($user['roles'])) {
    if (in_array('super_admin',$user['roles'],true) || in_array('admin',$user['roles'],true)) $canManage = true;
}

$csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);
$apiUrls = $ADMIN_UI_PAYLOAD['apiUrls'] ?? [];
$apiUrl = $apiUrls['vendorAttributes'] ?? '/api/routes/vendor_attributes_values.php';
$vendorsApi = $apiUrls['vendors'] ?? '/api/routes/vendors.php';
$attrsApi = $apiUrls['attributes'] ?? '/api/routes/attributes.php';

$isStandalone = !isset($ADMIN_UI_PAYLOAD['is_dashboard']) || $ADMIN_UI_PAYLOAD['is_dashboard'] === false;
?>

<!doctype html>
<html lang="<?= htmlspecialchars($lang,ENT_QUOTES) ?>" dir="<?= htmlspecialchars($direction,ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(gs('vendor_attributes.page_title',$flat,''),ENT_QUOTES) ?></title>
    
    <?php if ($isStandalone): ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php endif; ?>
</head>
<body>

<div id="vendorAttributes" class="admin-page">
    <div class="notification-area" id="vavNotification"></div>
    
    <div class="page-header">
        <h2><?= htmlspecialchars(gs('vendor_attributes.page_title',$flat,''),ENT_QUOTES) ?></h2>
        <?php if ($canManage): ?>
            <button id="vavNew" class="btn">
                <?= htmlspecialchars(gs('vendor_attributes.buttons.new',$flat,''),ENT_QUOTES) ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="filters-section">
        <div class="filters-grid">
            <div class="filter-control">
                <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.filters.vendor',$flat,''),ENT_QUOTES) ?></label>
                <select id="vavVendorFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('vendor_attributes.filters.all_vendors',$flat,''),ENT_QUOTES) ?></option>
                </select>
            </div>
            
            <div class="filter-control">
                <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.filters.attribute',$flat,''),ENT_QUOTES) ?></label>
                <select id="vavAttributeFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('vendor_attributes.filters.all_attributes',$flat,''),ENT_QUOTES) ?></option>
                </select>
            </div>
            
            <div class="filter-control">
                <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.filters.search',$flat,''),ENT_QUOTES) ?></label>
                <input type="text" id="vavSearch" class="form-input" 
                       placeholder="<?= htmlspecialchars(gs('vendor_attributes.search_placeholder',$flat,''),ENT_QUOTES) ?>">
            </div>
        </div>
        
        <div class="filters-actions">
            <div class="filters-right">
                <button type="button" id="vavResetFilters" class="btn">
                    <?= htmlspecialchars(gs('vendor_attributes.buttons.clear_filters',$flat,''),ENT_QUOTES) ?>
                </button>
                <button type="button" id="vavRefresh" class="btn">
                    <?= htmlspecialchars(gs('vendor_attributes.buttons.refresh',$flat,''),ENT_QUOTES) ?>
                </button>
            </div>
        </div>
    </div>

    <div class="table-section">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="60"><?= htmlspecialchars(gs('vendor_attributes.table.id',$flat,''),ENT_QUOTES) ?></th>
                        <th><?= htmlspecialchars(gs('vendor_attributes.table.vendor',$flat,''),ENT_QUOTES) ?></th>
                        <th><?= htmlspecialchars(gs('vendor_attributes.table.attribute',$flat,''),ENT_QUOTES) ?></th>
                        <th><?= htmlspecialchars(gs('vendor_attributes.table.value',$flat,''),ENT_QUOTES) ?></th>
                        <th width="140"><?= htmlspecialchars(gs('vendor_attributes.table.actions',$flat,''),ENT_QUOTES) ?></th>
                    </tr>
                </thead>
                <tbody id="vavTbody">
                    <tr>
                        <td colspan="5" class="loading-row">
                            <div class="loading-spinner"></div>
                            <div><?= htmlspecialchars(gs('vendor_attributes.messages.loading',$flat,''),ENT_QUOTES) ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- قسم الترقيم -->
        <div id="vavPagination" class="pagination"></div>
    </div>

    <?php if ($canManage): ?>
    <div id="vavFormWrap" class="form-section" style="display:none;">
        <h3 id="vavFormTitle"></h3>
        <form id="vavForm" autocomplete="off">
            <input type="hidden" name="id" id="vavId">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.form.vendor_label',$flat,''),ENT_QUOTES) ?></label>
                    <select name="vendor_id" id="vavVendor" class="form-select" required>
                        <option value=""><?= htmlspecialchars(gs('vendor_attributes.form.select_vendor',$flat,''),ENT_QUOTES) ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.form.attribute_label',$flat,''),ENT_QUOTES) ?></label>
                    <select name="attribute_id" id="vavAttribute" class="form-select" required>
                        <option value=""><?= htmlspecialchars(gs('vendor_attributes.form.select_attribute',$flat,''),ENT_QUOTES) ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(gs('vendor_attributes.form.value_label',$flat,''),ENT_QUOTES) ?></label>
                    <input type="text" name="value" id="vavValue" class="form-input" required 
                           placeholder="<?= htmlspecialchars(gs('vendor_attributes.form.value_placeholder',$flat,''),ENT_QUOTES) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="vavCancel" class="btn">
                    <?= htmlspecialchars(gs('vendor_attributes.buttons.cancel',$flat,''),ENT_QUOTES) ?>
                </button>
                <button type="submit" class="btn">
                    <?= htmlspecialchars(gs('vendor_attributes.buttons.save',$flat,''),ENT_QUOTES) ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
window.ADMIN_UI   = <?= json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE) ?>;
window.I18N_FLAT  = <?= json_encode($flat, JSON_UNESCAPED_UNICODE) ?>;
window.USER_INFO  = window.ADMIN_UI.user || {};
window.THEME      = <?= json_encode($theme, JSON_UNESCAPED_UNICODE) ?>;
window.LANG       = "<?= htmlspecialchars($lang, ENT_QUOTES) ?>";
window.DIRECTION  = "<?= htmlspecialchars($direction, ENT_QUOTES) ?>";
window.CSRF_TOKEN = "<?= $csrf ?>";

window.VAV_CONFIG = {
    apiUrl: "<?= addslashes($apiUrl) ?>",
    vendorsUrl: "<?= addslashes($vendorsApi) ?>",
    attrsUrl: "<?= addslashes($attrsApi) ?>",
    csrfToken: window.CSRF_TOKEN,
    lang: window.LANG,
    isStandalone: <?= $isStandalone ? 'true' : 'false' ?>
};
</script>
<script src="/admin/assets/js/pages/vendor_attributes_values.js" defer></script>
</body>
</html>