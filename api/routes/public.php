<?php
declare(strict_types=1);
/**
 * routes/public.php
 *
 * Public API routes under /api/public/* or /api/* when scope=public.
 * Dispatcher provides:
 *  - $_GET['segments'] (array)
 *  - $_GET['splat'] (string)
 *  - $_SERVER['CONTAINER']
 *
 * This file contains simple handlers for products, vendors, home, ui.
 */

$segments = $_GET['segments'] ?? [];
$first = strtolower($segments[0] ?? '');

$pdo = $GLOBALS['ADMIN_DB'] ?? null;

// Home
if ($first === '' || $first === 'home') {
    ResponseFormatter::success(['ok' => true, 'service' => 'Public API', 'time' => date('c')]);
    exit;
}

// UI: /api/ui
if ($first === 'ui') {
    ResponseFormatter::success(['ok' => true, 'ui' => $GLOBALS['PUBLIC_UI'] ?? []]);
    exit;
}

// Products list: GET /api/products or GET /api/products/{id}
if ($first === 'products') {
    // id may be in $_GET['id'] set by dispatcher or segments[1]
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit($segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare('SELECT id, name, price, active FROM products WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                ResponseFormatter::success(['ok' => true, 'product' => $row]);
            } else {
                ResponseFormatter::notFound('Product not found');
            }
        } else {
            ResponseFormatter::serverError('Database unavailable');
        }
        exit;
    }

    // list (basic pagination)
    if ($pdo instanceof PDO) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = min(100, max(1, (int)($_GET['per'] ?? 25)));
        $offset = ($page - 1) * $per;
        $st = $pdo->prepare('SELECT id, name, price, active FROM products LIMIT ? OFFSET ?');
        $st->bindValue(1, $per, PDO::PARAM_INT);
        $st->bindValue(2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        ResponseFormatter::success(['ok' => true, 'page' => $page, 'per' => $per, 'data' => $rows]);
    } else {
        // placeholder data if no DB
        ResponseFormatter::success(['ok' => true, 'data' => []]);
    }
    exit;
}

// Vendors: /api/vendors and /api/vendors/{id}
if ($first === 'vendors') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit($segments[1]) ? (int)$segments[1] : null);
    if ($id) {
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare('SELECT id, name, active FROM vendors WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) ResponseFormatter::success(['ok' => true, 'vendor' => $row]);
            else ResponseFormatter::notFound('Vendor not found');
        } else {
            ResponseFormatter::serverError('Database unavailable');
        }
        exit;
    }
    ResponseFormatter::success(['ok' => true, 'data' => []]); // stub list
    exit;
}

// If none matched
ResponseFormatter::notFound('Public route not found: ' . ($first ?: '/'));
exit;