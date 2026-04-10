<?php
/**
 * frontend/partials/menu.php
 * QOOQZ — Sidebar Navigation Menu (Production Version)
 */

$_ctx = $GLOBALS['PUB_CONTEXT'] ?? [];
$_user = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath = '/frontend';

if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('t')) {
    function t(string $key, string|array $r = []): string {
        return is_string($r) ? $r : $key;
    }
}
?>

<aside class="pub-sidebar" id="pubSidebar" role="navigation" aria-label="<?= e(t('nav.main_navigation')) ?>">
    
    <!-- Sidebar Header -->
    <div class="pub-sidebar-header">
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-logo">
            <span class="pub-sidebar-logo-icon">🌐</span>
            <span class="pub-sidebar-title"><?= e($_appName) ?></span>
        </a>
        <button class="pub-sidebar-close" id="pubSidebarClose" aria-label="<?= e(t('nav.menu_close')) ?>">✕</button>
    </div>

    <!-- Navigation -->
    <nav class="pub-sidebar-nav">

        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1v-5m10-10l2 2m-2-2v10a1 1 0 01-1 1v-5m-6 0a1 1 0 001-1v5" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.home')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/products.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0 4 4 0 008 0 4 4 0 00-4 4v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.products')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.categories')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/discounts.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 01-9 9 9 9 0 01-9-9 9 9 0 019-9 9 9 0 019 9z" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.offers')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/entities.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 01-2-2H7a2 2 0 01-2 2v16m14 0h-4m-6 0H5" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.entities')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/tenants.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.tenants')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/auctions.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 4.01V8" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.auctions')) ?></span>
        </a>

        <div class="pub-sidebar-divider"></div>

        <!-- Cart, Wishlist, Compare -->
        <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.cart')) ?></span>
            <span id="pubCartCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>

        <a href="<?= e($_basePath . '/wishlist.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.wishlist')) ?></span>
            <span id="pubWishlistCountSidebar" class="pub-sidebar-badge" style="display:none;"></span>
        </a>

        <a href="<?= e($_basePath . '/compare.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4 4m4-4l-4-4m0 12H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.compare')) ?></span>
        </a>

        <div class="pub-sidebar-divider"></div>

        <?php if ($_isLoggedIn): ?>
        <!-- Logged In User -->
        <a href="<?= e($_basePath . '/notifications.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-9-5.197V8.5m.002 3.5L12 15l-3.002-3" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.notifications')) ?></span>
        </a>

        <a href="<?= e($_basePath . '/orders.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.orders')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/profile.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/logout.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4V7m-4 4V7" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.logout')) ?></span>
        </a>

        <?php else: ?>
        <!-- Guest -->
        <a href="<?= e($_authPath . '/login.php') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4v8" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.login')) ?></span>
        </a>

        <a href="<?= e($_authPath . '/login.php?tab=register') ?>" class="pub-sidebar-link">
            <span class="pub-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </span>
            <span class="pub-sidebar-text"><?= e(t('nav.register')) ?></span>
        </a>
        <?php endif; ?>

    </nav>
</aside>