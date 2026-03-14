<?php
declare(strict_types=1);
/**
 * Public API sub-route: orders
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'orders') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        ResponseFormatter::error('Method not allowed', 405);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    // Parse input (supports both JSON body and form POST)
    $raw  = file_get_contents('php://input');
    $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
          ? (json_decode($raw, true) ?? [])
          : $_POST;

    $entityId   = isset($body['entity_id'])  && is_numeric($body['entity_id'])  ? (int)$body['entity_id']  : 0;
    $pmCode     = trim((string)($body['payment_method'] ?? ''));
    $custName   = trim((string)($body['customer_name']  ?? ''));
    $custPhone  = trim((string)($body['customer_phone'] ?? ''));
    $address    = trim((string)($body['delivery_address'] ?? ''));
    $notes      = trim((string)($body['notes'] ?? ''));

    // Cart items — JSON array from body or hidden form field
    $rawItems = $body['items'] ?? $body['cart_items_json'] ?? '[]';
    if (is_string($rawItems)) $rawItems = json_decode($rawItems, true);
    $items = is_array($rawItems) ? $rawItems : [];

    // Require authenticated user (check both session formats)
    $sessUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$sessUserId) {
        ResponseFormatter::error('Login required to place an order', 401);
        exit;
    }

    // If entity_id not provided, resolve to first active entity for the tenant
    if (!$entityId && $tenantId) {
        $eRow = $pdoOne(
            "SELECT id FROM entities WHERE tenant_id = ? AND status = 'approved' ORDER BY id ASC LIMIT 1",
            [$tenantId]
        );
        $entityId = $eRow ? (int)$eRow['id'] : 1;
    } elseif (!$entityId) {
        $entityId = 1; // global fallback
    }

    // Validate
    if (!$custName || !$custPhone || empty($items)) {
        ResponseFormatter::error('customer_name, customer_phone, and items are required', 422);
        exit;
    }
    if (!$tenantId) {
        ResponseFormatter::error('tenant_id is required', 422);
        exit;
    }

    // Calculate totals
    $subtotal = 0.0;
    foreach ($items as $it) {
        $price = (float)($it['price'] ?? 0);
        $qty   = max(1, (int)($it['qty'] ?? 1));
        $subtotal += $price * $qty;
    }
    $subtotal     = round($subtotal, 2);
    $taxAmount    = 0.0;
    $grandTotal   = $subtotal;
    $currencyCode = trim((string)($body['currency_code'] ?? 'SAR'));

    // Generate order number: ORD-{tenantId}-{timestamp}-{rand}
    $orderNumber = 'ORD-' . $tenantId . '-' . time() . '-' . rand(100, 999);

    // Verify order number uniqueness
    $checkSt = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
    $checkSt->execute([$orderNumber]);
    if ($checkSt->fetch()) {
        $orderNumber .= '-' . rand(10, 99); // collision resolution
    }

    try {
        $pdo->beginTransaction();

        // Insert order
        $oSt = $pdo->prepare(
            "INSERT INTO orders
               (tenant_id, entity_id, order_number, user_id, status, payment_status,
                subtotal, tax_amount, shipping_cost, discount_amount, total_amount, grand_total,
                currency_code, customer_notes, ip_address)
             VALUES (?, ?, ?, ?, 'pending', 'pending',
                     ?, 0, 0, 0, ?, ?,
                     ?, ?, ?)"
        );
        $oSt->execute([
            $tenantId, $entityId, $orderNumber, $sessUserId,
            $subtotal, $grandTotal, $grandTotal,
            $currencyCode, $notes,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert order items
        $iSt = $pdo->prepare(
            "INSERT INTO order_items
               (tenant_id, order_id, entity_id, product_id, product_name, sku,
                quantity, unit_price, subtotal, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $it) {
            $pId    = (int)($it['id'] ?? 0);
            $pName  = (string)($it['name'] ?? '');
            $pSku   = (string)($it['sku']  ?? '');
            $qty    = max(1, (int)($it['qty'] ?? 1));
            $price  = (float)($it['price'] ?? 0);
            $itSub  = round($price * $qty, 2);
            if (!$pId || !$pName) continue;
            $iSt->execute([$tenantId, $orderId, $entityId, $pId, $pName, $pSku, $qty, $price, $itSub, $itSub]);
        }

        // Mark user's active cart as converted
        $userCart = $pdoOne(
            "SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$sessUserId]
        );
        if ($userCart) {
            $pdo->prepare(
                "UPDATE carts SET status = 'converted', converted_to_order_id = ?, updated_at = NOW()
                   WHERE id = ?"
            )->execute([$orderId, (int)$userCart['id']]);
        }

        $pdo->commit();

        ResponseFormatter::success([
            'ok'           => true,
            'id'           => $orderId,
            'order_number' => $orderNumber,
        ], 'Order created', 201);
    } catch (Throwable $ex) {
        try { $pdo->rollBack(); } catch (Throwable $rb) {}
        ResponseFormatter::error('Order creation failed', 500);
    }
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/job_applications — guest job application
 * Accepts: job_id, full_name, email, phone, cover_letter,
 *          cv_file_url, portfolio_url, linkedin_url
 * ----------------------------------------------------- */
