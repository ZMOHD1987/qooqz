<?php
/**
 * Unified Admin Sidebar – Always stays on /admin/dashboard.php
 * - All navigation uses AJAX, never changes browser URL.
 * - Menu items are sorted by numeric 'order' (recursively).
 * - Modern icons via Font Awesome 6.
 * - Colors fetched from DB theme settings.
 * - RTL & i18n ready.
 */

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// -----------------------
// Obtain payload & settings from DB
// -----------------------
$ui_payload = $GLOBALS['ADMIN_UI'] ?? ($ADMIN_UI_PAYLOAD ?? []);
$strings = is_array($ui_payload['strings'] ?? null) ? $ui_payload['strings'] : [];
$theme = $ui_payload['theme'] ?? [];
$settings = $ui_payload['system_settings'] ?? [];
$dir = $ui_payload['direction'] ?? 'ltr';
$isRtl = $dir === 'rtl';
$GLOBALS['ADMIN_UI_LANG_DIR'] = $dir;
$GLOBALS['ADMIN_UI_LANG_CODE'] = $ui_payload['lang'] ?? ($GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en');

// -----------------------
// Helpers
// -----------------------
function getMenuSetting($key, $default = '') {
    global $settings;
    foreach ($settings as $s) {
        if ($s['setting_key'] === $key) return $s['setting_value'];
    }
    return $default;
}

function getMenuThemeValue($arrayKey, $settingKey, $default = '') {
    global $theme;
    foreach ($theme[$arrayKey] ?? [] as $item) {
        if ($item['setting_key'] === $settingKey) return $item['setting_value'];
    }
    return $default;
}

function _can_view($perm) {
    if (!$perm) return true;
    if (function_exists('user_can')) return user_can($perm);
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($perm, $_SESSION['permissions'], true);
    }
    return true;
}

function resolve_dot_key(array $arr, string $key) {
    if ($key === '') return null;
    if (array_key_exists($key, $arr)) return $arr[$key];
    $parts = explode('.', $key);
    $cur = $arr;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return null;
        $cur = $cur[$p];
    }
    return $cur;
}

function t(string $key, $fallback = '') {
    global $strings, $ui_payload;
    if (!$key) return $fallback;
    $val = resolve_dot_key($strings, $key);
    if (is_string($val) || is_numeric($val)) return (string)$val;
    $altKey = str_replace('.', '_', $key);
    $val = $strings[$altKey] ?? null;
    if (is_string($val) || is_numeric($val)) return (string)$val;
    $lang = $GLOBALS['ADMIN_UI_LANG_CODE'] ?? 'en';
    $jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/languages/admin/' . $lang . '.json';
    static $jsonStrings = null;
    if ($jsonStrings === null && file_exists($jsonPath)) {
        $jsonStrings = json_decode(file_get_contents($jsonPath), true) ?: [];
    }
    if ($jsonStrings && isset($jsonStrings[$key])) return $jsonStrings[$key];
    return $fallback;
}

function getMenuIcon($id, $default = '') {
    $icon = getMenuThemeValue('design_settings', 'icon_' . $id);
    if ($icon) return $icon;
    $icon = getMenuThemeValue('button_styles', 'icon_' . $id);
    if ($icon) return $icon;
    return $default;
}

function getMenuTitle($id, $default = '') {
    return getMenuSetting('menu_title_' . $id, $default);
}

/**
 * Recursively sort menu items by the 'order' key.
 */
function sortMenuByOrder(&$items) {
    usort($items, function($a, $b) {
        $orderA = $a['order'] ?? PHP_INT_MAX;
        $orderB = $b['order'] ?? PHP_INT_MAX;
        return $orderA - $orderB;
    });
    foreach ($items as &$item) {
        if (!empty($item['children'])) {
            sortMenuByOrder($item['children']);
        }
    }
}

/**
 * Renders menu items.
 * - Parent items (with children) have href="#" and js-toggle class.
 * - Leaf items have js-ajax-link class and data-load-url attribute.
 */
