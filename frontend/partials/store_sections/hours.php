<?php
/**
 * frontend/partials/store_sections/hours.php
 * Store Working Hours Section — Daily schedule with open/closed logic
 *
 * Expected variables:
 *   $entity    — Entity data array (includes working_hours)
 *   $dayNames  — Array of day name translations (0=Sun … 6=Sat)
 *   $sectionSettings — Section JSON settings
 */
?>

<div class="pub-entity-section-content" id="sectionHours">
    <?php if (!empty($entity['working_hours'])): ?>
    <div class="pub-info-card">
        <div class="pub-hours-table">
            <?php foreach ($entity['working_hours'] as $h): ?>
            <div class="pub-hours-row <?= empty($h['is_open']) ? 'pub-hours-row--closed' : '' ?>">
                <span class="pub-hours-day"><?= e($dayNames[(int)($h['day_of_week'] ?? 0)] ?? $h['day_of_week']) ?></span>
                <span class="pub-hours-time">
                    <?php if (empty($h['is_open'])): ?>
                        <span style="color:var(--pub-muted);"><?= e(t('entity.closed')) ?></span>
                    <?php else: ?>
                        <?= e($h['open_time'] ?? '') ?> — <?= e($h['close_time'] ?? '') ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>