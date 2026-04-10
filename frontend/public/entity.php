<?php
/**
 * frontend/public/entity.php
 * QOOQZ — Dynamic Store Page Builder (Entity/Vendor Profile)
 *
 * Renders a fully dynamic, section-based store page similar to Shopify / Google Maps.
 * Sections are loaded from store_pages + store_sections tables as a GLOBAL template
 * per tenant (shared by all entities), with fallback defaults.
 *
 * Section types: header, contact, tabs, products, info, hours, location, offers, reviews, policies
 * Each section is rendered via a partial template in /partials/store_sections/{type}.php
 *
 * Features:
 *   - Cover image + logo + store name + rating + verified badge + open/closed status
 *   - Contact info, social links, share button
 *   - Tabbed navigation (products, info, hours, location, offers, reviews)
 *   - Product grid with categories, search, pagination, add-to-cart
 *   - Working hours with live open/closed logic
 *   - Location with OpenStreetMap + Google Maps links
 *   - Discounts/promotions with copy codes
 *   - Ratings/reviews with submission form
 *   - Multi-language support (LTR + RTL)
 *   - Multi-tenant isolation
 *   - Mobile-first responsive design
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

/* -------------------------------------------------------
 * Entity ID from URL
 * ----------------------------------------------------- */
$entityId = (int)($_GET['id'] ?? $_GET['entity_id'] ?? 0);
$slug     = $_GET['slug'] ?? '';

if (!$entityId && !$slug) {
    header('Location: /frontend/public/entities.php');
    exit;
}

$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';

/* -------------------------------------------------------
 * Fetch entity data — PDO-first, HTTP fallback
 * ----------------------------------------------------- */
$qs   = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId;
$entity = [];
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $eStmt = $pdo->prepare(
            "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                    e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                    e.status, e.tenant_id, e.created_at,
                    (SELECT i.url FROM images i JOIN image_types it ON it.id = i.image_type_id WHERE i.owner_id = e.id AND it.code = 'entity_logo' ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS logo_url,
                    (SELECT i.url FROM images i JOIN image_types it ON it.id = i.image_type_id WHERE i.owner_id = e.id AND it.code = 'entity_cover' ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS cover_url
               FROM entities e
              WHERE e.id = ? AND e.status NOT IN ('suspended','rejected') LIMIT 1"
        );
        $eStmt->execute([$entityId]);
        $entity = $eStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($entity) {
            // Translation override
            $trStmt = $pdo->prepare(
                "SELECT store_name, description FROM entity_translations
                  WHERE entity_id = ? AND language_code = ? LIMIT 1"
            );
            $trStmt->execute([$entityId, $lang]);
            $tr = $trStmt->fetch(PDO::FETCH_ASSOC);
            if ($tr) {
                if (!empty($tr['store_name'])) $entity['store_name'] = $tr['store_name'];
                if (!empty($tr['description'])) $entity['description'] = $tr['description'];
            }
            // Working hours
            $whStmt = $pdo->prepare(
                "SELECT day_of_week, open_time, close_time, is_open FROM entities_working_hours
                  WHERE entity_id = ? ORDER BY day_of_week ASC"
            );
            $whStmt->execute([$entityId]);
            $entity['working_hours'] = $whStmt->fetchAll(PDO::FETCH_ASSOC);
            // Addresses
            try {
                $adStmt = $pdo->prepare(
                    "SELECT id, address_line1, address_line2, latitude, longitude, is_primary
                       FROM addresses WHERE owner_type='entity' AND owner_id=? ORDER BY is_primary DESC, id ASC LIMIT 5"
                );
                $adStmt->execute([$entityId]);
                $entity['addresses'] = $adStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $_) { $entity['addresses'] = []; }
            // Payment methods
            try {
                $pmStmt = $pdo->prepare(
                    "SELECT pm.id, pm.method_name AS name, pm.method_key AS code, pm.icon_url AS icon
                       FROM entity_payment_methods epm
                  LEFT JOIN payment_methods pm ON pm.id = epm.payment_method_id
                      WHERE epm.entity_id = ? AND epm.is_active = 1"
                );
                $pmStmt->execute([$entityId]);
                $entity['payment_methods'] = $pmStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $_) { $entity['payment_methods'] = []; }
            // Attributes
            try {
                $atStmt = $pdo->prepare(
                    "SELECT COALESCE(eat.name, ea.slug) AS attribute_name, eav.value
                       FROM entities_attribute_values eav
                  LEFT JOIN entities_attributes ea ON ea.id = eav.attribute_id
                  LEFT JOIN entities_attribute_translations eat ON eat.attribute_id = ea.id AND eat.language_code = ?
                      WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != '' LIMIT 20"
                );
                $atStmt->execute([$lang, $entityId]);
                $entity['attributes'] = $atStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $_) { $entity['attributes'] = []; }
        }
    } catch (Throwable $e) {
        error_log('[entity.php] PDO entity load failed: ' . $e->getMessage());
        $entity = [];
    }
}
// HTTP fallback
if (empty($entity)) {
    $resp = pub_fetch(pub_api_url('public/entity/' . $entityId) . '?' . $qs);
    $entity = $resp['data']['data'] ?? $resp['data'] ?? [];
}

