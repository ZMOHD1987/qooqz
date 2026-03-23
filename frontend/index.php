<?php
declare(strict_types=1);
session_start();

// 1. إعدادات اللغة (بناءً على طلبك السابق)
$lang = $_SESSION['lang'] ?? 'ar';
$rtlLanguages = ['ar', 'fa', 'ur'];
$direction = in_array($lang, $rtlLanguages) ? 'rtl' : 'ltr';

// 2. محاكاة جلب البيانات (أو استخدم curl الفعلي)
$json_data = '{"status":"ok","data":{"ui":{"theme":{"name":"default","mode":"light"},"colors":{"primary":"#0d6efd","secondary":"#6c757d","accent":"#f39c12","success":"#2ecc71","background":"#ffffff","surface":"#f8f9fb","text":"#222831","muted":"#7f8c8d"},"fonts":{"base":"Cairo, sans-serif"},"buttons":{"radius":"6px"},"cards":{"shadow":true}},"data":{"sections":["featured_products","new_products","hot_products","featured_vendors"],"banners":[{"id":1,"title":"عروض الصيف","subtitle":"خصومات تصل إلى 50%","image_url":"/images/banners/summer-sale.jpg","mobile_image_url":"/images/banners/summer-sale-m.jpg","link_url":"/products?sale=summer","link_text":"تسوق الآن","background_color":"#1a237e","text_color":"#ffffff","button_style":"#f39c12"},{"id":2,"title":"وصل حديثاً","subtitle":"أحدث المنتجات","image_url":"/images/banners/new-arrivals.jpg","link_url":"/products?new=1","link_text":"اكتشف","background_color":"#004d40","text_color":"#ffffff"}],"categories":[{"id":1,"name":"إلكترونيات","icon":"fas fa-laptop","children":[{"id":11,"name":"هواتف","icon":"fas fa-mobile-alt"},{"id":12,"name":"حواسيب","icon":"fas fa-desktop"},{"id":13,"name":"إكسسوارات","icon":"fas fa-headphones"}]},{"id":2,"name":"أزياء","icon":"fas fa-tshirt","children":[{"id":21,"name":"رجالي","icon":"fas fa-male"},{"id":22,"name":"نسائي","icon":"fas fa-female"},{"id":23,"name":"أطفال","icon":"fas fa-child"}]},{"id":3,"name":"المنزل والحديقة","icon":"fas fa-home","children":[{"id":31,"name":"أثاث","icon":"fas fa-couch"},{"id":32,"name":"مطبخ","icon":"fas fa-utensils"}]},{"id":4,"name":"رياضة","icon":"fas fa-running"},{"id":5,"name":"كتب","icon":"fas fa-book"}],"products":{"featured":[{"id":1,"slug":"iphone-14-pro-max-256gb","is_featured":1,"name":"iPhone 15"},{"id":2,"slug":"samsung-galaxy-s23-ultra","is_featured":1,"name":"Samsung Galaxy S23 Ultra"},{"id":3,"slug":"nike-air-max-black","is_featured":1,"name":"Nike Air Max Black"}],"new":[{"id":25,"slug":"52jhgv","created_at":"2025-12-21 14:46:54","name":"52jhgv"},{"id":24,"slug":"gg6h-ddd","created_at":"2025-12-21 14:44:48","name":"gg6h-ddd"}],"hot":[]},"vendors":{"featured":[{"id":2,"store_name":"Fashion Hub","slug":"fashion-hub","logo_url":"/vendors/fashion-logo.png","rating_average":"0.00","total_products":0},{"id":35,"store_name":"بيب-55","slug":"تتنت-ى","logo_url":"/uploads/vendors/v35_logo_url_1766597294_f2e1ea80.jpg","rating_average":"0.00","total_products":0}]},"ad_zones":{"sidebar":[{"id":1,"image_url":"/images/ads/sidebar-ad-1.jpg","link_url":"#","alt":"إعلان جانبي"}],"inline":[{"id":2,"image_url":"/images/ads/inline-banner.jpg","link_url":"#","alt":"إعلان وسط الصفحة","background_color":"#f0f4ff"}]}}}}';

