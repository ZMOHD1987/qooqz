<?php
declare(strict_types=1);
/**
 * Public API sub-route: products
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'products') {
    // ── POST /api/public/products/{id}/reviews|questions must be caught HERE
    // before the $id-based product detail path (which also matches and calls exit).
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $subPid    = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : 0;
        $subAction = strtolower($segments[2] ?? '');
        if ($subPid && in_array($subAction, ['reviews', 'questions'], true)) {
            $subUserId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
            if (!$subUserId) { ResponseFormatter::error('Login required', 401); exit; }
            if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
            if ($subAction === 'reviews') {
                $rating  = (int)($_POST['rating'] ?? 0);
                $title   = trim($_POST['title'] ?? '');
                $comment = trim($_POST['comment'] ?? '');
                if ($rating < 1 || $rating > 5) { ResponseFormatter::error('Rating must be 1-5', 422); exit; }
                try {
                    $st = $pdo->prepare(
                        'INSERT INTO product_reviews (product_id, user_id, rating, title, comment, is_approved, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())'
                    );
                    $st->execute([$subPid, $subUserId, $rating, $title ?: null, $comment ?: null]);
                    ResponseFormatter::success(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 'Review submitted pending approval', 201);
                } catch (Throwable $ex) { ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500); }
            } else {
                $question = trim($_POST['question'] ?? '');
                if (strlen($question) < 5) { ResponseFormatter::error('Question too short', 422); exit; }
                try {
                    $st = $pdo->prepare(
                        'INSERT INTO product_questions (product_id, user_id, question, is_approved, created_at, updated_at)
                         VALUES (?, ?, ?, 0, NOW(), NOW())'
                    );
                    $st->execute([$subPid, $subUserId, $question]);
                    ResponseFormatter::success(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 'Question submitted pending review', 201);
                } catch (Throwable $ex) { ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500); }
            }
            exit;
        }
    }

    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    if (!$id && !empty($_GET['slug'])) {
        $slugParams = [$_GET['slug']];
        $slugCond = ' AND is_active = 1';
        if ($tenantId) { $slugCond .= ' AND tenant_id = ?'; $slugParams[] = $tenantId; }
        $slugRow = $pdoOne('SELECT id FROM products WHERE slug = ?' . $slugCond . ' LIMIT 1', $slugParams);
        if ($slugRow) $id = (int)$slugRow['id'];
    }

    if ($id) {
        // Full product detail — scalar subqueries for pricing and images to avoid
        // multi-row JOIN issues and dependency on pp.is_active in product_pricing.
        $qParams = [$lang, (int)$id];
        $tidCond = '';
        if ($tenantId) { $tidCond = ' AND p.tenant_id = ?'; $qParams[] = $tenantId; }
        $row = $pdoOne(
            "SELECT p.id, p.sku, p.slug, p.barcode, p.brand_id,
                    p.is_active, p.is_featured, p.is_new, p.is_bestseller,
                    p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                    p.views_count, p.tenant_id,
                    COALESCE(pt.name, p.slug) AS name,
                    pt.short_description, pt.description, pt.specifications, pt.meta_title,
                    (SELECT pp.price FROM product_pricing pp
                       WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    NULL AS compare_at_price,
                    (SELECT pp.currency_code FROM product_pricing pp
                       WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    NULL AS brand_name,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id
                       ORDER BY i.id ASC LIMIT 1) AS image_url,
                    NULL AS image_thumb_url
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              WHERE p.id = ?" . $tidCond,
            $qParams
        );
        if (!$row) { ResponseFormatter::notFound('Product not found'); exit; }

        // All product images (gallery) — select only id and url (safe columns that always exist)
        $productImages = $pdoList(
            "SELECT i.id, i.url
               FROM images i
              WHERE i.owner_id = ?
              ORDER BY i.id ASC LIMIT 10",
            [(int)$id]
        );

        // Categories
        $productCategories = $pdoList(
            "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug
               FROM categories c
         INNER JOIN product_categories pc ON pc.category_id = c.id AND pc.product_id = ?
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
              LIMIT 5",
            [(int)$id, $lang]
        );

        // Related products (same first category)
        $related = [];
        if (!empty($productCategories[0]['id'])) {
            $related = $pdoList(
                "SELECT p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug,
                        (SELECT pp2.price FROM product_pricing pp2
                           WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS price,
                        (SELECT pp2.currency_code FROM product_pricing pp2
                           WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS currency_code,
                        (SELECT i2.url FROM images i2 WHERE i2.owner_id = p2.id
                         ORDER BY i2.id ASC LIMIT 1) AS image_url
                   FROM products p2
             INNER JOIN product_categories pc2 ON pc2.product_id = p2.id AND pc2.category_id = ?
              LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                  WHERE p2.is_active = 1 AND p2.id != ?
                  ORDER BY p2.is_featured DESC, p2.id DESC LIMIT 8",
                [(int)$productCategories[0]['id'], $lang, (int)$id]
            );
        }

        ResponseFormatter::success([
            'ok'         => true,
            'product'    => $row,
            'images'     => $productImages,
            'categories' => $productCategories,
            'related'    => $related,
        ]);
        exit;
    }

    // $whereParams: params for WHERE clause only (no lang — lang is for the translation JOIN)
    $where       = 'WHERE p.is_active = 1';
    $whereParams = [];
    if ($tenantId) { $where .= ' AND p.tenant_id = ?'; $whereParams[] = $tenantId; }
    if (!empty($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
        $where .= ' AND p.brand_id = ?'; $whereParams[] = (int)$_GET['brand_id'];
    }
    if (!empty($_GET['is_featured'])) {
        $where .= ' AND p.is_featured = ?'; $whereParams[] = (int)$_GET['is_featured'];
    }
    if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
        $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id = ?)';
        $whereParams[] = (int)$_GET['category_id'];
    }
    if (!empty($_GET['search'])) {
        // Escape LIKE wildcards in the search term to prevent unintended broad matches
        $kw = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (p.slug LIKE ? OR p.id IN (SELECT product_id FROM product_translations WHERE name LIKE ? AND language_code = ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $lang;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $whereParams);
    $rows  = $pdoList(
        "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug, p.is_featured, p.tenant_id,
                p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                (SELECT i.url FROM images i WHERE i.owner_id = p.id ORDER BY i.id ASC LIMIT 1) AS image_url,
                NULL AS image_thumb_url
           FROM products p
      LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
         $where ORDER BY p.id DESC LIMIT ? OFFSET ?",
        array_merge([$lang], $whereParams, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ]
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Categories (public listing with images)
 * GET /api/public/categories[/{id}]
 * image_type: category (600×600 webp)
 * ----------------------------------------------------- */
