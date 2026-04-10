<?php
/**
 * frontend/partials/store_sections/offers.php
 * Store Offers/Discounts Section — Discount cards with codes, badges, terms
 *
 * Expected variables:
 *   $discounts                  — Discounts array
 *   $_entityDiscountCardStyle   — Card inline style
 *   $_entityDiscountCardClass   — Card CSS class
 *   $sectionSettings            — Section JSON settings
 */
?>

<div class="pub-entity-section-content" id="sectionOffers">
    <?php if (!empty($discounts)): ?>
    <div style="display:grid;gap:14px;">
        <?php foreach ($discounts as $d): ?>
        <div class="pub-discount-card<?= $_entityDiscountCardClass ? ' ' . $_entityDiscountCardClass : '' ?>"<?= $_entityDiscountCardStyle ? ' style="' . e($_entityDiscountCardStyle) . '"' : '' ?>>
            <?php if (!empty($d['marketing_badge'])): ?>
                <span class="pub-discount-badge-top"><?= e($d['marketing_badge']) ?></span>
            <?php endif; ?>
            <div class="pub-discount-inner">
                <div class="pub-discount-icon">🏷️</div>
                <div class="pub-discount-body">
                    <p class="pub-discount-title"><?= e($d['title'] ?? $d['code'] ?? '') ?></p>
                    <?php if (!empty($d['description'])): ?>
                        <p class="pub-discount-desc"><?= e($d['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($d['code'])): ?>
                        <div class="pub-discount-code-row">
                            <span class="pub-discount-code"><?= e($d['code']) ?></span>
                            <button class="pub-btn pub-btn--ghost pub-btn--sm"
                                    onclick="pubCopyDiscount('<?= e(addslashes($d['code'])) ?>', this)">
                                📋 <?= e(t('discounts.copy_code')) ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($d['ends_at'])): ?>
                        <p class="pub-discount-expires">
                            ⏰ <?= e(t('discounts.expires')) ?>: <?= e(substr($d['ends_at'], 0, 10)) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($d['terms_conditions'])): ?>
                <details class="pub-discount-terms">
                    <summary><?= e(t('discounts.terms')) ?></summary>
                    <p><?= e($d['terms_conditions']) ?></p>
                </details>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="pub-empty" style="margin-top:40px;">
        <div class="pub-empty-icon">🏷️</div>
        <p class="pub-empty-msg"><?= e(t('discounts.none')) ?></p>
    </div>
    <?php endif; ?>
</div>