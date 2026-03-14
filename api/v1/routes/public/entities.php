<?php
declare(strict_types=1);
/**
 * Public API sub-route: entities
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'entities') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne("SELECT * FROM entities WHERE id = ? AND status = 'approved' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'entity' => $row]);
        else      ResponseFormatter::notFound('Entity not found');
        exit;
    }

    $where  = "WHERE e.status NOT IN ('suspended','rejected')";
    $params = [];
    if ($tenantId)                          { $where .= ' AND e.tenant_id = ?';    $params[] = $tenantId; }
    if (!empty($_GET['vendor_type']))        { $where .= ' AND e.vendor_type = ?'; $params[] = $_GET['vendor_type']; }
    if (!empty($_GET['is_verified']))        { $where .= ' AND e.is_verified = ?'; $params[] = 1; }
    if (!empty($_GET['q'])) {
        $like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $_GET['q']) . '%';
        $where .= ' AND (e.store_name LIKE ? OR e.email LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM entities e $where", $params);
    $rows  = $pdoList(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.is_verified, e.tenant_id,
                (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                NULL AS logo_thumb_url,
                es.additional_settings,
                es.card_style_id,
                cs.slug AS card_style_slug,
                cs.background_color AS card_bg_color,
                cs.border_color AS card_border_color,
                cs.border_width AS card_border_width,
                cs.border_radius AS card_border_radius,
                cs.shadow_style AS card_shadow,
                cs.padding AS card_padding,
                cs.hover_effect AS card_hover_effect,
                cs.text_align AS card_text_align,
                cs.image_aspect_ratio AS card_image_aspect_ratio
           FROM entities e
           LEFT JOIN entity_settings es ON es.entity_id = e.id
           LEFT JOIN card_styles cs ON cs.id = es.card_style_id AND cs.is_active = 1
          $where ORDER BY e.is_verified DESC, e.id DESC LIMIT ? OFFSET ?",
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
 * Route: Tenants (public listing)
 * GET /api/public/tenants[/{id}]
 * ----------------------------------------------------- */
