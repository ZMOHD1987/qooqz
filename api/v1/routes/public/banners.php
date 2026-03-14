<?php
declare(strict_types=1);
/**
 * Public API sub-route: banners
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'banners') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $banWhere  = 'WHERE b.tenant_id = ? AND b.is_active = 1
                    AND (b.start_date IS NULL OR b.start_date <= NOW())
                    AND (b.end_date   IS NULL OR b.end_date   >= NOW())';
    $banParams = [$tenantId];
    if (!empty($_GET['position'])) { $banWhere .= ' AND b.position = ?'; $banParams[] = $_GET['position']; }
    $rows = $pdoList(
        "SELECT b.id, b.title, b.subtitle, b.image_url, b.mobile_image_url,
                b.link_url, b.link_text, b.background_color, b.text_color, b.sort_order, b.position
           FROM banners b $banWhere ORDER BY b.sort_order ASC, b.id ASC LIMIT 20",
        $banParams
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Discounts (public active discounts for tenant)
 * GET /api/public/discounts?tenant_id=X&lang=Y
 * ----------------------------------------------------- */
