<?php
declare(strict_types=1);
/**
 * frontend/public/display.php
 * QOOQZ — Standalone Products & Category Menu Showcase Page
 *
 * Completely self-contained page: no header/footer partials included.
 * Features:
 *   - Animated banner carousel (CSS + JS)
 *   - Products grid (fetched from API or PDO)
 *   - Category menu (sidebar + mobile drawer)
 *   - Theme colours from DB (primary #03874e, secondary #10B981, accent #098684)
 *
 * Usage: /frontend/public/display.php
 *        /frontend/public/display.php?category_id=5
 *        /frontend/public/display.php?q=search+term
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

/* ------------------------------------------------------------------
 * Context
 * ---------------------------------------------------------------- */
$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = $ctx['tenant_id'];
$isRtl    = ($dir === 'rtl');

// Theme CSS variables (pulled from loaded palette; fall back to brand defaults)
$primaryColor   = $theme['primary']   ?? '#03874e';
$secondaryColor = $theme['secondary'] ?? '#10B981';
$accentColor    = $theme['accent']    ?? '#098684';
$bgColor        = $theme['background']?? '#0f172a';
$surfaceColor   = $theme['surface']   ?? '#1e293b';
$textColor      = $theme['text']      ?? '#e2e8f0';
$textMuted      = $theme['text_muted']?? '#94a3b8';
$borderColor    = $theme['border']    ?? '#334155';

/* ------------------------------------------------------------------
 * Filters
 * ---------------------------------------------------------------- */
$catId   = (int)($_GET['category_id'] ?? 0);
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;

/* ------------------------------------------------------------------
 * Fetch data via PDO (same pattern as products.php)
 * ---------------------------------------------------------------- */
$products   = [];
$categories = [];
$banners    = [];

$pdo = pub_get_pdo();
if ($pdo) {
    try {
        /* --- Products --- */
        $where  = ['1=1', 'p.is_active = 1'];
        $params = [$lang];
        if ($tenantId) { $where[] = 'p.tenant_id = ?'; $params[] = $tenantId; }
        if ($catId)    { $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)'; $params[] = $catId; }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(COALESCE(pt.name, p.slug) LIKE ?)';
            $params[] = $like;
        }
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare(
            "SELECT p.id, p.slug, p.is_featured, p.stock_quantity, p.stock_status,
                    COALESCE(pt.name, p.slug) AS name,
                    (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url
             FROM products p
             LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
             WHERE $whereClause
             ORDER BY p.is_featured DESC, p.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* --- Categories --- */
        $cParams = $tenantId ? [$tenantId] : [];
        $cWhere  = $tenantId ? 'WHERE tenant_id = ? AND is_active = 1' : 'WHERE is_active = 1';
        $cStmt = $pdo->prepare(
            "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug, c.parent_id,
                    (SELECT i.url FROM images i WHERE i.owner_id = c.id ORDER BY i.id ASC LIMIT 1) AS image_url
             FROM categories c
             LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
             $cWhere
             ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.id
             LIMIT 30"
        );
        $cStmt->execute(array_merge([$lang], $cParams));
        $categories = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        /* --- Banners --- */
        $bParams = $tenantId ? [$tenantId] : [];
        $bWhere  = $tenantId ? 'WHERE tenant_id = ? AND is_active = 1' : 'WHERE is_active = 1';
        $bStmt = $pdo->prepare(
            "SELECT id, title, subtitle, image_url, mobile_image_url, link_url, button_text
             FROM banners $bWhere ORDER BY sort_order, id LIMIT 8"
        );
        $bStmt->execute($bParams);
        $banners = $bStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[display.php] PDO error: ' . $e->getMessage());
    }
}

// HTTP API fallback if PDO is unavailable
if (!$pdo || (empty($products) && empty($categories))) {
    $apiBase = pub_api_url('');
    $qs = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=' . $limit . '&page=' . $page;
    if ($catId)    $qs .= '&category_id=' . $catId;
    if ($search)   $qs .= '&q=' . urlencode($search);

    $rProd = pub_fetch($apiBase . 'public/products?' . $qs);
    $products = $rProd['data']['data'] ?? ($rProd['data']['items'] ?? []);

    $rCat = pub_fetch($apiBase . 'public/categories?lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=30');
    $categories = $rCat['data']['data'] ?? ($rCat['data']['items'] ?? []);

    $rBan = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId);
    $banners = $rBan['data']['data'] ?? ($rBan['data']['items'] ?? []);
}

