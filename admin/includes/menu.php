<?php
// htdocs/admin/includes/menu.php
// Production version: DB-driven sidebar menu with i18n from DB/JSON, permissions, theme, icons, titles, RTL support.

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
//////
$validPages = [
    'dashboard' => 'fragments/dashboard.php',
    'users' => 'fragments/users.php',
    'tenant' => 'fragments/tenant.php',
    'permissions' => 'fragments/permissions.php',  // ✅ Add this
    // ... other pages
];

// -----------------------
// Obtain payload & settings from DB
// -----------------------
$ui_payload = $GLOBALS['ADMIN_UI'] ?? ($ADMIN_UI_PAYLOAD ?? []);
$strings = is_array($ui_payload['strings'] ?? null) ? $ui_payload['strings'] : ([]);
$theme = $ui_payload['theme'] ?? [];
$settings = $ui_payload['system_settings'] ?? [];

// Helper to get setting value from DB
function getMenuSetting($key, $default = '') {
    global $settings;
    foreach ($settings as $s) {
        if ($s['setting_key'] === $key) return $s['setting_value'];
    }
    return $default;
}

// Helper to get theme value from DB
function getMenuThemeValue($arrayKey, $settingKey, $default = '') {
    global $theme;
    foreach ($theme[$arrayKey] ?? [] as $item) {
        if ($item['setting_key'] === $settingKey) return $item['setting_value'];
    }
    return $default;
}

// direction default ltr
$dir = $ui_payload['direction'] ?? 'ltr';
$isRtl = $dir === 'rtl';
$GLOBALS['ADMIN_UI_LANG_DIR'] = $dir;
$GLOBALS['ADMIN_UI_LANG_CODE'] = $ui_payload['lang'] ?? ($GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en');

// -----------------------
// Permission helper
// -----------------------
function _can_view($perm) {
    if (!$perm) return true;
    if (function_exists('user_can')) return user_can($perm);
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($perm, $_SESSION['permissions'], true);
    }
    return true;
}

// -----------------------
// Active detection
// -----------------------
function _is_active_item($item) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $u = $item['url'] ?? ($item['load'] ?? '');
    if (!$u) return false;
    $uri_path = parse_url($uri, PHP_URL_PATH) ?: $uri;
    $u_path = parse_url($u, PHP_URL_PATH) ?: $u;
    if ($uri_path === $u_path) return true;
    if ($u_path !== '' && strpos($uri_path, $u_path) === 0) return true;
    return false;
}

// -----------------------
// Translation helpers (updated with JSON fallback)
// -----------------------
function resolve_dot_key(array $arr, string $key) {
    if ($key === '') return null;
    if (array_key_exists($key, $arr)) return $arr[$key];
    $parts = explode('.', $key);
    $cur = $arr;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return null;
        }
        $cur = $cur[$p];
    }
    return $cur;
}

function t(string $key, $fallback = '') {
    global $strings, $ui_payload;

    if (!$key) return $fallback;

    // First, try from DB strings
    $val = resolve_dot_key($strings, $key);
    if (is_string($val) || is_numeric($val)) return (string)$val;

    // Nested strings
    if (isset($strings['strings']) && is_array($strings['strings'])) {
        $val = resolve_dot_key($strings['strings'], $key);
        if (is_string($val) || is_numeric($val)) return (string)$val;
    }

    // From ui_payload
    if (!empty($ui_payload) && is_array($ui_payload)) {
        $val = resolve_dot_key($ui_payload, $key);
        if (is_string($val) || is_numeric($val)) return (string)$val;
        if (isset($ui_payload['strings']) && is_array($ui_payload['strings'])) {
            $val = resolve_dot_key($ui_payload['strings'], $key);
            if (is_string($val) || is_numeric($val)) return (string)$val;
            if (isset($ui_payload['strings']['strings']) && is_array($ui_payload['strings']['strings'])) {
                $val = resolve_dot_key($ui_payload['strings']['strings'], $key);
                if (is_string($val) || is_numeric($val)) return (string)$val;
            }
        }
    }

    // Alt key
    $altKey = str_replace('.', '_', $key);
    $val = $strings[$altKey] ?? null;
    if (is_string($val) || is_numeric($val)) return (string)$val;

    // JSON fallback
    $lang = $GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en';
    $jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/languages/admin/' . $lang . '.json';
    static $jsonStrings = null;
    if ($jsonStrings === null && file_exists($jsonPath)) {
        $content = file_get_contents($jsonPath);
        $jsonStrings = json_decode($content, true) ?: [];
    }
    if ($jsonStrings && isset($jsonStrings[$key])) {
        return $jsonStrings[$key];
    }

    return $fallback;
}

