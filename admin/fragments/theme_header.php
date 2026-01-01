<?php
// admin/fragments/theme_header.php
// يقرأ الثيم الافتراضي أو ثيم معين من DB عبر bootstrap + repo
require_once __DIR__ . '/../../api/bootstrap.php'; // إذا مسار مشروعك يسمح بالوصول
$container = container(); // متاحة من bootstrap

// حدد ثيم: إما الافتراضي من DB أو رقمه 3 مثلاً
$themeId = $_GET['theme_id'] ?? null;
if (!$themeId) {
    // خذ الثيم الافتراضي من جدول themes
    $res = $container['db']->query("SELECT id FROM themes WHERE is_default = 1 LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $themeId = $row['id'] ?? 1;
}

// جلب الألوان
$stmt = $container['db']->prepare("SELECT setting_key, color_value FROM color_settings WHERE theme_id = ? AND is_active = 1");
$stmt->bind_param('i', $themeId);
$stmt->execute();
$res = $stmt->get_result();
$colors = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// أخرج CSS متغيّرات (CSS variables)
echo "<style id='theme-vars'>:root{\n";
foreach ($colors as $c) {
    $key = preg_replace('/[^a-z0-9_-]/i', '-', $c['setting_key']);
    $val = $c['color_value'] ?? '#000';
    echo "  --theme-{$key}: {$val};\n";
}
echo "}</style>\n";
?>