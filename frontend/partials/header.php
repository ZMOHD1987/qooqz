<?php
/**
 * Frontend Header Partial — QOOQZ Global Public Interface
 * Requires: frontend/includes/public_context.php (or $GLOBALS['PUB_CONTEXT'])
 * Supports: RTL/LTR, mobile-first, dynamic theme colors, all-language t() system
 */

// Resolve context
$_ctx  = $GLOBALS['PUB_CONTEXT'] ?? [];
$lang  = $_ctx['lang'] ?? 'ar';
$dir   = $_ctx['dir']  ?? 'rtl';
$theme = $_ctx['theme'] ?? [];
$_seo  = $_ctx['seo']  ?? [];
$_user = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, array $r = []): string { return $key; }
}

// Nav items using translation system
$_navItems = [
    t('nav.home')       => $_basePath . '/index.php',
    t('nav.products')   => $_basePath . '/products.php',
    t('nav.categories') => $_basePath . '/categories.php',
    t('nav.offers')     => $_basePath . '/discounts.php',
    t('nav.jobs')       => $_basePath . '/jobs.php',
    t('nav.entities')   => $_basePath . '/entities.php',
    t('nav.tenants')    => $_basePath . '/tenants.php',
    t('nav.auctions')  => $_basePath . '/auctions.php',
];
// Orders link only for logged-in users
if (!empty($GLOBALS['PUB_CONTEXT']['user']['id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['user']['id'])) {
    $_navItems[t('nav.orders')] = $_basePath . '/orders.php';
}
$_cartUrl   = $_basePath . '/cart.php';
$_cartLabel = e(t('nav.cart'));

// Font: Cairo for Arabic/RTL, Inter for LTR
$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($theme['primary'] ?? '#FF0000') ?>">

    <?php
    // Merge PUB_SEO global (set by each page) with header-level SEO defaults
    $_pubSeo  = $GLOBALS['PUB_SEO'] ?? [];
    $_robots  = $_pubSeo['robots']       ?? 'index,follow';
    $_keywords= $_pubSeo['keywords']     ?? '';
    $_canonical = $_pubSeo['canonical_url'] ?? '';
    $_ogTitle = $_pubSeo['og_title']     ?? $_pageTitle;
    $_ogDesc  = $_pubSeo['og_description'] ?? $_pageDesc;
    $_ogImage = $_pubSeo['og_image']     ?? ($theme['logo_url'] ?? '');
    $_ogType  = $_pubSeo['og_type']      ?? 'website';
    $_siteUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'hcsfcs.top');
    $_curUrl  = $_siteUrl . ($_SERVER['REQUEST_URI'] ?? '/');
    if (!$_canonical) $_canonical = $_curUrl;
    ?>
    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?><meta name="description" content="<?= e($_pageDesc) ?>"><?php endif; ?>
    <?php if ($_keywords): ?><meta name="keywords" content="<?= e($_keywords) ?>"><?php endif; ?>
    <meta name="robots" content="<?= e($_robots) ?>">
    <link rel="canonical" href="<?= e($_canonical) ?>">

    <!-- Open Graph -->
    <meta property="og:type"        content="<?= e($_ogType) ?>">
    <meta property="og:title"       content="<?= e($_ogTitle ?: $_pageTitle) ?>">
    <meta property="og:description" content="<?= e($_ogDesc ?: $_pageDesc) ?>">
    <meta property="og:url"         content="<?= e($_curUrl) ?>">
    <meta property="og:site_name"   content="<?= e($_appName) ?>">
    <?php if ($_ogImage): ?><meta property="og:image" content="<?= e(strpos($_ogImage,'http')===0 ? $_ogImage : $_siteUrl.$_ogImage) ?>"><?php endif; ?>
    <meta property="og:locale"      content="<?= e(str_replace('-','_',$lang)) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= e($_ogTitle ?: $_pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($_ogDesc ?: $_pageDesc) ?>">
    <?php if ($_ogImage): ?><meta name="twitter:image" content="<?= e(strpos($_ogImage,'http')===0 ? $_ogImage : $_siteUrl.$_ogImage) ?>"><?php endif; ?>

    <?php
    // JSON-LD Schema Markup — use admin-configured schema_markup if available,
    // otherwise auto-generate from PUB_SEO data (Product, WebSite, etc.)
    $_schemaMarkup = $_pubSeo['schema_markup'] ?? '';
    if (!$_schemaMarkup && !empty($_pubSeo['schema_type'])) {
        // Auto-generate Product schema
        if ($_pubSeo['schema_type'] === 'Product') {
            $__schema = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Product',
                'name'        => $_pubSeo['schema_name']  ?? $_pageTitle,
                'description' => $_pubSeo['schema_description'] ?? '',
                'sku'         => $_pubSeo['schema_sku'] ?? '',
                'url'         => $_curUrl,
            ];
            if (!empty($_pubSeo['schema_image'])) $__schema['image'] = $_siteUrl . $_pubSeo['schema_image'];
            if (!empty($_pubSeo['schema_price'])) {
                $__schema['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => (string)$_pubSeo['schema_price'],
                    'priceCurrency' => $_pubSeo['schema_currency'] ?? 'USD',
                    'availability'  => $_pubSeo['schema_availability'] ?? 'https://schema.org/InStock',
                    'url'           => $_curUrl,
                ];
            }
            if (!empty($_pubSeo['schema_rating'])) {
                $__schema['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $_pubSeo['schema_rating']['ratingValue'],
                    'reviewCount' => $_pubSeo['schema_rating']['reviewCount'],
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ];
            }
            $_schemaMarkup = json_encode($__schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        } elseif ($_pubSeo['schema_type'] === 'WebSite') {
            $_schemaMarkup = json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => $_appName,
                'url'      => $_siteUrl,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        } elseif ($_pubSeo['schema_type'] === 'LocalBusiness') {
            $__schema = [
                '@context' => 'https://schema.org',
                '@type'    => 'LocalBusiness',
                'name'     => $_pubSeo['schema_name'] ?? $_pageTitle,
                'url'      => !empty($_pubSeo['schema_url']) ? $_pubSeo['schema_url'] : $_curUrl,
            ];
            if (!empty($_pubSeo['schema_phone'])) $__schema['telephone'] = $_pubSeo['schema_phone'];
            if (!empty($_pubSeo['schema_email'])) $__schema['email'] = $_pubSeo['schema_email'];
            if (!empty($_pubSeo['og_image']))     $__schema['image'] = $_siteUrl . $_pubSeo['og_image'];
            $_schemaMarkup = json_encode($__schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        } elseif ($_pubSeo['schema_type'] === 'ItemList') {
            $_schemaMarkup = json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'name'     => $_pageTitle,
                'url'      => $_curUrl,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        }
    }
    if (!$_schemaMarkup) {
        // Default WebSite schema for all pages
        $_schemaMarkup = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $_appName,
            'url'      => $_siteUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
    ?>
    <script type="application/ld+json"><?= $_schemaMarkup ?></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= e($_fontUrl) ?>" rel="stylesheet">

    <!-- Public UI styles — ?v= cache-busting: forces browser to re-download on every deploy -->
    <?php
    $_pubCssV = @filemtime(FRONTEND_BASE . '/assets/css/public.css') ?: '1';
    $_pubHjV  = @filemtime(FRONTEND_BASE . '/assets/js/homepage-engine.js') ?: '1';
    ?>
    <link rel="stylesheet" href="/frontend/assets/css/public.css?v=<?= $_pubCssV ?>">
    <!-- Homepage section engine (loaded early so init() can be called inline) -->
    <script src="/frontend/assets/js/homepage-engine.js?v=<?= $_pubHjV ?>"></script>

    <!-- Theme: inject CSS variables from DB color_settings -->
    <?php if (!empty($theme)): ?>
    <style id="pubThemeVars">
    :root {
        --pub-primary:    <?= e($theme['primary']    ?? '#FF0000') ?>;
        --pub-secondary:  <?= e($theme['secondary']  ?? '#10B981') ?>;
        --pub-accent:     <?= e($theme['accent']     ?? '#F59E0B') ?>;
        --pub-bg:         <?= e($theme['background'] ?? '#0d0d0d') ?>;
        --pub-surface:    <?= e($theme['surface']    ?? '#1a1a2e') ?>;
        --pub-text:       <?= e($theme['text']       ?? '#FFFFFF') ?>;
        --pub-muted:      <?= e($theme['text_muted'] ?? '#B0B0B0') ?>;
        --pub-border:     <?= e($theme['border']     ?? '#333333') ?>;
        --pub-header-bg:  <?= e($theme['header_bg']        ?? $theme['primary'] ?? '#1e2533') ?>;
        --pub-header-text: <?= e($theme['header_text_color'] ?? '#FFFFFF') ?>;
        --pub-footer-bg:  <?= e($theme['footer_bg']        ?? '#1e2a38') ?>;
        --pub-footer-text:<?= e($theme['footer_text_color'] ?? '#B0B0B0') ?>;
        --pub-card:       <?= e($theme['surface']    ?? '#1a1a2e') ?>;
    }
    .pub-header  { background: var(--pub-header-bg) !important; color: var(--pub-header-text) !important; }
    .pub-hero    { background: linear-gradient(135deg, var(--pub-header-bg) 0%, var(--pub-accent) 100%) !important; }
    </style>
    <?php if (!empty($theme['generated_css'])): ?>
    <style id="pubDynamicTheme"><?= $theme['generated_css'] ?></style>
    <?php endif; ?>
    <script type="application/json" id="pubThemeData"><?= json_encode($theme, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <?php
    // Inject PHP session user so JS can show username even when localStorage is empty
    // (happens when user logged in via admin panel instead of frontend login.php).
    // Only safe non-sensitive fields are exposed: id, name, username, email.
    $_phpUser = null;
    if ($_isLoggedIn && !empty($_user['id'])) {
        $_phpUser = [
            'id'       => (int)$_user['id'],
            'name'     => $_user['name'] ?? $_user['username'] ?? '',
            'username' => $_user['username'] ?? '',
            'email'    => $_user['email']    ?? '',
        ];
    }
    ?>
    <script>window.pubSessionUser = <?= json_encode($_phpUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    window.PUB_TENANT_ID = <?= (int)($ctx['tenant_id'] ?? 1) ?>;
    window.PUB_LANG = <?= json_encode($ctx['lang'] ?? 'en', JSON_HEX_TAG) ?>;
    </script>
    <?php
    // Inject notifications as JSON for the bell widget (pub_load_notifications already ran)
    $_pubNotifJson = $ctx['notifications'] ?? [];
    ?>
    <script type="application/json" id="pubNotifData"><?= json_encode($_pubNotifJson, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
    <?php unset($_pubNotifJson); ?>
</head>

<body class="pub-body <?= e($dir) ?>">

<!-- =============================================
     HEADER
============================================= -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!-- Logo -->
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
            <?php
            $_logoUrl = $theme['logo_url'] ?? '';
            // Also check static file fallback: /frontend/assets/images/logo.{png,svg,webp}
            if (empty($_logoUrl)) {
                $_baseImgDir = FRONTEND_BASE . '/assets/images/';
                foreach (['logo.png', 'logo.svg', 'logo.webp'] as $_lf) {
                    if (@file_exists($_baseImgDir . $_lf)) {
                        $_logoUrl = '/frontend/assets/images/' . $_lf;
                        break;
                    }
                }
                unset($_baseImgDir, $_lf);
            }
            if (!empty($_logoUrl)):
            ?>
                <img src="<?= e($_logoUrl) ?>" alt="<?= e($_appName) ?>" class="pub-logo-img"
                     style="width:auto;vertical-align:middle;object-fit:contain;flex-shrink:0;">
                <span class="pub-logo-name" style="margin-inline-start:4px;font-weight:700;letter-spacing:0.5px;"><?= e($_appName) ?></span>
            <?php else: ?>
                <span class="pub-logo-icon" aria-hidden="true">🌐</span>
                <?= e($_appName) ?>
            <?php endif; ?>
        </a>

        <!-- Desktop navigation -->
        <nav class="pub-nav" aria-label="<?= e(t('nav.menu_open')) ?>">
            <?php foreach ($_navItems as $label => $href): ?>
                <a href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <!-- Cart link with live count badge -->
            <a href="<?= e($_cartUrl) ?>" class="pub-cart-nav-link" style="position:relative;display:inline-flex;align-items:center;gap:4px;">
                🛒 <?= $_cartLabel ?>
                <span id="pubCartCount"
                      style="display:none;background:var(--pub-accent,#F59E0B);color:#000;
                             border-radius:50%;min-width:18px;height:18px;padding:0 4px;
                             font-size:0.7rem;font-weight:800;line-height:18px;
                             text-align:center;vertical-align:middle;"></span>
            </a>
            <!-- Wishlist link with live count badge -->
            <a href="/frontend/public/wishlist.php" class="pub-wishlist-badge" style="position:relative;display:inline-flex;align-items:center;gap:4px;" title="<?= e(t('nav.wishlist')) ?>">
                ♡ <?= e(t('nav.wishlist')) ?>
                <span id="pubWishlistCount"></span>
            </a>
            <!-- Compare link with live count badge -->
            <a href="/frontend/public/compare.php" style="position:relative;display:inline-flex;align-items:center;gap:4px;" title="<?= e(t('nav.compare', ['default' => 'Compare'])) ?>">
                ⚖️ <?= e(t('nav.compare', ['default' => 'Compare'])) ?>
                <span class="pub-compare-badge" style="display:none;background:var(--pub-secondary,#10b981);color:#fff;
                       border-radius:50%;min-width:18px;height:18px;padding:0 4px;
                       font-size:0.7rem;font-weight:800;line-height:18px;text-align:center;vertical-align:middle;"></span>
            </a>
        </nav>

        <!-- Header actions -->
        <div class="pub-header-actions">
            <!-- Notification bell (all users) -->
            <div class="pub-notif-wrap" id="pubNotifWrap">
                <button class="pub-notif-btn" id="pubNotifBtn"
                        aria-label="<?= e(t('nav.notifications', ['default' => 'Notifications'])) ?>"
                        aria-haspopup="true" aria-expanded="false">
                    🔔
                    <span class="pub-notif-badge" id="pubNotifBadge"></span>
                </button>
                <!-- Notification dropdown -->
                <div class="pub-notif-dropdown" id="pubNotifDropdown" role="dialog"
                     aria-label="<?= e(t('nav.notifications', ['default' => 'Notifications'])) ?>">
                    <div class="pub-notif-header">
                        <span><?= e(t('nav.notifications', ['default' => 'Notifications'])) ?></span>
                        <button class="pub-notif-mark-all" id="pubNotifMarkAll">
                            <?= e(t('notifications.mark_all_read', ['default' => 'Mark all read'])) ?>
                        </button>
                    </div>
                    <div class="pub-notif-list" id="pubNotifList">
                        <div class="pub-notif-empty"><?= e(t('notifications.empty', ['default' => 'No notifications'])) ?></div>
                    </div>
                    <div class="pub-notif-footer">
                        <a href="/frontend/public/notifications.php"><?= e(t('notifications.view_all', ['default' => 'View all'])) ?></a>
                    </div>
                </div>
            </div>
            <!-- Login / user — no language switcher button (auto-detected) -->
            <?php if ($_isLoggedIn): ?>
                <a href="/frontend/profile.php" class="pub-login-btn" style="margin-inline-end:6px;">
                    <?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?>
                </a>
                <a href="/frontend/logout.php"
                   class="pub-btn pub-btn--ghost pub-btn--sm"
                   style="font-size:0.8rem;padding:4px 10px;"><?= e(t('nav.logout')) ?></a>
            <?php else: ?>
                <a href="/frontend/login.php" class="pub-login-btn"><?= e(t('nav.login')) ?></a>
            <?php endif; ?>

            <!-- Hamburger (mobile) -->
            <button class="pub-hamburger" id="pubHamburger"
                    aria-label="<?= e(t('nav.menu_open')) ?>"
                    aria-expanded="false" aria-controls="pubMobileNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile nav drawer -->
<div class="pub-mobile-nav" id="pubMobileNav" role="dialog" aria-modal="true"
     aria-label="<?= e(t('nav.menu_open')) ?>">
    <nav class="pub-mobile-nav-inner">
        <?php foreach ($_navItems as $label => $href): ?>
            <a href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <!-- Cart link -->
        <a href="<?= e($_cartUrl) ?>">
            🛒 <?= $_cartLabel ?>
            <span id="pubCartCountMobile"
                  style="display:none;background:var(--pub-accent,#F59E0B);color:#000;
                         border-radius:50%;min-width:18px;height:18px;padding:0 4px;
                         font-size:0.7rem;font-weight:800;line-height:18px;
                         text-align:center;vertical-align:middle;margin-inline-start:4px;"></span>
        </a>
        <!-- Wishlist link -->
        <a href="/frontend/public/wishlist.php">
            ♡ <?= e(t('nav.wishlist')) ?>
            <span id="pubWishlistCountMobile"></span>
        </a>
        <hr style="border-color:rgba(255,255,255,0.15);margin:12px 0;">
        <?php if ($_isLoggedIn): ?>
            <a href="/frontend/public/orders.php">📦 <?= e(t('nav.orders')) ?></a>
            <a href="/frontend/profile.php">👤 <?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?></a>
            <a href="/frontend/logout.php"><?= e(t('nav.logout')) ?></a>
        <?php else: ?>
            <a href="/frontend/login.php"><?= e(t('nav.login')) ?></a>
            <a href="/frontend/login.php?tab=register"><?= e(t('nav.register')) ?></a>
        <?php endif; ?>
    </nav>
</div>

<!-- =============================================
     PAGE CONTENT START
============================================= -->