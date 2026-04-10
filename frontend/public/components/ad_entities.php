<?php
declare(strict_types=1);
/**
 * Component: ad_entities
 * Renders entity/vendor/brand cards with avatars and verification badges.
 */

if (empty($sectionData)) {
    return;
}

$_cardEntity = $_cardStyles['entities']['inline'] ?? '';
$_clsEntity = $_cardStyles['entities']['class'] ?? '';
?>
<div class="pub-grid-md">
    <?php foreach ($sectionData as $ent):
        $entCardStyle = pub_entity_card_style($ent, $_cardEntity) ?? '';
    ?>
    <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
       class="pub-entity-card<?= $_clsEntity ? ' ' . $_clsEntity : '' ?>" 
       data-track-type="entity"
       data-track-id="<?= (int)($ent['id'] ?? 0) ?>"
       style="text-decoration:none;<?= e($entCardStyle) ?>">
        <div class="pub-entity-avatar">
            <?php if (!empty($ent['logo_url'])): ?>
                <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($ent['store_name'] ?? $ent['name'] ?? '') ?>" 
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;" aria-hidden="true">🏢</span>
            <?php else: ?>
                <span aria-hidden="true">🏢</span>
            <?php endif; ?>
        </div>
        <div class="pub-entity-info">
            <p class="pub-entity-name"><?= e($ent['store_name'] ?? $ent['name'] ?? '') ?></p>
            <?php if (!empty($ent['vendor_type'])): ?>
                <p class="pub-entity-desc"><?= e($ent['vendor_type']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ent['is_verified'])): ?>
                <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>