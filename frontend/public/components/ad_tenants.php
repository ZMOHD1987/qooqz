<?php
declare(strict_types=1);
/**
 * Component: ad_tenants
 * Renders tenant/store cards.
 */

if (empty($sectionData)) {
    return;
}

$_cardTenant = $_cardStyles['tenants']['inline'] ?? '';
$_clsTenant = $_cardStyles['tenants']['class'] ?? '';
?>
<div class="pub-grid-md">
    <?php foreach ($sectionData as $ten): ?>
    <a href="/frontend/public/tenant.php?id=<?= (int)($ten['id'] ?? 0) ?>"
       class="pub-entity-card<?= $_clsTenant ? ' ' . $_clsTenant : '' ?>" 
       style="text-decoration:none;<?= e($_cardTenant) ?>">
        <div class="pub-entity-avatar">
            <?php if (!empty($ten['logo_url'])): ?>
                <img src="<?= e(pub_img($ten['logo_url'], 'tenant_logo')) ?>"
                     alt="<?= e($ten['name'] ?? '') ?>" 
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;" aria-hidden="true">🏪</span>
            <?php else: ?>
                <span aria-hidden="true">🏪</span>
            <?php endif; ?>
        </div>
        <div class="pub-entity-info">
            <p class="pub-entity-name"><?= e($ten['name'] ?? '') ?></p>
            <?php if (($ten['status'] ?? '') === 'active'): ?>
                <span class="pub-entity-verified">🟢 <?= e(t('tenants.active')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>