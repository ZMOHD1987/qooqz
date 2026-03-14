<?php
declare(strict_types=1);
/**
 * Public API sub-route: homepage_sections
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'homepage_sections') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $rows = $pdoList(
        "SELECT hs.id, hs.section_type, hs.component, hs.layout_type, hs.layout_config,
                hs.items_per_row, hs.background_color, hs.text_color, hs.padding,
                hs.custom_css, hs.data_source, hs.sort_order, hs.is_active,
                COALESCE(hst.title, hs.title)       AS title,
                COALESCE(hst.subtitle, hs.subtitle) AS subtitle
           FROM homepage_sections hs
      LEFT JOIN homepage_section_translations hst
             ON hst.section_id = hs.id AND hst.language_code = ?
          WHERE hs.tenant_id = ? AND hs.is_active = 1
          ORDER BY hs.sort_order ASC, hs.id ASC",
        [$lang, $tenantId]
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Banners (public listing — active, date-ranged)
 * GET /api/public/banners?tenant_id=X[&position=X]
 * ----------------------------------------------------- */
