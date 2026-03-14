<?php
declare(strict_types=1);
/**
 * Public API sub-route: discounts
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'discounts') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $rows = $pdoList(
        "SELECT d.id, d.code, d.type, d.auto_apply, d.currency_code, d.status,
                d.starts_at, d.ends_at, d.max_redemptions, d.current_redemptions,
                COALESCE(dt.name, d.code) AS title,
                dt.description, dt.terms_conditions, dt.marketing_badge
           FROM discounts d
      LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
          WHERE d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?)
            AND d.status NOT IN ('cancelled','deleted')
          ORDER BY d.status ASC, d.id DESC LIMIT 50",
        [$lang, $tenantId]
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Brands (public listing)
 * GET /api/public/brands?tenant_id=X&lang=Y[&is_featured=1][&per=N][&page=N]
 * ----------------------------------------------------- */
