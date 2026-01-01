<?php
declare(strict_types=1);
/**
 * admin/dashboard.php
 * Dashboard page integrated with header.php and server-driven i18n.
 *
 * - Uses $GLOBALS['ADMIN_UI'] injected by includes/header.php
 * - All visible strings use data-i18n attributes for client-side translation
 * - Provides safe server-side fallbacks via s() when translations aren't injected yet
 */

require_once __DIR__ . '/includes/header.php';

// safe escape helper
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// server-side strings fallback accessor (safe)
$payload = $GLOBALS['ADMIN_UI'] ?? ($ADMIN_UI_PAYLOAD ?? []);
$S = is_array($payload['strings'] ?? null) ? $payload['strings'] : [];

/**
 * s(key, default) - server-side fallback lookup for key like "dashboard_title" or "nav.products"
 */
function s(string $key, $def = '') {
    global $S;
    if ($key === '') return $def;
    // try dotted lookup
    $parts = preg_split('/[\\.\\_]/', $key);
    $cur = $S;
    foreach ($parts as $p) {
        if (is_array($cur) && array_key_exists($p, $cur)) {
            $cur = $cur[$p];
        } else {
            $cur = null;
            break;
        }
    }
    if ($cur !== null && $cur !== '') return (string)$cur;
    // direct flat key
    if (isset($S[$key]) && is_scalar($S[$key])) return (string)$S[$key];
    return $def;
}

// current language for meta hint
$lang = $payload['lang'] ?? ($_SESSION['preferred_language'] ?? 'en');

// default fragment (set to '' to disable auto-load)
$defaultLoad = '/admin/fragments/menus_list.php';

// permission example for drivers
$canViewDrivers = false;
if (function_exists('user_has')) {
    $canViewDrivers = user_has('view_drivers');
} else {
    $perms = $_SESSION['permissions'] ?? [];
    $canViewDrivers = in_array('view_drivers', $perms, true) || (($_SESSION['role_id'] ?? 0) == 1);
}
$embedDrivers = !empty($_GET['embed']);
?>
<meta data-page="dashboard"
      data-i18n-files="/languages/admin/<?php echo rawurlencode($lang); ?>.json,/languages/Dashboard/<?php echo rawurlencode($lang); ?>.json">

<section class="container" style="padding:18px 0;">
  <h1 class="page-title" data-i18n="dashboard_title"><?php echo h(s('dashboard_title', 'Admin dashboard')); ?></h1>
  <p class="text-muted" data-i18n="dashboard_subtitle"><?php echo h(s('dashboard_subtitle', 'Use the sidebar to navigate.')); ?></p>
</section>

<section class="container" style="padding:18px 0;">
  <div style="margin-top:6px;padding:18px;background:#f8fafc;border-radius:10px;border:1px solid rgba(2,6,23,0.04);">
    <div style="display:flex;gap:12px;align-items:flex-start;">
      <div style="font-size:28px;line-height:1;">🧭</div>
      <div>
        <h3 style="margin:0 0 8px 0;" data-i18n="welcome_title"><?php echo h(s('welcome_title', 'Welcome')); ?></h3>
        <p class="text-muted" style="margin:0;" data-i18n="welcome_message">
          <?php echo h(s('welcome_message', 'This admin panel lets you manage the site.')); ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="container" style="padding:18px 0;">
  <div style="margin-top:20px;padding:20px;background:#eef2ff;border:1px solid #c7e0ff;border-radius:8px;">
    <h3 style="margin:0 0 10px 0;color:#0b6ea8;" data-i18n="test_instructions_title"><?php echo h(s('test_instructions_title','Test instructions')); ?></h3>
    <ol style="margin:0;padding-left:20px;line-height:1.8;">
      <li data-i18n="test_step_1"><strong data-i18n="test_step_1_heading"><?php echo h(s('test_step_1_heading','Open devtools')); ?></strong> (F12)</li>
      <li data-i18n="test_step_2"><?php echo h(s('test_step_2','Look for console messages starting with ===')); ?></li>
      <li data-i18n="test_step_3"><?php echo h(s('test_step_3','Toggle the sidebar using the button')); ?></li>
      <li data-i18n="test_step_4"><?php echo h(s('test_step_4','Check network tab for failed fragment loads')); ?></li>
      <li data-i18n="test_step_5"><?php echo h(s('test_step_5','Run testSidebar() to toggle via console')); ?></li>
    </ol>
  </div>
</section>

