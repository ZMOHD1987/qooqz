<?php
declare(strict_types=1);
/**
 * Public API sub-route: entity_types
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'entity_types') {
    $rows = $pdoList("SELECT id, code, name, description FROM entity_types ORDER BY id ASC");
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Homepage Sections
 * GET /api/public/homepage_sections?tenant_id=X&lang=Y
 * Returns active sections with translated title/subtitle
 * ----------------------------------------------------- */
