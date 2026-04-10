<?php
declare(strict_types=1);
/**
 * Component: ad_auctions
 * Renders active auction cards with countdown timers and bid info.
 */

if (empty($sectionData)) {
    return;
}

$_cardAuction = $_cardStyles['auction']['inline'] ?? '';
$_clsAuction = $_cardStyles['auction']['class'] ?? '';
$_imgAuction = $_cardStyles['auction']['img'] ?? '';
?>
<div class="pub-grid">
    <?php foreach ($sectionData as $auction):
        $aId = (int)($auction['id'] ?? 0);
        $aTitle = trim($auction['title'] ?? 'مزاد');
        $aImg = pub_img($auction['image_url'] ?? null, 'auction');
        $aPrice = $auction['current_price'] ?? $auction['starting_price'] ?? null;
        $aCur = $auction['currency_code'] ?? '';
        $aEndDate = $auction['end_date'] ?? '';
        $aBids = (int)($auction['total_bids'] ?? 0);
        $aHref = '/frontend/public/auction.php?id=' . $aId;
        $aFeatured = !empty($auction['is_featured']);
    ?>
    <a href="<?= e($aHref) ?>"
       class="pub-product-card<?= $_clsAuction ? ' ' . $_clsAuction : '' ?>"
       data-track-type="auction"
       data-track-id="<?= $aId ?>"
       style="text-decoration:none;<?= e($_cardAuction) ?>">
        <div class="pub-cat-img-wrap" style="<?= e($_imgAuction) ?>">
            <?php if ($aImg): ?>
                <img src="<?= e($aImg) ?>"
                     alt="<?= e($aTitle) ?>"
                     class="pub-cat-img"
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🔨</span>
            <?php else: ?>
                <span class="pub-img-placeholder" aria-hidden="true">🔨</span>
            <?php endif; ?>
        </div>
        <div class="pub-product-card-body">
            <?php if ($aFeatured): ?>
                <span class="pub-product-badge"><?= e(t('auctions.featured')) ?></span>
            <?php endif; ?>
            <p class="pub-product-name"><?= e($aTitle) ?></p>
            <?php if ($aPrice !== null): ?>
                <p class="pub-product-price">
                    <?= number_format((float)$aPrice, 2) ?>
                    <small><?= e($aCur) ?></small>
                </p>
            <?php endif; ?>
            <?php if ($aBids > 0): ?>
                <p class="pub-entity-desc" style="font-size:.8rem;">
                    🔨 <?= $aBids ?> <?= e(t('auctions.bids', [])) ?>
                </p>
            <?php endif; ?>
            <?php if ($aEndDate !== ''):
                $aEndTs = strtotime($aEndDate);
            ?>
                <p class="pub-entity-desc" style="font-size:.8rem;" data-auction-end="<?= e($aEndDate) ?>">
                    ⏳ <?= e(t('auctions.ends')) ?>: <?= e($aEndTs !== false ? date('Y-m-d H:i', $aEndTs) : '') ?>
                </p>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>