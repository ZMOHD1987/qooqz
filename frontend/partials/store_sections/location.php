<?php
/**
 * frontend/partials/store_sections/location.php
 * Store Location Section — Map with coordinates, addresses
 *
 * Expected variables:
 *   $entity          — Entity data array (includes addresses)
 *   $sectionSettings — Section JSON settings
 */

$showOsm    = ($sectionSettings['show_osm']    ?? true);
$showGoogle = ($sectionSettings['show_google'] ?? true);
?>

<div class="pub-entity-section-content" id="sectionLocation">
    <?php if (!empty($entity['addresses'])): ?>
    <div style="display:grid;gap:16px;">
        <?php foreach ($entity['addresses'] as $addr): ?>
        <div class="pub-info-card">
            <h3 class="pub-info-card-title">
                📍 <?= e($addr['label'] ?? '') ?>
                <?php if (!empty($addr['is_primary'])): ?>
                    <span style="font-size:0.75rem;background:var(--pub-primary);color:#fff;padding:2px 8px;border-radius:20px;margin-inline-start:6px;">★</span>
                <?php endif; ?>
            </h3>
            <p style="padding:8px 16px;color:var(--pub-text);margin:0;">
                <?= e($addr['address_line1'] ?? '') ?>
                <?php if (!empty($addr['address_line2'])): ?>, <?= e($addr['address_line2']) ?><?php endif; ?>
            </p>
            <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
            <div style="padding:0 16px 16px; display:flex; flex-wrap:wrap; gap:10px;">
                <?php if ($showOsm): ?>
                <a href="https://www.openstreetmap.org/?mlat=<?= e($addr['latitude']) ?>&mlon=<?= e($addr['longitude']) ?>#map=16/<?= e($addr['latitude']) ?>/<?= e($addr['longitude']) ?>"
                   target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;flex:1;justify-content:center;white-space:nowrap;">
                    🗺️ <?= e(t('entity.view_on_map')) ?>
                </a>
                <?php endif; ?>
                <?php if ($showGoogle): ?>
                <a href="https://maps.google.com/?q=<?= e($addr['latitude']) ?>,<?= e($addr['longitude']) ?>"
                   target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;flex:1;justify-content:center;white-space:nowrap;">
                    📍 Google Maps
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="pub-empty" style="margin-top:40px;">
        <div class="pub-empty-icon">📍</div>
        <p class="pub-empty-msg"><?= e(t('entity.no_addresses')) ?></p>
    </div>
    <?php endif; ?>
</div>