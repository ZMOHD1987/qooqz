<?php
declare(strict_types=1);
/**
 * Component: ad_deals
 * Renders deal/discount cards with badges, descriptions, and expiry dates.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}

$_cardDeal = $_cardStyles['promo']['inline'] ?? '';
$_clsDeal  = $_cardStyles['promo']['class'] ?? '';
?>
<div class="pub-grid-lg">
    <?php foreach ($sectionData as $deal): ?>
    <div class="pub-deal-card<?= $_clsDeal ? ' ' . $_clsDeal : '' ?>"<?= $_cardDeal ? ' style="' . e($_cardDeal) . '"' : '' ?>>
        <?php if (!empty($deal['code'])): ?>
            <span class="pub-deal-badge"><?= e($deal['code']) ?></span>
        <?php endif; ?>
        <p class="pub-deal-title"><?= e($deal['title'] ?? $deal['code'] ?? '') ?></p>
        <?php if (!empty($deal['description'])): ?>
            <p class="pub-deal-desc"><?= e($deal['description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($deal['ends_at'])): ?>
            <p class="pub-deal-expires">⏰ <?= e(t('deals.expires')) ?>: <?= e(substr($deal['ends_at'], 0, 10)) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