// -----------------------
// Dynamic icon getter from DB
// -----------------------
function getMenuIcon($id, $default = '') {
    $icon = getMenuThemeValue('design_settings', 'icon_' . $id);
    if ($icon) return $icon;
    $icon = getMenuThemeValue('button_styles', 'icon_' . $id);
    if ($icon) return $icon;
    return $default;
}

// -----------------------
// Dynamic title getter from DB
// -----------------------
function getMenuTitle($id, $default = '') {
    return getMenuSetting('menu_title_' . $id, $default);
}

// -----------------------
// Render menu recursively
// -----------------------
function render_menu_items($items, $level = 0) {
    global $isRtl;
    if (!is_array($items) || empty($items)) return '';
    $ulClass = 'sidebar-list sidebar-level-' . (int)$level;
    if ($isRtl) $ulClass .= ' rtl';
    $out = '<ul class="' . h($ulClass) . '" role="' . ($level === 0 ? 'menu' : 'group') . '">';
    foreach ($items as $item) {
        $perm = $item['permission'] ?? null;
        if (!_can_view($perm)) continue;

        $hasChildren = !empty($item['children']) && is_array($item['children']);
        $active = _is_active_item($item);
        $childActive = false;
        if ($hasChildren) {
            foreach ($item['children'] as $c) {
                if (_is_active_item($c)) { $childActive = true; break; }
            }
        }

        $liClasses = [];
        if ($active) $liClasses[] = 'active';
        if ($childActive) $liClasses[] = 'open';
        if ($hasChildren) $liClasses[] = 'has-children';

        $liClassAttr = $liClasses ? ' class="' . h(implode(' ', $liClasses)) . '"' : '';
        $idAttr = isset($item['id']) ? ' data-menu-id="' . h($item['id']) . '"' : '';

        $i18nKey = $item['i18n'] ?? (isset($item['id']) ? 'nav.' . $item['id'] : '');
        $titleFallback = getMenuTitle($item['id'] ?? '', $item['title'] ?? (isset($item['id']) ? ucwords(str_replace(['_', '-'], ' ', $item['id'])) : ''));
        $titleText = t($i18nKey, $titleFallback);

        $iconHtml = getMenuIcon($item['id'] ?? '', $item['icon'] ?? '');
        if ($iconHtml) {
            $iconHtml = '<span class="sidebar-icon" aria-hidden="true">' . h($iconHtml) . '</span>';
        }

        $url = $item['url'] ?? '#';
        $load = $item['load'] ?? $url;
        $loadAttr = ' data-load-url="' . h($load) . '"';
        $ariaHasPopup = $hasChildren ? ' aria-haspopup="true"' : '';

        $out .= "<li{$liClassAttr}{$idAttr} role=\"none\">";
        $out .= '<a href="' . h($url) . '" role="menuitem" class="sidebar-link"' . $loadAttr . $ariaHasPopup . '>';
        $out .= $iconHtml;
        $out .= '<span class="sidebar-title" data-i18n="' . h($i18nKey) . '">' . h($titleText) . '</span>';
        $out .= '</a>';

        if ($hasChildren) {
            $out .= render_menu_items($item['children'], $level + 1);
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
}

// -----------------------
// Menu items (dynamic from DB where possible)
// -----------------------
$ADMIN_MENU = [
    ['id'=>'dashboard','i18n'=>'nav.dashboard','icon'=>'🏠','url'=>'/admin/dashboard.php','load'=>'/admin/dashboard.php'],
    ['id'=>'platform','i18n'=>'menu.platform','icon'=>'📁','children'=>[
        ['id'=>'vendor_attributes','i18n'=>'menu.vendor_attributes','icon'=>'🏷️','url'=>'/admin/fragments/vendor_attributes_values.php','load'=>'/admin/fragments/vendor_attributes_values.php'],
        ['id'=>'vendor_working_hours','i18n'=>'menu.vendor_working_hours','icon'=>'🕒','url'=>'/admin/fragments/vendor_working_hours.php','load'=>'/admin/fragments/vendor_working_hours.php'],
        ['id'=>'banners','i18n'=>'menu.banners','icon'=>'📢','url'=>'/admin/fragments/banners.php','load'=>'/admin/fragments/banners.php'],
    ]],
    ['id'=>'menus','i18n'=>'nav.menus','icon'=>'📋','url'=>'/admin/fragments/categories.php','load'=>'/admin/fragments/categories.php'],
    ['id'=>'tenant_users','i18n'=>'nav.tenant_users','icon'=>'🛡️','url'=>'/admin/fragments/tenant_users.php','load'=>'/admin/fragments/tenant_users.php'],
    ['id'=>'permissions','i18n'=>'nav.permissions','icon'=>'🔐','url'=>'/admin/fragments/permissions.php','load'=>'/admin/fragments/permissions.php'],
    ['id'=>'categories','i18n'=>'nav.categories','icon'=>'📂','url'=>'/admin/menus_list.php','load'=>'/admin/menus_list.php'],
    ['id'=>'products','i18n'=>'nav.products','icon'=>'📦','url'=>'/admin/fragments/products.php','load'=>'/admin/fragments/products.php'],
    ['id'=>'vendors','i18n'=>'menu.vendors','icon'=>'🏪','url'=>'/admin/fragments/tenant_categories.php','load'=>'/admin/fragments/tenant_categories.php'],
    ['id'=>'delivery_companies','i18n'=>'menu.delivery_companies','icon'=>'🚚','url'=>'/admin/fragments/IndependentDriver.php','load'=>'/admin/fragments/IndependentDriver.php'],
    ['id'=>'orders','i18n'=>'nav.orders','icon'=>'🧾','url'=>'/admin/orders.php','load'=>'/admin/orders.php'],
    ['id'=>'payments','i18n'=>'menu.payments','icon'=>'💳','url'=>'/admin/payments.php','load'=>'/admin/payments.php'],
    ['id'=>'shipping','i18n'=>'menu.shipping','icon'=>'🚛','url'=>'/admin/shipping.php','load'=>'/admin/shipping.php'],
    ['id'=>'users','i18n'=>'nav.users','icon'=>'👥','url'=>'/admin/fragments/users.php','load'=>'/admin/fragments/users.php'],
    ['id'=>'reviews','i18n'=>'menu.reviews','icon'=>'⭐','url'=>'/admin/reviews.php','load'=>'/admin/reviews.php'],
    ['id'=>'auctions','i18n'=>'menu.auctions','icon'=>'🔨','url'=>'/admin/auctions.php','load'=>'/admin/auctions.php'],
    ['id'=>'jobs','i18n'=>'menu.jobs','icon'=>'💼','url'=>'/admin/jobs.php','load'=>'/admin/jobs.php'],
    ['id'=>'coupons','i18n'=>'menu.coupons','icon'=>'🏷️','url'=>'/admin/coupons.php','load'=>'/admin/coupons.php'],
    ['id'=>'notifications','i18n'=>'menu.notifications','icon'=>'🔔','url'=>'/admin/notifications.php','load'=>'/admin/notifications.php'],
    ['id'=>'reports','i18n'=>'nav.reports','icon'=>'📈','url'=>'/admin/reports.php','load'=>'/admin/reports.php'],
    ['id'=>'support','i18n'=>'menu.support','icon'=>'🛠️','url'=>'/admin/support.php','load'=>'/admin/support.php'],
    ['id'=>'wallet','i18n'=>'menu.wallet','icon'=>'👛','url'=>'/admin/wallet.php','load'=>'/admin/wallet.php'],
    ['id'=>'settings','i18n'=>'nav.settings','icon'=>'⚙️','url'=>'/admin/settings.php','load'=>'/admin/settings.php','children'=>[
        ['id'=>'languages','i18n'=>'nav.languages','icon'=>'🌍','url'=>'/admin/fragments/languages.php','load'=>'/admin/fragments/languages.php'],
    ]],
];

// -----------------------
// Output with DB-driven styles
// -----------------------
$sidebarBg = getMenuThemeValue('color_settings', 'sidebar_background');
$sidebarText = getMenuThemeValue('color_settings', 'sidebar_text');
echo '<style>
.sidebar-list { background-color: ' . h($sidebarBg ?: '#4B0082') . '; color: ' . h($sidebarText ?: '#FFFFFF') . '; }
' . ($isRtl ? '.sidebar-list.rtl { direction: rtl; text-align: right; }' : '') . '
</style>';
echo render_menu_items($ADMIN_MENU, 0);