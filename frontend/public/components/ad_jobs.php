<?php
declare(strict_types=1);
/**
 * Component: ad_jobs
 * Renders job listing cards with metadata and tags.
 */

if (empty($sectionData)) {
    return;
}

$_cardJob = $_cardStyles['feature']['inline'] ?? '';
$_clsJob = $_cardStyles['feature']['class'] ?? '';
?>
<div class="pub-grid-lg">
    <?php foreach ($sectionData as $j): ?>
    <a href="/frontend/public/jobs.php?id=<?= (int)($j['id'] ?? 0) ?>"
       class="pub-job-card<?= $_clsJob ? ' ' . $_clsJob : '' ?>"
       data-track-type="job"
       data-track-id="<?= (int)($j['id'] ?? 0) ?>"
       style="text-decoration:none;<?= e($_cardJob) ?>">
        <h3 class="pub-job-title"><?= e($j['title'] ?? '') ?></h3>
        <div class="pub-job-meta">
            <?php if (!empty($j['employment_type'])): ?>
                <span>🕐 <?= e(str_replace('_', ' ', $j['employment_type'])) ?></span>
            <?php endif; ?>
        </div>
        <div class="pub-job-tags">
            <?php if (!empty($j['is_featured'])): ?>
                <span class="pub-tag pub-tag--featured"><?= e(t('jobs.featured')) ?></span>
            <?php endif; ?>
            <?php if (!empty($j['is_urgent'])): ?>
                <span class="pub-tag pub-tag--urgent"><?= e(t('jobs.urgent')) ?></span>
            <?php endif; ?>
            <?php if (!empty($j['is_remote'])): ?>
                <span class="pub-tag pub-tag--remote"><?= e(t('jobs.remote')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>