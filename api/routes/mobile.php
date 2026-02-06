<?php
declare(strict_types=1);
/**
 * routes/mobile.php
 *
 * Mobile API endpoints under /api/mobile/*
 * - GET  /api/mobile/health
 * - GET  /api/mobile/profile
 * - POST /api/mobile/auth (if needed)
 *
 * Uses $GLOBALS['ADMIN_DB'] or mobile-specific globals if set.
 */

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$segments = $_GET['segments'] ?? [];
$first = strtolower($segments[0] ?? '');

$mobileUser = $GLOBALS['MOBILE_USER'] ?? $_SESSION['mobile_user'] ?? $GLOBALS['ADMIN_USER'] ?? $_SESSION['user'] ?? null;
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

// Health: /api/mobile/health
if ($first === '' || $first === 'health') {
    ResponseFormatter::success([
        'ok' => true,
        'scope' => 'mobile',
        'time' => date('c'),
        'db_connected' => $pdo instanceof PDO,
        'user' => $mobileUser ? ['id' => $mobileUser['id'] ?? null, 'username' => $mobileUser['username'] ?? null] : null
    ]);
    exit;
}

// Profile: /api/mobile/profile
if ($first === 'profile') {
    if (!$mobileUser) {
        ResponseFormatter::notFound('Not authenticated');
        exit;
    }
    ResponseFormatter::success(['ok' => true, 'user' => $mobileUser]);
    exit;
}

// Auth endpoint placeholder: POST /api/mobile/auth
if ($first === 'auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // optional: reuse routes/auth.php logic or implement mobile auth (JWT)
    ResponseFormatter::success(['ok' => true, 'message' => 'Mobile auth placeholder']);
    exit;
}

ResponseFormatter::notFound('Mobile route not found: ' . ($first ?: '/'));
exit;