<?php
declare(strict_types=1);
/**
 * Public API sub-route: recent
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'recent') {
    $sub = $segments[1] ?? '';

    if ($sub === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $recentPid = (int)($_POST['product_id'] ?? 0);
        if (!$recentPid) { ResponseFormatter::error('product_id required', 422); exit; }
        $recentUid = $userId ?? null;
        $recentSid = session_id() ?: null;
        try {
            // upsert: update viewed_at if already exists, otherwise insert
            $pdo->prepare(
                'INSERT INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE viewed_at = NOW()'
            )->execute([$recentUid, $recentSid, $recentPid]);
        } catch (Throwable $_) {
            // table may not have unique constraint — try insert ignore
            try {
                $pdo->prepare(
                    'INSERT IGNORE INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                     VALUES (?, ?, ?, NOW())'
                )->execute([$recentUid, $recentSid, $recentPid]);
            } catch (Throwable $__) { /* non-fatal */ }
        }
        ResponseFormatter::success(['ok' => true]);
        exit;
    }

    // GET /api/public/recent — list recently viewed for current user/session
    $recentUid = $userId ?? null;
    $recentSid = session_id() ?: null;
    $recentCond = $recentUid ? 'rvp.user_id = ?' : 'rvp.session_id = ?';
    $recentParam = $recentUid ?? $recentSid;
    try {
        $rows = $pdoList(
            "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.slug,
                    p.stock_status, p.stock_quantity,
                    (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    rvp.viewed_at
               FROM recently_viewed_products rvp
               JOIN products p ON p.id = rvp.product_id
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              WHERE $recentCond
              ORDER BY rvp.viewed_at DESC LIMIT 20",
            [$lang, $recentParam]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    } catch (Throwable $ex) {
        ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: Product Comparisons
 * GET  /api/public/compare         — list current comparison
 * POST /api/public/compare/add     — add product
 * POST /api/public/compare/remove  — remove product
 * POST /api/public/compare/clear   — clear all
 * ----------------------------------------------------- */