if (empty($entity)) {
    $GLOBALS['PUB_PAGE_TITLE'] = t('entity.not_found') . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<div class="pub-container" style="padding:60px 0;text-align:center;"><p>' . e(t('entity.not_found')) . '</p></div>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

/* -------------------------------------------------------
 * Fetch entity_settings and respect them
 * ----------------------------------------------------- */
$entitySettings = [];
if ($pdo) {
    try {
        $esStmt = $pdo->prepare(
            "SELECT * FROM entity_settings WHERE entity_id = ? LIMIT 1"
        );
        $esStmt->execute([$entity['id'] ?? $entityId]);
        $entitySettings = $esStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $_) { $entitySettings = []; }
}
// HTTP fallback: use settings embedded in the API response (when PDO is unavailable)
if (empty($entitySettings) && !empty($entity['settings']) && is_array($entity['settings'])) {
    $entitySettings = $entity['settings'];
}

// Respect is_visible: hide entity if not visible
if (isset($entitySettings['is_visible']) && (int)$entitySettings['is_visible'] === 0) {
    $GLOBALS['PUB_PAGE_TITLE'] = t('entity.not_found') . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<div class="pub-container" style="padding:60px 0;text-align:center;"><p>' . e(t('entity.not_available', 'This entity is currently unavailable.')) . '</p></div>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

// Respect maintenance_mode: show maintenance page
$entityInMaintenance = !empty($entitySettings['maintenance_mode']);

// Respect show_reviews setting
$entityShowReviews = !isset($entitySettings['show_reviews']) || (int)$entitySettings['show_reviews'] !== 0;

// Respect show_contact_info setting
$entityShowContactInfo = !isset($entitySettings['show_contact_info']) || (int)$entitySettings['show_contact_info'] !== 0;

/* -------------------------------------------------------
 * Fetch entity products
 * ----------------------------------------------------- */
$productPage  = max(1, (int)($_GET['page'] ?? 1));
$productLimit = 12;
$selectedCat  = (int)($_GET['cat'] ?? 0);  // category filter
$productSearch = trim($_GET['q'] ?? '');    // product search within entity
$products     = [];
$productMeta  = ['total' => 0, 'total_pages' => 1];
$entityTenantId = (int)($entity['tenant_id'] ?? $tenantId);

// Check if entity has category assignments — when rows exist, only show those categories' products
$entityHasCatAssignments = false;
if ($pdo) {
    try {
        $ecStmt = $pdo->prepare('SELECT COUNT(*) FROM entity_categories WHERE entity_id = ? AND is_active = 1');
        $ecStmt->execute([$entityId]);
        $entityHasCatAssignments = (int)$ecStmt->fetchColumn() > 0;
    } catch (Throwable $_) {}
}

if ($pdo) {
    try {
        $pWhere  = 'WHERE p.is_active = 1 AND p.tenant_id = ?';
        $pParams = [$entityTenantId];
        if ($entityHasCatAssignments) {
            $pWhere .= ' AND EXISTS (
                SELECT 1 FROM product_categories pc_ec
                JOIN entity_categories ec ON ec.category_id = pc_ec.category_id
                WHERE pc_ec.product_id = p.id AND ec.entity_id = ? AND ec.is_active = 1)';
            $pParams[] = $entityId;
        }
        if ($selectedCat) {
            // Include products in the selected category AND its direct child categories
            $pWhere .= ' AND EXISTS (
                SELECT 1 FROM product_categories pc2
                 JOIN categories cc ON cc.id = pc2.category_id
                WHERE pc2.product_id = p.id
                  AND (cc.id = ? OR cc.parent_id = ?))';
            $pParams[] = $selectedCat;
            $pParams[] = $selectedCat;
        }
        if ($productSearch !== '') {
            $like = '%' . addcslashes($productSearch, '%_\\') . '%';
            $pWhere .= ' AND (pt.name LIKE ? OR p.sku LIKE ?)';
            $pParams[] = $like; $pParams[] = $like;
        }
        $pOffset = ($productPage - 1) * $productLimit;
        $pCntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $pWhere");
        $pCntStmt->execute($pParams);
        $pTotal = (int)$pCntStmt->fetchColumn();
        $pStmt = $pdo->prepare(
            "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug,
                    p.is_featured, p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                    COALESCE(
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id = ? AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id IS NULL AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1)
                    ) AS price,
                    COALESCE(
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id = ? AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.entity_id IS NULL AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1),
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id AND pp.is_active = 1 ORDER BY pp.id ASC LIMIT 1)
                    ) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    (SELECT GROUP_CONCAT(i.url ORDER BY i.id ASC SEPARATOR '|') FROM images i WHERE i.owner_id = p.id) AS image_urls
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              $pWhere ORDER BY p.is_featured DESC, p.id DESC
              LIMIT $productLimit OFFSET $pOffset"
        );
        $pStmt->execute(array_merge([$entityId, $entityId, $lang], $pParams));
        $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        $productMeta = ['total' => $pTotal, 'total_pages' => max(1, (int)ceil($pTotal / $productLimit))];
    } catch (Throwable $_) {}
}
if (empty($products) && !$selectedCat) {
    $rp = pub_fetch(
        pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/products')
        . '?' . $qs . '&per=' . $productLimit . '&page=' . $productPage
    );
    $products    = $rp['data']['data'] ?? ($rp['data']['items'] ?? []);
    $productMeta = $rp['data']['meta'] ?? ['total' => count($products), 'total_pages' => 1];
}