// Static demo banners if table is empty
if (empty($banners)) {
    $banners = [
        ['id' => 1, 'title' => $isRtl ? 'عروض حصرية' : 'Exclusive Deals', 'subtitle' => $isRtl ? 'اكتشف أفضل المنتجات بأسعار لا تُصدق' : 'Discover top products at unbeatable prices', 'image_url' => '', 'link_url' => '', 'button_text' => $isRtl ? 'تسوق الآن' : 'Shop Now', 'color' => '#03874e'],
        ['id' => 2, 'title' => $isRtl ? 'منتجات جديدة' : 'New Arrivals',   'subtitle' => $isRtl ? 'أحدث المنتجات المتاحة في متجرنا'      : 'Latest products now available in our store', 'image_url' => '', 'link_url' => '', 'button_text' => $isRtl ? 'اكتشف الجديد' : 'Explore New', 'color' => '#098684'],
        ['id' => 3, 'title' => $isRtl ? 'تشكيلة واسعة' : 'Wide Selection', 'subtitle' => $isRtl ? 'آلاف المنتجات تنتظرك في مكان واحد'      : 'Thousands of products waiting for you in one place', 'image_url' => '', 'link_url' => '', 'button_text' => $isRtl ? 'تصفح المنتجات' : 'Browse All', 'color' => '#10B981'],
    ];
}

/* ------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------- */
function d_e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function d_price(?string $price, ?string $currency): string {
    if (!$price) return '';
    $cur = strtoupper($currency ?? 'SAR');
    return number_format((float)$price, 2) . ' ' . $cur;
}
function d_img(?string $url, string $fallback = ''): string {
    if (!$url) return $fallback ?: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%231e293b"/%3E%3Ctext x="150" y="100" font-family="sans-serif" font-size="14" fill="%2364748b" text-anchor="middle" dominant-baseline="middle"%3ENo Image%3C/text%3E%3C/svg%3E';
    // Only allow safe URL schemes (relative paths, http, https) to prevent javascript: or data: injection.
    if (!preg_match('#^(https?://|/)#i', $url)) return $fallback ?: '';
    return d_e($url);
}

