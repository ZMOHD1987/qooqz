<?php
/**
 * //htdocs/frontend/partials/breadcrumbs.php
 * يعتمد على $breadcrumbs الممرر من الصفحة
 */

$breadcrumbs = $breadcrumbs ?? [];
if (empty($breadcrumbs)) return;
?>

<nav class="breadcrumbs">
    <ul>
        <li><a href="/frontend/index.php">الرئيسية</a></li>

        <?php foreach ($breadcrumbs as $item): ?>
            <?php if (!empty($item['url'])): ?>
                <li><a href="<?= htmlspecialchars($item['url']) ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a></li>
            <?php else: ?>
                <li class="active"><?= htmlspecialchars($item['label']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</nav>
