<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * QOOQZ — Global Public Homepage (100% DB-driven)
 *
 * All sections are rendered from the homepage_sections table.
 * Each section delegates to a component file in components/{component}.php.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = $ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');

/* -------------------------------------------------------
 * CSS color sanitizer — only allow safe CSS color values
 * ----------------------------------------------------- */
if (!function_exists('_pub_safe_color')) {
    function _pub_safe_color(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) return $v;
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v)) return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) return $v;
        return '';
    }
}

/* -------------------------------------------------------
 * CSS padding sanitizer — only allow safe padding values
 * ----------------------------------------------------- */
if (!function_exists('_pub_safe_padding')) {
    function _pub_safe_padding(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        // Only allow non-negative numeric values with optional units (up to 4 values)
        if (preg_match('/^(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?)(?:\s+\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?){0,3}$/', $v)) return $v;
        return '';
    }
}

/* -------------------------------------------------------
 * getSectionData — parse data_source and fetch from API
 * ----------------------------------------------------- */
function getSectionData(string $dataSource, string $apiBase, string $lang, int $tenantId): array {
    $dataSource = trim($dataSource);
    if ($dataSource === '') return [];

    $qs = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=12&page=1';

    // Parse "type:filter" format
    $parts  = explode(':', $dataSource, 2);
    $type   = $parts[0];
    $filter = $parts[1] ?? '';

    return match ($type) {
        'categories' => pub_fetch($apiBase . 'public/categories?' . $qs . ($filter === 'featured' ? '&featured=1' : ''))['data']['data'] ?? [],
        'products'   => pub_fetch($apiBase . 'public/products?' . $qs . match ($filter) {
            'featured' => '&is_featured=1',
            'new'      => '&is_new=1',
            default    => '',
        })['data']['data'] ?? [],
        'banners'    => (function () use ($apiBase, $tenantId, $filter) {
            $position = ($filter && $filter !== 'all') ? '&position=' . urlencode($filter) : '';
            $result = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $position);
            $data   = $result['data']['data'] ?? $result['data'] ?? [];
            // Fallback: if position-filtered returned empty, try all banners
            if (empty($data) && $position !== '') {
                $result = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId);
                $data   = $result['data']['data'] ?? $result['data'] ?? [];
            }
            return $data;
        })(),
        'deals'      => pub_fetch($apiBase . 'public/discounts?tenant_id=' . $tenantId . '&lang=' . urlencode($lang))['data']['data'] ?? [],
        'entities'   => pub_fetch($apiBase . 'public/entities?' . $qs . ($filter === 'featured' ? '&is_featured=1' : ''))['data']['data'] ?? [],
        'brands'     => pub_fetch($apiBase . 'public/brands?' . $qs . ($filter === 'featured' ? '&is_featured=1' : ''))['data']['data'] ?? [],
        'tenants'    => pub_fetch($apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=12&page=1' . ($filter === 'active' ? '&status=active' : ''))['data']['data'] ?? [],
        'jobs'       => pub_fetch($apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=12&page=1' . ($filter === 'featured' ? '&is_featured=1' : ''))['data']['data'] ?? [],
        'stats'      => [], // ad_stats component fetches its own data
        default      => [],
    };
}

/* -------------------------------------------------------
 * Fetch all active sections from DB via API
 * ----------------------------------------------------- */
$sectionsResp = pub_fetch($apiBase . 'public/homepage_sections?tenant_id=' . $tenantId . '&lang=' . urlencode($lang));
$sections     = $sectionsResp['data']['data'] ?? [];

/* -------------------------------------------------------
 * Pre-resolve card styles from DB (already loaded in theme)
 * ----------------------------------------------------- */
include dirname(__DIR__) . '/partials/header.php';

$_cardStyles = [
    'entities' => [
        'inline' => pub_card_inline_style('entities'),
        'class'  => pub_card_css_class('entities'),
    ],
    'tenants' => [
        'inline' => pub_card_inline_style('tenants'),
        'class'  => pub_card_css_class('tenants'),
    ],
    'product' => [
        'inline' => pub_card_inline_style('product'),
        'class'  => pub_card_css_class('product'),
        'img'    => pub_card_img_style('product'),
    ],
    'category' => [
        'inline' => pub_card_inline_style('category'),
        'class'  => pub_card_css_class('category'),
        'img'    => pub_card_img_style('category'),
    ],
    'promo' => [
        'inline' => pub_card_inline_style('promo'),
        'class'  => pub_card_css_class('promo'),
    ],
    'feature' => [
        'inline' => pub_card_inline_style('feature'),
        'class'  => pub_card_css_class('feature'),
    ],
];

$componentsDir = __DIR__ . '/components';
?>

<div id="pub-homepage-sections">
<?php foreach ($sections as $section):
    $component   = $section['component'] ?? 'default';
    $sectionData = getSectionData($section['data_source'] ?? '', $apiBase, $lang, $tenantId);

    // Build section inline style from DB fields
    $secBg      = _pub_safe_color($section['background_color'] ?? '');
    $secText    = _pub_safe_color($section['text_color'] ?? '');
    $secPadding = _pub_safe_padding($section['padding'] ?? '');
    $secCss     = $section['custom_css'] ?? '';

    $sStyle = '';
    if ($secBg)      $sStyle .= 'background-color:' . e($secBg) . ';';
    if ($secText)    $sStyle .= 'color:' . e($secText) . ';';
    if ($secPadding) $sStyle .= 'padding:' . e($secPadding) . ';';

    $secTitle = $section['title'] ?? '';
    $secSub   = $section['subtitle'] ?? '';

    // Determine "view all" link from component type
    $viewAllLink = match ($component) {
        'ad_categories'           => '/frontend/public/categories.php',
        'ad_products'             => '/frontend/public/products.php',
        'ad_deals'                => '/frontend/public/products.php',
        'ad_entities'             => '/frontend/public/entities.php',
        'ad_jobs'                 => '/frontend/public/jobs.php',
        'ad_tenants'              => '/frontend/public/tenants.php',
        default                   => '',
    };

    // Components that manage their own outer wrapper (no pub-container needed)
    $isFullWidth = in_array($component, ['ad_slider', 'ad_search'], true);
?>
<section class="homepage-section"<?= $sStyle ? ' style="' . $sStyle . '"' : '' ?>>
<?php if ($secCss !== ''):
    // Strip potential style-tag breakout sequences for security
    $safeCss = str_ireplace(['</style', '<script', '</', '<!--'], '', $secCss);
?>
<style><?= $safeCss ?></style>
<?php endif; ?>

<?php if ($isFullWidth): ?>
    <?php
        $componentFile = $componentsDir . '/' . basename($component) . '.php';
        if (is_file($componentFile)) {
            include $componentFile;
        }
    ?>
<?php else: ?>
    <div class="pub-container">
        <?php if ($secTitle !== ''): ?>
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e($secTitle) ?></h2>
            <?php if ($viewAllLink !== ''): ?>
            <a href="<?= e($viewAllLink) ?>" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($secSub !== ''): ?><p class="pub-section-sub"><?= e($secSub) ?></p><?php endif; ?>
        <?php endif; ?>

        <?php
            $componentFile = $componentsDir . '/' . basename($component) . '.php';
            if (is_file($componentFile)) {
                include $componentFile;
            }
        ?>
    </div>
<?php endif; ?>
</section>
<?php endforeach; ?>
</div><!-- #pub-homepage-sections -->

<script>
if (typeof PubHomepageEngine !== 'undefined') {
    PubHomepageEngine.init(<?= (int)$tenantId ?>, '<?= e($lang) ?>');
}
</script>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>