<?php
declare(strict_types=1);
/**
 * Component: ad_products
 * Renders product cards (featured, new, etc.) with hover overlay and add-to-cart.
 */

if (empty($sectionData)) {
    return;
}

$_cardProduct = $_cardStyles['product']['inline'] ?? '';
$_clsProduct = $_cardStyles['product']['class'] ?? '';
$_imgProduct = $_cardStyles['product']['img'] ?? '';
?>
<div class="pub-grid">
    <?php foreach ($sectionData as $p):
        $pId = (int)($p['id'] ?? 0);
        $pName = trim($p['name'] ?? '');
        $pPrice = $p['price'] ?? null;
        $pCur = $p['currency_code'] ?? '';
        $pImg = pub_img($p['image_thumb_url'] ?? $p['image_url'] ?? null, 'product');
    ?>
    <div class="pub-product-card<?= $_clsProduct ? ' ' . $_clsProduct : '' ?>" 
         data-track-type="product"
         data-track-id="<?= $pId ?>"
         style="position:relative;<?= e($_cardProduct) ?>">
        
        <a href="/frontend/public/product.php?id=<?= $pId ?>"
           style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
           aria-label="<?= e($pName) ?>">
            <div class="pub-card-img-wrap" style="<?= e($_imgProduct) ?>">
                <?php if ($pImg): ?>
                    <img src="<?= e($pImg) ?>"
                         alt="<?= e($pName) ?>" 
                         class="pub-cat-img" 
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                <?php endif; ?>
                <!-- Hover overlay -->
                <div class="pub-card-overlay">
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.wishlist', 'Wishlist')) ?>"
                            data-product-id="<?= $pId ?>"
                            data-entity-id="<?= $p['entity_id'] ?? 1 ?>"
                            onclick="event.preventDefault();event.stopPropagation();pubToggleWishlist(this)"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg></button>
                    <a class="pub-card-action" href="/frontend/public/product.php?id=<?= $pId ?>"
                       title="<?= e(t('products.view_product', 'Quick View')) ?>"
                       onclick="event.stopPropagation()"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></a>
                </div>
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

        <div class="pub-card-cart-bar">
            <button class="pub-btn pub-btn--primary pub-btn--sm"
                    type="button"
                    title="<?= e(t('cart.add', 'Add to Cart')) ?>"
                    data-product-id="<?= $pId ?>"
                    data-product-name="<?= e($pName) ?>"
                    data-product-price="<?= e((string)($pPrice ?? '0')) ?>"
                    data-product-image="<?= e($pImg ?: '') ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>"
                    data-currency="<?= e($pCur) ?>"
                    data-added-text="✅ <?= e(t('cart.added')) ?>"
                    onclick="pubAddToCart(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>