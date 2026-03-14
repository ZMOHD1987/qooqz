<?php
/**
 * frontend/public/entities.php
 * QOOQZ — Public Entities Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('entities.page_title') . ' — QOOQZ';

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');
$vType  = trim($_GET['vendor_type'] ?? '');

/* Fetch — PDO-first */
$entities = [];
$total    = 0;
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $where  = ["e.status NOT IN ('suspended','rejected')"];
        $params = [];

        if ($tenantId) { $where[] = 'e.tenant_id = ?'; $params[] = $tenantId; }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(e.store_name LIKE ? OR e.email LIKE ?)';
            $params[] = $like; $params[] = $like;
        }

        if ($vType !== '') { $where[] = 'e.vendor_type = ?'; $params[] = $vType; }

        $whereClause = implode(' AND ', $where);

        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM entities e WHERE $whereClause");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.is_verified,
                    (SELECT et2.description FROM entity_translations et2
                     WHERE et2.entity_id = e.id AND et2.language_code = ? LIMIT 1) AS description,
                    (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                    (SELECT GROUP_CONCAT(i2.url ORDER BY i2.id ASC SEPARATOR '|')
                     FROM images i2 WHERE i2.owner_id = e.id) AS all_image_urls,
                    es.additional_settings,
                    es.allow_online_booking, es.delivery_radius_km, es.allow_cod,
                    es.featured_in_app, es.min_order_amount, es.free_delivery_min_order,
                    es.preparation_time_minutes
             FROM entities e
             LEFT JOIN entity_settings es ON es.entity_id = e.id
             WHERE $whereClause
             ORDER BY COALESCE(es.featured_in_app, 0) DESC, e.is_verified DESC, e.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(array_merge([$lang], $params));
        $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[entities.php] PDO error: ' . $e->getMessage());
    }
}
if (!$entities && !$pdo) {
    $qs = http_build_query(array_filter([
        'lang' => $lang, 'page' => $page, 'limit' => $limit,
        'tenant_id' => $tenantId, 'vendor_type' => $vType ?: null, 'q' => $search ?: null,
    ]));
    $resp     = pub_fetch(pub_api_url('public/entities') . '?' . $qs);
    $entities = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
    $total    = (int)(($resp['data']['meta']['total'] ?? count($entities)));
}
$totalPg = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;

/* Vendor type labels (static map — no API call needed) */
$vendorTypes = [
    ''                => t('entities.type_all'),
    'product_seller'  => t('entities.type_product'),
    'service_provider'=> t('entities.type_service'),
    'both'            => t('entities.type_both'),
];

include dirname(__DIR__) . '/partials/header.php';

// Resolve entity card style from DB card_styles (card_type='entities')
$_entityCardStyle = pub_card_inline_style('entities');
$_entityCardClass = pub_card_css_class('entities');
?>


