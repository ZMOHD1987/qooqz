<?php
/**
 * frontend/public/entity.php
 * QOOQZ — Public Entity/Vendor Profile Page
 *
 * Shows: banner (entity_cover), logo (entity_logo), store name, description,
 *        social links, working hours, addresses (with coordinates), products,
 *        payment methods, attributes, type badge
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
                    (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                    (SELECT i2.url FROM images i2 WHERE i2.owner_id = e.id ORDER BY i2.id ASC LIMIT 1 OFFSET 1) AS cover_url
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

if ($pdo) {
    try {
        $pWhere  = 'WHERE p.is_active = 1 AND p.tenant_id = ?';
        $pParams = [$entityTenantId];
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
                    (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    (SELECT GROUP_CONCAT(i.url ORDER BY i.id ASC SEPARATOR '|') FROM images i WHERE i.owner_id = p.id) AS image_urls
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              $pWhere ORDER BY p.is_featured DESC, p.id DESC
              LIMIT $productLimit OFFSET $pOffset"
        );
        $pStmt->execute(array_merge([$lang], $pParams));
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
        // Fetch every category (with parent_id) that has active products for this tenant
        $catStmt = $pdo->prepare(
            "SELECT DISTINCT c.id, c.parent_id, COALESCE(ct.name, c.name) AS name, c.slug,
                    c.sort_order
               FROM product_categories pc
               JOIN categories c ON c.id = pc.category_id AND c.is_active = 1
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
               JOIN products p ON p.id = pc.product_id AND p.tenant_id = ? AND p.is_active = 1
              ORDER BY c.sort_order ASC, c.id ASC LIMIT 100"
        );
        $catStmt->execute([$lang, $entityTenantId]);
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
             WHERE d.entity_id = ? AND d.status NOT IN ('cancelled','deleted')
             ORDER BY d.status ASC, d.priority DESC, d.id DESC LIMIT 30"
        );
        $dStmt->execute([$lang, $entityId]);
        $discounts = $dStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {}
}

$GLOBALS['PUB_PAGE_TITLE'] = e($entity['store_name'] ?? '') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = e($entity['description'] ?? '');

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
?>

<!-- Entity Banner -->
<?php if ($entityInMaintenance): ?>
<div class="pub-container" style="padding:40px 0;text-align:center;">
    <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:40px 20px;">
        <div style="font-size:3rem;margin-bottom:16px;">🔧</div>
        <h2 style="margin:0 0 10px;color:var(--pub-text);"><?= e(t('entity.maintenance_title', 'Under Maintenance')) ?></h2>
        <p style="color:var(--pub-muted);margin:0;"><?= e(t('entity.maintenance_msg', 'This entity is currently undergoing maintenance. Please check back later.')) ?></p>
    </div>
</div>
<?php else: ?>
<div class="pub-entity-banner">
    <?php if (!empty($entity['cover_url'])): ?>
        <img src="<?= e(pub_img($entity['cover_url'], 'entity_cover')) ?>"
             alt="<?= e($entity['store_name']) ?>"
             class="pub-entity-banner-img"
             loading="eager"
             onerror="this.style.display='none'">
    <?php else: ?>
        <div class="pub-entity-banner-placeholder"></div>
    <?php endif; ?>
</div>

<!-- Entity Header Card -->
<div class="pub-container">
    <div class="pub-entity-profile-header">

        <!-- Logo -->
        <div class="pub-entity-profile-logo">
            <?php if (!empty($entity['logo_url'])): ?>
                <img src="<?= e(pub_img($entity['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($entity['store_name']) ?>"
                     loading="eager"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;align-items:center;justify-content:center;font-size:2rem;">🏢</span>
            <?php else: ?>
                <span style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🏢</span>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pub-entity-profile-info">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h1 class="pub-entity-profile-name"><?= e($entity['store_name'] ?? '') ?></h1>
                    <?php if ($entityRatingAvg !== null): ?>
                        <span class="pub-entity-rating-avg" title="<?= (int)$entityRatingTotal ?> reviews">
                            ⭐ <?= number_format((float)$entityRatingAvg, 1) ?>
                            <span style="font-size:0.78rem;opacity:0.7;">(<?= $entityRatingTotal ?>)</span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($entity['is_verified'])): ?>
                    <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                <?php endif; ?>
                <?php if ($entityIsOpen !== null): ?>
                    <span class="pub-open-badge <?= $entityIsOpen ? 'pub-open-badge--open' : 'pub-open-badge--closed' ?>">
                        <?= $entityIsOpen ? '🟢' : '🔴' ?> <?= e($entityOpenLabel) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($entity['type_name'] ?? $entity['vendor_type'])): ?>
                    <span class="pub-tag" style="font-size:0.8rem;">
                        <?= e($entity['type_icon'] ?? '') ?> <?= e($entity['type_name'] ?? $entity['vendor_type']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($entity['description'])): ?>
                <p class="pub-entity-profile-desc"><?= e($entity['description']) ?></p>
            <?php endif; ?>

            <!-- Contact info -->
            <?php if ($entityShowContactInfo): ?>
            <div class="pub-entity-contacts">
                <?php if (!empty($entity['phone'])): ?>
                    <a href="tel:<?= e($entity['phone']) ?>" class="pub-contact-item">
                        📞 <?= e($entity['phone']) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($entity['email'])): ?>
                    <a href="mailto:<?= e($entity['email']) ?>" class="pub-contact-item">
                        📧 <?= e($entity['email']) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($entity['website'])): ?>
                    <a href="<?= e($entity['website']) ?>" target="_blank" rel="noopener" class="pub-contact-item">
                        🌐 <?= e(parse_url($entity['website'], PHP_URL_HOST) ?: $entity['website']) ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Social links -->
            <div class="pub-entity-social">
                <?php
                $waNum = ltrim($entity['whatsapp'] ?? '', '+');
                $socials = [
                    'whatsapp'  => [$waNum ? 'https://wa.me/' . $waNum : '', '💬 WhatsApp'],
                    'facebook'  => [$entity['facebook']  ?? '', '📘 Facebook'],
                    'instagram' => [$entity['instagram'] ?? '', '📷 Instagram'],
                    'twitter'   => [$entity['twitter']   ?? '', '🐦 Twitter'],
                    'snapchat'  => [$entity['snapchat']  ?? '', '👻 Snapchat'],
                ];
                foreach ($socials as $net => [$url, $label]):
                    if (empty($entity[$net])) continue;
                ?>
                    <a href="<?= e($url) ?>"
                       target="_blank" rel="noopener" class="pub-social-btn pub-social-btn--<?= e($net) ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Share button -->
            <div style="margin-top:12px;">
                <button class="pub-btn pub-btn--ghost pub-btn--sm" id="pubShareBtn"
                        onclick="pubShareEntity()" style="display:inline-flex;align-items:center;gap:6px;">
                    📤 <?= e(t('entity.share')) ?>
                </button>
                <div id="pubSharePanel" style="display:none;margin-top:10px;padding:12px;
                     background:var(--pub-surface);border:1px solid var(--pub-border);
                     border-radius:var(--pub-radius);max-width:320px;">
                    <p style="margin:0 0 10px;font-size:0.85rem;font-weight:600;"><?= e(t('entity.share')) ?></p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($entity['store_name'] ?? '') ?>%20" id="pubShareWA"
                           target="_blank" rel="noopener" class="pub-social-btn">💬 WhatsApp</a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($entity['store_name'] ?? '') ?>&url=" id="pubShareTW"
                           target="_blank" rel="noopener" class="pub-social-btn">🐦 Twitter/X</a>
                        <button class="pub-social-btn" onclick="pubCopyLink()">🔗 <?= e(t('entity.copy_link')) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main tabs -->
    <div class="pub-tabs" style="margin-top:24px;" role="tablist">
        <button class="pub-tab active" data-tab="products" role="tab"
                aria-selected="true" aria-controls="tabProducts">
            🛍️ <?= e(t('entity.products_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="info" role="tab"
                aria-selected="false" aria-controls="tabInfo">
            ℹ️ <?= e(t('entity.info_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="hours" role="tab"
                aria-selected="false" aria-controls="tabHours">
            🕐 <?= e(t('entity.hours_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="map" role="tab"
                aria-selected="false" aria-controls="tabMap">
            🗺️ <?= e(t('entity.location_tab')) ?>
        </button>
        <?php if (!empty($discounts)): ?>
        <button class="pub-tab" data-tab="discounts" role="tab"
                aria-selected="false" aria-controls="tabDiscounts">
            🏷️ <?= e(t('entity.discounts_tab')) ?>
            <span class="pub-tab-count"><?= count($discounts) ?></span>
        </button>
        <?php endif; ?>
        <?php if ($entityShowReviews): ?>
        <button class="pub-tab" data-tab="ratings" role="tab"
                aria-selected="false" aria-controls="tabRatings">
            ⭐ <?= e(t('entity.ratings_tab')) ?>
            <?php if ($entityRatingTotal > 0): ?><span class="pub-tab-count"><?= $entityRatingTotal ?></span><?php endif; ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- TAB: Products -->
    <div class="pub-tab-panel active" id="tabProducts">
        <!-- Hierarchical category menus + search -->
        <?php if (!empty($categoryTree)):
            // Pre-compute which parent category (if any) contains the selected category
            $activePrimaryId = 0;
            foreach ($categoryTree as $mainCat) {
                $mId = (int)($mainCat['id'] ?? 0);
                if ($selectedCat === $mId) { $activePrimaryId = $mId; break; }
                foreach (($mainCat['children'] ?? []) as $ch) {
                    if ($selectedCat === (int)($ch['id'] ?? 0)) { $activePrimaryId = $mId; break 2; }
                }
            }
        ?>
        <!-- Main category tabs (parent categories) -->
        <div class="pub-cat-tabs pub-cat-tabs--main" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;" role="tablist">
            <a href="?id=<?= $entityId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
               class="pub-cat-tab-btn <?= !$selectedCat ? 'active' : '' ?>" role="tab"
               aria-selected="<?= !$selectedCat ? 'true' : 'false' ?>">
                <?= e(t('entity.all_categories')) ?>
            </a>
            <?php foreach ($categoryTree as $mainCat):
                $mainId      = (int)($mainCat['id'] ?? 0);
                $parentActive = ($activePrimaryId === $mainId);
            ?>
            <a href="?id=<?= $entityId ?>&cat=<?= $mainId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
               class="pub-cat-tab-btn <?= $parentActive ? 'active' : '' ?>" role="tab"
               aria-selected="<?= $parentActive ? 'true' : 'false' ?>">
                <?= e($mainCat['name'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($activePrimaryId):
            // Find the active parent and render its sub-category tabs
            foreach ($categoryTree as $mainCat):
                if ((int)($mainCat['id'] ?? 0) !== $activePrimaryId) continue;
                $children = $mainCat['children'] ?? [];
                if (empty($children)) break;
        ?>
        <!-- Sub-category tabs -->
        <div class="pub-cat-tabs pub-cat-tabs--sub" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;padding-inline-start:16px;" role="tablist">
            <a href="?id=<?= $entityId ?>&cat=<?= $activePrimaryId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
               class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= ($selectedCat === $activePrimaryId) ? 'active' : '' ?>">
                <?= e(t('entity.all_in_category', 'All items')) ?>
            </a>
            <?php foreach ($children as $subCat):
                $subId = (int)($subCat['id'] ?? 0);
            ?>
            <a href="?id=<?= $entityId ?>&cat=<?= $subId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
               class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= $selectedCat === $subId ? 'active' : '' ?>">
                <?= e($subCat['name'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>
        <?php endif; ?>

        <!-- Product search within entity -->
        <form method="get" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="id" value="<?= $entityId ?>">
            <?php if ($selectedCat): ?><input type="hidden" name="cat" value="<?= $selectedCat ?>"><?php endif; ?>
            <input type="search" name="q" class="pub-search-input"
                   style="max-width:320px;"
                   placeholder="🔍 <?= e(t('products.search_placeholder')) ?>"
                   value="<?= e($productSearch) ?>">
            <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('products.filter')) ?></button>
            <?php if ($productSearch): ?>
                <a href="?id=<?= $entityId ?><?= $selectedCat ? '&cat=' . $selectedCat : '' ?>"
                   class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('products.clear')) ?></a>
            <?php endif; ?>
        </form>

        <?php if (!empty($products)): ?>
        <div class="pub-grid" style="margin-top:20px;">
            <?php foreach ($products as $p): ?>
            <?php
                // Build all images list for slideshow
                $pAllImgs = [];
                if (!empty($p['image_urls'])) {
                    foreach (explode('|', $p['image_urls']) as $rawU) {
                        $s = pub_img(trim($rawU), 'product_thumb');
                        if ($s) $pAllImgs[] = $s;
                    }
                } elseif (!empty($p['image_url'])) {
                    $pAllImgs[] = pub_img($p['image_url'], 'product_thumb');
                }
                $pHasMulti = count($pAllImgs) > 1;
            ?>
            <div class="pub-product-card<?= $_entityProductCardClass ? ' ' . $_entityProductCardClass : '' ?>"<?= $_entityProductCardStyle ? ' style="' . e($_entityProductCardStyle) . '"' : '' ?><?= $pHasMulti ? ' data-img-slide="1"' : '' ?>>
                <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
                   style="text-decoration:none;display:block;">
                <div class="pub-cat-img-wrap" style="<?= e($_entityProductImgStyle) ?>">
                    <?php if (!empty($pAllImgs)): ?>
                        <?php foreach ($pAllImgs as $piIdx => $piSrc): ?>
                        <img src="<?= e($piSrc) ?>"
                             alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img pub-slide-img<?= $piIdx > 0 ? ' pub-slide-img--hidden' : '' ?>" loading="lazy"
                             onerror="this.style.display='none'">
                        <?php endforeach; ?>
                        <span class="pub-img-placeholder" style="display:none;">🖼️</span>
                        <?php if ($pHasMulti): ?>
                        <div class="pub-slide-dots" aria-hidden="true">
                            <?php for ($pdi = 0; $pdi < count($pAllImgs); $pdi++): ?>
                            <span class="pub-slide-dot<?= $pdi === 0 ? ' pub-slide-dot--active' : '' ?>"></span>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="pub-img-placeholder">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? t('common.currency')) ?></p>
                    <?php endif; ?>
                </div>
                </a>
                <button class="pub-cart-add-btn"
                        onclick="pubAddToCart(this)"
                        data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                        data-product-name="<?= e($p['name'] ?? '') ?>"
                        data-product-price="<?= (float)($p['price'] ?? 0) ?>"
                        data-product-image="<?= e($pAllImgs[0] ?? ($p['image_url'] ?? '')) ?>"
                        data-product-sku="<?= e($p['sku'] ?? '') ?>">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Product pagination -->
        <?php
        $totalPg = (int)($productMeta['total_pages'] ?? 1);
        if ($totalPg > 1):
            $pg_url = fn(int $pg) => '?id=' . $entityId . ($selectedCat ? '&cat=' . $selectedCat : '') . '&page=' . $pg . '#tabProducts';
        ?>
        <nav class="pub-pagination" style="margin-top:24px;">
            <a href="<?= $pg_url(max(1,$productPage-1)) ?>" class="pub-page-btn <?= $productPage<=1?'disabled':'' ?>">
                <?= e(t('pagination.prev')) ?>
            </a>
            <?php for ($i=max(1,$productPage-2); $i<=min($totalPg,$productPage+2); $i++): ?>
                <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$productPage?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $pg_url(min($totalPg,$productPage+1)) ?>" class="pub-page-btn <?= $productPage>=$totalPg?'disabled':'' ?>">
                <?= e(t('pagination.next')) ?>
            </a>
        </nav>
        <?php endif; ?>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">🛍️</div>
            <p class="pub-empty-msg"><?= e(t('entity.no_products')) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Info -->
    <div class="pub-tab-panel" id="tabInfo" style="display:none;">
        <div style="margin-top:20px;display:grid;gap:16px;">

            <!-- Attributes -->
            <?php if (!empty($entity['attributes'])): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title"><?= e(t('entity.details')) ?></h3>
                <div class="pub-attr-grid">
                    <?php foreach ($entity['attributes'] as $attr): ?>
                        <?php if (empty($attr['value'])) continue; ?>
                        <div class="pub-attr-row">
                            <span class="pub-attr-key"><?= e($attr['attribute_name'] ?? '') ?></span>
                            <span class="pub-attr-val"><?= e($attr['value'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Methods -->
            <?php if (!empty($entity['payment_methods'])): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title"><?= e(t('entity.payment_methods')) ?></h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;padding:12px 16px;">
                    <?php foreach ($entity['payment_methods'] as $pm): ?>
                        <span class="pub-tag" style="font-size:0.85rem;padding:6px 14px;">
                            <?= e($pm['icon'] ?? '💳') ?> <?= e($pm['name'] ?? '') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Entity Settings / Business Info -->
            <?php
            $_esRows = [];
            if (!empty($entitySettings)) {
                if ((float)($entitySettings['min_order_amount'] ?? 0) > 0)
                    $_esRows[] = ['🛒', t('entity.settings_min_order'), number_format((float)$entitySettings['min_order_amount'], 2) . ' ' . e(t('common.currency'))];
                if ((int)($entitySettings['preparation_time_minutes'] ?? 0) > 0)
                    $_esRows[] = ['⏱️', t('entity.settings_prep_time'), (int)$entitySettings['preparation_time_minutes'] . ' ' . e(t('entity.settings_minutes'))];
                if ((float)($entitySettings['delivery_radius_km'] ?? 0) > 0)
                    $_esRows[] = ['🚚', t('entity.settings_delivery_radius'), (float)$entitySettings['delivery_radius_km'] . ' ' . e(t('entity.settings_km'))];
                if ((float)($entitySettings['free_delivery_min_order'] ?? 0) > 0)
                    $_esRows[] = ['🆓', t('entity.settings_free_delivery'), number_format((float)$entitySettings['free_delivery_min_order'], 2) . ' ' . e(t('common.currency'))];
                if (!empty($entitySettings['allow_online_booking']))
                    $_esRows[] = ['📅', t('entity.settings_online_booking'), e(t('common.yes'))];
                if (!empty($entitySettings['booking_window_days']) && (int)$entitySettings['booking_window_days'] > 0)
                    $_esRows[] = ['📆', t('entity.settings_booking_window'), (int)$entitySettings['booking_window_days'] . ' ' . e(t('entity.settings_days'))];
                if (!empty($entitySettings['max_bookings_per_slot']) && (int)$entitySettings['max_bookings_per_slot'] > 0)
                    $_esRows[] = ['👥', t('entity.settings_max_per_slot'), (int)$entitySettings['max_bookings_per_slot']];
                if (isset($entitySettings['booking_cancellation_allowed']))
                    $_esRows[] = ['↩️', t('entity.settings_cancellation'), !empty($entitySettings['booking_cancellation_allowed']) ? e(t('common.yes')) : e(t('common.no'))];
                if (!empty($entitySettings['allow_preorders']))
                    $_esRows[] = ['📋', t('entity.settings_preorders'), e(t('common.yes'))];
                if (!empty($entitySettings['allow_cod']))
                    $_esRows[] = ['💵', t('entity.settings_cod'), e(t('common.yes'))];
                if (!empty($entitySettings['auto_accept_orders']))
                    $_esRows[] = ['✅', t('entity.settings_auto_accept'), e(t('common.yes'))];
                if (!empty($entitySettings['max_daily_orders']) && (int)$entitySettings['max_daily_orders'] > 0)
                    $_esRows[] = ['📦', t('entity.settings_max_daily'), (int)$entitySettings['max_daily_orders']];
                if (!empty($entitySettings['default_payment_method']))
                    $_esRows[] = ['💳', t('entity.settings_default_payment'), e($entitySettings['default_payment_method'])];
                if (!empty($entitySettings['featured_in_app']))
                    $_esRows[] = ['⭐', t('entity.settings_featured'), e(t('common.yes'))];
            }
            if (!empty($_esRows)):
            ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title">⚙️ <?= e(t('entity.settings_title')) ?></h3>
                <div class="pub-attr-grid">
                    <?php foreach ($_esRows as [$icon, $label, $value]): ?>
                    <div class="pub-attr-row">
                        <span class="pub-attr-key"><?= $icon ?> <?= $label ?></span>
                        <span class="pub-attr-val"><?= $value ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Working Hours -->
    <div class="pub-tab-panel" id="tabHours" style="display:none;">
        <?php if (!empty($entity['working_hours'])): ?>
        <div class="pub-info-card" style="margin-top:20px;">
            <h3 class="pub-info-card-title">🕐 <?= e(t('entity.hours_tab')) ?></h3>
            <div class="pub-hours-table">
                <?php foreach ($entity['working_hours'] as $h): ?>
                <div class="pub-hours-row <?= empty($h['is_open']) ? 'pub-hours-row--closed' : '' ?>">
                    <span class="pub-hours-day"><?= e($dayNames[(int)($h['day_of_week'] ?? 0)] ?? $h['day_of_week']) ?></span>
                    <span class="pub-hours-time">
                        <?php if (empty($h['is_open'])): ?>
                            <span style="color:var(--pub-muted);"><?= e(t('entity.closed')) ?></span>
                        <?php else: ?>
                            <?= e($h['open_time'] ?? '') ?> — <?= e($h['close_time'] ?? '') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Map / Location -->
    <div class="pub-tab-panel" id="tabMap" style="display:none;">
        <?php if (!empty($entity['addresses'])): ?>
        <div style="margin-top:20px;display:grid;gap:16px;">
            <?php foreach ($entity['addresses'] as $addr): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title">
                    📍 <?= e($addr['label'] ?? '') ?>
                    <?php if (!empty($addr['is_primary'])): ?>
                        <span style="font-size:0.75rem;background:var(--pub-primary);color:#fff;padding:2px 8px;border-radius:20px;margin-inline-start:6px;">★</span>
                    <?php endif; ?>
                </h3>
                <p style="padding:8px 16px;color:var(--pub-text);margin:0;">
                    <?= e($addr['address_line1'] ?? '') ?>
                    <?php if (!empty($addr['address_line2'])): ?>, <?= e($addr['address_line2']) ?><?php endif; ?>
                </p>
                <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
                <div style="padding:0 16px 16px;">
                    <a href="https://www.openstreetmap.org/?mlat=<?= e($addr['latitude']) ?>&mlon=<?= e($addr['longitude']) ?>#map=16/<?= e($addr['latitude']) ?>/<?= e($addr['longitude']) ?>"
                       target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;">
                        🗺️ <?= e(t('entity.view_on_map')) ?>
                    </a>
                    <a href="https://maps.google.com/?q=<?= e($addr['latitude']) ?>,<?= e($addr['longitude']) ?>"
                       target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;margin-inline-start:8px;">
                        📍 Google Maps
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">📍</div>
            <p class="pub-empty-msg"><?= e(t('entity.no_addresses')) ?></p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.pub-container -->

<?php if (!empty($discounts)): ?>
<!-- TAB: Discounts (rendered outside container so it spans full context) -->
<?php endif; ?>

<!-- Discounts tab panel (inside container) -->
<div class="pub-container">

    <!-- TAB: Discounts panel -->
    <div class="pub-tab-panel" id="tabDiscounts" style="display:none;">
        <?php if (!empty($discounts)): ?>
        <div style="margin-top:20px;display:grid;gap:14px;">
            <?php foreach ($discounts as $d): ?>
            <div class="pub-discount-card<?= $_entityDiscountCardClass ? ' ' . $_entityDiscountCardClass : '' ?>"<?= $_entityDiscountCardStyle ? ' style="' . e($_entityDiscountCardStyle) . '"' : '' ?>>
                <?php if (!empty($d['marketing_badge'])): ?>
                    <span class="pub-discount-badge-top"><?= e($d['marketing_badge']) ?></span>
                <?php endif; ?>
                <div class="pub-discount-inner">
                    <div class="pub-discount-icon">🏷️</div>
                    <div class="pub-discount-body">
                        <p class="pub-discount-title"><?= e($d['title'] ?? $d['code'] ?? '') ?></p>
                        <?php if (!empty($d['description'])): ?>
                            <p class="pub-discount-desc"><?= e($d['description']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($d['code'])): ?>
                            <div class="pub-discount-code-row">
                                <span class="pub-discount-code"><?= e($d['code']) ?></span>
                                <button class="pub-btn pub-btn--ghost pub-btn--sm"
                                        onclick="pubCopyDiscount('<?= e(addslashes($d['code'])) ?>', this)">
                                    📋 <?= e(t('discounts.copy_code')) ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($d['ends_at'])): ?>
                            <p class="pub-discount-expires">
                                ⏰ <?= e(t('discounts.expires')) ?>: <?= e(substr($d['ends_at'], 0, 10)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($d['terms_conditions'])): ?>
                    <details class="pub-discount-terms">
                        <summary><?= e(t('discounts.terms')) ?></summary>
                        <p><?= e($d['terms_conditions']) ?></p>
                    </details>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">🏷️</div>
            <p class="pub-empty-msg"><?= e(t('discounts.none')) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Ratings -->
    <?php if ($entityShowReviews): ?>
    <div class="pub-tab-panel" id="tabRatings" style="display:none;">
        <div style="margin-top:20px;">
            <?php if ($entityRatingAvg !== null): ?>
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">
                <div style="font-size:2.8rem;font-weight:900;color:var(--pub-accent,#F59E0B);">
                    <?= number_format((float)$entityRatingAvg, 1) ?>
                </div>
                <div>
                    <div style="font-size:1.3rem;letter-spacing:2px;">
                        <?php for ($si=1; $si<=5; $si++): ?>
                            <?php if ($si <= $entityRatingAvg): ?>
                                <span style="color:#F59E0B;">★</span>
                            <?php elseif ($si - 0.5 <= $entityRatingAvg): ?>
                                <span style="color:#F59E0B;opacity:0.6;">★</span>
                            <?php else: ?>
                                <span style="color:var(--pub-border);">☆</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div style="font-size:0.82rem;color:var(--pub-muted);"><?= $entityRatingTotal ?> <?= e(t('entity.ratings_count')) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($entityRatings)): ?>
            <div style="display:grid;gap:12px;">
                <?php foreach ($entityRatings as $r): ?>
                <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:14px 16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                        <span style="font-weight:700;font-size:0.9rem;"><?= e($r['reviewer_name']) ?></span>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="color:#F59E0B;font-size:1rem;">
                                <?php for ($si=1; $si<=5; $si++): ?>
                                    <?= $si <= (float)$r['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </span>
                            <span style="font-size:0.75rem;color:var(--pub-muted);"><?= e(substr($r['created_at'] ?? '', 0, 10)) ?></span>
                        </div>
                    </div>
                    <?php if (!empty($r['review'])): ?>
                        <p style="margin:0;font-size:0.88rem;color:var(--pub-text);"><?= e($r['review']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pub-empty" style="padding:40px 0;">
                <div class="pub-empty-icon">⭐</div>
                <p class="pub-empty-msg"><?= e(t('entity.no_ratings')) ?></p>
            </div>
            <?php endif; ?>

            <!-- Rate this entity (login-gated) -->
            <?php if ($_isLoggedIn): ?>
            <div style="margin-top:24px;background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:20px;">
                <h3 style="margin:0 0 14px;font-size:1rem;font-weight:700;"><?= e(t('entity.rate_title')) ?></h3>
                <form id="pubEntityRateForm" onsubmit="pubSubmitEntityRating(event)">
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:6px;"><?= e(t('entity.your_rating')) ?></label>
                        <div id="pubEntityStarPicker" style="display:flex;gap:6px;font-size:1.8rem;cursor:pointer;" role="group" aria-label="<?= e(t('entity.your_rating')) ?>">
                            <?php for ($si=1; $si<=5; $si++): ?>
                                <span class="pub-star-pick" data-val="<?= $si ?>" onclick="pubPickEntityStar(<?= $si ?>)"
                                      style="color:var(--pub-border);transition:color 0.15s;user-select:none;">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" id="pubEntityRating" name="rating" value="0">
                    </div>
                    <div style="margin-bottom:12px;">
                        <textarea id="pubEntityReview" name="review" rows="3"
                                  placeholder="<?= e(t('entity.write_review')) ?>"
                                  class="pub-input" style="width:100%;padding:8px 12px;border-radius:var(--pub-radius);border:1px solid var(--pub-border);background:var(--pub-bg);color:var(--pub-text);font-size:0.88rem;resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="pub-btn pub-btn--primary">
                        ⭐ <?= e(t('entity.submit_rating')) ?>
                    </button>
                    <p id="pubEntityRateMsg" style="margin:8px 0 0;font-size:0.85rem;display:none;"></p>
                </form>
            </div>
            <?php else: ?>
            <p style="text-align:center;margin-top:20px;font-size:0.88rem;color:var(--pub-muted);">
                <a href="/frontend/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="pub-link"><?= e(t('common.login')) ?></a>
                <?= e(t('entity.login_to_rate')) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; // end show_reviews ?>

</div><!-- /.pub-container discounts -->

<script>
// Simple tab switcher
document.querySelectorAll('.pub-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tab = this.dataset.tab;
        document.querySelectorAll('.pub-tab').forEach(function(b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected','false');
        });
        document.querySelectorAll('.pub-tab-panel').forEach(function(p) {
            p.style.display = 'none';
            p.classList.remove('active');
        });
        this.classList.add('active');
        this.setAttribute('aria-selected','true');
        var panel = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (panel) { panel.style.display = ''; panel.classList.add('active'); }
    });
});

// Share panel toggle
function pubShareEntity() {
    var panel = document.getElementById('pubSharePanel');
    if (!panel) return;
    var isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        var url = encodeURIComponent(window.location.href);
        var wa = document.getElementById('pubShareWA');
        var tw = document.getElementById('pubShareTW');
        if (wa) wa.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(document.title + ' ') + url;
        if (tw) tw.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(document.title) + '&url=' + url;
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
.pub-tab-panel { padding-bottom:40px; }
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
.pub-cart-add-btn { width:100%; margin-top:6px; padding:7px 0; background:var(--pub-primary,#03874e); color:#fff;
  border:none; border-radius:0 0 var(--pub-radius) var(--pub-radius); font-size:0.82rem; font-weight:600;
  cursor:pointer; transition:opacity 0.2s; }
.pub-cart-add-btn:hover { opacity:0.85; }
/* Open/closed badge */
.pub-open-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px;
  font-size:0.78rem; font-weight:700; }
.pub-open-badge--open  { background:#d1fae5; color:#065f46; }
.pub-open-badge--closed{ background:#fee2e2; color:#991b1b; }
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
  background:#fef3c7; color:#92400e; }
/* Mobile entity profile */
@media(max-width:600px){
  .pub-entity-profile-header { flex-direction:column; align-items:center; text-align:center; gap:10px; margin-top:-36px; }
  .pub-entity-profile-info { padding-top:0; width:100%; }
  .pub-entity-profile-logo { width:72px; height:72px; margin:0 auto; }
  .pub-entity-profile-name { font-size:1.15rem; }
  .pub-entity-contacts { justify-content:flex-start; }
  .pub-entity-social { justify-content:flex-start; }
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