/* Fetch categories for this entity — with parent_id for hierarchical menus */
$categories     = [];   // flat list of categories linked to this entity's products
$categoryTree   = [];   // parent categories → children
if ($pdo) {
    try {
        // Fetch every category (with parent_id) that has active products for this entity
        $entityCategoryJoin = '';
        $catParams          = [$lang, $entityTenantId];
        if ($entityHasCatAssignments) {
            $entityCategoryJoin = 'JOIN entity_categories ec ON ec.category_id = c.id AND ec.entity_id = ? AND ec.is_active = 1';
            $catParams[] = $entityId;
        }
        $catStmt = $pdo->prepare(
            "SELECT DISTINCT c.id, c.parent_id, COALESCE(ct.name, c.name) AS name, c.slug,
                    c.sort_order
               FROM product_categories pc
               JOIN categories c ON c.id = pc.category_id AND c.is_active = 1
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
               JOIN products p ON p.id = pc.product_id AND p.tenant_id = ? AND p.is_active = 1
               $entityCategoryJoin
              ORDER BY c.sort_order ASC, c.id ASC LIMIT 100"
        );
        $catStmt->execute($catParams);
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build a map for quick lookup
        $catById = [];
        foreach ($categories as &$cat) {
            $cat['children'] = [];
            $catById[$cat['id']] = &$cat;
        }
        unset($cat);

        // Organise into parent → children tree
        foreach ($categories as &$cat) {
            $pid = (int)($cat['parent_id'] ?? 0);
            if ($pid && isset($catById[$pid])) {
                $catById[$pid]['children'][] = &$cat;
            }
        }
        unset($cat);

        // Top-level (root) categories only
        foreach ($categories as $cat) {
            if (empty($cat['parent_id'])) {
                $categoryTree[] = $cat;
            }
        }
        // If all categories are leaf nodes (no parent_id column or no hierarchy), fall back to flat list
        if (empty($categoryTree)) {
            $categoryTree = $categories;
        }
    } catch (Throwable $_) {}
}

