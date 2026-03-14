<?php
declare(strict_types=1);
/**
 * Public API sub-route: vendors
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'vendors') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    if ($id) {
        $row = $pdoOne('SELECT id, store_name AS name, is_active FROM entities WHERE id = ? LIMIT 1', [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'vendor' => $row]);
        else      ResponseFormatter::notFound('Vendor not found');
    } else {
        $rows = $pdoList('SELECT id, store_name AS name, is_active FROM entities LIMIT ? OFFSET ?', [$per, $offset]);
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: Entity Types (public list — used for filter dropdown)
 * GET /api/public/entity_types
 * ----------------------------------------------------- */
