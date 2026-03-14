<?php
declare(strict_types=1);
/**
 * Public API sub-route: bundles
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'bundles') {
    $bundleId = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null;

    if ($bundleId) {
        try {
            $bundle = $pdoOne(
                "SELECT b.id, b.entity_id, b.bundle_name, b.bundle_name_ar,
                        b.description, b.description_ar,
                        b.bundle_image, b.original_total_price, b.bundle_price,
                        b.discount_amount, b.discount_percentage, b.stock_quantity,
                        b.is_active, b.start_date, b.end_date, b.sold_count
                   FROM product_bundles b
                  WHERE b.id = ? AND b.is_active = 1",
                [$bundleId]
            );
            if (!$bundle) { ResponseFormatter::notFound('Bundle not found'); exit; }
            $items = $pdoList(
                "SELECT bi.id, bi.product_id, bi.quantity, bi.product_price,
                        COALESCE(pt.name, p.slug) AS product_name,
                        (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url
                   FROM product_bundle_items bi
                   JOIN products p ON p.id = bi.product_id
              LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                  WHERE bi.bundle_id = ?",
                [$lang, $bundleId]
            );
            $bundle['name'] = $lang === 'ar' ? ($bundle['bundle_name_ar'] ?? $bundle['bundle_name']) : $bundle['bundle_name'];
            $bundle['description_text'] = $lang === 'ar' ? ($bundle['description_ar'] ?? $bundle['description']) : $bundle['description'];
            $bundle['items'] = $items;
            ResponseFormatter::success(['ok' => true, 'bundle' => $bundle]);
        } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
        exit;
    }

    // List bundles
    $bundleWhere = 'WHERE b.is_active = 1';
    $bundleParams = [];
    if ($tenantId) {
        $bundleWhere .= ' AND EXISTS (SELECT 1 FROM entities e WHERE e.id = b.entity_id AND e.tenant_id = ?)';
        $bundleParams[] = $tenantId;
    }
    if (!empty($_GET['entity_id'])) {
        $bundleWhere .= ' AND b.entity_id = ?';
        $bundleParams[] = (int)$_GET['entity_id'];
    }
    try {
        $rows = $pdoList(
            "SELECT b.id, b.entity_id,
                    CASE WHEN ? = 'ar' THEN COALESCE(b.bundle_name_ar, b.bundle_name) ELSE b.bundle_name END AS name,
                    b.bundle_image, b.original_total_price, b.bundle_price,
                    b.discount_amount, b.discount_percentage, b.stock_quantity,
                    b.start_date, b.end_date, b.sold_count
               FROM product_bundles b
               $bundleWhere
              ORDER BY b.discount_percentage DESC, b.id DESC LIMIT ? OFFSET ?",
            array_merge([$lang], $bundleParams, [$per, $offset])
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    } catch (Throwable $ex) { ResponseFormatter::error($ex->getMessage(), 500); }
    exit;
}

// ==============================================================
// POST /api/public/products/{id}/reviews — submit product review
// POST /api/public/products/{id}/questions — submit question
// ==============================================================