$pageTitle = $isRtl ? 'عرض المنتجات والقوائم' : 'Products & Menu Display';
$activeCategory = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === $catId) { $activeCategory = $c; break; }
}
?>
<!DOCTYPE html>
<html lang="<?= d_e($lang) ?>" dir="<?= d_e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= d_e($pageTitle) ?> — QOOQZ</title>

    <!-- Google Fonts: Cairo (Arabic/RTL) + Inter (LTR) -->
    <?php if ($isRtl): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php else: ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>

    <style>
        /* ============================================================
         * CSS Custom Properties (from DB theme — theme.md reference)
         * ============================================================ */
        :root {
            --primary:          <?= d_e($primaryColor) ?>;
            --primary-dark:     <?= d_e($primaryColor) ?>cc;
            --secondary:        <?= d_e($secondaryColor) ?>;
            --accent:           <?= d_e($accentColor) ?>;
            --bg:               <?= d_e($bgColor) ?>;
            --surface:          <?= d_e($surfaceColor) ?>;
            --surface-alt:      #263548;
            --text:             <?= d_e($textColor) ?>;
            --text-muted:       <?= d_e($textMuted) ?>;
            --border:           <?= d_e($borderColor) ?>;
            --radius-sm:        6px;
            --radius-md:        12px;
            --radius-lg:        20px;
            --shadow-sm:        0 2px 8px rgba(0,0,0,.25);
            --shadow-md:        0 4px 20px rgba(0,0,0,.35);
            --shadow-lg:        0 8px 40px rgba(0,0,0,.45);
            --sidebar-width:    260px;
            --header-h:         64px;
            --transition:       .28s ease;
            --font:             <?= $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif" ?>;
        }

        /* ============================================================
         * Reset & Base
         * ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        ul { list-style: none; }

        /* ============================================================
         * Layout: Top Nav Bar
         * ============================================================ */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 200;
            height: var(--header-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }
        .topbar-brand {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            flex-shrink: 0;
        }
        .topbar-search {
            flex: 1;
            max-width: 480px;
        }
        .topbar-search form {
            display: flex;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .topbar-search input {
            flex: 1;
            background: none;
            border: none;
            outline: none;
            padding: 8px 14px;
            color: var(--text);
            font-family: var(--font);
            font-size: .9rem;
        }
        .topbar-search button {
            background: var(--primary);
            border: none;
            color: #fff;
            padding: 8px 16px;
            cursor: pointer;
            font-size: .9rem;
            transition: background var(--transition);
        }
        .topbar-search button:hover { background: var(--accent); }
        .topbar-spacer { flex: 1; }
        .topbar-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            cursor: pointer;
            padding: 6px;
            font-size: 1.4rem;
            line-height: 1;
        }

        /* ============================================================
         * Wrapper: Sidebar + Content
         * ============================================================ */
        .page-wrapper {
            display: flex;
            min-height: calc(100vh - var(--header-h));
        }

        /* ============================================================
         * Sidebar / Category Menu
         * ============================================================ */
        .sidebar {
            width: var(--sidebar-width);
            flex-shrink: 0;
            background: var(--surface);
            border-<?= $isRtl ? 'left' : 'right' ?>: 1px solid var(--border);
            padding: 20px 0;
            position: sticky;
            top: var(--header-h);
            height: calc(100vh - var(--header-h));
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
            transition: transform var(--transition);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        .sidebar-title {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0 18px 10px;
        }

        .cat-list { display: flex; flex-direction: column; gap: 2px; }
        .cat-item a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            margin: 0 8px;
            font-size: .9rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: background var(--transition), color var(--transition);
        }
        .cat-item a:hover,
        .cat-item.active a {
            background: color-mix(in srgb, var(--primary) 14%, transparent);
            color: var(--primary);
        }
        .cat-item.active a { font-weight: 700; }
        .cat-item a .cat-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: var(--surface-alt);
        }
        .cat-item a .cat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--border);
            flex-shrink: 0;
        }
        .cat-item.active a .cat-dot { background: var(--primary); }

        /* ============================================================
         * Main Content Area
         * ============================================================ */
        .main-content {
            flex: 1;
            min-width: 0;
            padding: 0 0 40px;
        }

        /* ============================================================
         * Animated Banner Carousel
         * ============================================================ */
        .banner-carousel {
            position: relative;
            overflow: hidden;
            background: var(--surface);
            height: 380px;
        }
        @media (max-width: 768px) { .banner-carousel { height: 240px; } }

        .banner-track {
            display: flex;
            height: 100%;
            /* JS drives the translateX; CSS handles the smooth transition */
            transition: transform .6s cubic-bezier(.4, 0, .2, 1);
            will-change: transform;
        }

        .banner-slide {
            min-width: 100%;
            height: 100%;
            position: relative;
            flex-shrink: 0;
            overflow: hidden;
        }
        .banner-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(.65);
        }
        .banner-slide-bg {
            /* Used when no image is available */
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--slide-color, var(--primary)) 0%, var(--bg) 100%);
        }

        /* Animated particles overlay on slides */
        .banner-slide-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255,255,255,.06) 0%, transparent 60%),
                        radial-gradient(circle at 80% 20%, rgba(255,255,255,.04) 0%, transparent 50%);
            animation: pulseGlow 4s ease-in-out infinite alternate;
        }
        @keyframes pulseGlow {
            from { opacity: .6; }
            to   { opacity: 1; }
        }

        .banner-caption {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: <?= $isRtl ? 'flex-end' : 'flex-start' ?>;
            padding: 40px 60px;
            text-align: <?= $isRtl ? 'right' : 'left' ?>;
        }
        @media (max-width: 768px) { .banner-caption { padding: 20px 24px; } }

        .banner-caption h2 {
            font-size: clamp(1.4rem, 3.5vw, 2.6rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0,0,0,.5);
            /* Animate in when slide becomes active */
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .5s ease .3s, transform .5s ease .3s;
        }
        .banner-caption p {
            font-size: clamp(.85rem, 1.8vw, 1.1rem);
            color: rgba(255,255,255,.85);
            margin-top: 10px;
            max-width: 520px;
            text-shadow: 0 1px 4px rgba(0,0,0,.5);
            opacity: 0;
            transform: translateY(18px);
            transition: opacity .5s ease .45s, transform .5s ease .45s;
        }
        .banner-caption .banner-btn {
            display: inline-block;
            margin-top: 22px;
            padding: 12px 28px;
            background: var(--primary);
            color: #fff;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: .95rem;
            transition: background var(--transition), transform var(--transition);
            opacity: 0;
            transform: translateY(14px);
            transition: opacity .5s ease .6s, transform .5s ease .6s, background .2s;
        }
        .banner-caption .banner-btn:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }

        /* When slide is active → animate captions in */
        .banner-slide.is-active .banner-caption h2,
        .banner-slide.is-active .banner-caption p,
        .banner-slide.is-active .banner-caption .banner-btn {
            opacity: 1;
            transform: translateY(0);
        }

        /* Navigation dots */
        .banner-dots {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .banner-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,.4);
            cursor: pointer;
            transition: background var(--transition), transform var(--transition), width var(--transition);
            border: none;
        }
        .banner-dot.is-active {
            background: #fff;
            width: 24px;
            border-radius: 4px;
        }

        /* Prev / Next arrows */
        .banner-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: rgba(0,0,0,.35);
            border: none;
            color: #fff;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background var(--transition);
            backdrop-filter: blur(4px);
        }
        .banner-arrow:hover { background: rgba(0,0,0,.6); }
        .banner-arrow-prev { <?= $isRtl ? 'right' : 'left' ?>: 16px; }
        .banner-arrow-next { <?= $isRtl ? 'left' : 'right' ?>: 16px; }

        /* Progress bar */
        .banner-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary);
            width: 0%;
            transition: width linear;
            z-index: 5;
        }

        /* ============================================================
         * Page Content (below banner)
         * ============================================================ */
        .content-header {
            padding: 24px 28px 8px;
            display: flex;
            align-items: baseline;
            gap: 10px;
            border-bottom: 1px solid var(--border);
        }
        .content-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .content-header .result-count {
            font-size: .85rem;
            color: var(--text-muted);
        }

        /* ============================================================
         * Products Grid
         * ============================================================ */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 18px;
            padding: 22px 28px;
        }
        @media (max-width: 560px) {
            .products-grid { grid-template-columns: repeat(2, 1fr); padding: 14px; gap: 12px; }
        }

        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: transform var(--transition), box-shadow var(--transition);
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .product-card-img {
            position: relative;
            padding-top: 72%;
            overflow: hidden;
            background: var(--surface-alt);
        }
        .product-card-img img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s ease;
        }
        .product-card:hover .product-card-img img { transform: scale(1.06); }

        .product-card-badge {
            position: absolute;
            top: 10px;
            <?= $isRtl ? 'right' : 'left' ?>: 10px;
            background: var(--primary);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: .05em;
            z-index: 2;
        }
        .product-card-badge.out { background: #ef4444; }

        .product-card-body {
            padding: 12px 14px;
        }
        .product-card-name {
            font-size: .9rem;
            font-weight: 600;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .product-card-price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        .product-card-actions {
            padding: 0 14px 14px;
        }
        .btn-add {
            display: block;
            width: 100%;
            padding: 9px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: var(--font);
            font-size: .85rem;
            font-weight: 600;
            transition: background var(--transition);
            text-align: center;
        }
        .btn-add:hover { background: var(--accent); }

        /* Empty state */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state svg { width: 64px; height: 64px; opacity: .35; margin: 0 auto 16px; display: block; }

        /* ============================================================
         * Footer
         * ============================================================ */
        footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            text-align: center;
            padding: 20px;
            font-size: .8rem;
            color: var(--text-muted);
        }

        /* ============================================================
         * Mobile: sidebar overlay
         * ============================================================ */
        @media (max-width: 900px) {
            .sidebar {
                position: fixed;
                top: var(--header-h);
                <?= $isRtl ? 'right' : 'left' ?>: 0;
                z-index: 150;
                height: calc(100vh - var(--header-h));
                transform: translateX(<?= $isRtl ? '110%' : '-110%' ?>);
                box-shadow: var(--shadow-lg);
            }
            .sidebar.is-open { transform: translateX(0); }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                top: var(--header-h);
                background: rgba(0,0,0,.5);
                z-index: 140;
            }
            .sidebar-overlay.is-open { display: block; }
            .topbar-menu-toggle { display: flex; }
        }

        /* ============================================================
         * Accessibility: focus ring
         * ============================================================ */
        :focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        button { font-family: var(--font); }
    </style>
