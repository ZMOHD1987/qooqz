<?php
declare(strict_types=1);
/**
 * frontend/public/tenants.php
 * QOOQZ — Tenants Listing Page
 * Shows all active tenants with search and pagination.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx  = $GLOBALS['PUB_CONTEXT'];
$lang = $ctx['lang'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('nav.tenants') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'tenants';

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');

/* Fetch tenants — PDO-first, API fallback */
$tenants = [];
$total   = 0;
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $where  = ["t.status = 'active'"];
        $params = [];

        if ($search !== '') {
            $like     = '%' . addcslashes($search, '%_\\') . '%';
            $where[]  = 't.name LIKE ?';
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);

        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tenants t WHERE $whereClause");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT t.id, t.name, t.status,
                    sp.plan_name,
                    (SELECT i.url FROM images i
                      JOIN image_types it ON it.id = i.image_type_id
                      WHERE i.owner_id = t.id AND it.code = 'tenant_logo'
                      ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS logo_url
               FROM tenants t
          LEFT JOIN subscription_plans sp ON sp.id = (
                  SELECT plan_id FROM subscriptions s
                   WHERE s.tenant_id = t.id AND s.status IN ('active','trial')
                   ORDER BY s.id DESC LIMIT 1
              )
              WHERE $whereClause
              ORDER BY t.id DESC
              LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[tenants.php] PDO error: ' . $e->getMessage());
    }
}
if (!$tenants && !$pdo) {
    $qs = http_build_query(array_filter([
        'lang' => $lang, 'page' => $page, 'per_page' => $limit,
        'search' => $search ?: null,
    ]));
    $resp    = pub_fetch(pub_api_url('public/tenants') . '?' . $qs);
    $tenants = $resp['data']['data'] ?? [];
    $total   = (int)($resp['data']['meta']['total'] ?? count($tenants));
}
$totalPg = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;

include dirname(__DIR__) . '/partials/header.php';

// Resolve card style from DB card_styles
$_tenantCardStyle = pub_card_inline_style('tenants');
$_tenantCardClass = pub_card_css_class('tenants');
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.tenants')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">🏪 <?= e(t('nav.tenants')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('nav.tenants')) ?>
        </span>
    </div>

    <!-- Join as Tenant CTA -->
    <?php if (!empty($GLOBALS['PUB_CONTEXT']['user'])): ?>
    <div class="pub-cta-banner" style="margin-bottom:24px;">
        <div>
            <h2>🚀 <?= e(t('join_tenant.cta_title')) ?></h2>
            <p><?= e(t('join_tenant.cta_subtitle')) ?></p>
        </div>
        <a href="/frontend/public/join_tenant.php" class="pub-btn--cta"><?= e(t('join_tenant.cta_btn')) ?></a>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <form method="get" class="pub-filter-bar">
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('entities.filter')) ?></button>
        <?php if ($search): ?>
            <a href="/frontend/public/tenants.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('entities.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Tenants grid -->
    <?php if (!empty($tenants)): ?>
    <div class="pub-grid-md">
        <?php foreach ($tenants as $ten): ?>
        <a href="/frontend/public/tenant.php?id=<?= (int)($ten['id'] ?? 0) ?>"
           class="pub-entity-card<?= $_tenantCardClass ? ' ' . $_tenantCardClass : '' ?>"
           style="text-decoration:none;<?= e($_tenantCardStyle) ?>">
            <div class="pub-entity-avatar">
                <?php if (!empty($ten['logo_url'])): ?>
                    <img src="<?= e(pub_img($ten['logo_url'], 'tenant_logo')) ?>"
                         alt="<?= e($ten['name'] ?? '') ?>" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;">🏪</span>
                <?php else: ?>
                    🏪
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ten['name'] ?? '') ?></p>
                <?php if (($ten['status'] ?? '') === 'active'): ?>
                    <span class="pub-entity-verified">🟢 <?= e(t('tenants.active')) ?></span>
                <?php endif; ?>
                <?php if (!empty($ten['plan_name'])): ?>
                    <p class="pub-entity-desc"><?= e($ten['plan_name']) ?></p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" style="margin-top:28px;">
        <?php
        $base_qs = http_build_query(array_filter(['q' => $search]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1, $page - 1)) ?>" class="pub-page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPg, $page + 2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg, $page + 1)) ?>" class="pub-page-btn <?= $page >= $totalPg ? 'disabled' : '' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🏪</div>
        <p class="pub-empty-msg"><?= e(t('entities.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>