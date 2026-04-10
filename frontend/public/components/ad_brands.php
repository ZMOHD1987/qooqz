<?php
declare(strict_types=1);
/**
 * Component: ad_brands
 * Renders brand cards with logos and names.
 */

if (empty($sectionData)) {
    return;
}
?>
<div class="pub-grid-cat">
    <?php foreach ($sectionData as $brand):
        $bId = (int)($brand['id'] ?? 0);
        $bName = trim($brand['name'] ?? $brand['slug'] ?? 'بدون اسم');
        $bLogo = $brand['logo_url'] ?? null;
        $bHref = '/frontend/public/brands.php?id=' . $bId;
    ?>
    <a href="<?= e($bHref) ?>"
       class="pub-cat-card"
       data-track-type="brand"
       data-track-id="<?= $bId ?>"
       style="text-decoration:none;">
        <div class="pub-cat-img-wrap">
            <?php if ($bLogo): ?>
                <img src="<?= e(pub_img($bLogo, 'brand')) ?>"
                     alt="<?= e($bName) ?>"
                     class="pub-cat-img"
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🏷️</span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true">🏷️</span>
            <?php endif; ?>
        </div>
        <div class="pub-cat-body">
            <h3 class="pub-cat-name"><?= e($bName) ?></h3>
            <?php if (!empty($brand['is_featured'])): ?>
                <span class="pub-tag pub-tag--featured"><?= e(t('brands.featured')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>