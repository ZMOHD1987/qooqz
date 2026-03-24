<?php
declare(strict_types=1);
/**
 * Component: ad_categories
 * Renders a category grid with images and product counts.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}

$_cardCategory = $_cardStyles['category']['inline'] ?? '';
$_clsCategory  = $_cardStyles['category']['class'] ?? '';
$_imgCategory  = $_cardStyles['category']['img'] ?? '';
?>
<div class="pub-grid-cat">
    <?php foreach ($sectionData as $cat): ?>
    <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
       class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?><?= $_clsCategory ? ' ' . $_clsCategory : '' ?>"
       style="text-decoration:none;<?= e($_cardCategory) ?>">
        <div class="pub-cat-img-wrap" style="<?= e($_imgCategory) ?>">
            <?php if (!empty($cat['image_url'])): ?>
                <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                     alt="<?= e($cat['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">📂</span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true">📂</span>
            <?php endif; ?>
        </div>
        <div class="pub-cat-body">
            <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
            <?php if (!empty($cat['product_count'])): ?>
                <span class="pub-cat-count"><?= (int)$cat['product_count'] ?> <?= e(t('categories.products_count')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
