<?php
// htdocs/frontend/category.php
require_once __DIR__ . '/../api/config/db.php';
if (function_exists('connectDB')) $conn = connectDB(); elseif (!isset($conn)) die('خطأ: اتصال DB غير متوفر.');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = $_GET['slug'] ?? null;
if (!$id && !$slug) die('معرّف التصنيف غير موجود.');

if ($id) { $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1"); $stmt->bind_param('i',$id); }
else { $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1"); $stmt->bind_param('s',$slug); }
$stmt->execute(); $cres = $stmt->get_result(); $category = $cres ? $cres->fetch_assoc() : null; $stmt->close();
if (!$category) die('التصنيف غير موجود.');

$stmt = $conn->prepare("SELECT p.id, COALESCE(p.slug,p.sku) AS title, COALESCE(pm.file_url,pm.thumbnail_url,'') AS image FROM products p JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN product_media pm ON pm.product_id=p.id AND pm.is_primary=1 WHERE pc.category_id=? AND p.is_active=1 GROUP BY p.id ORDER BY p.published_at DESC");
$stmt->bind_param('i', $category['id']); $stmt->execute(); $pRes = $stmt->get_result(); $products = $pRes ? $pRes->fetch_all(MYSQLI_ASSOC) : []; $stmt->close();
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo htmlspecialchars($category['slug']); ?></title></head>
<body style="font-family:Inter,Arial,sans-serif">
<div style="max-width:1000px;margin:20px auto;padding:16px">
  <header><a href="/frontend/categories.php">العودة للتصنيفات</a><h1><?php echo htmlspecialchars($category['slug']); ?></h1></header>
  <?php if ($products): ?><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px"><?php foreach ($products as $p){ $img = $p['image']?:'/assets/images/placeholder.png'; echo '<article style="border:1px solid #eee;padding:12px;border-radius:8px;text-align:center"><a href="/frontend/product.php?id='.(int)$p['id'].'"><div style="height:140px;overflow:hidden"><img src="'.htmlspecialchars($img).'" style="width:100%;height:100%;object-fit:cover"></div><h4 style="margin-top:8px">'.htmlspecialchars($p['title']).'</h4></a></article>'; } ?></div><?php else: ?><p>لا توجد منتجات في هذا التصنيف حالياً.</p><?php endif; ?>
</div>
</body></html>