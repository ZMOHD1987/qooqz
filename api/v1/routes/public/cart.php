<?php
declare(strict_types=1);
/**
 * Public API sub-route: cart
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'cart') {
    $cartMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $subPath    = strtolower($segments[1] ?? '');

    // Require authenticated user (uses top-level session, same as admin)
    $cartUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$cartUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    $cartTenantId = $tenantId ?? 1;

    // Helper: get or create the user's active cart
    $getOrCreateCart = function () use ($pdo, $cartUserId, $cartTenantId): int {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $st->execute([$cartUserId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
        $ins = $pdo->prepare(
            "INSERT INTO carts (entity_id, user_id, session_id, status, ip_address)
             VALUES (1, ?, ?, 'active', ?)"
        );
        $ins->execute([$cartUserId, session_id() ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
        return (int)$pdo->lastInsertId();
    };

    // Helper: refresh cart totals after any item change
    $refreshCartTotals = function (int $cid) use ($pdo): void {
        $st = $pdo->prepare("SELECT SUM(quantity) AS ti, SUM(total) AS tot FROM cart_items WHERE cart_id = ?");
        $st->execute([$cid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE carts SET total_items = ?, subtotal = ?, total_amount = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([(int)($row['ti'] ?? 0), (float)($row['tot'] ?? 0), (float)($row['tot'] ?? 0), $cid]);
    };

    // ── GET /api/public/cart ─────────────────────────────
    if ($cartMethod === 'GET' && $subPath === '') {
        $cartRow = $pdoOne(
            "SELECT id, total_items, subtotal, total_amount, currency_code, status
               FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$cartUserId]
        );
        if (!$cartRow) {
            ResponseFormatter::success(['cart' => null, 'items' => []]);
            exit;
        }
        $cartItems = $pdoList(
            "SELECT id, product_id, entity_id, product_name, sku, quantity,
                    unit_price, sale_price, subtotal, total, currency_code
               FROM cart_items WHERE cart_id = ? ORDER BY added_at ASC",
            [(int)$cartRow['id']]
        );
        ResponseFormatter::success(['cart' => $cartRow, 'items' => $cartItems]);
        exit;
    }

    // ── POST /api/public/cart/add ────────────────────────
    if ($subPath === 'add' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;

        $pId   = (int)($body['product_id'] ?? 0);
        $pName = trim((string)($body['product_name'] ?? ''));
        $pSku  = trim((string)($body['sku'] ?? ''));
        $price = (float)($body['unit_price'] ?? $body['price'] ?? 0);
        $qty   = max(1, (int)($body['qty'] ?? 1));
        $eid   = max(1, (int)($body['entity_id'] ?? 1));

        if (!$pId || !$pName) {
            ResponseFormatter::error('product_id and product_name are required', 422);
            exit;
        }
        try {
            $cid      = $getOrCreateCart();
            $existing = $pdoOne(
                "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1",
                [$cid, $pId]
            );
            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $sub    = round($price * $newQty, 2);
                $pdo->prepare("UPDATE cart_items SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newQty, $sub, $sub, (int)$existing['id']]);
            } else {
                $sub = round($price * $qty, 2);
                $pdo->prepare(
                    "INSERT INTO cart_items
                       (cart_id, product_id, entity_id, product_name, sku, quantity,
                        unit_price, subtotal, total, currency_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'SAR')"
                )->execute([$cid, $pId, $eid, $pName, $pSku, $qty, $price, $sub, $sub]);
            }
            $refreshCartTotals($cid);
            ResponseFormatter::success(['ok' => true, 'cart_id' => $cid], 'Item added', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to add item: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // ── POST /api/public/cart/update ─────────────────────
    if ($subPath === 'update' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = max(1, (int)($body['qty'] ?? 1));
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.unit_price, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $sub = round((float)$item['unit_price'] * $qty, 2);
            $pdo->prepare("UPDATE cart_items SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$qty, $sub, $sub, $itemId]);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to update item', 500);
        }
        exit;
    }

    // ── POST/DELETE /api/public/cart/remove ──────────────
    if ($subPath === 'remove' && in_array($cartMethod, ['POST', 'DELETE'], true)) {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? $_GET['item_id'] ?? 0);
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $pdo->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$itemId]);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to remove item', 500);
        }
        exit;
    }

    // ── POST /api/public/cart/clear ──────────────────────
    if ($subPath === 'clear' && $cartMethod === 'POST') {
        try {
            $cRow = $pdoOne(
                "SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
                [$cartUserId]
            );
            if ($cRow) {
                $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([(int)$cRow['id']]);
                $pdo->prepare("UPDATE carts SET total_items = 0, subtotal = 0, total_amount = 0, last_activity_at = NOW() WHERE id = ?")
                    ->execute([(int)$cRow['id']]);
            }
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to clear cart', 500);
        }
        exit;
    }

    ResponseFormatter::error('Unknown cart action', 404);
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/orders — order creation
 * Accepts: entity_id, payment_method, items (JSON), customer_name, customer_phone,
 *          delivery_address, notes, tenant_id
 * ----------------------------------------------------- */