</head>
<body>

<!-- ================================================================
     TOP NAV BAR
     ================================================================ -->
<header class="topbar">
    <button class="topbar-menu-toggle" id="menuToggle" aria-label="Toggle menu">☰</button>
    <a class="topbar-brand" href="/frontend/public/display.php">QOOQZ</a>

    <div class="topbar-search">
        <form method="get" action="">
            <?php if ($catId): ?><input type="hidden" name="category_id" value="<?= $catId ?>"> <?php endif; ?>
            <input
                type="search"
                name="q"
                value="<?= d_e($search) ?>"
                placeholder="<?= $isRtl ? 'البحث عن منتج…' : 'Search products…' ?>"
                aria-label="search">
            <button type="submit">&#x1F50D;</button>
        </form>
    </div>

    <div class="topbar-spacer"></div>

    <nav style="display:flex;gap:16px;font-size:.85rem;font-weight:500">
        <a href="/frontend/public/index.php"      style="color:var(--text-muted)"><?= $isRtl ? 'الرئيسية'  : 'Home' ?></a>
        <a href="/frontend/public/products.php"   style="color:var(--text-muted)"><?= $isRtl ? 'المنتجات'  : 'Products' ?></a>
        <a href="/frontend/public/categories.php" style="color:var(--text-muted)"><?= $isRtl ? 'التصنيفات' : 'Categories' ?></a>
    </nav>
