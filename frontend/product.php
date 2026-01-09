<?php
// htdocs/frontend/product.php
// تفاصيل منتج متوافقة مع بنية products لديك

require_once __DIR__ . '/../api/config/db.php';
if (function_exists('connectDB')) $conn = connectDB();
elseif (!isset($conn)) die('خطأ: اتصال DB غير متوفر.');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = $_GET['slug'] ?? null;
if (!$id && !$slug) die('معرّف المنتج غير موجود.');

if ($id) { $stmt = $conn->prepare("SELECT * FROM products WHERE id=? LIMIT 1"); $stmt->bind_param('i',$id); }
else { $stmt = $conn->prepare("SELECT * FROM products WHERE slug=? LIMIT 1"); $stmt->bind_param('s',$slug); }
$stmt->execute(); $res = $stmt->get_result(); $product = $res ? $res->fetch_assoc() : null; $stmt->close();
if (!$product) die('المنتج غير موجود.');

$mstmt = $conn->prepare("SELECT file_url,thumbnail_url,alt_text,title,is_primary FROM product_media WHERE product_id=? ORDER BY is_primary DESC, sort_order ASC");
$mstmt->bind_param('i', $product['id']); $mstmt->execute(); $mres = $mstmt->get_result(); $media = $mres ? $mres->fetch_all(MYSQLI_ASSOC) : []; $mstmt->close();
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo htmlspecialchars($product['slug'] ?? $product['sku']); ?></title></head>
<body style="font-family:Inter,Arial,sans-serif">
<div style="max-width:1000px;margin:20px auto;padding:16px">
  <header><a href="/frontend/products.php">العودة إلى المنتجات</a></header>
  <main style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:0 0 420px">
      <?php $main = $media[0]['file_url'] ?? '/assets/images/placeholder.png'; ?>
      <img id="mainImg" src="<?php echo htmlspecialchars($main); ?>" style="width:100%;height:420px;object-fit:cover;border-radius:8px">
      <?php if ($media): ?><div style="display:flex;gap:8px;margin-top:8px"><?php foreach ($media as $m){ $thumb = $m['thumbnail_url'] ?: $m['file_url']; echo '<img src="'.htmlspecialchars($thumb).'" data-src="'.htmlspecialchars($m['file_url']).'" style="width:64px;height:64px;object-fit:cover;border:1px solid #eee;cursor:pointer" onclick="document.getElementById(\'mainImg\').src=this.dataset.src">'; } ?></div><?php endif; ?>
    </div>
    <div style="flex:1">
      <h1><?php echo htmlspecialchars($product['slug'] ?? $product['sku']); ?></h1>
      <p><?php echo nl2br(htmlspecialchars($product['product_type'] ?? '')); ?></p>
      <div style="margin-top:12px"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></div>
    </div>
  </main>
</div>
</body></html>