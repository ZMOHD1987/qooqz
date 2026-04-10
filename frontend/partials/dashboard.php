<?php
/**///htdocs/frontend/partials/dashboard.php
 * User Dashboard
 */
$UI   = $GLOBALS['PUBLIC_UI'] ?? [];
$user = $UI['user'] ?? [];

if (empty($user['id'])) {
    header('Location: /frontend/login.php');
    exit;
}
?>

<section class="dashboard">
    <h1>لوحة التحكم</h1>

    <div class="dashboard-cards grid grid-3">
        <div class="card">
            <h3>الملف الشخصي</h3>
            <a href="/frontend/profile.php">إدارة</a>
        </div>

        <div class="card">
            <h3>طلباتي</h3>
            <a href="/frontend/orders.php">عرض الطلبات</a>
        </div>

        <div class="card">
            <h3>الإعدادات</h3>
            <a href="/frontend/settings.php">تعديل</a>
        </div>
    </div>
</section>
