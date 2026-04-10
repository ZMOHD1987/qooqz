<?php
declare(strict_types=1);
session_start();

// 1. إعدادات اللغة (بناءً على طلبك السابق)
$lang = $_SESSION['lang'] ?? 'ar';
$rtlLanguages = ['ar', 'fa', 'ur'];
$direction = in_array($lang, $rtlLanguages) ? 'rtl' : 'ltr';

// 2. محاكاة جلب البيانات (أو استخدم curl الفعلي)
$json_data = '{"status":"ok","data":{"ui":{"theme":{"name":"default","mode":"light"},"colors":{"primary":"#0d6efd","secondary":"#6c757d","background":"#ffffff"},"fonts":{"base":"Cairo, sans-serif"},"buttons":{"radius":"6px"},"cards":{"shadow":true}},"data":{"sections":["featured_products","new_products","hot_products","featured_vendors"],"banners":[],"products":{"featured":[{"id":1,"slug":"iphone-14-pro-max-256gb","is_featured":1,"name":"iPhone 15"},{"id":2,"slug":"samsung-galaxy-s23-ultra","is_featured":1,"name":"samsung-galaxy-s23-ultra"},{"id":3,"slug":"nike-air-max-black","is_featured":1,"name":"nike-air-max-black"}],"new":[{"id":25,"slug":"52jhgv","created_at":"2025-12-21 14:46:54","name":"52jhgv"},{"id":24,"slug":"gg6h-ddd","created_at":"2025-12-21 14:44:48","name":"gg6h-ddd"}],"hot":[]},"vendors":{"featured":[{"id":2,"store_name":"Fashion Hub","slug":"fashion-hub","logo_url":"\/vendors\/fashion-logo.png","rating_average":"0.00","total_products":0},{"id":35,"store_name":"بيب-55","slug":"تتنت-ى","logo_url":"\/uploads\/vendors\/v35_logo_url_1766597294_f2e1ea80.jpg","rating_average":"0.00","total_products":0}]}}}}';

$apiResponse = json_decode($json_data, true);
$ui = $apiResponse['data']['ui'] ?? [];
$content = $apiResponse['data']['data'] ?? [];
$sections = $content['sections'] ?? []; // الترتيب المطلوب من الـ API

// تمرير البيانات للـ Partials عبر Global
$GLOBALS['PUBLIC_UI'] = $ui;
$banners = $content['banners'] ?? [];
$breadcrumbs = [['label' => ($lang == 'ar' ? 'الرئيسية' : 'Home'), 'url' => '/']];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <title><?= $ui['site_name'] ?? 'QOOQZ' ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        :root {
            --color-primary: <?= $ui['colors']['primary'] ?>;
            --button-radius: <?= $ui['buttons']['radius'] ?>;
            --card-shadow: <?= $ui['cards']['shadow'] ? '0 4px 10px rgba(0,0,0,0.1)' : 'none' ?>;
        }
        body { font-family: <?= $ui['fonts']['base'] ?>; background-color: <?= $ui['colors']['background'] ?>; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .card { border: 1px solid #eee; padding: 15px; border-radius: var(--button-radius); box-shadow: var(--card-shadow); text-align: center; }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/menu.php'; ?>

<main class="container">
    <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>

    <?php if(!empty($banners)) include __DIR__ . '/partials/slider.php'; ?>

    <?php foreach ($sections as $sectionKey): ?>
        
        <?php if ($sectionKey === 'featured_products' && !empty($content['products']['featured'])): ?>
            <section>
                <h2>المنتجات المميزة</h2>
                <div class="grid">
                    <?php foreach ($content['products']['featured'] as $p): ?>
                        <div class="card">
                            <h4><?= htmlspecialchars($p['name']) ?></h4>
                            <a href="/product/<?= $p['slug'] ?>">عرض</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($sectionKey === 'new_products' && !empty($content['products']['new'])): ?>
            <section>
                <h2>أحدث المنتجات</h2>
                <div class="grid">
                    <?php foreach ($content['products']['new'] as $p): ?>
                        <div class="card">
                            <h4><?= htmlspecialchars($p['name']) ?></h4>
                            <small><?= $p['created_at'] ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($sectionKey === 'featured_vendors' && !empty($content['vendors']['featured'])): ?>
            <section>
                <h2>أفضل المتاجر</h2>
                <div class="grid">
                    <?php foreach ($content['vendors']['featured'] as $v): ?>
                        <div class="card">
                            <img src="<?= $v['logo_url'] ?>" style="width: 60px; height: 60px; border-radius: 50%;">
                            <h4><?= htmlspecialchars($v['store_name']) ?></h4>
                            <p>التقييم: <?= $v['rating_average'] ?> ★</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php endforeach; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>