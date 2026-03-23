<?php
/**
 * frontend/partials/menu.php
 * QOOQZ — Sidebar Navigation Menu (standalone component)
 *
 * This file renders the pub-sidebar navigation.
 * It is included by header.php INSIDE <div class="pub-layout">.
 * The header does NOT contain any menus — they are here.
 *
 * Classes used (defined in public.css):
 *   .pub-sidebar, .pub-sidebar-header, .pub-sidebar-nav,
 *   .pub-sidebar-link, .pub-sidebar-icon, .pub-sidebar-text,
 *   .pub-sidebar-badge, .pub-sidebar-divider
 */

$_ctx       = $GLOBALS['PUB_CONTEXT'] ?? [];
$_user      = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath  = '/frontend'; // Auth pages (login, register, profile, logout) live at /frontend/ level

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, array $r = []): string { return $key; }
}
?>

<!-- =============================================
     SIDEBAR MENU (persistent desktop / slide-out mobile)
============================================= -->
<aside class="pub-sidebar" id="pubSidebar" role="navigation"
       aria-label="<?= e(t('nav.main_navigation')) ?>">

    <!-- Sidebar header: brand + close button (close only visible on mobile) -->
    <div class="pub-sidebar-header">
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-logo">
            <span class="pub-sidebar-logo-icon">🌐</span>
            <span class="pub-sidebar-title"><?= e($_appName) ?></span>
        </a>
        <button class="pub-sidebar-close" id="pubSidebarClose"
                aria-label="<?= e(t('nav.menu_close', ['default' => 'Close'])) ?>">✕</button>
    </div>

    <!-- Navigation links -->
    <nav class="pub-sidebar-nav">
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🏠</span>
            <span class="pub-sidebar-text"><?= e(t('nav.home')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/products.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🛍️</span>
            <span class="pub-sidebar-text"><?= e(t('nav.products')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">📂</span>
            <span class="pub-sidebar-text"><?= e(t('nav.categories')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/discounts.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🏷️</span>
            <span class="pub-sidebar-text"><?= e(t('nav.offers')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/jobs.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">💼</span>
            <span class="pub-sidebar-text"><?= e(t('nav.jobs')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/entities.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🏢</span>
            <span class="pub-sidebar-text"><?= e(t('nav.entities')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/tenants.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🏪</span>
            <span class="pub-sidebar-text"><?= e(t('nav.tenants')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/auctions.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🔨</span>
            <span class="pub-sidebar-text"><?= e(t('nav.auctions')) ?></span>
        </a>

        <div class="pub-sidebar-divider"></div>

        <!-- Cart + Wishlist + Compare -->
        <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🛒</span>
            <span class="pub-sidebar-text"><?= e(t('nav.cart')) ?></span>
            <span id="pubCartCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>
        <a href="<?= e($_basePath . '/wishlist.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">♡</span>
            <span class="pub-sidebar-text"><?= e(t('nav.wishlist')) ?></span>
            <span id="pubWishlistCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>
        <a href="<?= e($_basePath . '/compare.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">⚖️</span>
            <span class="pub-sidebar-text"><?= e(t('nav.compare', ['default' => 'Compare'])) ?></span>
        </a>

        <?php if ($_isLoggedIn): ?>
        <div class="pub-sidebar-divider"></div>
        <!-- Logged-in user links -->
        <a href="<?= e($_basePath . '/orders.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">📦</span>
            <span class="pub-sidebar-text"><?= e(t('nav.orders')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/tickets.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🎫</span>
            <span class="pub-sidebar-text"><?= e(t('nav.tickets')) ?></span>
        </a>
        <a href="<?= e($_basePath . '/returns.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">↩</span>
            <span class="pub-sidebar-text"><?= e(t('nav.returns')) ?></span>
        </a>
        <a href="<?= e($_authPath . '/profile.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">👤</span>
            <span class="pub-sidebar-text"><?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?></span>
        </a>
        <a href="<?= e($_authPath . '/logout.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🚪</span>
            <span class="pub-sidebar-text"><?= e(t('nav.logout')) ?></span>
        </a>
        <?php else: ?>
        <div class="pub-sidebar-divider"></div>
        <!-- Guest links -->
        <a href="<?= e($_authPath . '/login.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">🔑</span>
            <span class="pub-sidebar-text"><?= e(t('nav.login')) ?></span>
        </a>
        <a href="<?= e($_authPath . '/login.php?tab=register') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">📝</span>
            <span class="pub-sidebar-text"><?= e(t('nav.register')) ?></span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