<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.entities')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">🏢 <?= e(t('nav.entities')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('entities.entity_count')) ?>
        </span>
    </div>

    <!-- Join as Vendor CTA -->
    <div class="pub-cta-banner">
        <div>
            <h2>🚀 <?= e(t('join_entity.cta_title')) ?></h2>
            <p><?= e(t('join_entity.cta_subtitle')) ?></p>
        </div>
        <a href="/frontend/public/join_entity.php" class="pub-btn--cta"><?= e(t('join_entity.cta_btn')) ?></a>
    </div>

    <!-- Filters -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input"
               placeholder="<?= e(t('entities.search_placeholder')) ?>"
               value="<?= e($search) ?>">
        <select name="vendor_type" class="pub-filter-select" data-auto-submit>
            <?php foreach ($vendorTypes as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $vType===$val?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('entities.filter')) ?></button>
        <?php if ($search||$vType): ?>
            <a href="/frontend/public/entities.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('entities.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Entities grid -->
    <?php if (!empty($entities)): ?>
    <div class="pub-grid-md">
        <?php foreach ($entities as $ent):
            // Per-entity card color: use entity_settings.additional_settings.card_color if set
            $entCardStyle = pub_entity_card_style($ent, $_entityCardStyle);
            // Build image list for slideshow
            $entImgs = [];
            if (!empty($ent['all_image_urls'])) {
                foreach (explode('|', $ent['all_image_urls']) as $rawU) {
                    $s = pub_img(trim($rawU), 'entity_logo');
                    if ($s) $entImgs[] = $s;
                }
            } elseif (!empty($ent['logo_url'])) {
                $s = pub_img($ent['logo_url'], 'entity_logo');
                if ($s) $entImgs[] = $s;
            }
            $entHasMulti = count($entImgs) > 1;
        ?>
        <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
           class="pub-entity-card<?= $_entityCardClass ? ' '.$_entityCardClass : '' ?>"
           style="text-decoration:none;<?= e($entCardStyle) ?>"<?= $entHasMulti ? ' data-img-slide="1"' : '' ?>>
            <div class="pub-entity-avatar" style="position:relative;overflow:hidden;">
                <?php if (!empty($entImgs)): ?>
                    <?php foreach ($entImgs as $eIdx => $eSrc): ?>
                    <img src="<?= e($eSrc) ?>"
                         alt="<?= e($ent['store_name'] ?? '') ?>"
                         class="pub-ent-slide-img<?= $eIdx > 0 ? ' pub-ent-slide-img--hidden' : '' ?>"
                         loading="lazy"
                         onerror="this.style.display='none'">
                    <?php endforeach; ?>
                    <span style="display:none;align-items:center;justify-content:center;">🏢</span>
                <?php else: ?>
                    🏢
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ent['store_name'] ?? $ent['name'] ?? '') ?></p>
                <?php if (!empty($ent['vendor_type'])): ?>
                    <p class="pub-entity-desc"><?= e($vendorTypes[$ent['vendor_type']] ?? $ent['vendor_type']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['description'])): ?>
                    <p class="pub-entity-desc" style="margin-top:3px;"><?= e($ent['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['is_verified'])): ?>
                    <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                <?php endif; ?>
                <!-- Entity settings feature badges -->
                <?php
                $_entBadges = [];
                if (!empty($ent['featured_in_app'])) $_entBadges[] = '<span class="pub-entity-feature-badge pub-entity-feature-badge--featured">⭐ ' . e(t('entities.featured')) . '</span>';
                if (!empty($ent['allow_online_booking'])) $_entBadges[] = '<span class="pub-entity-feature-badge pub-entity-feature-badge--booking">📅 ' . e(t('entities.online_booking')) . '</span>';
                if ((float)($ent['delivery_radius_km'] ?? 0) > 0) $_entBadges[] = '<span class="pub-entity-feature-badge pub-entity-feature-badge--delivery">🚚 ' . e(t('entities.delivery')) . '</span>';
                if (!empty($ent['allow_cod'])) $_entBadges[] = '<span class="pub-entity-feature-badge pub-entity-feature-badge--cod">💵 ' . e(t('entities.cod')) . '</span>';
                if ((float)($ent['min_order_amount'] ?? 0) > 0) $_entBadges[] = '<span class="pub-entity-feature-badge pub-entity-feature-badge--minorder">🛒 ' . e(t('entities.min_order')) . ': ' . number_format((float)$ent['min_order_amount'], 2) . '</span>';
                if (!empty($_entBadges)):
                ?>
                <div class="pub-entity-badges" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;">
                    <?= implode('', $_entBadges) ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'vendor_type'=>$vType]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs?$base_qs.'&':'') . 'page='.$pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>" class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>" class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🏢</div>
        <p class="pub-empty-msg"><?= e(t('entities.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<style>
.pub-ent-slide-img { width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0; transition:opacity 0.4s; }
.pub-ent-slide-img--hidden { opacity:0; pointer-events:none; }
.pub-entity-feature-badge {
  display:inline-flex; align-items:center; gap:3px;
  font-size:0.72rem; font-weight:600; padding:2px 7px;
  border-radius:20px; line-height:1.5; white-space:nowrap;
  border:1px solid transparent;
}
.pub-entity-feature-badge--featured  { background:rgba(245,158,11,0.12); color:#b45309; border-color:rgba(245,158,11,0.3); }
.pub-entity-feature-badge--booking   { background:rgba(59,130,246,0.10); color:#1d4ed8; border-color:rgba(59,130,246,0.25); }
.pub-entity-feature-badge--delivery  { background:rgba(16,185,129,0.10); color:#047857; border-color:rgba(16,185,129,0.25); }
.pub-entity-feature-badge--cod       { background:rgba(139,92,246,0.10); color:#6d28d9; border-color:rgba(139,92,246,0.25); }
.pub-entity-feature-badge--minorder  { background:rgba(107,114,128,0.08); color:#374151; border-color:rgba(107,114,128,0.2); }
</style>
<script>
(function () {
    var INTERVAL = 3000;
    document.querySelectorAll('[data-img-slide="1"]').forEach(function (card) {
        var imgs = card.querySelectorAll('.pub-ent-slide-img');
        if (imgs.length < 2) return;
        var cur = 0;
        var timer = null;
        function showSlide(n) {
            imgs[cur].classList.add('pub-ent-slide-img--hidden');
            cur = (n + imgs.length) % imgs.length;
            imgs[cur].classList.remove('pub-ent-slide-img--hidden');
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

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>