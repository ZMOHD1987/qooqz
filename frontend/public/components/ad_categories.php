<?php
declare(strict_types=1);
/**
 * Component: ad_categories
 * Renders a category grid with images and product counts.
 */

if (empty($sectionData)) {
    return;
}

$_cardCategory = $_cardStyles['category']['inline'] ?? '';
$_clsCategory = $_cardStyles['category']['class'] ?? '';
$_imgCategory = $_cardStyles['category']['img'] ?? '';
?>
<div class="pub-grid-cat">
    <?php foreach ($sectionData as $cat): 
        if (empty($cat['name']) && empty($cat['id'])) continue;
    ?>
    <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
       class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?><?= $_clsCategory ? ' ' . $_clsCategory : '' ?>"
       data-track-type="category"
       data-track-id="<?= (int)($cat['id'] ?? 0) ?>"
       style="text-decoration:none;<?= e($_cardCategory) ?>">
        <div class="pub-cat-img-wrap" style="<?= e($_imgCategory) ?>">
            <?php if (!empty($cat['image_url'])): ?>
                <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                     alt="<?= e($cat['name'] ?? '') ?>" 
                     class="pub-cat-img" 
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
            <?php endif; ?>
        </div>
        <div class="pub-cat-body">
            <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
            <?php if (!empty($cat['product_count'])): ?>
                <span class="pub-cat-count">
                    <?= (int)$cat['product_count'] ?> <?= e(t('categories.products')) ?>
                </span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>