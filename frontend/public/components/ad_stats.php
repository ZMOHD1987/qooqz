<?php
declare(strict_types=1);
/**
 * Component: ad_stats
 * Renders ad statistics from API.
 */

$qs = 'lang=' . urlencode($lang) . '&per=10&page=1&tenant_id=' . $tenantId;

// استدعاء API الخاص بـ ad_stats
$rStats = pub_fetch($apiBase . 'public/ad-stats?' . $qs);

// البيانات
$stats = $rStats['data']['items'] ?? [];
?>

<div class="pub-stats-row">

    <?php foreach ($stats as $row): ?>
        <div class="pub-stat-item" style="min-width:220px">
            
            <div><strong>Ad ID:</strong> <?= (int)$row['ad_id'] ?></div>
            
            <div>
                <strong>Views:</strong> <?= number_format((int)$row['views']) ?>
            </div>

            <div>
                <strong>Clicks:</strong> <?= number_format((int)$row['clicks']) ?>
            </div>

            <div>
                <strong>CTR:</strong>
                <?php
                $views = (int)$row['views'];
                $clicks = (int)$row['clicks'];
                $ctr = $views > 0 ? ($clicks / $views) * 100 : 0;
                ?>
                <?= number_format($ctr, 2) ?>%
            </div>

            <div>
                <strong>Event:</strong> <?= e($row['event_type']) ?>
            </div>

            <div>
                <strong>Date:</strong> <?= e($row['date']) ?>
            </div>

        </div>
    <?php endforeach; ?>

</div>