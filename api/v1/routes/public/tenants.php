<?php
declare(strict_types=1);
/**
 * Public API sub-route: tenants
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'tenants') {
    $id  = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    $sub = strtolower($segments[2] ?? '');

    // GET /api/public/tenants/{id}/entities
    if ($id && $sub === 'entities') {
        $total = $pdoCount(
            "SELECT COUNT(*) FROM entities e WHERE e.tenant_id = ? AND e.status = 'approved'",
            [(int)$id]
        );
        $rows  = $pdoList(
            "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.is_verified, e.phone,
                    (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                    es.additional_settings
               FROM entities e
               LEFT JOIN entity_settings es ON es.entity_id = e.id
              WHERE e.tenant_id = ? AND e.status = 'approved'
              ORDER BY e.id ASC LIMIT ? OFFSET ?",
            [(int)$id, $per, $offset]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                       'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1]]);
        exit;
    }

    if ($id) {
        $row = $pdoOne("SELECT id, name, domain, status FROM tenants WHERE id = ? AND status = 'active' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'tenant' => $row]);
        else      ResponseFormatter::notFound('Tenant not found');
        exit;
    }

    $where  = "WHERE t.status = 'active'";
    $params = [];
    if (!empty($_GET['search'])) {
        $where .= ' AND (t.name LIKE ? OR t.domain LIKE ?)';
        $kw     = '%' . $_GET['search'] . '%';
        $params = [$kw, $kw];
    }

    $total = $pdoCount("SELECT COUNT(*) FROM tenants t $where", $params);
    $rows  = $pdoList(
        "SELECT t.id, t.name, t.domain, t.status,
                sp.plan_name
           FROM tenants t
      LEFT JOIN subscription_plans sp ON sp.id = (
              SELECT plan_id FROM subscriptions s
               WHERE s.tenant_id = t.id AND s.status IN ('active','trial')
               ORDER BY s.id DESC LIMIT 1
          )
          $where ORDER BY t.id DESC LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                   'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Legacy: Vendors (kept for backwards compatibility)
 * ----------------------------------------------------- */
