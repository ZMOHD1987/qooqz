<?php
/**
 * frontend/partials/store_sections/products.php
 * Store Products Section — Product grid with categories, search, pagination
 *
 * Expected variables:
 *   $entity, $entityId, $entityTenantId
 *   $products, $productMeta, $productPage, $productLimit
 *   $categories, $categoryTree, $selectedCat, $productSearch
 *   $lang, $_entityProductCardStyle, $_entityProductCardClass, $_entityProductImgStyle
 *   $sectionSettings — Section JSON settings
 */

$showCategories = ($sectionSettings['show_categories'] ?? true);
$showSearch     = ($sectionSettings['show_search']     ?? true);
$showCart       = ($sectionSettings['show_cart']        ?? true);
?>

<div class="pub-entity-section-content" id="sectionProducts">
    <!-- Hierarchical category menus + search -->
    <?php if ($showCategories && !empty($categoryTree)):
        // Pre-compute which parent category (if any) contains the selected category
        $activePrimaryId = 0;
        foreach ($categoryTree as $mainCat) {
            $mId = (int)($mainCat['id'] ?? 0);
            if ($selectedCat === $mId) { $activePrimaryId = $mId; break; }
            foreach (($mainCat['children'] ?? []) as $ch) {
                if ($selectedCat === (int)($ch['id'] ?? 0)) { $activePrimaryId = $mId; break 2; }
            }
        }
    ?>
    <!-- Main category tabs (parent categories) -->
    <div class="pub-cat-tabs pub-cat-tabs--main" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;" role="tablist">
        <a href="?id=<?= $entityId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= !$selectedCat ? 'active' : '' ?>" role="tab"
           aria-selected="<?= !$selectedCat ? 'true' : 'false' ?>">
            <?= e(t('entity.all_categories')) ?>
        </a>
        <?php foreach ($categoryTree as $mainCat):
            $mainId      = (int)($mainCat['id'] ?? 0);
            $parentActive = ($activePrimaryId === $mainId);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $mainId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= $parentActive ? 'active' : '' ?>" role="tab"
           aria-selected="<?= $parentActive ? 'true' : 'false' ?>">
            <?= e($mainCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($activePrimaryId):
        // Find the active parent and render its sub-category tabs
        foreach ($categoryTree as $mainCat):
            if ((int)($mainCat['id'] ?? 0) !== $activePrimaryId) continue;
            $children = $mainCat['children'] ?? [];
            if (empty($children)) break;
    ?>
    <!-- Sub-category tabs -->
    <div class="pub-cat-tabs pub-cat-tabs--sub" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;padding-inline-start:16px;" role="tablist">
        <a href="?id=<?= $entityId ?>&cat=<?= $activePrimaryId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= ($selectedCat === $activePrimaryId) ? 'active' : '' ?>">
            <?= e(t('entity.all_in_category', 'All items')) ?>
        </a>
        <?php foreach ($children as $subCat):
            $subId = (int)($subCat['id'] ?? 0);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $subId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= $selectedCat === $subId ? 'active' : '' ?>">
            <?= e($subCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
    <div class="pub-grid" style="margin-top:20px;">
        <?php foreach ($products as $p): ?>
        <?php
            // Build all images list for slideshow
            $pAllImgs = [];
            if (!empty($p['image_urls'])) {
                foreach (explode('|', $p['image_urls']) as $rawU) {
                    $s = pub_img(trim($rawU), 'product_thumb');
                    if ($s) $pAllImgs[] = $s;
                }
            } elseif (!empty($p['image_url'])) {
                $pAllImgs[] = pub_img($p['image_url'], 'product_thumb');
            }
            $pHasMulti = count($pAllImgs) > 1;
        ?>
        <div class="pub-product-card<?= $_entityProductCardClass ? ' ' . $_entityProductCardClass : '' ?>" style="position:relative;<?= $_entityProductCardStyle ? e($_entityProductCardStyle) : '' ?>"<?= $pHasMulti ? ' data-img-slide="1"' : '' ?>>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               style="text-decoration:none;display:flex;flex-direction:column;flex:1;" aria-label="<?= e($p['name'] ?? '') ?>">
            <div class="pub-card-img-wrap" style="<?= e($_entityProductImgStyle) ?>">
                <?php if (!empty($pAllImgs)): ?>
                    <?php foreach ($pAllImgs as $piIdx => $piSrc): ?>
                    <img src="<?= e($piSrc) ?>"
                         alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img pub-slide-img<?= $piIdx > 0 ? ' pub-slide-img--hidden' : '' ?>" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <?php endforeach; ?>
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                    <?php if ($pHasMulti): ?>
                    <div class="pub-slide-dots" aria-hidden="true">
                        <?php for ($pdi = 0; $pdi < count($pAllImgs); $pdi++): ?>
                        <span class="pub-slide-dot<?= $pdi === 0 ? ' pub-slide-dot--active' : '' ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-image pub-img-placeholder-icon"></i></span>
                <?php endif; ?>
                <!-- Hover overlay with action buttons -->
                <div class="pub-card-overlay">
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.wishlist', 'Wishlist')) ?>"
                            data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                            data-entity-id="<?= $entityId ?>"
                            onclick="event.preventDefault();event.stopPropagation();if(typeof pubToggleWishlist==='function')pubToggleWishlist(this)"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg></button>
                    <button class="pub-card-action" type="button" title="<?= e(t('nav.compare', 'Compare')) ?>"
                            data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                            onclick="event.preventDefault();event.stopPropagation();if(typeof pubCompare==='function')pubCompare(this)"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg></button>
                    <a class="pub-card-action" href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
                       title="<?= e(t('products.view_product', 'Quick View')) ?>"
                       onclick="event.stopPropagation()"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></a>
                </div>
            </div>
            <div class="pub-product-card-body">
                <?php if (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                <?php
                $pRating = round((float)($p['rating_average'] ?? 0), 1);
                $pRatingCount = (int)($p['rating_count'] ?? 0);
                if ($pRating > 0): ?>
                <div class="pub-stars" style="font-size:0.85rem;margin:3px 0;" title="<?= $pRating ?>/5">
                    <?php for ($s = 1; $s <= 5; $s++):
                        if ($s <= $pRating) echo '<span class="pub-star--full">★</span>';
                        elseif ($s - 0.5 <= $pRating) echo '<span class="pub-star--half">★</span>';
                        else echo '<span class="pub-star--empty">☆</span>';
                    endfor; ?>
                    <?php if ($pRatingCount > 0): ?>
                        <span class="pub-rating-count">(<?= $pRatingCount ?>)</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['sku'])): ?>
                    <small style="color:var(--pub-muted);font-size:0.76rem;"><?= e($p['sku']) ?></small>
                <?php endif; ?>
                <?php if (isset($p['price'])): ?>
                    <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <small><?= e($p['currency_code'] ?? t('common.currency')) ?></small></p>
                <?php endif; ?>
                <?php
                $pStock = $p['stock_status'] ?? '';
                $pQty   = (int)($p['stock_quantity'] ?? 0);
                if ($pStock === 'out_of_stock'):
                ?>
                    <span class="pub-stock-badge pub-stock-badge--out"><?= e(t('products.out_of_stock')) ?></span>
                <?php elseif ($pQty > 0 && $pQty <= 10): ?>
                    <span class="pub-stock-badge pub-stock-badge--low"><?= e(t('products.low_stock', ['count' => $pQty, 'default' => 'Only '.$pQty.' left'])) ?></span>
                <?php elseif ($pStock === 'in_stock' || $pQty > 0): ?>
                    <span class="pub-stock-badge pub-stock-badge--in"><?= e(t('products.in_stock')) ?></span>
                <?php endif; ?>
            </div>
            </a>
            <?php if ($showCart): ?>
            <!-- Add to Cart button -->
            <div class="pub-card-cart-bar">
                <button class="pub-btn pub-btn--primary pub-btn--sm"
                        type="button"
                        title="<?= e(t('cart.add', 'Add to Cart')) ?>"
                        data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                        data-product-name="<?= e($p['name'] ?? '') ?>"
                        data-product-price="<?= (float)($p['price'] ?? 0) ?>"
                        data-product-image="<?= e($pAllImgs[0] ?? ($p['image_url'] ?? '')) ?>"
                        data-product-sku="<?= e($p['sku'] ?? '') ?>"
                        data-currency="<?= e($p['currency_code'] ?? '') ?>"
                        data-added-text="✅ <?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Product pagination -->
    <?php
    $totalPg = (int)($productMeta['total_pages'] ?? 1);
    if ($totalPg > 1):
        $pg_url = fn(int $pg) => '?id=' . $entityId . ($selectedCat ? '&cat=' . $selectedCat : '') . '&page=' . $pg . '#tabProducts';
    ?>
    <nav class="pub-pagination" style="margin-top:24px;">
        <a href="<?= $pg_url(max(1,$productPage-1)) ?>" class="pub-page-btn <?= $productPage<=1?'disabled':'' ?>">
            <?= e(t('pagination.prev')) ?>
        </a>
        <?php for ($i=max(1,$productPage-2); $i<=min($totalPg,$productPage+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$productPage?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$productPage+1)) ?>" class="pub-page-btn <?= $productPage>=$totalPg?'disabled':'' ?>">
            <?= e(t('pagination.next')) ?>
        </a>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <div class="pub-empty" style="margin-top:40px;">
        <div class="pub-empty-icon">🛍️</div>
        <p class="pub-empty-msg"><?= e(t('entity.no_products')) ?></p>
    </div>
    <?php endif; ?>
</div>