/* Fetch discounts for this entity */
$discounts = [];
if ($pdo) {
    try {
        $dStmt = $pdo->prepare(
            "SELECT d.id, d.code, d.type, d.status, d.ends_at, d.currency_code,
                    COALESCE(dt.name, d.code) AS title, dt.description, dt.marketing_badge, dt.terms_conditions
               FROM discounts d
          LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
             WHERE d.entity_id = ? AND d.status = 'active'
               AND (d.starts_at IS NULL OR d.starts_at <= NOW())
               AND (d.ends_at IS NULL OR d.ends_at >= NOW())
             ORDER BY d.priority DESC, d.id DESC LIMIT 30"
        );
        $dStmt->execute([$lang, $entityId]);
        $discounts = $dStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {}
}

$GLOBALS['PUB_PAGE_TITLE'] = e($entity['store_name'] ?? '') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = e($entity['description'] ?? '');
$GLOBALS['PUB_PAGE_TYPE']  = 'entities';

// Entity ratings — last 5 + average (reuse $pdo already set above)
$entityRatings    = [];
$entityRatingAvg  = null;
$entityRatingTotal = 0;
if ($pdo) {
    try {
        $rStmt = $pdo->prepare(
            "SELECT er.id, er.rating, er.review, er.created_at,
                    COALESCE(u.username, 'Anonymous') AS reviewer_name
               FROM entity_ratings er
          LEFT JOIN users u ON u.id = er.user_id
              WHERE er.entity_id = ? AND er.is_active = 1
              ORDER BY er.created_at DESC LIMIT 5"
        );
        $rStmt->execute([$entity['id'] ?? $entityId]);
        $entityRatings = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        $rAvgStmt = $pdo->prepare('SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM entity_ratings WHERE entity_id = ? AND is_active = 1');
        $rAvgStmt->execute([$entity['id'] ?? $entityId]);
        $rAvg = $rAvgStmt->fetch(PDO::FETCH_ASSOC);
        $entityRatingAvg   = $rAvg['avg_rating'] ?? null;
        $entityRatingTotal = (int)($rAvg['total'] ?? 0);
    } catch (Throwable $_) {}
}

// SEO meta — load from seo_meta table, fallback to entity_translations fields
$seoMeta = function_exists('pub_get_seo_meta') ? pub_get_seo_meta('entity', $entity['id'] ?? $entityId, $lang) : [];
$GLOBALS['PUB_SEO'] = [
    'title'       => $seoMeta['meta_title']       ?? ($entity['meta_title']       ?? $entity['store_name'] ?? ''),
    'description' => $seoMeta['meta_description'] ?? ($entity['meta_description'] ?? $entity['description'] ?? ''),
    'keywords'    => $seoMeta['meta_keywords']     ?? ($entity['meta_keywords']    ?? ''),
    'og_image'    => $seoMeta['og_image']          ?? ($entity['logo_url']         ?? $entity['cover_url'] ?? ''),
    'canonical'   => $seoMeta['canonical_url']     ?? '',
    'robots'      => $seoMeta['robots']            ?? 'index,follow',
    'schema_markup'=> $seoMeta['schema_markup']    ?? '',
    'schema_type' => 'LocalBusiness',
    'schema_name' => $entity['store_name']         ?? '',
    'schema_phone'=> $entity['phone']              ?? '',
    'schema_email'=> $entity['email']              ?? '',
    'schema_url'  => $entity['website_url']        ?? '',
];

/* -------------------------------------------------------
 * Open / Closed status — calculate from working_hours + current local time
 * working_hours is already in $entity from the API call
 * day_of_week: 0=Sun, 1=Mon … 6=Sat  (PHP: 0=Sun via date('w'))
 * ----------------------------------------------------- */
$entityIsOpen    = null;   // null = unknown (no hours data)
$entityOpenLabel = '';
$workingHoursArr = $entity['working_hours'] ?? [];
if (!empty($workingHoursArr)) {
    $nowDow   = (int)date('w');                  // 0(Sun)…6(Sat)
    $nowMins  = (int)date('H') * 60 + (int)date('i');
    foreach ($workingHoursArr as $h) {
        if ((int)($h['day_of_week'] ?? -1) !== $nowDow) continue;
        if (empty($h['is_open'])) {
            $entityIsOpen    = false;
            $entityOpenLabel = t('entity.closed');
            break;
        }
        $openMin  = 0;
        $closeMin = 24 * 60;
        if (!empty($h['open_time'])) {
            [$oh, $om] = array_map('intval', explode(':', $h['open_time']));
            $openMin = $oh * 60 + $om;
        }
        if (!empty($h['close_time'])) {
            [$ch, $cm] = array_map('intval', explode(':', $h['close_time']));
            $closeMin = $ch * 60 + $cm;
        }
        if ($nowMins >= $openMin && ($closeMin === 0 || $nowMins < $closeMin)) {
            $entityIsOpen    = true;
            $entityOpenLabel = t('entity.open_now');
        } else {
            $entityIsOpen    = false;
            $entityOpenLabel = t('entity.closed');
        }
        break;
    }
}

/* Day names */
$dayNames = [
    0 => t('entity.day_sunday'),
    1 => t('entity.day_monday'),
    2 => t('entity.day_tuesday'),
    3 => t('entity.day_wednesday'),
    4 => t('entity.day_thursday'),
    5 => t('entity.day_friday'),
    6 => t('entity.day_saturday'),
];

include dirname(__DIR__) . '/partials/header.php';

// Resolve card styles from DB card_styles for cards rendered on this page
$_entityProductCardStyle = pub_card_inline_style('product');
$_entityProductCardClass = pub_card_css_class('product');
$_entityProductImgStyle  = pub_card_img_style('product');
$_entityDiscountCardStyle = pub_card_inline_style('discount');
$_entityDiscountCardClass = pub_card_css_class('discount');

/* -------------------------------------------------------
 * Dynamic Section System — Load global template from store_pages / store_sections
 * Sections are a fixed template per tenant (shared by ALL entities).
 * Falls back to default section order when no DB config exists.
 * ----------------------------------------------------- */
$storeSections = [];
if ($pdo) {
    try {
        $spStmt = $pdo->prepare(
            "SELECT ss.id, ss.type, ss.position, ss.settings,
                    sst.title AS translated_title, sst.content AS translated_content
               FROM store_sections ss
               JOIN store_pages sp ON sp.id = ss.page_id
          LEFT JOIN store_section_translations sst ON sst.section_id = ss.id AND sst.language_code = ?
              WHERE sp.tenant_id = ? AND sp.type = 'store' AND sp.is_active = 1 AND ss.is_active = 1
              ORDER BY ss.position ASC"
        );
        $spStmt->execute([$lang, $entityTenantId]);
        $storeSections = $spStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {
        // Table may not exist yet — fall back to defaults
        $storeSections = [];
    }
}

// Default section titles per language (used when no DB config or no translation)
$defaultSectionTitles = [
    'header'   => '', // header has no title
    'contact'  => '', // contact has no title
    'products' => t('entity.products_tab'),
    'info'     => t('entity.info_tab'),
    'hours'    => t('entity.hours_tab'),
    'location' => t('entity.location_tab'),
    'offers'   => t('entity.discounts_tab'),
    'reviews'  => t('entity.ratings_tab'),
    'policies'   => t('entity.policies_tab', 'Policies'),
    'attributes' => t('entity.attributes_tab', 'Merchant Attributes'),
];

// Section icons for visual distinction
$sectionIcons = [
    'products' => '🛍️',
    'info'     => 'ℹ️',
    'hours'    => '🕐',
    'location' => '🗺️',
    'offers'   => '🏷️',
    'reviews'  => '⭐',
    'policies'   => '📋',
    'attributes' => '📝',
];

// Default section order when no DB config exists
if (empty($storeSections)) {
    $storeSections = [
        ['type' => 'header',   'position' => 10, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'contact',  'position' => 20, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'products', 'position' => 40, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'info',     'position' => 50, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'hours',    'position' => 60, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'location', 'position' => 70, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'offers',   'position' => 80, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'reviews',  'position' => 90, 'settings' => null, 'translated_title' => null, 'translated_content' => null],
        ['type' => 'policies',   'position' => 95,  'settings' => '{"types":["refund","privacy","shipping","terms"]}', 'translated_title' => null, 'translated_content' => null],
        ['type' => 'attributes', 'position' => 55,  'settings' => null, 'translated_title' => null, 'translated_content' => null],
    ];
}

// Build list of active section types for tabs section
$activeSections = array_column($storeSections, 'type');

// Section template directory
$sectionDir = dirname(__DIR__) . '/partials/store_sections';
?>

<!-- Entity Page -->
<?php if ($entityInMaintenance): ?>
<div class="pub-container" style="padding:40px 0;text-align:center;">
    <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:40px 20px;">
        <div style="font-size:3rem;margin-bottom:16px;">🔧</div>
        <h2 style="margin:0 0 10px;color:var(--pub-text);"><?= e(t('entity.maintenance_title', 'Under Maintenance')) ?></h2>
        <p style="color:var(--pub-muted);margin:0;"><?= e(t('entity.maintenance_msg', 'This entity is currently undergoing maintenance. Please check back later.')) ?></p>
    </div>
</div>
<?php else: ?>

<?php
/* -------------------------------------------------------
 * Dynamic Section Renderer
 * Loops through active sections and includes partial templates.
 * Each content section is rendered as a visible block with a
 * translated section title (from store_section_translations
 * or default language file).
 *
 * Deduplication: each section type is rendered at most once.
 * Styling: background_color, text_color, padding, custom_css
 *          from section settings JSON are applied inline.
 * ----------------------------------------------------- */

// Safe-style helpers (shared with index.php — guarded by function_exists)
if (!function_exists('_pub_safe_color')) {
    function _pub_safe_color(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?$/', $v))         return $v;
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v))    return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v))                                 return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]{1,80}\)$/', $v))                  return $v;
        return '';
    }
}
if (!function_exists('_pub_safe_padding')) {
    function _pub_safe_padding(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $unit   = '(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?)';
        $single = $unit . '(?:\s+' . $unit . '){0,3}';
        return preg_match('/^' . $single . '$/', $v) ? $v : '';
    }
}
if (!function_exists('_pub_safe_css')) {
    function _pub_safe_css(string $css): string {
        $css = str_replace(['<', '>'], '', $css);
        $css = preg_replace('/\bexpression\s*\(/i', '', $css);
        $css = preg_replace('/\bbehaviour\s*:/i',   '', $css);
        $css = preg_replace('/@import\b/i',         '', $css);
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:data|javascript):/i', 'url(about:', $css);
        // Strip any residual </style> or <script> sequences
        $css = preg_replace('#<\s*/?\s*(?:style|script)\b[^>]*>#i', '', $css);
        return $css;
    }
}

