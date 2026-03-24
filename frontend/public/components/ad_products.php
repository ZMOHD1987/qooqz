<?php
declare(strict_types=1);
/**
 * Component: ad_products
 * Renders product cards (featured, new, etc.) with wishlist and add-to-cart.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}

$_cardProduct = $_cardStyles['product']['inline'] ?? '';
$_clsProduct  = $_cardStyles['product']['class'] ?? '';
$_imgProduct  = $_cardStyles['product']['img'] ?? '';
?>
<div class="pub-grid">
    <?php foreach ($sectionData as $p):
        $pId    = (int)($p['id'] ?? 0);
        $pName  = $p['name'] ?? '';
        $pPrice = $p['price'] ?? null;
        $pCur   = $p['currency_code'] ?? t('common.currency');
        $pImg   = pub_img($p['image_url'] ?? $p['image_thumb_url'] ?? null, 'product');
    ?>
    <div class="pub-product-card<?= $_clsProduct ? ' ' . $_clsProduct : '' ?>" style="position:relative;<?= e($_cardProduct) ?>">
        <button class="pub-wishlist-btn"
                type="button"
                data-product-id="<?= $pId ?>"
                data-entity-id="<?= $p['entity_id'] ?? 1 ?>"
                onclick="pubToggleWishlist(this)"
                title="<?= e(t('wishlist.add')) ?>">♡</button>
        <a href="/frontend/public/product.php?id=<?= $pId ?>"
           style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
           aria-label="<?= e($pName) ?>">
            <div class="pub-cat-img-wrap" style="<?= e($_imgProduct) ?>">
                <?php if ($pImg): ?>
                    <img src="<?= e($pImg) ?>"
                         alt="<?= e($pName) ?>" class="pub-cat-img" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                <?php endif; ?>
            </div>
            <div class="pub-product-card-body">
                <?php if (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                <p class="pub-product-name"><?= e($pName) ?></p>
                <?php if ($pPrice !== null): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$pPrice, 2) ?>
                        <small><?= e($pCur) ?></small>
                    </p>
                <?php endif; ?>
            </div>
        </a>
        <div style="padding:0 14px 12px;">
            <button class="pub-btn pub-btn--primary pub-btn--sm"
                    style="width:100%;justify-content:center;"
                    type="button"
                    data-product-id="<?= $pId ?>"
                    data-product-name="<?= e($pName) ?>"
                    data-product-price="<?= e((string)($pPrice ?? '0')) ?>"
                    data-product-image="<?= e($pImg ?: '') ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>"
                    data-currency="<?= e($pCur) ?>"
                    data-added-text="✅ <?= e(t('cart.added')) ?>"
                    onclick="pubAddToCart(this)">
                🛒 <?= e(t('cart.add')) ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
