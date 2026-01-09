<?php
// htdocs/frontend/products.php
// قائمة المنتجات متوافقة مع بنية products لديك (تستخدم slug/sku كعنوان)

require_once __DIR__ . '/../api/config/db.php';
if (function_exists('connectDB')) $conn = connectDB();
elseif (!isset($conn)) die('خطأ: اتصال DB غير متوفر.');

function fetchAll(mysqli $conn, $sql, $params = []) {
    if (empty($params)) { $res = $conn->query($sql); return $res ? $res->fetch_all(MYSQLI_ASSOC) : []; }
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); $res = $stmt->get_result(); $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : []; $stmt->close(); return $rows;
}

$products = fetchAll($conn, "SELECT p.id, COALESCE(p.slug,p.sku) AS title, COALESCE(pm.file_url,pm.thumbnail_url,'') AS image FROM products p LEFT JOIN product_media pm ON pm.product_id=p.id AND pm.is_primary=1 WHERE p.is_active=1 GROUP BY p.id ORDER BY p.published_at DESC LIMIT 200");
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>المنتجات</title></head>
<body style="font-family:Inter,Arial,sans-serif">
<div style="max-width:1100px;margin:20px auto;padding:16px">
  <header><h1>المنتجات</h1><nav><a href="/frontend/index.php">الرئيسية</a> — <a href="/frontend/categories.php">التصنيفات</a></nav></header>
  <?php if ($products): ?><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:18px">
    <?php foreach ($products as $p): $img = $p['image'] ?: '/assets/images/placeholder.png'; ?>
      <article style="border:1px solid #eee;padding:12px;border-radius:8px;text-align:center">
        <a href="/frontend/product.php?id=<?php echo (int)$p['id']; ?>" style="text-decoration:none;color:inherit">
          <div style="height:180px;overflow:hidden"><img src="<?php echo htmlspecialchars($img); ?>" style="width:100%;height:100%;object-fit:cover"></div>
          <h3 style="margin:10px 0 6px"><?php echo htmlspecialchars($p['title']); ?></h3>
        </a>
      </article>
    <?php endforeach; ?>
  </div><?php else: ?><p>لا توجد منتجات لعرضها.</p><?php endif; ?>
</div>
</body></html>