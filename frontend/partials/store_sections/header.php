<?php
/**
 * frontend/partials/store_sections/header.php
 * Store Header Section — Cover image, logo, name, rating, verification, open/closed
 *
 * Expected variables:
 *   $entity           — Entity data array
 *   $entityRatingAvg  — Average rating (float|null)
 *   $entityRatingTotal — Total rating count (int)
 *   $entityIsOpen     — Open status (true/false/null)
 *   $entityOpenLabel  — Open/closed label text
 *   $sectionSettings  — Section JSON settings (optional)
 */

$showCover    = ($sectionSettings['show_cover']    ?? true);
$showRating   = ($sectionSettings['show_rating']   ?? true);
$showVerified = ($sectionSettings['show_verified'] ?? true);
$showStatus   = ($sectionSettings['show_status']   ?? true);
?>

<!-- Store Header: Banner -->
<div class="pub-entity-banner">
    <?php if ($showCover && !empty($entity['cover_url'])): ?>
        <img src="<?= e(pub_img($entity['cover_url'], 'entity_cover')) ?>"
             alt="<?= e($entity['store_name']) ?>"
             class="pub-entity-banner-img"
             loading="eager"
             onerror="this.style.display='none'">
    <?php else: ?>
        <div class="pub-entity-banner-placeholder"></div>
    <?php endif; ?>
</div>

<!-- Store Header: Profile Card -->
<div class="pub-container">
    <div class="pub-entity-profile-header">

        <!-- Logo -->
        <div class="pub-entity-profile-logo">
            <?php if (!empty($entity['logo_url'])): ?>
                <img src="<?= e(pub_img($entity['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($entity['store_name']) ?>"
                     loading="eager"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;align-items:center;justify-content:center;font-size:2rem;">🏢</span>
            <?php else: ?>
                <span style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🏢</span>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pub-entity-profile-info">
            <div style="display:flex;flex-direction:column;gap:8px;">
                <h1 class="pub-entity-profile-name" style="margin-bottom:0;"><?= e($entity['store_name'] ?? '') ?></h1>
                
                <?php if ($showRating && $entityRatingAvg !== null): ?>
                    <div style="display:inline-flex;">
                        <span class="pub-entity-rating-avg" title="<?= (int)$entityRatingTotal ?> reviews">
                            ⭐ <?= number_format((float)$entityRatingAvg, 1) ?>
                            <span style="font-size:0.78rem;opacity:0.7;">(<?= $entityRatingTotal ?>)</span>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($showVerified && !empty($entity['is_verified'])): ?>
                    <div style="display:inline-flex;">
                        <span class="pub-entity-verified" style="display:inline-flex;align-items:center;gap:4px;color:var(--pub-success,#065f46);background:color-mix(in srgb, var(--pub-success, #22c55e) 15%, transparent);padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:700;">✅ <?= e(t('entities.verified')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($showStatus && $entityIsOpen !== null): ?>
                    <div style="display:inline-flex;">
                        <span class="pub-open-badge <?= $entityIsOpen ? 'pub-open-badge--open' : 'pub-open-badge--closed' ?>">
                            <?= $entityIsOpen ? '🟢' : '🔴' ?> <?= e($entityOpenLabel) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($entity['type_name'] ?? $entity['vendor_type'])): ?>
                    <div style="display:inline-flex;">
                        <span class="pub-tag" style="font-size:0.85rem;background:var(--pub-surface);border:1px solid var(--pub-border);padding:3px 10px;border-radius:20px;">
                            <?= e($entity['type_icon'] ?? '🏢') ?> <?= e($entity['type_name'] ?? $entity['vendor_type']) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($entity['description'])): ?>
                <p class="pub-entity-profile-desc"><?= e($entity['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
