<?php
// htdocs/frontend/index.php
// مبسّط ليتوافق مع بنية الجداول الفعلية (products بدون name/title/price)

$debug = false;
if ($debug) { ini_set('display_errors',1); error_reporting(E_ALL); }

$dbCfg = __DIR__ . '/../api/config/db.php';
if (!file_exists($dbCfg)) die('خطأ: ملف اتصال قاعدة البيانات غير موجود');
require_once $dbCfg;
if (function_exists('connectDB')) $conn = connectDB();
elseif (!isset($conn) || !($conn instanceof mysqli)) die('خطأ: اتصال DB غير متوفر.');

function fetchAll(mysqli $conn, string $sql, array $params = []) {
    if (empty($params)) {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function fetchOne(mysqli $conn, string $sql, array $params = []) {
    $rows = fetchAll($conn, $sql, $params);
    return $rows[0] ?? null;
}

$theme = fetchOne($conn, "SELECT * FROM themes WHERE is_default = 1 LIMIT 1") ?: fetchOne($conn, "SELECT * FROM themes WHERE is_active = 1 LIMIT 1");
$theme_id = $theme['id'] ?? null;

if ($theme_id) {
    $banners = fetchAll($conn, "SELECT * FROM banners WHERE is_active = 1 AND (theme_id IS NULL OR theme_id = ?) ORDER BY sort_order", [$theme_id]);
    $sections = fetchAll($conn, "SELECT * FROM homepage_sections WHERE is_active = 1 AND (theme_id IS NULL OR theme_id = ?) ORDER BY sort_order", [$theme_id]);
} else {
    $banners = fetchAll($conn, "SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order");
    $sections = fetchAll($conn, "SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order");
}

function getFeaturedProducts(mysqli $conn, int $limit = 8) {
    $sql = "SELECT p.id, COALESCE(p.slug, p.sku) AS title, p.slug, p.sku,
                   COALESCE(pm.file_url, pm.thumbnail_url, '') AS image
            FROM products p
            LEFT JOIN product_media pm ON pm.product_id = p.id AND pm.is_primary = 1
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY p.published_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function getCategories(mysqli $conn, int $limit = 6) {
    return fetchAll($conn, "SELECT id, slug, icon_url, image_url FROM categories WHERE is_active = 1 ORDER BY sort_order LIMIT ?", [$limit]);
}

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>الصفحة الرئيسية</title>
<link rel="stylesheet" href="/assets/css/main.css">
<style>
/* مبسط */
body{font-family:Inter,Arial,sans-serif;background:#fff;color:#222;margin:0}
.container{max-width:1200px;margin:0 auto;padding:20px}
.hero-slide img{width:100%;height:420px;object-fit:cover;border-radius:8px}
.grid{display:grid;gap:16px}
.grid-4{grid-template-columns:repeat(4,1fr)}
.grid-3{grid-template-columns:repeat(3,1fr)}
.card{border:1px solid #eee;border-radius:8px;padding:12px;text-align:center}
.card img{width:100%;height:140px;object-fit:cover;border-radius:6px}
@media(max-width:900px){.grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.grid-4{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
<header><h1>موقعي</h1><nav><a href="/frontend/products.php">المنتجات</a> — <a href="/frontend/categories.php">التصنيفات</a></nav></header>

<?php if (!empty($banners)): ?>
<section class="hero-slider">
  <?php foreach ($banners as $i => $b):
    $img = $b['file_url'] ?? $b['image_url'] ?? '';
    $title = $b['title'] ?? $b['subtitle'] ?? '';
  ?>
    <div class="hero-slide <?php echo $i===0?'active':''; ?>">
      <?php if ($img): ?><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($title); ?>"><?php else: ?><div style="height:420px;background:#2d8cf0;color:#fff;display:flex;align-items:center;justify-content:center;border-radius:8px;"><?php echo htmlspecialchars($title?:''); ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<main>
<?php
if (!empty($sections)) {
  foreach ($sections as $sec) {
    $stype = $sec['section_type'] ?? 'featured_products';
    $title = $sec['title'] ?? ($sec['section_type'] ?? '');
    $items_per_row = (int)($sec['items_per_row'] ?: 4);
    echo '<section style="margin:24px 0;">';
    echo '<h2>'.htmlspecialchars($title).'</h2>';
    if ($stype === 'categories') {
      $cats = getCategories($conn, $items_per_row);
      if ($cats) {
        echo '<div class="grid grid-3">';
        foreach ($cats as $c) {
          $cname = $c['slug'];
          $img = $c['image_url'] ?: $c['icon_url'] ?: '/assets/images/placeholder.png';
          echo '<article class="card"><img src="'.htmlspecialchars($img).'" alt="'.htmlspecialchars($cname).'"><h4>'.htmlspecialchars($cname).'</h4></article>';
        }
        echo '</div>';
      } else { echo '<p>لا توجد تصنيفات للعرض.</p>'; }
    } else {
      $products = getFeaturedProducts($conn, $items_per_row);
      if ($products) {
        echo '<div class="grid grid-4">';
        foreach ($products as $p) {
          $pt = $p['title'] ?? $p['slug'] ?? $p['sku'] ?? 'منتج';
          $img = $p['image'] ?: '/assets/images/placeholder.png';
          echo '<article class="card"><a href="/frontend/product.php?id='.(int)$p['id'].'"><img src="'.htmlspecialchars($img).'" alt="'.htmlspecialchars($pt).'"><h4>'.htmlspecialchars($pt).'</h4></a></article>';
        }
        echo '</div>';
      } else { echo '<p>لا توجد منتجات للعرض.</p>'; }
    }
    echo '</section>';
  }
} else {
  echo '<p>لم تُجهز أقسام الصفحة الرئيسية بعد.</p>';
}
?>
</main>

<footer style="margin-top:40px;border-top:1px solid #eee;padding-top:12px;text-align:center;color:#666">جميع الحقوق محفوظة</footer>
</div>
<script>
(function(){const slides=document.querySelectorAll('.hero-slide');if(!slides.length)return;let i=0;setInterval(()=>{slides.forEach(s=>s.classList.remove('active'));slides[(++i)%slides.length].classList.add('active');},4500)})();
</script>
</body>
</html>