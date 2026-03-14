<?php
declare(strict_types=1);
/**
 * Public API sub-route: brands
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'brands') {
    if (!$tenantId) {
        ResponseFormatter::success(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => $per, 'total_pages' => 0]]);
        exit;
    }
    $bWhere  = 'WHERE b.tenant_id = ? AND b.is_active = 1';
    $bParams = [$tenantId];
    if (!empty($_GET['is_featured'])) { $bWhere .= ' AND b.is_featured = 1'; }

    $bCount = $pdoCount("SELECT COUNT(*) FROM brands b $bWhere", $bParams);
    $rows   = $pdoList(
        "SELECT b.id, b.slug, b.logo_url, b.website_url, b.is_featured,
                COALESCE(bt.name, b.slug) AS name,
                COALESCE(bt.description, '') AS description
           FROM brands b
      LEFT JOIN brand_translations bt ON bt.brand_id = b.id AND bt.language_code = ?
         $bWhere
         ORDER BY b.is_featured DESC, b.sort_order ASC, b.id ASC
         LIMIT $per OFFSET $offset",
        array_merge([$lang], $bParams)
    );
    ResponseFormatter::success([
        'data' => $rows,
        'meta' => [
            'total'       => $bCount,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => (int)ceil($bCount / $per),
        ],
    ]);
    exit;
}

/* -------------------------------------------------------
 * /api/public/notifications — Public notifications for tenant
 * Delegated to a dedicated route file for maintainability.
 * Sub-paths: (none)=list, types=type-list, mark-seen=POST
 * ----------------------------------------------------- */