// Sections that need a section-title header & container wrapping
$titledSections = ['products', 'info', 'hours', 'location', 'offers', 'reviews', 'policies', 'attributes'];

// Track rendered section types to prevent duplicates
$renderedSectionTypes = [];

foreach ($storeSections as $section):
    $sectionType     = $section['type'];

    // Skip tabs section — sections are now shown directly on the page
    if ($sectionType === 'tabs') continue;

    // ── Deduplication: never render the same section type twice ──
    if (isset($renderedSectionTypes[$sectionType])) continue;
    $renderedSectionTypes[$sectionType] = true;

    $sectionSettings = is_string($section['settings'] ?? null) ? (json_decode($section['settings'], true) ?: []) : ($section['settings'] ?? []);
    $sectionFile     = $sectionDir . '/' . basename($sectionType) . '.php';

    // Use partial template if available, otherwise skip unknown types
    if (file_exists($sectionFile)):
        // Resolve section title: DB translation → default translation → type name
        $sectionTitle = trim($section['translated_title'] ?? '');
        if ($sectionTitle === '') {
            $sectionTitle = $defaultSectionTitles[$sectionType] ?? '';
        }
        $sectionIcon = $sectionIcons[$sectionType] ?? '';

        // ── Build section inline style from DB settings ──
        $secBg      = _pub_safe_color((string)($sectionSettings['background_color'] ?? ''));
        $secText    = _pub_safe_color((string)($sectionSettings['text_color'] ?? ''));
        $secPadding = _pub_safe_padding((string)($sectionSettings['padding'] ?? ''));
        $secCss     = _pub_safe_css((string)($sectionSettings['custom_css'] ?? ''));

        $sStyle = '';
        if ($secBg)      $sStyle .= 'background-color:' . e($secBg) . ';';
        if ($secText)    $sStyle .= 'color:'             . e($secText) . ';';
        if ($secPadding) $sStyle .= 'padding:'           . e($secPadding) . ';';

        // Content sections are wrapped in a container with a section header
        if (in_array($sectionType, $titledSections)):