$apiResponse = json_decode($json_data, true);
$ui = $apiResponse['data']['ui'] ?? [];
$content = $apiResponse['data']['data'] ?? [];
$sections = $content['sections'] ?? [];

// تمرير البيانات للـ Partials عبر Global
$GLOBALS['PUBLIC_UI'] = $ui;
$banners    = $content['banners'] ?? [];
$categories = $content['categories'] ?? [];
$adZones    = $content['ad_zones'] ?? [];
$breadcrumbs = [['label' => ($lang == 'ar' ? 'الرئيسية' : 'Home'), 'url' => '/']];

// Helper function for escaping
if (!function_exists('_he')) {
    function _he($v) { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $ui['site_name'] ?? 'QOOQZ' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        :root {
            --color-primary: <?= _he($ui['colors']['primary'] ?? '#0d6efd') ?>;
            --color-secondary: <?= _he($ui['colors']['secondary'] ?? '#6c757d') ?>;
            --color-accent: <?= _he($ui['colors']['accent'] ?? '#f39c12') ?>;
            --color-success: <?= _he($ui['colors']['success'] ?? '#2ecc71') ?>;
            --color-bg: <?= _he($ui['colors']['background'] ?? '#ffffff') ?>;
            --color-surface: <?= _he($ui['colors']['surface'] ?? '#f8f9fb') ?>;
            --color-text: <?= _he($ui['colors']['text'] ?? '#222831') ?>;
            --color-muted: <?= _he($ui['colors']['muted'] ?? '#7f8c8d') ?>;
            --button-radius: <?= _he($ui['buttons']['radius'] ?? '6px') ?>;
            --card-shadow: <?= ($ui['cards']['shadow'] ?? false) ? '0 4px 10px rgba(0,0,0,0.08)' : 'none' ?>;
        }
        body {
            font-family: <?= _he($ui['fonts']['base'] ?? 'Cairo, sans-serif') ?>;
            background-color: var(--color-bg);
            margin: 0;
        }

        /* ── Homepage Layout ── */
        .homepage-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px 16px;
        }
        @media (min-width: 768px) {
            .homepage-layout {
                grid-template-columns: 250px 1fr;
                gap: 24px;
                padding: 24px;
            }
        }
        @media (min-width: 1024px) {
            .homepage-layout {
                grid-template-columns: 260px 1fr 200px;
            }
        }

        /* ── Sidebar Category Accordion ── */
        .cat-sidebar {
            background: var(--color-surface);
            border: 1px solid var(--color-border, #e6e9ee);
            border-radius: var(--radius-md, 8px);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .cat-sidebar__header {
            background: var(--color-primary);
            color: #fff;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cat-sidebar__list {
            list-style: none; margin: 0; padding: 0;
        }
        .cat-sidebar__item { border-bottom: 1px solid var(--color-border, #e6e9ee); }
        .cat-sidebar__item:last-child { border-bottom: none; }
        .cat-sidebar__link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px;
            color: var(--color-text);
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
            position: relative;
        }
        .cat-sidebar__link:hover {
            background: color-mix(in srgb, var(--color-primary) 8%, transparent);
            color: var(--color-primary);
            text-decoration: none;
        }
        .cat-sidebar__link.active {
            background: var(--color-primary);
            color: #fff;
        }
        .cat-sidebar__icon {
            width: 18px; text-align: center;
            color: var(--color-muted);
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .cat-sidebar__link:hover .cat-sidebar__icon,
        .cat-sidebar__link.active .cat-sidebar__icon { color: inherit; }
        .cat-sidebar__text { flex: 1; }
        .cat-sidebar__arrow {
            font-size: 0.65rem;
            color: var(--color-muted);
            transition: transform 0.25s;
            margin-inline-start: auto;
        }
        .cat-sidebar__item.is-open > .cat-sidebar__link > .cat-sidebar__arrow {
            transform: rotate(90deg);
            color: var(--color-primary);
        }
        [dir="rtl"] .cat-sidebar__item.is-open > .cat-sidebar__link > .cat-sidebar__arrow {
            transform: rotate(-90deg);
        }
        .cat-sidebar__sub {
            list-style: none; margin: 0; padding: 0;
            display: none;
            background: color-mix(in srgb, var(--color-surface) 90%, var(--color-primary) 10%);
        }
        .cat-sidebar__item.is-open > .cat-sidebar__sub { display: block; }
        .cat-sidebar__sub .cat-sidebar__link {
            padding-inline-start: 42px;
            font-size: 0.85rem;
        }

        /* ── Main content area ── */
        .homepage-main { min-width: 0; }

        /* ── Ad Zones ── */
        .ad-sidebar { display: none; }
        @media (min-width: 1024px) {
            .ad-sidebar { display: block; }
        }
        .ad-sidebar__slot {
            border-radius: var(--radius-md, 8px);
            overflow: hidden;
            margin-bottom: 16px;
            background: var(--color-surface);
            border: 1px solid var(--color-border, #e6e9ee);
        }
        .ad-sidebar__slot img {
            width: 100%; height: auto; display: block;
        }
        .ad-sidebar__label {
            font-size: 0.7rem;
            color: var(--color-muted);
            text-align: center;
            padding: 4px;
        }

        .ad-inline {
            border-radius: var(--radius-md, 8px);
            overflow: hidden;
            margin: 24px 0;
            position: relative;
        }
        .ad-inline img {
            width: 100%; height: auto; display: block;
            border-radius: var(--radius-md, 8px);
        }
        .ad-inline__label {
            position: absolute; top: 6px; right: 6px;
            font-size: 0.65rem; color: rgba(0,0,0,0.4);
            background: rgba(255,255,255,0.7);
            padding: 2px 6px; border-radius: 3px;
        }
        [dir="rtl"] .ad-inline__label { right: auto; left: 6px; }

        /* ── Section styling ── */
        .homepage-section {
            margin-bottom: 32px;
        }
        .homepage-section__head {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 16px; gap: 12px;
        }
        .homepage-section__title {
            font-size: 1.25rem; font-weight: 700;
            color: var(--color-text); margin: 0;
        }
        .homepage-section__link {
            font-size: 0.85rem; color: var(--color-primary);
            white-space: nowrap; text-decoration: none;
        }
        .homepage-section__link:hover { text-decoration: underline; }

        /* ── Product / Vendor Grid ── */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .card {
            border: 1px solid var(--color-border, #e6e9ee);
            padding: 16px; border-radius: var(--radius-md, 8px);
            box-shadow: var(--card-shadow);
            text-align: center; background: var(--color-bg, #fff);
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .card:hover {
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .card a { color: var(--color-primary); text-decoration: none; font-weight: 600; }
        .card a:hover { text-decoration: underline; }
        .card h4 { margin: 0 0 8px; font-size: 0.95rem; color: var(--color-text); }
        .card small { color: var(--color-muted); font-size: 0.8rem; }

        /* Vendor card */
        .vendor-card img {
            width: 60px; height: 60px; border-radius: 50%;
            object-fit: cover; margin: 0 auto 8px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/menu.php'; ?>

<div class="homepage-layout">

    <!-- ═══ LEFT SIDEBAR: Category Accordion ═══ -->
    <aside class="cat-sidebar-wrap">
        <?php if (!empty($categories)): ?>
        <nav class="cat-sidebar" aria-label="<?= $lang === 'ar' ? 'التصنيفات' : 'Categories' ?>">
            <div class="cat-sidebar__header">
                <i class="fas fa-bars" aria-hidden="true"></i>
                <?= $lang === 'ar' ? 'جميع التصنيفات' : 'All Categories' ?>
            </div>
            <ul class="cat-sidebar__list" id="catAccordion">
                <?php foreach ($categories as $cat):
                    $hasChildren = !empty($cat['children']);
                ?>
                <li class="cat-sidebar__item<?= $hasChildren ? ' has-children' : '' ?>">
                    <a href="<?= $hasChildren ? '#' : '/frontend/products.php?category_id=' . (int)($cat['id'] ?? 0) ?>"
                       class="cat-sidebar__link<?= $hasChildren ? ' js-cat-toggle' : '' ?>">
                        <?php if (!empty($cat['icon'])): ?>
                        <i class="<?= _he($cat['icon']) ?> cat-sidebar__icon" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span class="cat-sidebar__text"><?= _he($cat['name'] ?? '') ?></span>
                        <?php if ($hasChildren): ?>
                        <span class="cat-sidebar__arrow"><?= $direction === 'rtl' ? '❮' : '❯' ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($hasChildren): ?>
                    <ul class="cat-sidebar__sub">
                        <?php foreach ($cat['children'] as $child): ?>
                        <li class="cat-sidebar__item">
                            <a href="/frontend/products.php?category_id=<?= (int)($child['id'] ?? 0) ?>"
                               class="cat-sidebar__link">
                                <?php if (!empty($child['icon'])): ?>
                                <i class="<?= _he($child['icon']) ?> cat-sidebar__icon" aria-hidden="true"></i>
                                <?php endif; ?>
                                <span class="cat-sidebar__text"><?= _he($child['name'] ?? '') ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </aside>

    <!-- ═══ MAIN CONTENT ═══ -->
    <main class="homepage-main">
        <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>

        <!-- Slider -->
        <?php if (!empty($banners)) include __DIR__ . '/partials/slider.php'; ?>

        <!-- Dynamic Sections with inline ad zones -->
        <?php
        $sectionIndex = 0;
        foreach ($sections as $sectionKey):
            // Insert inline ad after first section
            if ($sectionIndex === 1 && !empty($adZones['inline'])):
                foreach ($adZones['inline'] as $inlineAd): ?>
                <div class="ad-inline"
                     <?= !empty($inlineAd['background_color']) ? 'style="background:' . _he($inlineAd['background_color']) . ';"' : '' ?>>
                    <a href="<?= _he($inlineAd['link_url'] ?? '#') ?>">
                        <img src="<?= _he($inlineAd['image_url'] ?? '') ?>"
                             alt="<?= _he($inlineAd['alt'] ?? '') ?>" loading="lazy">
                    </a>
                    <span class="ad-inline__label"><?= $lang === 'ar' ? 'إعلان' : 'Ad' ?></span>
                </div>
                <?php endforeach;
            endif;
        ?>

            <?php if ($sectionKey === 'featured_products' && !empty($content['products']['featured'])): ?>
            <section class="homepage-section">
                <div class="homepage-section__head">
                    <h2 class="homepage-section__title"><?= $lang === 'ar' ? 'المنتجات المميزة' : 'Featured Products' ?></h2>
                    <a href="/frontend/products.php?featured=1" class="homepage-section__link"><?= $lang === 'ar' ? 'عرض الكل ←' : 'View All →' ?></a>
                </div>
                <div class="grid">
                    <?php foreach ($content['products']['featured'] as $p): ?>
                    <div class="card">
                        <h4><?= _he($p['name']) ?></h4>
                        <a href="/product/<?= _he($p['slug']) ?>"><?= $lang === 'ar' ? 'عرض' : 'View' ?></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($sectionKey === 'new_products' && !empty($content['products']['new'])): ?>
            <section class="homepage-section">
                <div class="homepage-section__head">
                    <h2 class="homepage-section__title"><?= $lang === 'ar' ? 'أحدث المنتجات' : 'New Products' ?></h2>
                    <a href="/frontend/products.php?new=1" class="homepage-section__link"><?= $lang === 'ar' ? 'عرض الكل ←' : 'View All →' ?></a>
                </div>
                <div class="grid">
                    <?php foreach ($content['products']['new'] as $p): ?>
                    <div class="card">
                        <h4><?= _he($p['name']) ?></h4>
                        <small><?= _he($p['created_at']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($sectionKey === 'featured_vendors' && !empty($content['vendors']['featured'])): ?>
            <section class="homepage-section">
                <div class="homepage-section__head">
                    <h2 class="homepage-section__title"><?= $lang === 'ar' ? 'أفضل المتاجر' : 'Top Vendors' ?></h2>
                    <a href="/frontend/vendors.php" class="homepage-section__link"><?= $lang === 'ar' ? 'عرض الكل ←' : 'View All →' ?></a>
                </div>
                <div class="grid">
                    <?php foreach ($content['vendors']['featured'] as $v): ?>
                    <div class="card vendor-card">
                        <img src="<?= _he($v['logo_url']) ?>" alt="<?= _he($v['store_name']) ?>">
                        <h4><?= _he($v['store_name']) ?></h4>
                        <p style="margin:0;color:var(--color-muted);font-size:0.85rem;">
                            <?= $lang === 'ar' ? 'التقييم' : 'Rating' ?>: <?= _he($v['rating_average']) ?> ★
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        <?php
            $sectionIndex++;
        endforeach; ?>

    </main>

    <!-- ═══ RIGHT SIDEBAR: Ad Zones ═══ -->
    <aside class="ad-sidebar">
        <?php if (!empty($adZones['sidebar'])):
            foreach ($adZones['sidebar'] as $ad): ?>
        <div class="ad-sidebar__slot">
            <a href="<?= _he($ad['link_url'] ?? '#') ?>">
                <img src="<?= _he($ad['image_url'] ?? '') ?>"
                     alt="<?= _he($ad['alt'] ?? '') ?>" loading="lazy">
            </a>
            <div class="ad-sidebar__label"><?= $lang === 'ar' ? 'إعلان' : 'Ad' ?></div>
        </div>
        <?php endforeach;
        endif; ?>

        <!-- Placeholder ad slots -->
        <div class="ad-sidebar__slot" style="min-height:250px;display:flex;align-items:center;justify-content:center;">
            <span style="color:var(--color-muted);font-size:0.8rem;"><?= $lang === 'ar' ? 'مساحة إعلانية' : 'Ad Space' ?></span>
        </div>
        <div class="ad-sidebar__slot" style="min-height:250px;display:flex;align-items:center;justify-content:center;">
            <span style="color:var(--color-muted);font-size:0.8rem;"><?= $lang === 'ar' ? 'مساحة إعلانية' : 'Ad Space' ?></span>
        </div>
    </aside>

</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

<!-- Category Accordion Script (like admin menu.php) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion toggle for category sidebar — same pattern as admin/includes/menu.php
    function closeDescendants(item) {
        item.querySelectorAll('.cat-sidebar__item.has-children.is-open').forEach(function(desc) {
            desc.classList.remove('is-open');
        });
    }

    document.querySelectorAll('.js-cat-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var currentItem = this.closest('.cat-sidebar__item');
            var parentList  = currentItem.parentElement;
            var isOpening   = !currentItem.classList.contains('is-open');

            // Accordion: close all other open siblings at the same level
            if (isOpening && parentList) {
                parentList.querySelectorAll(':scope > .cat-sidebar__item.has-children.is-open').forEach(function(sibling) {
                    if (sibling !== currentItem) {
                        closeDescendants(sibling);
                        sibling.classList.remove('is-open');
                    }
                });
            }

            // When closing, also close all open descendants
            if (!isOpening) {
                closeDescendants(currentItem);
            }

            currentItem.classList.toggle('is-open');
        });
    });
});
</script>
</body>
</html>