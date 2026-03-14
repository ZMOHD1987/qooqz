<?php
declare(strict_types=1);
/**
 * Public API sub-route: wishlist
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'wishlist') {
    $wishUserId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
    if (!$wishUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo) { ResponseFormatter::error('Database unavailable', 503); exit; }

    $wishSub = $segments[1] ?? '';

    /** Helper: get or create default wishlist for user */
    $getDefaultWishlist = function() use ($pdo, $wishUserId, $tenantId) {
        // tenant_id from GET may be null for POST requests; fall back to session value
        $wlTenantId = $tenantId ?? (int)($_SESSION['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
        $row = $pdo->prepare(
            'SELECT id FROM wishlists WHERE user_id = ? AND is_default = 1 LIMIT 1'
        );
        $row->execute([$wishUserId]);
        $wl = $row->fetch(PDO::FETCH_ASSOC);
        if ($wl) return (int)$wl['id'];
        // Create default wishlist
        $ins = $pdo->prepare(
            'INSERT INTO wishlists (user_id, tenant_id, entity_id, wishlist_name, is_default, total_items, created_at, updated_at)
             VALUES (?, ?, 1, ?, 1, 0, NOW(), NOW())'
        );
        $wlName = ($_GET['lang'] ?? $_SESSION['user']['preferred_language'] ?? 'en') === 'ar' ? 'قائمة مفضلتي' : 'My Wishlist';
        $ins->execute([$wishUserId, $wlTenantId, $wlName]);
        return (int)$pdo->lastInsertId();
    };

    /** Helper: refresh total_items count */
    $refreshWishlistCount = function(int $wlId) use ($pdo) {
        try {
            $cnt = $pdo->prepare(
                'SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id = ? AND removed_at IS NULL'
            );
            $cnt->execute([$wlId]);
            $pdo->prepare('UPDATE wishlists SET total_items = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$cnt->fetchColumn(), $wlId]);
        } catch (Throwable $__) { /* cached count is optional */ }
    };

    // GET /api/public/wishlist — list items in default wishlist
    if ($wishSub === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $wlId = $getDefaultWishlist();
            $lang = $_GET['lang'] ?? $lang;
            $st = $pdo->prepare(
                "SELECT wi.id, wi.product_id, wi.priority, wi.notes, wi.created_at,
                        COALESCE(pt.name, p.slug) AS product_name,
                        (SELECT i2.url FROM images i2 WHERE i2.owner_id = p.id AND i2.owner_type = 'product' ORDER BY i2.id ASC LIMIT 1) AS image_url,
                        (SELECT pp2.price FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS price,
                        (SELECT pp2.currency_code FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS currency_code
                 FROM wishlist_items wi
                 JOIN products p ON p.id = wi.product_id
                 LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                 WHERE wi.wishlist_id = ? AND wi.removed_at IS NULL
                 ORDER BY wi.priority DESC, wi.created_at DESC"
            );
            $st->execute([$lang, $wlId]);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            ResponseFormatter::success(['wishlist_id' => $wlId, 'items' => $items, 'total' => count($items)]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to load wishlist: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // GET /api/public/wishlist/ids — just product IDs (for heart button state on page load)
    if ($wishSub === 'ids' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $wlId = $getDefaultWishlist();
            $st = $pdo->prepare(
                'SELECT product_id FROM wishlist_items WHERE wishlist_id = ? AND removed_at IS NULL'
            );
            $st->execute([$wlId]);
            $ids = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'product_id');
            ResponseFormatter::success(['ids' => $ids]);
        } catch (Throwable $ex) {
            ResponseFormatter::success(['ids' => []]);
        }
        exit;
    }

    // POST /api/public/wishlist/add
    if ($wishSub === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $entityId  = (int)($_POST['entity_id']  ?? 1);
        if (!$productId) { ResponseFormatter::error('product_id required', 422); exit; }
        try {
            // Auto-resolve tenant_id from product's tenant if not provided
            $wlItemTenantId = $tenantId;
            if (!$wlItemTenantId) {
                $ptRow = $pdo->prepare('SELECT tenant_id FROM products WHERE id = ? LIMIT 1');
                $ptRow->execute([$productId]);
                $ptFetch = $ptRow->fetch(PDO::FETCH_ASSOC);
                $wlItemTenantId = $ptFetch ? (int)$ptFetch['tenant_id'] : 0;
            }
            if (!$wlItemTenantId) {
                $wlItemTenantId = (int)($_SESSION['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
            }
            $wlId = $getDefaultWishlist();
            // Check if already in wishlist (including soft-deleted → restore)
            $exist = $pdo->prepare('SELECT id, removed_at FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1');
            $exist->execute([$wlId, $productId]);
            $row = $exist->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row['removed_at'] !== null) {
                    // Restore soft-deleted item
                    $pdo->prepare('UPDATE wishlist_items SET removed_at = NULL, updated_at = NOW() WHERE id = ?')
                        ->execute([$row['id']]);
                }
                // else already active — no-op
            } else {
                $pdo->prepare(
                    'INSERT INTO wishlist_items (wishlist_id, product_id, entity_id, tenant_id, product_variant_id, priority, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 0, 0, NOW(), NOW())'
                )->execute([$wlId, $productId, $entityId, $wlItemTenantId]);
            }
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true, 'wishlist_id' => $wlId], 'Added to wishlist', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to add to wishlist: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // POST /api/public/wishlist/remove
    if ($wishSub === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!$productId) { ResponseFormatter::error('product_id required', 422); exit; }
        try {
            $wlId = $getDefaultWishlist();
            // Soft delete
            $pdo->prepare(
                'UPDATE wishlist_items SET removed_at = NOW(), updated_at = NOW() WHERE wishlist_id = ? AND product_id = ? AND removed_at IS NULL'
            )->execute([$wlId, $productId]);
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to remove: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // POST /api/public/wishlist/clear
    if ($wishSub === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $wlId = $getDefaultWishlist();
            $pdo->prepare('UPDATE wishlist_items SET removed_at = NOW(), updated_at = NOW() WHERE wishlist_id = ? AND removed_at IS NULL')
                ->execute([$wlId]);
            $refreshWishlistCount($wlId);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to clear: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    ResponseFormatter::notFound('Unknown wishlist endpoint');
    exit;
}

/* -------------------------------------------------------
 * Route: Recently Viewed Products
 * GET  /api/public/recent          — list (last 20, newest first)
 * POST /api/public/recent/add      — record a view
 * ----------------------------------------------------- */