?>
<section class="pub-entity-section pub-entity-section--<?= e($sectionType) ?>"<?= $sStyle ? ' style="' . $sStyle . '"' : '' ?>>
<?php if ($secCss !== ''): ?>
    <style data-section="<?= e($sectionType) ?>"><?= $secCss ?></style>
<?php endif; ?>
    <div class="pub-container">
        <?php if ($sectionTitle !== ''): ?>
        <div class="pub-section-head pub-entity-section-head">
            <h2 class="pub-section-title">
                <?php if ($sectionIcon !== ''): ?><span class="pub-entity-section-icon"><?= $sectionIcon ?></span><?php endif; ?>
                <?= e($sectionTitle) ?>
            </h2>
        </div>
        <?php endif; ?>
        <?php include $sectionFile; ?>
    </div>
</section>
<?php
        else:
            // header and contact manage their own wrapping
            include $sectionFile;
        endif;
    endif;
endforeach;
?>

<?php /* (Legacy inline sections removed — now rendered via partials/store_sections/) */ ?>

<script>
// Share panel toggle or native share execution
function pubShareEntity() {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href
        }).catch(console.error);
        return;
    }
    var panel = document.getElementById('pubSharePanel');
    if (!panel) return;
    var isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        var url = encodeURIComponent(window.location.href);
        var title = encodeURIComponent(document.title);
        var wa = document.getElementById('pubShareWA');
        var tw = document.getElementById('pubShareTW');
        var fb = document.getElementById('pubShareFB');
        var tg = document.getElementById('pubShareTG');
        if (wa) wa.href = 'https://api.whatsapp.com/send?text=' + title + '%20' + url;
        if (tw) tw.href = 'https://twitter.com/intent/tweet?text=' + title + '&url=' + url;
        if (fb) fb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
        if (tg) tg.href = 'https://t.me/share/url?url=' + url + '&text=' + title;
    }
}

function pubCopyLink() {
    var url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { alert('✅'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('✅');
    }
}

function pubCopyDiscount(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(function() {
            var orig = btn.textContent;
            btn.textContent = '✅';
            setTimeout(function() { btn.textContent = orig; }, 1800);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = code;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        var orig = btn.textContent;
        btn.textContent = '✅';
        setTimeout(function() { btn.textContent = orig; }, 1800);
    }
}
function pubPickEntityStar(val) {
    document.getElementById('pubEntityRating').value = val;
    document.querySelectorAll('.pub-star-pick').forEach(function(s) {
        s.style.color = parseInt(s.dataset.val) <= val ? '#F59E0B' : 'var(--pub-border)';
    });
}

function pubSubmitEntityRating(e) {
    e.preventDefault();
    var rating = parseInt(document.getElementById('pubEntityRating').value);
    if (!rating || rating < 1) { alert('<?= addslashes(t('entity.pick_rating')) ?>'); return; }
    var review = (document.getElementById('pubEntityReview') || {}).value || '';
    var msg = document.getElementById('pubEntityRateMsg');
    fetch('<?= e(pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/ratings')) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'rating=' + rating + '&review=' + encodeURIComponent(review)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (msg) {
            msg.style.display = 'block';
            msg.style.color = d.success ? 'var(--pub-primary)' : '#dc2626';
            msg.textContent = d.success ? '✅ <?= addslashes(t('entity.rating_submitted')) ?>' : (d.message || '<?= addslashes(t('common.error')) ?>');
        }
        if (d.success) {
            // Reset form
            document.getElementById('pubEntityRating').value = '0';
            document.querySelectorAll('.pub-star-pick').forEach(function(s) { s.style.color = 'var(--pub-border)'; });
            if (document.getElementById('pubEntityReview')) document.getElementById('pubEntityReview').value = '';
        }
    }).catch(function() {
        if (msg) { msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='<?= addslashes(t('common.error')) ?>'; }
    });
}
</script>

