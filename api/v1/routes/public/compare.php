<?php
declare(strict_types=1);
/**
 * Public API sub-route: compare
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'compare') {
    $cmpUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$cmpUserId) { ResponseFormatter::error('Login required', 401); exit; }
    $cmpSub = $segments[1] ?? '';

    // Helper: get or create the user's active comparison row
    $getCmpId = function () use ($pdo, $pdoOne, $cmpUserId): int {
        $row = $pdoOne('SELECT id FROM product_comparisons WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$cmpUserId]);
        if ($row) return (int)$row['id'];
        $pdo->prepare('INSERT INTO product_comparisons (user_id, created_at) VALUES (?, NOW())')->execute([$cmpUserId]);
        return (int)$pdo->lastInsertId();
    };

    if ($cmpSub === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cmpPid = (int)($_POST['product_id'] ?? 0);
        if (!$cmpPid) { ResponseFormatter::error('product_id required', 422); exit; }
        try {
            $cmpId = $getCmpId();
            // Max 4 products in comparison
            $cmpCount = (int)($pdoOne('SELECT COUNT(*) AS c FROM product_comparison_items WHERE comparison_id = ?', [$cmpId])['c'] ?? 0);
            if ($cmpCount >= 4) { ResponseFormatter::error('Max 4 products in comparison', 400); exit; }
            $pdo->prepare('INSERT IGNORE INTO product_comparison_items (comparison_id, product_id, added_at) VALUES (?, ?, NOW())')
                ->execute([$cmpId, $cmpPid]);
            ResponseFormatter::success(['ok' => true, 'comparison_id' => $cmpId]);
        } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
        exit;
    }

    if ($cmpSub === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cmpPid = (int)($_POST['product_id'] ?? 0);
        try {
            $row = $pdoOne('SELECT id FROM product_comparisons WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$cmpUserId]);
            if ($row) {
                $pdo->prepare('DELETE FROM product_comparison_items WHERE comparison_id = ? AND product_id = ?')
                    ->execute([(int)$row['id'], $cmpPid]);
            }
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
        exit;
    }

    if ($cmpSub === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $row = $pdoOne('SELECT id FROM product_comparisons WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$cmpUserId]);
            if ($row) {
                $pdo->prepare('DELETE FROM product_comparison_items WHERE comparison_id = ?')->execute([(int)$row['id']]);
            }
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
        exit;
    }

    // GET — list products in comparison with full details
    try {
        $cmpRow = $pdoOne('SELECT id FROM product_comparisons WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$cmpUserId]);
        $rows = [];
        if ($cmpRow) {
            $rows = $pdoList(
                "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.slug, p.sku,
                        p.stock_status, p.stock_quantity, p.rating_average, p.rating_count,
                        p.is_featured, p.is_new, p.is_bestseller,
                        pt.description, pt.specifications,
                        NULL AS brand_name,
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                        (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                        pci.added_at
                   FROM product_comparison_items pci
                   JOIN products p ON p.id = pci.product_id
              LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                  WHERE pci.comparison_id = ?
                  ORDER BY pci.added_at ASC",
                [$lang, (int)$cmpRow['id']]
            );
        }
        ResponseFormatter::success(['ok' => true, 'products' => $rows]);
    } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
    exit;
}

/* -------------------------------------------------------
 * Route: Product Bundles
 * GET /api/public/bundles          — list bundles (entity_id filter optional)
 * GET /api/public/bundles/{id}     — single bundle with items
 * ----------------------------------------------------- */
