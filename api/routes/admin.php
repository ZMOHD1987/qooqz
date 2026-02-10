<?php
declare(strict_types=1);
/**
 * routes/admin.php
 *
 * Handles admin UI related endpoints under /api/admin/*
 * - Example endpoints:
 *   GET  /api/admin                -> basic admin info
 *   GET  /api/admin/ui             -> admin UI metadata
 *   GET  /api/admin/settings       -> settings list placeholder (requires permission)
 *
 * Expects dispatcher to provide:
 *  - $_GET['segments'] (array) | $_GET['splat'] (string)
 *  - $_SERVER['CONTAINER'] with 'db' and 'user' fields
 */

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$segments = $_GET['segments'] ?? [];
$action = strtolower($segments[0] ?? '');

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
$user = $GLOBALS['ADMIN_USER'] ?? $_SESSION['user'] ?? null;

// Small permission helper (fallback)
function _has_perm(string $perm): bool {
    if (empty($GLOBALS['ADMIN_USER'])) return false;
    if (!empty($GLOBALS['ADMIN_USER']['role_id']) && (int)$GLOBALS['ADMIN_USER']['role_id'] === 1) return true;
    $perms = $_SESSION['permissions'] ?? $GLOBALS['ADMIN_USER']['permissions'] ?? [];
    return in_array($perm, (array)$perms, true);
}

// Root admin info: GET /api/admin
if ($action === '' || $action === null) {
    ResponseFormatter::success([
        'ok' => true,
        'module' => 'admin',
        'db_connected' => $pdo instanceof PDO,
        'user' => $user,
    ]);
    exit;
}

// Admin UI metadata: GET /api/admin/ui
if ($action === 'ui') {
    ResponseFormatter::success([
        'ok' => true,
        'detected_module' => 'BootstrapAdminUi',
        'db_connected' => $pdo instanceof PDO,
        'user_lang' => $user['preferred_language'] ?? 'ar',
        'user_direction' => in_array($user['preferred_language'] ?? 'ar', ['ar','fa','ur']) ? 'rtl' : 'ltr',
        'languages_dir' => defined('LANGUAGES_PATH') ? LANGUAGES_PATH : null,
        'strings_count' => function_exists('i18n_count') ? i18n_count() : 0,
        'user_info' => [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'preferred_language' => $user['preferred_language'] ?? ($user['lang'] ?? 'ar'),
            'role_id' => $user['role_id'] ?? null,
            'roles' => $_SESSION['roles'] ?? ($user['roles'] ?? []),
            'permissions' => $_SESSION['permissions'] ?? ($user['permissions'] ?? []),
            'permissions_count' => count($_SESSION['permissions'] ?? ($user['permissions'] ?? [])),
            'roles_count' => count($_SESSION['roles'] ?? ($user['roles'] ?? [])),
            'is_active' => $user['is_active'] ?? true,
        ],
        'session_roles' => $_SESSION['roles'] ?? [],
        'session_permissions' => $_SESSION['permissions'] ?? [],
    ]);
    exit;
}

// Settings placeholder: GET /api/admin/settings
if ($action === 'settings') {
    if (!_has_perm('manage_settings')) {
        ResponseFormatter::error('Forbidden', 403);
        exit;
    }
    // Example: return a placeholder list or real from DB if exists
    $settings = [
        ['key' => 'site_name', 'value' => 'My Site', 'type' => 'string'],
        ['key' => 'maintenance', 'value' => false, 'type' => 'boolean']
    ];
    ResponseFormatter::success(['ok' => true, 'data' => $settings]);
    exit;
}

// Unknown admin sub-route
ResponseFormatter::notFound('Admin route not found: ' . ($action ?: '/'));
exit;