<?php
// CSS additions for entity page
echo '<style>
.pub-entity-banner { width:100%; height:220px; overflow:hidden; background:var(--pub-surface); position:relative; }
@media(min-width:900px){ .pub-entity-banner { height:320px; } }
.pub-entity-banner-img { width:100%; height:100%; object-fit:cover; display:block; }
.pub-entity-banner-placeholder { width:100%; height:100%; background: linear-gradient(135deg, var(--pub-primary) 0%, var(--pub-accent) 100%); }
.pub-entity-profile-header { display:flex; gap:20px; align-items:flex-start; margin-top:-48px; position:relative; z-index:2; flex-wrap:wrap; }
.pub-entity-profile-logo { width:96px; height:96px; border-radius:16px; overflow:hidden; background:var(--pub-bg); border:3px solid var(--pub-border); flex-shrink:0; box-shadow:var(--pub-shadow); }
.pub-entity-profile-logo img { width:100%; height:100%; object-fit:cover; }
.pub-entity-profile-info { flex:1; min-width:0; padding-top:52px; }
.pub-entity-profile-name { font-size:1.4rem; font-weight:800; margin:0 0 6px; color:var(--pub-text); }
.pub-entity-profile-desc { font-size:0.92rem; color:var(--pub-muted); margin:0 0 12px; }
.pub-entity-contacts { display:flex; gap:8px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; margin-bottom:10px; padding-bottom:4px; }
.pub-entity-contacts::-webkit-scrollbar { display:none; }
.pub-contact-item { font-size:0.82rem; color:var(--pub-primary); display:inline-flex; align-items:center; gap:4px; white-space:nowrap; padding:4px 10px; background:var(--pub-surface); border:1px solid var(--pub-border); border-radius:20px; flex-shrink:0; }
.pub-contact-item:hover { text-decoration:underline; }
.pub-entity-social { display:flex; gap:6px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding-bottom:4px; }
.pub-entity-social::-webkit-scrollbar { display:none; }
.pub-social-btn { padding:5px 12px; border-radius:20px; font-size:0.8rem; font-weight:600; border:1px solid var(--pub-border); background:var(--pub-surface); color:var(--pub-text); transition:opacity 0.2s; white-space:nowrap; flex-shrink:0; cursor:pointer; }
.pub-entity-section-content { padding-bottom:24px; }
/* Entity section headers */
.pub-entity-section { padding:12px 0; border-bottom:1px solid var(--pub-border); }
.pub-entity-section:last-child { border-bottom:none; }
.pub-entity-section-head { margin-bottom:12px; padding-top:8px; }
.pub-entity-section-head .pub-section-title { display:flex; align-items:center; gap:8px; }
.pub-entity-section-icon { font-size:1.2rem; }
.pub-info-card { background:var(--pub-bg); border:1px solid var(--pub-border); border-radius:var(--pub-radius); overflow:hidden; }
.pub-info-card-title { font-size:1rem; font-weight:700; margin:0; padding:12px 16px; border-bottom:1px solid var(--pub-border); color:var(--pub-text); }
.pub-attr-grid { padding:12px 16px; display:grid; gap:8px; }
.pub-attr-row { display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; }
.pub-attr-key { font-size:0.82rem; font-weight:600; color:var(--pub-muted); min-width:120px; }
.pub-attr-val { font-size:0.88rem; color:var(--pub-text); }
.pub-hours-table { padding:8px 16px 16px; display:grid; gap:6px; }
.pub-hours-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--pub-border); }
.pub-hours-row:last-child { border-bottom:none; }
.pub-hours-row--closed { opacity:0.5; }
.pub-hours-day { font-weight:600; font-size:0.88rem; color:var(--pub-text); }
.pub-hours-time { font-size:0.88rem; color:var(--pub-muted); }
/* Category filter tabs */
.pub-cat-tabs { border-bottom:1px solid var(--pub-border); margin-bottom:4px; }
.pub-cat-tab-btn { padding:7px 16px; border-radius:var(--pub-radius) var(--pub-radius) 0 0; font-size:0.85rem; font-weight:600;
  color:var(--pub-muted); text-decoration:none; white-space:nowrap; border:1px solid transparent;
  border-bottom:none; transition:background 0.15s,color 0.15s; display:inline-block; }
.pub-cat-tab-btn:hover { background:var(--pub-surface); color:var(--pub-text); }
.pub-cat-tab-btn.active { background:var(--pub-bg); color:var(--pub-primary); border-color:var(--pub-border);
  border-bottom-color:var(--pub-bg); margin-bottom:-1px; }
