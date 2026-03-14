<?php
declare(strict_types=1);
/**
 * Public API sub-route: auctions
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'auctions') {
    $auctionId = (isset($segments[1]) && ctype_digit((string)$segments[1])) ? (int)$segments[1] : 0;
    $auctionSub = strtolower($segments[2] ?? '');
    $auctionMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $auctionUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    $auctionLang = $lang ?: 'ar';

    // Helper: get auction translation
    $getAuctionName = function(int $aid, string $lng) use ($pdo, $pdoOne): string {
        $r = $pdoOne('SELECT title FROM auction_translations WHERE auction_id=? AND language_code=? LIMIT 1', [$aid, $lng]);
        if (!$r) $r = $pdoOne('SELECT title FROM auction_translations WHERE auction_id=? LIMIT 1', [$aid]);
        return $r['title'] ?? '';
    };

    // ---- GET listing ----
    if (!$auctionId && $auctionMethod === 'GET') {
        $aSt = in_array($_GET['status'] ?? '', ['active','scheduled','ended','all'], true) ? $_GET['status'] : 'active';
        $aType = in_array($_GET['type'] ?? '', ['normal','reserve','buy_now','dutch','sealed_bid'], true) ? $_GET['type'] : '';
        $aFeat = isset($_GET['featured']) ? 1 : null;
        $aWhere = '1=1';
        $aParams = [];
        if ($aSt !== 'all') { $aWhere .= ' AND a.status = ?'; $aParams[] = $aSt; }
        if ($aType)         { $aWhere .= ' AND a.auction_type = ?'; $aParams[] = $aType; }
        if ($aFeat !== null){ $aWhere .= ' AND a.is_featured = 1'; }
        if ($tenantId)      { $aWhere .= ' AND a.tenant_id = ?'; $aParams[] = $tenantId; }
        $aRows = $pdoList(
            "SELECT a.id, a.slug, a.auction_type, a.status, a.starting_price, a.current_price,
                    a.buy_now_price, a.bid_increment, a.total_bids, a.total_bidders,
                    a.start_date, a.end_date, a.is_featured, a.condition_type, a.quantity,
                    (SELECT c.code FROM currencies c WHERE c.id = a.currency_id LIMIT 1) AS currency_code,
                    a.entity_id,
                    (SELECT i.url FROM images i WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    (SELECT at2.title FROM auction_translations at2 WHERE at2.auction_id = a.id AND at2.language_code = ? LIMIT 1) AS title
             FROM auctions a
             WHERE $aWhere
             ORDER BY a.is_featured DESC, a.end_date ASC
             LIMIT ? OFFSET ?",
            array_merge([$auctionLang], $aParams, [$per, $offset])
        );
        ResponseFormatter::success(['ok' => true, 'auctions' => $aRows, 'page' => $page, 'per' => $per]);
        exit;
    }

    // ---- GET detail ----
    if ($auctionId && $auctionMethod === 'GET' && $auctionSub === '') {
        $aRow = $pdoOne(
            "SELECT a.*, 
                    (SELECT at2.title FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS title,
                    (SELECT at2.description FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS description,
                    (SELECT at2.terms_conditions FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS terms_conditions,
                    (SELECT i.url FROM images i WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url
             FROM auctions a WHERE a.id=?",
            [$auctionLang, $auctionLang, $auctionLang, $auctionId]
        );
        if (!$aRow) { ResponseFormatter::notFound('Auction not found'); exit; }
        // Bid history (last 20)
        $aBids = $pdoList(
            "SELECT ab.id, ab.bid_amount, ab.bid_type, ab.is_winning, ab.created_at,
                    COALESCE(u.username, CONCAT('User#', ab.user_id)) AS bidder
             FROM auction_bids ab LEFT JOIN users u ON u.id = ab.user_id
             WHERE ab.auction_id=? ORDER BY ab.bid_amount DESC, ab.created_at DESC LIMIT 20",
            [$auctionId]
        );
        // Is user watching?
        $aIsWatching = false;
        if ($auctionUserId) {
            $aIsWatching = (bool)$pdoOne('SELECT id FROM auction_watchers WHERE auction_id=? AND user_id=? LIMIT 1', [$auctionId, $auctionUserId]);
        }
        // User's current max auto-bid
        $aAutoBid = null;
        if ($auctionUserId) {
            $aAutoBid = $pdoOne('SELECT max_bid_amount, is_active FROM auto_bid_settings WHERE auction_id=? AND user_id=? AND is_active=1 LIMIT 1', [$auctionId, $auctionUserId]);
        }
        ResponseFormatter::success(['ok' => true, 'auction' => $aRow, 'bids' => $aBids, 'is_watching' => $aIsWatching, 'auto_bid' => $aAutoBid]);
        exit;
    }

    // ---- GET status (live poll) ----
    if ($auctionId && $auctionMethod === 'GET' && $auctionSub === 'status') {
        $aSt = $pdoOne(
            'SELECT current_price, total_bids, total_bidders, status, end_date,
                    (SELECT ab.bid_amount FROM auction_bids ab WHERE ab.auction_id=a.id AND ab.is_winning=1 ORDER BY ab.bid_amount DESC LIMIT 1) AS top_bid
             FROM auctions a WHERE a.id=?',
            [$auctionId]
        );
        if (!$aSt) { ResponseFormatter::notFound('Auction not found'); exit; }
        $aSt['server_time'] = date('c');
        ResponseFormatter::success(['ok' => true, 'status' => $aSt]);
        exit;
    }

    // ---- POST bid ----
    if ($auctionId && $auctionMethod === 'POST' && $auctionSub === 'bid') {
        if (!$auctionUserId) { ResponseFormatter::error('Login required', 401); exit; }
        $bidAmount = (float)($_POST['bid_amount'] ?? 0);
        if ($bidAmount <= 0) { ResponseFormatter::error('Invalid bid amount', 422); exit; }
        if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
        try {
            $aRow = $pdoOne('SELECT current_price, bid_increment, status, end_date, created_by FROM auctions WHERE id=?', [$auctionId]);
            if (!$aRow) { ResponseFormatter::notFound('Auction not found'); exit; }
            if ((int)($aRow['created_by'] ?? 0) === $auctionUserId) { ResponseFormatter::error('You cannot bid on your own auction', 403); exit; }
            if ($aRow['status'] !== 'active') { ResponseFormatter::error('Auction is not active', 422); exit; }
            if (strtotime($aRow['end_date']) < time()) { ResponseFormatter::error('Auction has ended', 422); exit; }
            $minBid = (float)$aRow['current_price'] + (float)$aRow['bid_increment'];
            if ($bidAmount < $minBid) { ResponseFormatter::error("Minimum bid is $minBid", 422); exit; }
            $pdo->beginTransaction();
            // Use FOR UPDATE to prevent race conditions on concurrent bids
            $pdo->prepare('SELECT id FROM auctions WHERE id=? FOR UPDATE')->execute([$auctionId]);
            // Re-read current price under lock
            $aLocked = $pdoOne('SELECT current_price, bid_increment, status, end_date FROM auctions WHERE id=?', [$auctionId]);
            if (!$aLocked || $aLocked['status'] !== 'active') {
                $pdo->rollBack();
                ResponseFormatter::error('Auction is no longer available', 422); exit;
            }
            $minBidLocked = (float)$aLocked['current_price'] + (float)$aLocked['bid_increment'];
            if ($bidAmount < $minBidLocked) {
                $pdo->rollBack();
                ResponseFormatter::error("Minimum bid is $minBidLocked", 422); exit;
            }
            // Mark only the previous winning bid as not winning (targeted update)
            $pdo->prepare('UPDATE auction_bids SET is_winning=0 WHERE auction_id=? AND is_winning=1')->execute([$auctionId]);
            // Insert new bid
            $stB = $pdo->prepare('INSERT INTO auction_bids (auction_id, user_id, bid_amount, bid_type, is_winning, ip_address, created_at) VALUES (?,?,?,?,1,?,NOW())');
            $stB->execute([$auctionId, $auctionUserId, $bidAmount, 'manual', $_SERVER['REMOTE_ADDR'] ?? null]);
            $newBidId = (int)$pdo->lastInsertId();
            // Update auction: current price, bid count, unique bidder count, winner
            $pdo->prepare(
                'UPDATE auctions SET current_price=?, total_bids=total_bids+1,
                 total_bidders=(SELECT COUNT(DISTINCT user_id) FROM auction_bids WHERE auction_id=?),
                 winner_user_id=?, winner_bid_id=? WHERE id=?'
            )->execute([$bidAmount, $auctionId, $auctionUserId, $newBidId, $auctionId]);
            $pdo->commit();
            ResponseFormatter::success(['ok' => true, 'bid_id' => $newBidId, 'new_price' => $bidAmount], 'Bid placed', 201);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // ---- POST auto-bid ----
    if ($auctionId && $auctionMethod === 'POST' && $auctionSub === 'auto-bid') {
        if (!$auctionUserId) { ResponseFormatter::error('Login required', 401); exit; }
        $maxBid = (float)($_POST['max_bid_amount'] ?? 0);
        if ($maxBid <= 0) { ResponseFormatter::error('Invalid max bid', 422); exit; }
        if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
        try {
            // Validate max_bid >= current minimum bid
            $aAbRow = $pdoOne('SELECT current_price, bid_increment FROM auctions WHERE id=? AND status="active" LIMIT 1', [$auctionId]);
            if (!$aAbRow) { ResponseFormatter::error('Auction not found or not active', 422); exit; }
            $abMinBid = (float)$aAbRow['current_price'] + (float)$aAbRow['bid_increment'];
            if ($maxBid < $abMinBid) { ResponseFormatter::error("Max bid must be at least $abMinBid", 422); exit; }
            $stAB = $pdo->prepare('INSERT INTO auto_bid_settings (auction_id, user_id, max_bid_amount, is_active, created_at, updated_at) VALUES (?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE max_bid_amount=VALUES(max_bid_amount), is_active=1, updated_at=NOW()');
            $stAB->execute([$auctionId, $auctionUserId, $maxBid]);
            ResponseFormatter::success(['ok' => true, 'max_bid' => $maxBid], 'Auto-bid set');
        } catch (Throwable $ex) { ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500); }
        exit;
    }

    // ---- POST watch / DELETE watch ----
    if ($auctionId && in_array($auctionSub, ['watch'], true)) {
        if (!$auctionUserId) { ResponseFormatter::error('Login required', 401); exit; }
        if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
        try {
            $exists = $pdoOne('SELECT id FROM auction_watchers WHERE auction_id=? AND user_id=? LIMIT 1', [$auctionId, $auctionUserId]);
            if ($exists) {
                $pdo->prepare('DELETE FROM auction_watchers WHERE auction_id=? AND user_id=?')->execute([$auctionId, $auctionUserId]);
                ResponseFormatter::success(['ok' => true, 'watching' => false], 'Unwatched');
            } else {
                $pdo->prepare('INSERT INTO auction_watchers (auction_id, user_id, created_at) VALUES (?,?,NOW())')->execute([$auctionId, $auctionUserId]);
                ResponseFormatter::success(['ok' => true, 'watching' => true], 'Watching', 201);
            }
        } catch (Throwable $ex) { ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500); }
        exit;
    }

    // ---- POST buy-now ----
    if ($auctionId && $auctionMethod === 'POST' && $auctionSub === 'buy-now') {
        if (!$auctionUserId) { ResponseFormatter::error('Login required', 401); exit; }
        if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
        try {
            $aRow = $pdoOne('SELECT buy_now_price, status, end_date, tenant_id, entity_id, product_id FROM auctions WHERE id=?', [$auctionId]);
            if (!$aRow) { ResponseFormatter::notFound('Auction not found'); exit; }
            if ($aRow['status'] !== 'active') { ResponseFormatter::error('Auction not active', 422); exit; }
            if (!$aRow['buy_now_price']) { ResponseFormatter::error('No buy-now price', 422); exit; }
            $aTenantId  = (int)$aRow['tenant_id'];
            $aEntityId  = (int)$aRow['entity_id'];
            $aProductId = (int)($aRow['product_id'] ?? 0);
            $aBuyPrice  = (float)$aRow['buy_now_price'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE auction_bids SET is_winning=0 WHERE auction_id=?')->execute([$auctionId]);
            $stBN = $pdo->prepare('INSERT INTO auction_bids (auction_id, user_id, bid_amount, bid_type, is_winning, created_at) VALUES (?,?,?,?,1,NOW())');
            $stBN->execute([$auctionId, $auctionUserId, $aBuyPrice, 'buy_now']);
            $bnId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE auctions SET status='sold', current_price=?, winner_user_id=?, winner_bid_id=?, winning_amount=?, ended_at=NOW() WHERE id=?")
                ->execute([$aBuyPrice, $auctionUserId, $bnId, $aBuyPrice, $auctionId]);

            // Create order (auction_id set, cart_id NULL)
            $bnOrderNum = 'AUC-' . $aTenantId . '-' . $auctionId . '-' . time();
            $prdName = 'Auction Item'; $prdSku = 'AUC-' . $auctionId;
            if ($aProductId) {
                $prdRow = $pdoOne(
                    'SELECT p.sku, (SELECT pt2.name FROM product_translations pt2 WHERE pt2.product_id=p.id ORDER BY pt2.id ASC LIMIT 1) AS pname FROM products p WHERE p.id=? LIMIT 1',
                    [$aProductId]
                );
                if ($prdRow) {
                    $prdName = $prdRow['pname'] ?: $prdName;
                    $prdSku  = $prdRow['sku']   ?: $prdSku;
                }
            }
            $pdo->prepare(
                "INSERT INTO orders (tenant_id, entity_id, order_number, user_id, cart_id, auction_id,
                    order_type, status, payment_status, subtotal, total_amount, grand_total,
                    currency_code, ip_address)
                 VALUES (?,?,?,?,NULL,?,'online','pending','pending',?,?,?,'SAR',?)"
            )->execute([
                $aTenantId, $aEntityId, $bnOrderNum, $auctionUserId, $auctionId,
                $aBuyPrice, $aBuyPrice, $aBuyPrice,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $bnOrderId = (int)$pdo->lastInsertId();
            if ($aProductId && $bnOrderId) {
                try {
                    $pdo->prepare(
                        "INSERT INTO order_items (tenant_id, order_id, entity_id, product_id, product_name, sku, quantity, unit_price, subtotal, total)
                         VALUES (?,?,?,?,?,?,1,?,?,?)"
                    )->execute([$aTenantId, $bnOrderId, $aEntityId, $aProductId, $prdName, $prdSku, $aBuyPrice, $aBuyPrice, $aBuyPrice]);
                } catch (Throwable) {}
            }
            $pdo->commit();
            // Insert payment record (non-fatal)
            try {
                $bnPmNum = 'PAY-AUC-' . $auctionId . '-' . time();
                $pdo->prepare(
                    "INSERT INTO payments (entity_id, payment_number, order_id, user_id, payment_method,
                        amount, currency_code, status, payment_type, ip_address, created_at, updated_at)
                     VALUES (?,?,?,?,'buy_now',?,'SAR','pending','order',?,NOW(),NOW())"
                )->execute([$aEntityId, $bnPmNum, $bnOrderId, $auctionUserId, $aBuyPrice, $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (Throwable) {}
            ResponseFormatter::success(['ok' => true, 'bid_id' => $bnId, 'amount' => $aBuyPrice, 'order_id' => $bnOrderId, 'order_number' => $bnOrderNum], 'Purchased!');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }
    // ---- POST expire (payment deadline fallback — transfer to 2nd bidder) ----
    if ($auctionId && $auctionMethod === 'POST' && $auctionSub === 'expire') {
        if (!$auctionUserId) { ResponseFormatter::error('Login required', 401); exit; }
        if (!$pdo) { ResponseFormatter::error('DB unavailable', 503); exit; }
        try {
            $aExp = $pdoOne('SELECT id, status, winner_user_id, winning_amount, payment_deadline_hours, tenant_id, entity_id FROM auctions WHERE id=? LIMIT 1', [$auctionId]);
            if (!$aExp) { ResponseFormatter::notFound('Auction not found'); exit; }
            if (!in_array($aExp['status'], ['sold','ended'])) { ResponseFormatter::error('Auction not in sold/ended state', 422); exit; }
            $winnerUserId = (int)($aExp['winner_user_id'] ?? 0);
            // Find winner's order and check payment status
            $winnerOrder = $pdoOne('SELECT id, payment_status, created_at FROM orders WHERE auction_id=? AND user_id=? ORDER BY id DESC LIMIT 1', [$auctionId, $winnerUserId]);
            if (!$winnerOrder) { ResponseFormatter::error('No winner order found', 404); exit; }
            if ($winnerOrder['payment_status'] === 'paid') { ResponseFormatter::error('Winner has already paid', 409); exit; }
            $deadlineHours = max(1, (int)($aExp['payment_deadline_hours'] ?? 48));
            $orderCreated = strtotime($winnerOrder['created_at'] ?? 'now');
            if ((time() - $orderCreated) < $deadlineHours * 3600) {
                ResponseFormatter::error('Payment deadline has not passed yet', 409); exit;
            }
            // Find 2nd highest bidder (different from current winner)
            $secondBid = $pdoOne(
                'SELECT user_id, bid_amount FROM auction_bids WHERE auction_id=? AND user_id != ? AND bid_type != "buy_now" ORDER BY bid_amount DESC, created_at DESC LIMIT 1',
                [$auctionId, $winnerUserId]
            );
            $pdo->beginTransaction();
            // Cancel winner's order
            $pdo->prepare("UPDATE orders SET status='cancelled', cancellation_reason='Payment deadline expired', cancelled_at=NOW() WHERE id=?")->execute([$winnerOrder['id']]);
            if ($secondBid) {
                $newWinnerId = (int)$secondBid['user_id'];
                $newAmount   = (float)$secondBid['bid_amount'];
                $expOrderNum = 'AUC-EXP-' . $auctionId . '-' . time();
                $pdo->prepare("UPDATE auctions SET winner_user_id=?, winning_amount=? WHERE id=?")->execute([$newWinnerId, $newAmount, $auctionId]);
                $pdo->prepare("INSERT INTO orders (tenant_id, entity_id, order_number, user_id, auction_id, order_type, status, payment_status, subtotal, total_amount, grand_total, currency_code, ip_address) VALUES (?,?,?,?,NULL,'online','pending','pending',?,?,?,'SAR',?)")
                    ->execute([(int)$aExp['tenant_id'], (int)$aExp['entity_id'], $expOrderNum, $newWinnerId, $newAmount, $newAmount, $newAmount, $_SERVER['REMOTE_ADDR'] ?? null]);
                $pdo->commit();
                ResponseFormatter::success(['ok' => true, 'transferred_to' => $newWinnerId, 'order_number' => $expOrderNum], 'Transferred to second bidder');
            } else {
                $pdo->prepare("UPDATE auctions SET winner_user_id=NULL, winning_amount=NULL, status='ended' WHERE id=?")->execute([$auctionId]);
                $pdo->commit();
                ResponseFormatter::success(['ok' => true, 'transferred_to' => null], 'No second bidder — auction marked as ended without winner');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ResponseFormatter::error('Failed: ' . $ex->getMessage(), 500);
        }
        exit;
    }
}

ResponseFormatter::notFound('Public route not found: /' . ($first ?: ''));