function render_menu_items($items, $level = 0) {
    global $isRtl;
    if (!is_array($items) || empty($items)) return '';
    $ulClass = 'sidebar-list level-' . (int)$level;
    if ($isRtl) $ulClass .= ' rtl';
    $out = '<ul class="' . h($ulClass) . '" role="' . ($level === 0 ? 'menu' : 'group') . '">';
    foreach ($items as $item) {
        if (!_can_view($item['permission'] ?? null)) continue;
        $hasChildren = !empty($item['children']) && is_array($item['children']);
        $href = $hasChildren ? '#' : h($item['url'] ?? '#');
        $titleFallback = getMenuTitle($item['id'] ?? '', $item['title'] ?? (isset($item['id']) ? ucwords(str_replace(['_', '-'], ' ', $item['id'])) : ''));
        $i18nKey = $item['i18n'] ?? (isset($item['id']) ? 'nav.' . $item['id'] : '');
        $titleText = t($i18nKey, $titleFallback);
        
        // Icon handling
        $iconHtml = '';
        $dbIcon = getMenuIcon($item['id'] ?? '', '');
        if ($dbIcon) {
            $iconHtml = '<span class="sidebar-icon" aria-hidden="true">' . h($dbIcon) . '</span>';
        } elseif (!empty($item['icon_class'])) {
            $iconHtml = '<i class="' . h($item['icon_class']) . ' sidebar-icon" aria-hidden="true"></i>';
        } elseif (!empty($item['icon'])) {
            $iconHtml = '<span class="sidebar-icon" aria-hidden="true">' . h($item['icon']) . '</span>';
        }
        
        $out .= '<li class="menu-item' . ($hasChildren ? ' has-children' : '') . '">';
        $out .= '<a href="' . $href . '" class="sidebar-link' . ($hasChildren ? ' js-toggle' : ' js-ajax-link') . '" data-load-url="' . h($item['url'] ?? '') . '" role="menuitem">';
        $out .= $iconHtml;
        $out .= '<span class="sidebar-title" data-i18n="' . h($i18nKey) . '">' . h($titleText) . '</span>';
        if ($hasChildren) {
            $out .= '<span class="arrow">' . ($isRtl ? '❮' : '❯') . '</span>';
        }
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
// Menu definition (ordered categories)
// -----------------------
$ADMIN_MENU = [

    // -----------------------
    // Dashboard
    // -----------------------
    [
        'id' => 'dashboard',
        'order' => 1,
        'icon_class' => 'fas fa-home',
        'i18n' => 'nav.dashboard',
        'url' => 'dashboard.php',
    ],

    // -----------------------
    // Users & Access
    // -----------------------
    [
        'id' => 'users_access',
        'order' => 10,
        'icon_class' => 'fas fa-user-shield',
        'i18n' => 'menu.users_access',
        'children' => [
            ['id'=>'tenant_users','order'=>10,'icon_class'=>'fas fa-users','i18n'=>'nav.tenant_users','url'=>'fragments/tenant_users.php'],
            ['id'=>'users','order'=>20,'icon_class'=>'fas fa-user','i18n'=>'nav.users','url'=>'fragments/users.php'],
            ['id'=>'permissions','order'=>30,'icon_class'=>'fas fa-lock','i18n'=>'nav.permissions','url'=>'fragments/permissions.php'],
        ]
    ],

    // -----------------------
    // Catalog
    // -----------------------
    [
        'id' => 'catalog',
        'order' => 20,
        'icon_class' => 'fas fa-boxes',
        'i18n' => 'menu.catalog',
        'children' => [
            ['id'=>'products','order'=>10,'icon_class'=>'fas fa-box','i18n'=>'nav.products','url'=>'fragments/products.php'],
            ['id'=>'categories','order'=>20,'icon_class'=>'fas fa-list','i18n'=>'nav.menus','url'=>'fragments/categories.php'],
            ['id'=>'categories','order'=>20,'icon_class'=>'fas fa-list','i18n'=>'nav.menus','url'=>'fragments/media_studio.php'],
            ['id'=>'categories','order'=>20,'icon_class'=>'fas fa-list','i18n'=>'nav.menus','url'=>'fragments/tenant_categories.php'],
            ['id'=>'brands','order'=>30,'icon_class'=>'fas fa-tags','i18n'=>'brands','url'=>'fragments/brands.php'],
        ]
    ],

    // -----------------------
    // Sales
    // -----------------------
    [
        'id' => 'sales',
        'order' => 30,
        'icon_class' => 'fas fa-cash-register',
        'i18n' => 'menu.sales',
        'children' => [
            ['id'=>'pos','order'=>10,'icon_class'=>'fas fa-cash-register','i18n'=>'nav.pos','url'=>'fragments/pos.php'],
            ['id'=>'discounts','order'=>20,'icon_class'=>'fas fa-percent','i18n'=>'discounts','url'=>'fragments/discounts.php'],
            ['id'=>'flash_sales','order'=>30,'icon_class'=>'fas fa-bolt','i18n'=>'Flash Sales','url'=>'fragments/flash_sales.php'],
            ['id'=>'carts','order'=>40,'icon_class'=>'fas fa-shopping-cart','i18n'=>'carts','url'=>'fragments/carts.php'],
        ]
    ],

    // -----------------------
    // Orders & Logistics
    // -----------------------
    [
        'id' => 'logistics',
        'order' => 40,
        'icon_class' => 'fas fa-truck',
        'i18n' => 'menu.logistics',
        'children' => [
            ['id'=>'delivery','order'=>10,'icon_class'=>'fas fa-truck','i18n'=>'menu.delivery','url'=>'fragments/delivery.php'],
            ['id'=>'auctions','order'=>20,'icon_class'=>'fas fa-gavel','i18n'=>'menu.auctions','url'=>'fragments/auctions.php'],
            ['id'=>'stock_movements','order'=>30,'icon_class'=>'fas fa-warehouse','i18n'=>'Stock Movements','url'=>'fragments/stock_movements.php'],
            ['id'=>'returns','order'=>40,'icon_class'=>'fas fa-undo','i18n'=>'menu.returns','url'=>'fragments/returns.php'],
        ]
    ],

    // -----------------------
    // Finance
    // -----------------------
    [
        'id' => 'finance',
        'order' => 50,
        'icon_class' => 'fas fa-money-bill-wave',
        'i18n' => 'menu.finance',
        'children' => [
            ['id'=>'escrow','order'=>10,'icon_class'=>'fas fa-handshake','i18n'=>'menu.escro','url'=>'fragments/escrow.php'],
            ['id'=>'commissions','order'=>20,'icon_class'=>'fas fa-percentage','i18n'=>'Commissions','url'=>'fragments/commissions.php'],
            ['id'=>'subscriptions','order'=>30,'icon_class'=>'fas fa-sync','i18n'=>'Subscriptions','url'=>'fragments/subscriptions.php'],
            ['id'=>'plan_selection','order'=>40,'icon_class'=>'fas fa-list-check','i18n'=>'plan_selection','url'=>'fragments/plan_selection.php'],
        ]
    ],

    // -----------------------
    // Content & Marketing
    // -----------------------
    [
        'id' => 'marketing',
        'order' => 60,
        'icon_class' => 'fas fa-bullhorn',
        'i18n' => 'menu.marketing',
        'children' => [
            ['id'=>'ads','order'=>10,'icon_class'=>'fas fa-ad','i18n'=>'ads','url'=>'fragments/ads.php'],
            ['id'=>'banners','order'=>20,'icon_class'=>'fas fa-image','i18n'=>'menu.banners','url'=>'fragments/banners.php'],
            ['id'=>'seo_meta','order'=>30,'icon_class'=>'fas fa-search','i18n'=>'Seo Meta','url'=>'fragments/seo_meta.php'],
            ['id'=>'notifications','order'=>40,'icon_class'=>'fas fa-bell','i18n'=>'menu.notifications','url'=>'fragments/notification.php'],
        ]
    ],

    // -----------------------
    // Support
    // -----------------------
    [
        'id' => 'support',
        'order' => 70,
        'icon_class' => 'fas fa-life-ring',
        'i18n' => 'menu.support',
        'children' => [
            ['id'=>'tickets','order'=>10,'icon_class'=>'fas fa-ticket-alt','i18n'=>'menu.tickets','url'=>'fragments/tickets.php'],
            ['id'=>'ticket_categories','order'=>20,'icon_class'=>'fas fa-tags','i18n'=>'menu.ticket_categories','url'=>'fragments/ticket_categories.php'],
            ['id'=>'bad_words','order'=>30,'icon_class'=>'fas fa-ban','i18n'=>'bad_words','url'=>'fragments/bad_words.php'],
        ]
    ],

    // -----------------------
    // Business / Entities
    // -----------------------
    [
        'id' => 'business',
        'order' => 80,
        'icon_class' => 'fas fa-building',
        'i18n' => 'menu.business',
        'children' => [
            ['id'=>'entities','order'=>10,'icon_class'=>'fas fa-building','i18n'=>'menu.entities','url'=>'fragments/entities.php'],
            ['id'=>'entities_payment','order'=>20,'icon_class'=>'fas fa-credit-card','i18n'=>'entities_Payment','url'=>'fragments/entities_Payment.php'],
            ['id'=>'jobs','order'=>30,'icon_class'=>'fas fa-briefcase','i18n'=>'menu.jobs','url'=>'fragments/jobs.php'],
            ['id'=>'job_categories','order'=>40,'icon_class'=>'fas fa-sitemap','i18n'=>'job_categories','url'=>'fragments/job_categories.php'],
        ]
    ],

    // -----------------------
    // System
    // -----------------------
    [
        'id' => 'system',
        'order' => 90,
        'icon_class' => 'fas fa-cogs',
        'i18n' => 'menu.system',
        'children' => [
            ['id'=>'tenant','order'=>10,'icon_class'=>'fas fa-store','i18n'=>'menu.tenant','url'=>'fragments/tenant.php'],
            ['id'=>'themes','order'=>20,'icon_class'=>'fas fa-palette','i18n'=>'nav.settings','url'=>'fragments/themes.php'],
            ['id'=>'addresses','order'=>30,'icon_class'=>'fas fa-map-marker-alt','i18n'=>'nav.addresses','url'=>'fragments/addresses.php'],
            ['id'=>'queues','order'=>40,'icon_class'=>'fas fa-stream','i18n'=>'queues','url'=>'fragments/queues.php'],
        ]
    ],

];

// Sort menu (including children) by order
sortMenuByOrder($ADMIN_MENU);

// -----------------------
// Dynamic CSS from DB
// -----------------------
$sidebarBg = getMenuThemeValue('color_settings', 'sidebar_background', '#1f2937');
$sidebarText = getMenuThemeValue('color_settings', 'sidebar_text', '#9ca3af');
$sidebarHover = getMenuThemeValue('color_settings', 'sidebar_hover', '#374151');
$sidebarActive = getMenuThemeValue('color_settings', 'sidebar_active', '#2563eb');
$primaryColor = getMenuThemeValue('color_settings', 'primary_color', '#3b82f6');
$dangerColor = getMenuThemeValue('color_settings', 'danger_color', '#ef4444');

echo '<style>
:root {
    --sidebar-bg: ' . h($sidebarBg) . ';
    --sidebar-text: ' . h($sidebarText) . ';
    --sidebar-hover: ' . h($sidebarHover) . ';
    --sidebar-active: ' . h($sidebarActive) . ';
    --primary-color: ' . h($primaryColor) . ';
    --danger-color: ' . h($dangerColor) . ';
}

.admin-sidebar-nav {
    width: 260px;
    background-color: var(--sidebar-bg);
    color: var(--sidebar-text);
    min-height: 100vh;
    user-select: none;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

.sidebar-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: inherit;
    text-decoration: none;
    transition: 0.2s;
    border-radius: 8px;
    margin: 2px 10px;
    gap: 12px;
}

.sidebar-link:hover {
    background-color: var(--sidebar-hover);
    color: #fff;
}

.sidebar-icon {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
}

.sidebar-title {
    flex: 1;
}

.arrow {
    margin-left: auto;
    font-size: 0.7rem;
    transition: transform 0.3s;
}

/* Submenus */
.level-1 {
    display: none;
    background: rgba(0,0,0,0.2);
    padding: 5px 0;
}

.menu-item.has-children.is-open > .level-1 {
    display: block;
}

.menu-item.has-children.is-open > .sidebar-link .arrow {
    transform: rotate(90deg);
}

.level-1 .sidebar-link {
    padding-left: 45px;
    font-size: 0.9rem;
}

/* Active page highlighting */
.sidebar-link.active {
    background-color: var(--sidebar-active);
    color: #fff;
}

/* RTL adjustments */
' . ($isRtl ? '
.rtl .sidebar-icon {
    margin-right: 0;
    margin-left: 12px;
}
.rtl .arrow {
    margin-left: 0;
    margin-right: auto;
}
.rtl .level-1 .sidebar-link {
    padding-right: 45px;
    padding-left: 20px;
}
' : '') . '
</style>';

// -----------------------
// Render the sidebar
// -----------------------
echo '<nav class="admin-sidebar-nav ' . ($isRtl ? 'rtl' : '') . '">';
echo render_menu_items($ADMIN_MENU, 0);
echo '</nav>';

// -----------------------
// JavaScript: Toggle categories & load fragments without changing URL
// -----------------------
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Toggle expand/collapse for categories
    document.querySelectorAll(".js-toggle").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();     // Prevents any URL change (href="#")
            e.stopPropagation();
            this.closest(".menu-item").classList.toggle("is-open");
        });
    });

    // 2. AJAX loading for fragment links – NEVER changes the browser URL
    document.querySelectorAll(".js-ajax-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();     // Prevents navigation away from dashboard.php
            const url = this.getAttribute("data-load-url");
            if (!url) return;
            
            // Use the global fragment loader (adjust function name as needed)
            if (typeof window.loadAdminFragment === "function") {
                window.loadAdminFragment(url);
            } else if (typeof window.loadPage === "function") {
                window.loadPage(url);
            } else {
                console.warn("No AJAX loader found. Falling back to full page load.");
                // Fallback: do NOT change URL, but load the fragment manually
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const mainContent = document.getElementById("main-content");
                        if (mainContent) mainContent.innerHTML = html;
                    });
            }
            
            // Highlight the active link
            document.querySelectorAll(".sidebar-link").forEach(el => el.classList.remove("active"));
            this.classList.add("active");
        });
    });
});
</script>';