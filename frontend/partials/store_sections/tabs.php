<?php
/**
 * frontend/partials/store_sections/tabs.php
 * Store Tabs Navigation Section
 *
 * Expected variables:
 *   $discounts           — Discounts array
 *   $entityShowReviews   — Whether reviews tab is visible
 *   $entityRatingTotal   — Total rating count
 *   $sectionSettings     — Section JSON settings (optional)
 *   $activeSections      — Array of active section types for this page
 */

// Determine which tabs to show based on settings and active sections
$configuredTabs = $sectionSettings['tabs'] ?? ['products', 'info', 'hours', 'location', 'offers', 'reviews'];
$firstTab = $configuredTabs[0] ?? 'products';
// Map tab keys to their panel IDs
$tabPanelMap = [
    'products' => 'tabProducts', 'info' => 'tabInfo', 'hours' => 'tabHours',
    'location' => 'tabMap', 'offers' => 'tabDiscounts', 'reviews' => 'tabRatings',
];
$isFirstTab = true;
?>

<div class="pub-container">
    <div class="pub-tabs" style="margin-top:24px;" role="tablist">
        <?php
        $tabDefs = [
            'products' => ['data' => 'products', 'panel' => 'tabProducts', 'icon' => '🛍️', 'label' => t('entity.products_tab'), 'show' => true, 'count' => null],
            'info'     => ['data' => 'info',     'panel' => 'tabInfo',     'icon' => 'ℹ️',  'label' => t('entity.info_tab'),     'show' => true, 'count' => null],
            'hours'    => ['data' => 'hours',    'panel' => 'tabHours',    'icon' => '🕐', 'label' => t('entity.hours_tab'),    'show' => true, 'count' => null],
            'location' => ['data' => 'map',      'panel' => 'tabMap',      'icon' => '🗺️',  'label' => t('entity.location_tab'), 'show' => true, 'count' => null],
            'offers'   => ['data' => 'discounts','panel' => 'tabDiscounts','icon' => '🏷️', 'label' => t('entity.discounts_tab'),'show' => !empty($discounts), 'count' => !empty($discounts) ? count($discounts) : null],
            'reviews'  => ['data' => 'ratings',  'panel' => 'tabRatings',  'icon' => '⭐', 'label' => t('entity.ratings_tab'),  'show' => $entityShowReviews, 'count' => $entityRatingTotal > 0 ? $entityRatingTotal : null],
        ];
        $isFirstRendered = true;
        foreach ($configuredTabs as $tabKey):
            if (!isset($tabDefs[$tabKey]) || !$tabDefs[$tabKey]['show']) continue;
            if (!in_array($tabKey, $configuredTabs)) continue;
            $td = $tabDefs[$tabKey];
            $active = $isFirstRendered;
            $isFirstRendered = false;
        ?>
        <button class="pub-tab<?= $active ? ' active' : '' ?>" data-tab="<?= e($td['data']) ?>" role="tab"
                aria-selected="<?= $active ? 'true' : 'false' ?>" aria-controls="<?= e($td['panel']) ?>">
            <?= $td['icon'] ?> <?= e($td['label']) ?>
            <?php if ($td['count'] !== null): ?><span class="pub-tab-count"><?= $td['count'] ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
