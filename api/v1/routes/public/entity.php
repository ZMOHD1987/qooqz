<?php
declare(strict_types=1);
/**
 * Public API sub-route: entity
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'entity') {
    $entityId = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : (int)($_GET['id'] ?? 0);
    $sub      = strtolower($segments[2] ?? '');

    if (!$entityId) {
        ResponseFormatter::notFound('Entity ID required');
        exit;
    }

    // Sub-route: entity categories — categories with products in this entity's tenant
    if ($sub === 'categories') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $eTenId = (int)$entityRow['tenant_id'];
        $rows   = $pdoList(
            "SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name, c.slug
               FROM product_categories pc
               JOIN categories c ON c.id = pc.category_id AND c.is_active = 1
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
               JOIN products p ON p.id = pc.product_id AND p.tenant_id = ? AND p.is_active = 1
              ORDER BY c.sort_order ASC, c.id ASC LIMIT 50",
            [$lang, $eTenId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity discounts — active discounts for this entity
    if ($sub === 'discounts') {
        $rows = $pdoList(
            "SELECT d.id, d.code, d.type, d.auto_apply, d.currency_code, d.status,
                    d.starts_at, d.ends_at, d.max_redemptions, d.current_redemptions,
                    COALESCE(dt.name, d.code) AS title,
                    dt.description, dt.marketing_badge, dt.terms_conditions
               FROM discounts d
          LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
              WHERE d.entity_id = ?
                AND d.status NOT IN ('cancelled','deleted')
              ORDER BY d.status ASC, d.priority DESC, d.id DESC LIMIT 30",
            [$lang, $entityId]
        );
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
        exit;
    }

    // Sub-route: entity ratings — GET list or POST submit
    if ($sub === 'ratings') {
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if ($isPost) {
            // Submit a rating
            $rateUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
            if (!$rateUserId) { ResponseFormatter::error('Login required', 401); exit; }
            $ratingVal  = (float)($_POST['rating'] ?? 0);
            $reviewText = trim($_POST['review'] ?? '');
            if ($ratingVal < 1 || $ratingVal > 5) { ResponseFormatter::error('Rating must be between 1 and 5'); exit; }
            try {
                // Upsert: one rating per user per entity (INSERT or UPDATE if exists)
                $existing = $pdoOne('SELECT id FROM entity_ratings WHERE entity_id = ? AND user_id = ? LIMIT 1', [$entityId, $rateUserId]);
                if ($existing) {
                    $pdo->prepare('UPDATE entity_ratings SET rating = ?, review = ?, is_active = 1, updated_at = NOW() WHERE id = ?')
                        ->execute([$ratingVal, $reviewText ?: null, (int)$existing['id']]);
                    $msg = 'Rating updated';
                } else {
                    $pdo->prepare('INSERT INTO entity_ratings (entity_id, user_id, rating, review, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
                        ->execute([$entityId, $rateUserId, $ratingVal, $reviewText ?: null]);
                    $msg = 'Rating submitted';
                }
                ResponseFormatter::success(['ok' => true, 'message' => $msg]);
            } catch (Throwable $ex) {
                ResponseFormatter::error('Failed to save rating: ' . $ex->getMessage());
            }
            exit;
        }
        // GET: last 5 active ratings
        $rows = $pdoList(
            "SELECT er.id, er.rating, er.review, er.created_at,
                    COALESCE(u.username, 'Anonymous') AS reviewer_name
               FROM entity_ratings er
          LEFT JOIN users u ON u.id = er.user_id
              WHERE er.entity_id = ? AND er.is_active = 1
              ORDER BY er.created_at DESC LIMIT 5",
            [$entityId]
        );
        $avg = $pdoOne('SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total FROM entity_ratings WHERE entity_id = ? AND is_active = 1', [$entityId]);
        ResponseFormatter::success(['ok' => true, 'ratings' => $rows, 'average' => $avg['avg_rating'] ?? null, 'total' => (int)($avg['total'] ?? 0)]);
        exit;
    }

    // Sub-route: entity products
    // Products have no entity_id column; use entity's tenant_id to scope products
    if ($sub === 'products') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $entityTenantId = (int)$entityRow['tenant_id'];
        $where  = 'WHERE p.is_active = 1 AND p.tenant_id = ?';
        $params = [$entityTenantId];
        if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM product_categories pc2 WHERE pc2.product_id = p.id AND pc2.category_id = ?)';
            $params[] = (int)$_GET['category_id'];
        }
        $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $params);
        $rows  = $pdoList(
            "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug,
                    p.is_featured, p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                    (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    NULL AS image_thumb_url
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              $where ORDER BY p.is_featured DESC, p.id DESC LIMIT ? OFFSET ?",
            array_merge([$lang], $params, [$per, $offset])
        );
        ResponseFormatter::success(['ok'=>true,'data'=>$rows,'meta'=>[
            'total'=>$total,'page'=>$page,'per_page'=>$per,
            'total_pages'=>$per > 0 ? (int)ceil($total/$per) : 1
        ]]);
        exit;
    }

    // Full entity profile — images fetched from images table (entity_logo / entity_cover)
    // entities table has vendor_type (varchar code), not entity_type_id — join entity_types by code
    $entity = $pdoOne(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                e.status, e.tenant_id, e.created_at,
                et.name AS type_name,
                (SELECT i.url FROM images i WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
                NULL AS logo_thumb_url,
                (SELECT i2.url FROM images i2 WHERE i2.owner_id = e.id ORDER BY i2.id ASC LIMIT 1 OFFSET 1) AS cover_url
           FROM entities e
      LEFT JOIN entity_types et ON et.code = e.vendor_type
          WHERE e.id = ? AND e.status NOT IN ('suspended','rejected') LIMIT 1",
        [$entityId]
    );

    if (!$entity) {
        ResponseFormatter::notFound('Entity not found');
        exit;
    }

    // Translation override (store_name / description)
    $translation = $pdoOne(
        "SELECT store_name, description FROM entity_translations
          WHERE entity_id = ? AND language_code = ? LIMIT 1",
        [$entityId, $lang]
    );
    if ($translation) {
        if (!empty($translation['store_name'])) $entity['store_name'] = $translation['store_name'];
        if (!empty($translation['description'])) $entity['description'] = $translation['description'];
    }

    // Working hours — table has is_open (tinyint), not is_closed; day_of_week is tinyint 0-6
    $workingHours = $pdoList(
        "SELECT day_of_week, open_time, close_time, is_open
           FROM entities_working_hours
          WHERE entity_id = ? ORDER BY day_of_week ASC",
        [$entityId]
    );

    // Addresses (with coordinates) — addresses table has no label column
    $addresses = $pdoList(
        "SELECT id, address_line1, address_line2, city_id, country_id,
                postal_code, latitude, longitude, is_primary
           FROM addresses
          WHERE owner_type = 'entity' AND owner_id = ? ORDER BY is_primary DESC, id ASC LIMIT 5",
        [$entityId]
    );

    // Payment methods — payment_methods columns: method_name, method_key, icon_url
    $paymentMethods = $pdoList(
        "SELECT pm.id, pm.method_name AS name, pm.method_key AS code, pm.icon_url AS icon, epm.is_active
           FROM entity_payment_methods epm
      LEFT JOIN payment_methods pm ON pm.id = epm.payment_method_id
          WHERE epm.entity_id = ? AND epm.is_active = 1",
        [$entityId]
    );

    // Attributes — entities_attributes has NO entity_type_id column; start from values table
    $attributes = $pdoList(
        "SELECT COALESCE(eat.name, ea.slug) AS attribute_name, eav.value
           FROM entities_attribute_values eav
      LEFT JOIN entities_attributes ea  ON ea.id = eav.attribute_id
      LEFT JOIN entities_attribute_translations eat
             ON eat.attribute_id = ea.id AND eat.language_code = ?
          WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != ''
          LIMIT 20",
        [$lang, $entityId]
    );

    // Entity settings — expose all business-logic fields for the frontend
    $entitySettings = $pdoOne(
        "SELECT auto_accept_orders, allow_cod, min_order_amount, preparation_time_minutes,
                allow_online_booking, booking_window_days, max_bookings_per_slot,
                booking_cancellation_allowed, allow_preorders, max_daily_orders,
                is_visible, maintenance_mode, show_reviews, show_contact_info,
                featured_in_app, default_payment_method, allow_multiple_payment_methods,
                delivery_radius_km, free_delivery_min_order,
                notification_preferences, additional_settings, card_style_id
           FROM entity_settings
          WHERE entity_id = ? LIMIT 1",
        [$entityId]
    );

    // Card style — fetch full card style when card_style_id is set in entity settings
    $entityCardStyle = null;
    if (!empty($entitySettings['card_style_id'])) {
        $entityCardStyle = $pdoOne(
            "SELECT id, name, slug, card_type, background_color, border_color, border_width,
                    border_radius, shadow_style, padding, hover_effect, text_align,
                    image_aspect_ratio
               FROM card_styles WHERE id = ? AND is_active = 1 LIMIT 1",
            [(int)$entitySettings['card_style_id']]
        );
    }

    ResponseFormatter::success([
        'ok'      => true,
        'data'    => array_merge($entity, [
            'working_hours'   => $workingHours,
            'addresses'       => $addresses,
            'payment_methods' => $paymentMethods,
            'attributes'      => $attributes,
            'settings'        => $entitySettings ?: [],
            'card_style'      => $entityCardStyle,
        ]),
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Entities (public listing)
 * GET /api/public/entities[/{id}]
 * ----------------------------------------------------- */
