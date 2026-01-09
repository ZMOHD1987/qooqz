<?php
/**
 * Frontend Header Partial
 * - يعتمد على بيانات API / bootstrap
 * - لا يفترض Theme ثابت
 * - يدعم RTL / LTR
 */

if (!defined('FRONTEND_BOOTSTRAPPED')) {
    // تأكيد أن bootstrap تم تحميله من index.php
    // لا نوقف الصفحة، فقط حماية
}

/**
 * المتغيرات القادمة من index.php أو bootstrap API
 * نتعامل معها بشكل Defensive
 */
$UI = $GLOBALS['PUBLIC_UI'] ?? [];
$user = $UI['user'] ?? [];
$lang = $UI['lang'] ?? 'ar';
$dir  = $UI['direction'] ?? 'rtl';

$isLoggedIn = !empty($user['id']);
$username   = $user['username'] ?? 'ضيف';

/**
 * Helpers
 */
function esc($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?= esc($lang) ?>" dir="<?= esc($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= esc($UI['seo']['title'] ?? 'المتجر') ?></title>
    <meta name="description" content="<?= esc($UI['seo']['description'] ?? '') ?>">
    <meta name="keywords" content="<?= esc($UI['seo']['keywords'] ?? '') ?>">

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/main.css">

    <!-- Runtime CSS Variables (من API / Theme مستقبلاً) -->
    <?php if (!empty($UI['theme']['colors'])): ?>
    <style>
        :root {
            <?php foreach ($UI['theme']['colors'] as $key => $val): ?>
            --<?= esc($key) ?>: <?= esc($val) ?>;
            <?php endforeach; ?>
        }
    </style>
    <?php endif; ?>
</head>

<body class="frontend <?= esc($dir) ?>">

<header class="site-header">
    <div class="container header-inner">

        <!-- Logo -->
        <div class="header-logo">
            <a href="/frontend/index.php" class="logo-link">
                <span class="logo-text">موقعي</span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="header-nav">
            <ul class="nav-list">
                <li><a href="/frontend/index.php">الرئيسية</a></li>
                <li><a href="/frontend/products.php">المنتجات</a></li>
                <li><a href="/frontend/categories.php">التصنيفات</a></li>
            </ul>
        </nav>

        <!-- Actions -->
        <div class="header-actions">

            <!-- User -->
            <?php if ($isLoggedIn): ?>
                <div class="user-menu">
                    <span class="user-name"><?= esc($username) ?></span>
                    <div class="user-dropdown">
                        <a href="/frontend/profile.php">الملف الشخصي</a>
                        <a href="/frontend/orders.php">طلباتي</a>
                        <a href="/frontend/logout.php">تسجيل الخروج</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/frontend/login.php" class="btn btn-outline">تسجيل الدخول</a>
            <?php endif; ?>

            <!-- Mobile Toggle -->
            <button class="nav-toggle" aria-label="Toggle menu">
                ☰
            </button>
        </div>

    </div>
</header>

<main class="site-main">
