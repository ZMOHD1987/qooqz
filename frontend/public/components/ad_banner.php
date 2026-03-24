<?php
declare(strict_types=1);
/**
 * Component: ad_banner
 * Renders inline banner(s) — single banner or small banner grid.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}
?>
<div class="pub-banner-slider">
    <?php foreach ($sectionData as $_bi => $_b): ?>
    <div class="pub-banner-slide<?= $_bi === 0 ? ' active' : '' ?>"<?php if (!empty($_b['background_color'])): ?> style="background:<?= e(_pub_safe_color($_b['background_color'])) ?>"<?php endif; ?>>
        <?php if (!empty($_b['image_url'])): ?>
        <a href="<?= e($_b['link_url'] ?? '#') ?>">
            <img src="<?= e(pub_img($_b['image_url'])) ?>"
                 alt="<?= e($_b['title'] ?? '') ?>" class="pub-banner-img" loading="lazy">
        </a>
        <?php endif; ?>
        <?php if (!empty($_b['title'])): ?>
        <div class="pub-banner-caption">
            <h3><?= e($_b['title']) ?></h3>
            <?php if (!empty($_b['subtitle'])): ?><p><?= e($_b['subtitle']) ?></p><?php endif; ?>
            <?php if (!empty($_b['link_url']) && !empty($_b['link_text'])): ?>
            <a href="<?= e($_b['link_url']) ?>" class="pub-btn pub-btn--primary pub-btn--sm"><?= e($_b['link_text']) ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
