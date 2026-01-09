<?php
declare(strict_types=1);
/**
 * frontend/index.php
 * Main frontend page that displays products, vendors, and home content
 * Uses bootstrap for consistent configuration and API access
 */

// Load bootstrap for session, config, and API access
require_once __DIR__ . '/../api/bootstrap.php';

// Get language from session or default
$lang = $_SESSION['lang'] ?? $_SESSION['preferred_language'] ?? 'ar';
$rtlLanguages = ['ar', 'fa', 'ur', 'he'];
$direction = in_array($lang, $rtlLanguages) ? 'rtl' : 'ltr';

// Fetch data from HomeController/HomeService
try {
    require_once __DIR__ . '/../api/controllers/HomeController.php';
    
    // Create controller and get data
    $controller = new HomeController();
    
    // Capture output from controller
    ob_start();
    $controller->index();
    $jsonOutput = ob_get_clean();
    
    $apiResponse = json_decode($jsonOutput, true);
    
    if (!$apiResponse || $apiResponse['status'] !== 'ok') {
        throw new Exception('Failed to load home data');
    }
    
    $homeData = $apiResponse['data'] ?? [];
} catch (Throwable $e) {
    error_log('Frontend index error: ' . $e->getMessage());
    // Fallback to empty data
    $homeData = [
        'theme' => ['name' => 'default', 'mode' => 'light'],
        'colors' => ['primary' => '#0d6efd', 'secondary' => '#6c757d', 'background' => '#ffffff'],
        'fonts' => ['base' => 'Cairo, sans-serif'],
        'buttons' => ['radius' => '6px'],
        'cards' => ['shadow' => true],
        'sections' => [],
        'banners' => [],
        'featured_products' => [],
        'new_products' => [],
        'hot_products' => [],
        'featured_vendors' => [],
        'categories' => []
    ];
}

// Extract UI and content data
$ui = [
    'theme' => $homeData['theme'] ?? ['name' => 'default'],
    'colors' => $homeData['colors'] ?? [],
    'fonts' => $homeData['fonts'] ?? [],
    'buttons' => $homeData['buttons'] ?? [],
    'cards' => $homeData['cards'] ?? []
];

$sections = $homeData['sections'] ?? [];
$banners = $homeData['banners'] ?? [];
$breadcrumbs = [['label' => ($lang == 'ar' ? 'الرئيسية' : 'Home'), 'url' => '/']];

// Set globals for partials
$GLOBALS['PUBLIC_UI'] = $ui;
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
        
        <?php if ($sectionKey === 'featured_products' && !empty($homeData['featured_products'])): ?>
            <section>
                <h2><?= $lang === 'ar' ? 'المنتجات المميزة' : 'Featured Products' ?></h2>
                <div class="grid">
                    <?php foreach ($homeData['featured_products'] as $p): ?>
                        <div class="card">
                            <h4><?= htmlspecialchars($p['name']) ?></h4>
                            <a href="/product/<?= $p['slug'] ?>"><?= $lang === 'ar' ? 'عرض' : 'View' ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($sectionKey === 'new_products' && !empty($homeData['new_products'])): ?>
            <section>
                <h2><?= $lang === 'ar' ? 'أحدث المنتجات' : 'New Products' ?></h2>
                <div class="grid">
                    <?php foreach ($homeData['new_products'] as $p): ?>
                        <div class="card">
                            <h4><?= htmlspecialchars($p['name']) ?></h4>
                            <small><?= $p['created_at'] ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($sectionKey === 'featured_vendors' && !empty($homeData['featured_vendors'])): ?>
            <section>
                <h2><?= $lang === 'ar' ? 'أفضل المتاجر' : 'Featured Stores' ?></h2>
                <div class="grid">
                    <?php foreach ($homeData['featured_vendors'] as $v): ?>
                        <div class="card">
                            <img src="<?= $v['logo_url'] ?>" style="width: 60px; height: 60px; border-radius: 50%;">
                            <h4><?= htmlspecialchars($v['store_name']) ?></h4>
                            <p><?= $lang === 'ar' ? 'التقييم' : 'Rating' ?>: <?= $v['rating_average'] ?> ★</p>
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