</header>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ================================================================
     PAGE WRAPPER  (Sidebar + Main)
     ================================================================ -->
<div class="page-wrapper">

    <!-- ----------------------------------------------------------------
         CATEGORY MENU SIDEBAR
         ---------------------------------------------------------------- -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="<?= $isRtl ? 'قائمة التصنيفات' : 'Category menu' ?>">
        <p class="sidebar-title"><?= $isRtl ? 'التصنيفات' : 'Categories' ?></p>

        <ul class="cat-list">
            <!-- All Products link -->
            <li class="cat-item <?= !$catId ? 'active' : '' ?>">
                <a href="?<?= $search ? 'q=' . urlencode($search) : '' ?>">
                    <span class="cat-dot"></span>
                    <?= $isRtl ? 'كل المنتجات' : 'All Products' ?>
                </a>
            </li>

            <?php foreach ($categories as $cat): ?>
            <li class="cat-item <?= ((int)$cat['id'] === $catId) ? 'active' : '' ?>">
                <a href="?category_id=<?= (int)$cat['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?>">
                    <?php if (!empty($cat['image_url'])): ?>
                        <img class="cat-img" src="<?= d_e($cat['image_url']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="cat-dot"></span>
                    <?php endif; ?>
                    <?= d_e($cat['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <!-- ----------------------------------------------------------------
         MAIN CONTENT
         ---------------------------------------------------------------- -->
    <main class="main-content">

        <!-- ============================================================
             ANIMATED BANNER CAROUSEL
             ============================================================ -->
        <div class="banner-carousel" role="region" aria-label="<?= $isRtl ? 'البانر' : 'Banner slideshow' ?>">

            <div class="banner-track" id="bannerTrack">
                <?php foreach ($banners as $i => $b):
                    $hasImg = !empty($b['image_url']);
                    $slideColor = $b['color'] ?? ([$primaryColor, $accentColor, $secondaryColor][$i % 3]);
                ?>
                <div class="banner-slide <?= $i === 0 ? 'is-active' : '' ?>"
                     role="group"
                     aria-label="<?= $isRtl ? 'شريحة ' . ($i + 1) : 'Slide ' . ($i + 1) ?>">

                    <?php if ($hasImg): ?>
                        <img src="<?= d_e($b['image_url']) ?>" alt="<?= d_e($b['title'] ?? '') ?>" loading="lazy">
                    <?php else: ?>
                        <div class="banner-slide-bg" style="--slide-color: <?= d_e($slideColor) ?>"></div>
                    <?php endif; ?>

                    <div class="banner-caption">
                        <?php if (!empty($b['title'])): ?>
                            <h2><?= d_e($b['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($b['subtitle'])): ?>
                            <p><?= d_e($b['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($b['link_url'])): ?>
                            <a class="banner-btn" href="<?= d_e($b['link_url']) ?>">
                                <?= d_e($b['button_text'] ?? ($isRtl ? 'اعرف أكثر' : 'Learn More')) ?>
                            </a>
                        <?php elseif (!empty($b['button_text'])): ?>
                            <span class="banner-btn">
                                <?= d_e($b['button_text']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div><!-- /banner-track -->

            <!-- Navigation dots -->
            <div class="banner-dots" role="tablist">
                <?php foreach ($banners as $i => $b): ?>
                <button class="banner-dot <?= $i === 0 ? 'is-active' : '' ?>"
                        data-idx="<?= $i ?>"
                        role="tab"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-label="<?= $isRtl ? 'الشريحة ' . ($i + 1) : 'Slide ' . ($i + 1) ?>">
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Arrows (only if > 1 slide) -->
            <?php if (count($banners) > 1): ?>
            <button class="banner-arrow banner-arrow-prev" id="bannerPrev" aria-label="<?= $isRtl ? 'السابق' : 'Previous' ?>">
                <?= $isRtl ? '&#8250;' : '&#8249;' ?>
            </button>
            <button class="banner-arrow banner-arrow-next" id="bannerNext" aria-label="<?= $isRtl ? 'التالي' : 'Next' ?>">
                <?= $isRtl ? '&#8249;' : '&#8250;' ?>
            </button>
            <?php endif; ?>

            <!-- Auto-play progress bar -->
            <div class="banner-progress" id="bannerProgress"></div>

        </div><!-- /banner-carousel -->

        <!-- ============================================================
             PRODUCTS SECTION
             ============================================================ -->
        <div class="content-header">
            <h1>
                <?php if ($activeCategory): ?>
                    <?= d_e($activeCategory['name']) ?>
                <?php elseif ($search): ?>
                    <?= $isRtl ? 'نتائج البحث: ' : 'Search results: ' ?>"<?= d_e($search) ?>"
                <?php else: ?>
                    <?= $isRtl ? 'جميع المنتجات' : 'All Products' ?>
                <?php endif; ?>
            </h1>
            <span class="result-count">
                (<?= count($products) ?> <?= $isRtl ? 'منتج' : 'items' ?>)
            </span>
        </div>

        <div class="products-grid">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M9 21h6"/>
                    </svg>
                    <p><?= $isRtl ? 'لا توجد منتجات متاحة' : 'No products found' ?></p>
                </div>

            <?php else: foreach ($products as $p):
                $stockStatus  = $p['stock_status'] ?? 'in_stock';
                $outOfStock   = in_array($stockStatus, ['out_of_stock', 'unavailable'], true);
                $isFeatured   = !empty($p['is_featured']);
                $priceStr     = d_price($p['price'] ?? null, $p['currency_code'] ?? null);
                $href = '/frontend/public/product.php?id=' . (int)$p['id'];
            ?>
                <article class="product-card">
                    <a href="<?= $href ?>">
                        <div class="product-card-img">
                            <img src="<?= d_img($p['image_url'] ?? null) ?>"
                                 alt="<?= d_e($p['name'] ?? '') ?>"
                                 loading="lazy">
                            <?php if ($isFeatured && !$outOfStock): ?>
                                <span class="product-card-badge">
                                    <?= $isRtl ? '★ مميز' : '★ Featured' ?>
                                </span>
                            <?php elseif ($outOfStock): ?>
                                <span class="product-card-badge out">
                                    <?= $isRtl ? 'نفذ' : 'Out' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="product-card-body">
                        <a href="<?= $href ?>">
                            <p class="product-card-name"><?= d_e($p['name'] ?? $p['slug'] ?? '') ?></p>
                        </a>
                        <?php if ($priceStr): ?>
                            <p class="product-card-price"><?= d_e($priceStr) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-actions">
                        <a class="btn-add" href="<?= $href ?>">
                            <?= $isRtl ? 'عرض المنتج' : 'View Product' ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; endif; ?>
        </div><!-- /products-grid -->

    </main><!-- /main-content -->
</div><!-- /page-wrapper -->

<!-- ================================================================
     FOOTER
     ================================================================ -->
<footer>
    &copy; <?= date('Y') ?> QOOQZ &mdash; <?= $isRtl ? 'جميع الحقوق محفوظة' : 'All rights reserved' ?>
</footer>

<!-- ================================================================
     JAVASCRIPT: Banner Carousel + Sidebar Toggle
     ================================================================ -->
<script>
(function () {
    'use strict';

    /* ----------------------------------------------------------
     * Banner Carousel
     * -------------------------------------------------------- */
    var AUTOPLAY_MS   = 5000;   // ms between slides
    var track         = document.getElementById('bannerTrack');
    var slides        = track ? Array.from(track.querySelectorAll('.banner-slide')) : [];
    var dots          = Array.from(document.querySelectorAll('.banner-dot'));
    var progressBar   = document.getElementById('bannerProgress');
    var prevBtn       = document.getElementById('bannerPrev');
    var nextBtn       = document.getElementById('bannerNext');
    var current       = 0;
    var timer         = null;
    var progressTimer = null;

    if (!slides.length) return; // no slides, nothing to do

    function goTo(idx) {
        // Clamp & wrap
        idx = ((idx % slides.length) + slides.length) % slides.length;

        // Remove active from old
        slides[current].classList.remove('is-active');
        if (dots[current]) {
            dots[current].classList.remove('is-active');
            dots[current].setAttribute('aria-selected', 'false');
        }

        current = idx;

        // Slide the track — always use negative offset for standard LTR slide direction.
        // RTL text layout does NOT affect the carousel slide direction; the slides are
        // positioned absolutely in the flex track and we always slide in the negative X direction.
        var offset = current * 100;
        track.style.transform = 'translateX(' + (-offset) + '%)';

        // Activate new slide (triggers caption animation via CSS)
        slides[current].classList.add('is-active');
        if (dots[current]) {
            dots[current].classList.add('is-active');
            dots[current].setAttribute('aria-selected', 'true');
        }

        // Restart progress bar
        resetProgress();
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function resetProgress() {
        if (!progressBar) return;
        progressBar.style.transition = 'none';
        progressBar.style.width = '0%';
        // Read offsetWidth to force a browser reflow; this ensures the transition:none
        // change has been applied before we re-enable the transition below.
        // eslint-disable-next-line no-unused-expressions
        progressBar.offsetWidth; // jshint ignore:line
        progressBar.style.transition = 'width ' + AUTOPLAY_MS + 'ms linear';
        progressBar.style.width = '100%';
    }

    function startAutoplay() {
        stopAutoplay();
        timer = setInterval(next, AUTOPLAY_MS);
        resetProgress();
    }

    function stopAutoplay() {
        if (timer) { clearInterval(timer); timer = null; }
        if (progressBar) {
            progressBar.style.transition = 'none';
            progressBar.style.width = '0%';
        }
    }

    // Arrow buttons
    if (prevBtn) prevBtn.addEventListener('click', function () { stopAutoplay(); prev(); startAutoplay(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { stopAutoplay(); next(); startAutoplay(); });

    // Dot clicks
    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            stopAutoplay();
            goTo(parseInt(dot.dataset.idx, 10));
            startAutoplay();
        });
    });

    // Touch / swipe support
    var touchStartX = null;
    track.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    track.addEventListener('touchend', function (e) {
        if (touchStartX === null) return;
        var dx = e.changedTouches[0].clientX - touchStartX;
        touchStartX = null;
        if (Math.abs(dx) > 40) {
            stopAutoplay();
            if (dx < 0) next(); else prev();
            startAutoplay();
        }
    }, { passive: true });

    // Keyboard (left/right arrows) — prevent default page scroll when navigating slides
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            stopAutoplay();
            (document.documentElement.dir === 'rtl' ? next : prev)();
            startAutoplay();
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            stopAutoplay();
            (document.documentElement.dir === 'rtl' ? prev : next)();
            startAutoplay();
        }
    });

    // Pause on hover
    track.addEventListener('mouseenter', stopAutoplay);
    track.addEventListener('mouseleave', startAutoplay);

    // Start!
    goTo(0);
    startAutoplay();

    /* ----------------------------------------------------------
     * Sidebar Toggle (mobile)
     * -------------------------------------------------------- */
    var sidebar       = document.getElementById('sidebar');
    var sidebarOverlay= document.getElementById('sidebarOverlay');
    var menuToggle    = document.getElementById('menuToggle');

    function openSidebar() {
        if (sidebar)        sidebar.classList.add('is-open');
        if (sidebarOverlay) sidebarOverlay.classList.add('is-open');
        if (menuToggle)     menuToggle.setAttribute('aria-expanded', 'true');
    }
    function closeSidebar() {
        if (sidebar)        sidebar.classList.remove('is-open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('is-open');
        if (menuToggle)     menuToggle.setAttribute('aria-expanded', 'false');
    }

    if (menuToggle)     menuToggle.addEventListener('click', function () {
        sidebar && sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
    });
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });

})();
</script>

</body>
</html>
