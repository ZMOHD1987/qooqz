<?php
declare(strict_types=1);
/**
 * frontend/public/jobs.php
 * QOOQZ — Public Jobs Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('jobs.page_title') . ' — QOOQZ';

// Resolve job card style from DB card_styles (card_type='job')
$_jobCardStyle = pub_card_inline_style('jobs');
$_jobCardClass = pub_card_css_class('jobs');

/* Filters */
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 15;
$search    = trim($_GET['q'] ?? '');
$jobType   = trim($_GET['employment_type'] ?? '');
$isRemote  = isset($_GET['remote']) && $_GET['remote'] === '1' ? 1 : null;
$isFeat    = isset($_GET['featured']) && $_GET['featured'] === '1' ? 1 : null;

/* Fetch from public jobs endpoint */
$qs = http_build_query(array_filter([
    'lang'            => $lang,
    'page'            => $page,
    'limit'           => $limit,
    'search'          => $search ?: null,
    'employment_type' => $jobType ?: null,
    'is_remote'       => $isRemote,
    'is_featured'     => $isFeat,
]));
$resp    = pub_fetch(pub_api_url('public/jobs') . '?' . $qs);
$jobs    = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
$meta    = $resp['data']['meta']  ?? [];
$total   = (int)($meta['total'] ?? count($jobs));
$totalPg = (int)($meta['total_pages'] ?? (($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1));

$empTypes = [
    ''            => t('jobs.type_all'),
    'full_time'   => t('jobs.type_full_time'),
    'part_time'   => t('jobs.type_part_time'),
    'contract'    => t('jobs.type_contract'),
    'freelance'   => t('jobs.type_freelance'),
    'internship'  => t('jobs.type_internship'),
];

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.jobs')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">💼 <?= e(t('nav.jobs')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('jobs.job_count')) ?>
        </span>
    </div>

    <!-- Filters -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:240px;"
               placeholder="<?= e(t('jobs.search_placeholder')) ?>"
               value="<?= e($search) ?>">

        <select name="employment_type" class="pub-filter-select" data-auto-submit>
            <?php foreach ($empTypes as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $jobType===$val?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="remote" value="1" <?= $isRemote?'checked':'' ?> onchange="this.form.submit()">
            <?= e(t('jobs.remote')) ?>
        </label>
        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="featured" value="1" <?= $isFeat?'checked':'' ?> onchange="this.form.submit()">
            <?= e(t('jobs.featured')) ?>
        </label>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('jobs.filter')) ?></button>
        <?php if ($search||$jobType||$isRemote||$isFeat): ?>
            <a href="/frontend/public/jobs.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('jobs.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Jobs list -->
    <?php if (!empty($jobs)): ?>
    <div class="pub-grid-lg">
        <?php foreach ($jobs as $j): ?>
        <a href="/frontend/public/job.php?id=<?= (int)($j['id'] ?? 0) ?>"
           class="pub-job-card<?= $_jobCardClass ? ' ' . $_jobCardClass : '' ?>"
           style="text-decoration:none;<?= e($_jobCardStyle) ?>">
            <h2 class="pub-job-title"><?= e($j['title'] ?? '') ?></h2>
            <div class="pub-job-meta">
                <?php if (!empty($j['city_name'])): ?>
                    <span>📍 <?= e($j['city_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['employment_type'])): ?>
                    <span>🕐 <?= e($empTypes[$j['employment_type']] ?? $j['employment_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['deadline'])): ?>
                    <span>📅 <?= e($j['deadline']) ?></span>
                <?php endif; ?>
            </div>
            <div class="pub-job-tags">
                <?php if (!empty($j['is_featured'])): ?><span class="pub-tag pub-tag--featured"><?= e(t('jobs.featured')) ?></span><?php endif; ?>
                <?php if (!empty($j['is_urgent'])): ?><span class="pub-tag pub-tag--urgent"><?= e(t('jobs.urgent')) ?></span><?php endif; ?>
                <?php if (!empty($j['is_remote'])): ?><span class="pub-tag pub-tag--remote"><?= e(t('jobs.remote')) ?></span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'employment_type'=>$jobType,'remote'=>$isRemote,'featured'=>$isFeat]));
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
        <div class="pub-empty-icon">💼</div>
        <p class="pub-empty-msg"><?= e(t('jobs.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>