/* Sub-category tabs */
.pub-cat-tabs--sub { border-bottom:1px dashed var(--pub-border); margin-top:2px; background:var(--pub-surface,#f8f9fa); border-radius:0 0 4px 4px; }
.pub-cat-tab-btn--sub { font-size:0.78rem; font-weight:500; padding:5px 12px;
  border-radius:var(--pub-radius) var(--pub-radius) 0 0; }
/* Cart add button on product card */
.pub-cart-add-btn { width:100%; margin-top:6px; padding:7px 0; background:var(--btn-primary-bg, var(--pub-primary)); color:var(--btn-primary-color, #fff);
  border:none; border-radius:0 0 var(--pub-radius) var(--pub-radius); font-size:0.82rem; font-weight:600;
  cursor:pointer; transition:opacity 0.2s; }
.pub-cart-add-btn:hover { opacity:0.85; }
/* Open/closed badge */
.pub-open-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px;
  font-size:0.78rem; font-weight:700; }
.pub-open-badge--open  { background:color-mix(in srgb, var(--pub-success, #22c55e) 15%, transparent); color:var(--pub-success, #065f46); }
.pub-open-badge--closed{ background:color-mix(in srgb, var(--pub-danger, #ef4444) 15%, transparent); color:var(--pub-danger, #991b1b); }
/* Tab count badge */
.pub-tab-count { background:var(--pub-primary); color:#fff; border-radius:20px; padding:1px 6px;
  font-size:0.72rem; font-weight:700; margin-inline-start:4px; }
/* Discount cards */
.pub-discount-card { background:var(--pub-surface); border:1px solid var(--pub-border); border-radius:var(--pub-radius);
  overflow:hidden; position:relative; }
.pub-discount-badge-top { position:absolute; top:0; right:0; background:var(--pub-accent,#F59E0B); color:#000;
  font-size:0.72rem; font-weight:800; padding:3px 10px;
  border-bottom-left-radius:var(--pub-radius); }
.pub-discount-inner { display:flex; gap:14px; padding:14px 16px; align-items:flex-start; }
.pub-discount-icon { font-size:2rem; flex-shrink:0; }
.pub-discount-body { flex:1; min-width:0; }
.pub-discount-title { font-size:1rem; font-weight:700; margin:0 0 4px; color:var(--pub-text); }
.pub-discount-desc  { font-size:0.85rem; color:var(--pub-muted); margin:0 0 8px; }
.pub-discount-code-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
.pub-discount-code { font-family:monospace; font-size:0.9rem; font-weight:800; letter-spacing:2px;
  background:var(--pub-bg); border:1px dashed var(--pub-border); padding:4px 12px;
  border-radius:6px; color:var(--pub-primary); }
.pub-discount-expires { font-size:0.78rem; color:var(--pub-muted); margin:0; }
.pub-discount-terms { padding:8px 16px 12px; border-top:1px solid var(--pub-border); }
.pub-discount-terms summary { font-size:0.82rem; color:var(--pub-muted); cursor:pointer; }
.pub-discount-terms p { font-size:0.82rem; color:var(--pub-muted); margin:6px 0 0; }
/* Entity rating average badge */
.pub-entity-rating-avg { display:inline-flex; align-items:center; gap:4px; padding:3px 10px;
  border-radius:20px; font-size:0.82rem; font-weight:700;
  background:color-mix(in srgb, var(--pub-warning, #f59e0b) 20%, transparent); color:var(--pub-warning, #92400e); }
/* Mobile entity profile */
@media(max-width:600px){
  .pub-entity-profile-header { flex-direction:column; align-items:center; text-align:center; gap:10px; margin-top:-36px; }
  .pub-entity-profile-info { padding-top:0; width:100%; }
  .pub-entity-profile-info > div { align-items: center; justify-content: center; }
  .pub-entity-profile-logo { width:72px; height:72px; margin:0 auto; }
  .pub-entity-profile-name { font-size:1.15rem; }
  .pub-entity-contacts { flex-direction:column; align-items:center; margin-bottom:16px; gap:8px !important; }
  .pub-contact-item { width:auto; justify-content:flex-start; padding:6px 16px; font-size:0.9rem; border-radius:24px; }
  .pub-entity-social { justify-content:center; flex-wrap:wrap; margin-top:12px; }
  .pub-attr-key { min-width:80px; }
  .pub-cat-tab-btn { padding:5px 10px; font-size:0.78rem; }
}
.pub-slide-img--hidden { display: none !important; }
.pub-slide-dots {
    position: absolute; bottom: 6px; left: 0; right: 0;
    display: flex; justify-content: center; gap: 4px; pointer-events: none;
}
.pub-slide-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(255,255,255,0.5); transition: background 0.3s;
}
.pub-slide-dot--active { background: rgba(255,255,255,0.95); }
</style>';
?>
<script>
(function () {
    var INTERVAL = 2800;
    document.querySelectorAll('[data-img-slide="1"]').forEach(function (card) {
        var imgs = card.querySelectorAll('.pub-slide-img');
        var dots = card.querySelectorAll('.pub-slide-dot');
        if (imgs.length < 2) return;
        var cur = 0;
        var timer = null;
        function showSlide(n) {
            imgs[cur].classList.add('pub-slide-img--hidden');
            if (dots[cur]) dots[cur].classList.remove('pub-slide-dot--active');
            cur = (n + imgs.length) % imgs.length;
            imgs[cur].classList.remove('pub-slide-img--hidden');
            if (dots[cur]) dots[cur].classList.add('pub-slide-dot--active');
        }
        function startAuto() {
            if (timer) clearInterval(timer);
            timer = setInterval(function () { showSlide(cur + 1); }, INTERVAL);
        }
        function stopAuto() {
            if (timer) { clearInterval(timer); timer = null; }
        }
        startAuto();
        card.addEventListener('mouseenter', stopAuto);
        card.addEventListener('mouseleave', startAuto);
    });
}());
</script>

<?php endif; // end maintenance check ?>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
<?php if (!empty($entity['id'])): ?>
<script>
// Track entity page view in core_events
(function () {
    // Expose entity ID for search tracking (header.php passes entity_id to search_suggest)
    window.__qzEntityId = <?= (int)$entity['id'] ?>;
    if (typeof window.pubTrackEvent !== 'function') {
        window.pubTrackEvent = function (entityType, entityId, eventType, value, onFail) {
            var params = 'entity_type=' + encodeURIComponent(entityType)
                + '&entity_id=' + encodeURIComponent(entityId)
                + '&event_type=' + encodeURIComponent(eventType);
            if (value !== undefined && value !== null) {
                params += '&value=' + encodeURIComponent(value);
            }
            fetch('/api/public/events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params,
                keepalive: true,
                credentials: 'include'
            }).then(function (resp) {
                return resp.json();
            }).then(function (json) {
                var ok = json && json.data && json.data.ok;
                if (!ok && typeof onFail === 'function') onFail();
            }).catch(function () {
                if (typeof onFail === 'function') onFail();
            });
        };
    }
    window.pubTrackEvent('entity', <?= (int)$entity['id'] ?>, 'view');
}());
</script>
<?php endif; ?>