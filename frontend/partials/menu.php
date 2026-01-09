<?php
/**
 * //htdocs/frontend/partials/menu.php
 */
$UI   = $GLOBALS['PUBLIC_UI'] ?? [];
$user = $UI['user'] ?? [];
$isLoggedIn = !empty($user['id']);
?>

<nav class="main-menu">
    <ul class="menu-list">
        <li><a href="/frontend/index.php">الرئيسية</a></li>
        <li><a href="/frontend/products.php">المنتجات</a></li>
        <li><a href="/frontend/categories.php">التصنيفات</a></li>

        <?php if ($isLoggedIn): ?>
            <li><a href="/frontend/dashboard.php">لوحة التحكم</a></li>
        <?php endif; ?>
    </ul>
</nav>
