<?php
declare(strict_types=1);
/**
 * Component: ad_slider
 * Renders a banner/slider section with images, titles, subtitles, and CTA buttons.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

if (empty($sectionData)) {
    return;
}
?>
<div class="pub-banner-slider pub-hero-banner-slider">
    <?php foreach ($sectionData as $_bi => $_b):
        $bannerBgColor       = _pub_safe_color($_b['background_color'] ?? '');
        $bannerTextColor     = _pub_safe_color($_b['text_color'] ?? '');
        $bannerImageUrl      = $_b['image_url'] ?? '';
        $bannerMobileImgUrl  = $_b['mobile_image_url'] ?? '';
        $_bStyle = '';
        if ($bannerBgColor)  $_bStyle .= 'background-color:' . e($bannerBgColor) . ';';
        if ($bannerTextColor) $_bStyle .= 'color:' . e($bannerTextColor) . ';';
    ?>
    <div class="pub-banner-slide<?= $_bi === 0 ? ' active' : '' ?>"<?= $_bStyle ? ' style="' . $_bStyle . '"' : '' ?>>
        <?php if ($bannerImageUrl || $bannerMobileImgUrl): ?>
        <a href="<?= e($_b['link_url'] ?? '#') ?>" tabindex="<?= $_bi === 0 ? '0' : '-1' ?>">
            <picture>
                <?php if ($bannerMobileImgUrl): ?>
                <source media="(max-width:600px)" srcset="<?= e(pub_img($bannerMobileImgUrl)) ?>">
                <?php endif; ?>
                <img src="<?= e(pub_img($bannerImageUrl ?: $bannerMobileImgUrl)) ?>"
                     alt="<?= e($_b['title'] ?? '') ?>"
                     class="pub-banner-img"
                     loading="<?= $_bi === 0 ? 'eager' : 'lazy' ?>">
            </picture>
        </a>
        <?php endif; ?>
        <?php if (!empty($_b['title']) || !empty($_b['subtitle'])): ?>
        <div class="pub-banner-caption"<?= $bannerTextColor ? ' style="color:' . e($bannerTextColor) . ';"' : '' ?>>
            <?php if (!empty($_b['title'])): ?>
            <h3><?= e($_b['title']) ?></h3>
            <?php endif; ?>
            <?php if (!empty($_b['subtitle'])): ?>
            <p><?= e($_b['subtitle']) ?></p>
            <?php endif; ?>
            <?php if (!empty($_b['link_url']) && !empty($_b['link_text'])): ?>
            <a href="<?= e($_b['link_url']) ?>" class="pub-btn pub-btn--primary pub-btn--sm"
               <?php $_btnStyle = _pub_safe_color($_b['button_style'] ?? ''); if ($_btnStyle): ?>style="background:<?= e($_btnStyle) ?>;"<?php endif; ?>>
                <?= e($_b['link_text']) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
