<?php
// htdocs/frontend/categories.php
require_once __DIR__ . '/../api/config/db.php';
if (function_exists('connectDB')) $conn = connectDB(); elseif (!isset($conn)) die('خطأ: اتصال DB غير متوفر.');

$catsRes = $conn->query("SELECT id, slug, image_url, icon_url FROM categories WHERE is_active = 1 ORDER BY sort_order");
$cats = $catsRes ? $catsRes->fetch_all(MYSQLI_ASSOC) : [];
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>التصنيفات</title></head>
<body style="font-family:Inter,Arial,sans-serif">
<div style="max-width:1000px;margin:20px auto;padding:16px">
  <header><h1>التصنيفات</h1><nav><a href="/frontend/index.php">الرئيسية</a></nav></header>
  <?php if ($cats): ?><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px"><?php foreach ($cats as $c){ $img = $c['image_url'] ?: $c['icon_url'] ?: '/assets/images/placeholder.png'; echo '<article style="border:1px solid #eee;border-radius:8px;padding:18px;text-align:center"><a href="/frontend/category.php?id='.(int)$c['id'].'"><img src="'.htmlspecialchars($img).'" style="width:100%;height:140px;object-fit:cover;border-radius:6px"><h3 style="margin-top:8px">'.htmlspecialchars($c['slug']).'</h3></a></article>'; } ?></div><?php else: ?><p>لا توجد تصنيفات.</p><?php endif; ?>
</div>
</body></html>