<section class="container quick-actions" style="padding:18px 0;">
  <h2 data-i18n="quick_actions_title"><?php echo h(s('quick_actions_title','Quick actions')); ?></h2>
  <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:12px;">
    <a class="card" href="/admin/products.php" data-load-url="/admin/products.php" style="text-decoration:none;">
      <div class="card-icon">📦</div>
      <div>
        <h3 data-i18n="nav.products"><?php echo h(s('nav.products','Products')); ?></h3>
        <p class="text-muted" data-i18n="manage_products"><?php echo h(s('manage_products','Manage products')); ?></p>
      </div>
    </a>

    <a class="card" href="/admin/menus_list.php" data-load-url="/admin/fragments/menus_list.php" style="text-decoration:none;">
      <div class="card-icon">📁</div>
      <div>
        <h3 data-i18n="nav.categories"><?php echo h(s('nav.categories','Categories')); ?></h3>
        <p class="text-muted" data-i18n="manage_categories"><?php echo h(s('manage_categories','Organize categories')); ?></p>
      </div>
    </a>

    <a class="card" href="/admin/users.php" data-load-url="/admin/users.php" style="text-decoration:none;">
      <div class="card-icon">👥</div>
      <div>
        <h3 data-i18n="nav.users"><?php echo h(s('nav.users','Users')); ?></h3>
        <p class="text-muted" data-i18n="manage_users"><?php echo h(s('manage_users','Manage users')); ?></p>
      </div>
    </a>

    <?php if ($canViewDrivers): ?>
    <a class="card" href="/admin/fragments/IndependentDriver.php" data-load-url="/admin/fragments/IndependentDriver.php" style="text-decoration:none;">
      <div class="card-icon">🚚</div>
      <div>
        <h3 data-i18n="nav.drivers"><?php echo h(s('nav.drivers','Drivers')); ?></h3>
        <p class="text-muted" data-i18n="manage_drivers"><?php echo h(s('manage_drivers','Manage drivers')); ?></p>
      </div>
    </a>
    <?php endif; ?>
  </div>
</section>

<section class="container" style="padding:18px 0;">
  <h2 data-i18n="theme_colors_title"><?php echo h(s('theme_colors_title','Theme Colors')); ?></h2>
  <p class="text-muted" data-i18n="theme_colors_subtitle"><?php echo h(s('theme_colors_subtitle','Preview and manage theme colors from the database')); ?></p>
  
  <div id="colorSliderContainer" data-color-slider style="margin-top:16px;"></div>
</section>

<?php if ($embedDrivers && $canViewDrivers): ?>
<meta data-page="independent_driver" data-assets-js="/admin/assets/js/pages/IndependentDriver.js"
      data-i18n-files="/languages/admin/<?php echo rawurlencode($lang); ?>.json,/languages/IndependentDriver/<?php echo rawurlencode($lang); ?>.json">
<?php
$fragFile = __DIR__ . '/fragments/IndependentDriver.php';
if (is_readable($fragFile)) {
    include $fragFile;
} else {
    echo '<div class="container" style="padding:20px;color:#c0392b;">IndependentDriver fragment not found</div>';
}
?>
<?php endif; ?>

<script>
console.log('=== DASHBOARD: page loaded ===');

// apply translations (tries a few loaders)
(function applyTranslations() {
    try {
        if (window.I18nLoader && typeof window.I18nLoader.translateFragment === 'function') {
            window.I18nLoader.translateFragment(document);
            console.log('Translations applied via I18nLoader');
            return;
        }
        if (window.AdminI18n && typeof window.AdminI18n.translateFragment === 'function') {
            window.AdminI18n.translateFragment(document);
            console.log('Translations applied via AdminI18n');
            return;
        }
        if (window._admin && typeof window._admin.applyTranslations === 'function') {
            window._admin.applyTranslations(document);
            console.log('Translations applied via _admin');
        }
    } catch (e) {
        console.warn('Translation apply error', e);
    }
})();

// Initialize color slider
(function initColorSlider() {
    console.log('=== DASHBOARD: initializing color slider ===');
    try {
        if (window.ColorSlider && typeof window.ColorSlider.render === 'function') {
            var container = document.getElementById('colorSliderContainer');
            if (container) {
                ColorSlider.render(container, {
                    onSelect: function(color) {
                        console.log('Color selected:', color);
                        // Optionally show a notification or perform other actions
                    }
                });
                console.log('Color slider initialized successfully');
            } else {
                console.warn('Color slider container not found');
            }
        } else {
            console.warn('ColorSlider not available, retrying in 100ms');
            setTimeout(initColorSlider, 100);
        }
    } catch (e) {
        console.error('Color slider initialization error', e);
    }
})();

window.testSidebar = function() {
    console.log('=== TEST: toggle sidebar ===');
    var toggle = document.getElementById('sidebarToggle');
    if (toggle) toggle.click();
};

// load default fragment (if configured)
(function loadDefaultFragment() {
    var defaultUrl = <?php echo json_encode($defaultLoad, JSON_UNESCAPED_UNICODE); ?>;
    var targetSelector = '#adminMainContent';
    if (!defaultUrl) return;
    if (!document.querySelector(targetSelector)) return;
    if (window.Admin && typeof window.Admin.fetchAndInsert === 'function') {
        window.Admin.fetchAndInsert(defaultUrl, targetSelector)
            .then(function(){ console.log('Default fragment loaded:', defaultUrl); })
            .catch(function(err){ console.warn('Failed to load default fragment', err); });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>