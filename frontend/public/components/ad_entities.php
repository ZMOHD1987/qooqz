<?php
declare(strict_types=1);
/**
 * Component: ad_entities
 * Renders entity/vendor/brand cards with avatars and verification badges.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}

$_cardEntity = $_cardStyles['entities']['inline'] ?? '';
$_clsEntity  = $_cardStyles['entities']['class'] ?? '';
?>
<div class="pub-grid-md">
    <?php foreach ($sectionData as $ent):
        $entCardStyle = pub_entity_card_style($ent, $_cardEntity);
    ?>
    <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
       class="pub-entity-card<?= $_clsEntity ? ' ' . $_clsEntity : '' ?>" style="text-decoration:none;<?= e($entCardStyle) ?>">
        <div class="pub-entity-avatar">
            <?php if (!empty($ent['logo_url'])): ?>
                <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;">🏢</span>
            <?php else: ?>
                🏢
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
