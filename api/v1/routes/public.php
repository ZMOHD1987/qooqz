<?php
declare(strict_types=1);
/**
 * routes/public.php
 *
 * Public API routes under /api/public/*
 * Handles: home, ui, products, vendors, jobs, entities, tenants
 *
 * Dispatcher provides:
 *  - $_GET['segments'] (array)
 *  - $_GET['splat']    (string)
 *  - $GLOBALS['ADMIN_DB'] (PDO|null)
 */

// Derive sub-route segments from the request URI.
// The Kernel loads this file for all /api/public/* requests but does NOT
// inject $_GET['segments'], so we parse the URI path here.
$pubUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pubRel  = (string)preg_replace('#^/api/public/?#i', '', (string)$pubUri);
$segments = array_values(array_filter(explode('/', trim($pubRel, '/'))));
$first    = strtolower($segments[0] ?? '');

/** @var PDO|null $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

// Fallback: on LiteSpeed/cPanel shared hosting ADMIN_DB may be null because
// the Kernel bootstraps DB lazily or the global is not preserved across requests.
// Try to build a fresh PDO connection from db.php when ADMIN_DB is missing.
if (!$pdo instanceof PDO) {
    $__paths = [
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php',
        dirname(__DIR__, 2) . '/shared/config/db.php',
    ];
    foreach ($__paths as $__f) {
        if ($__f && is_readable($__f)) {
            $__cfg = require $__f;
            if (is_array($__cfg) && isset($__cfg['host'], $__cfg['name'], $__cfg['user'])) {
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                            $__cfg['host'],
                            (int)($__cfg['port'] ?? 3306),
                            $__cfg['name'],
                            $__cfg['charset'] ?? 'utf8mb4'
                        ),
                        $__cfg['user'],
                        $__cfg['pass'] ?? '',
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                         PDO::ATTR_TIMEOUT => 5]
                    );
                    $GLOBALS['ADMIN_DB'] = $pdo; // cache for subsequent requires
                } catch (Throwable $__e) { $pdo = null; }
                break;
            }
        }
    }
    unset($__paths, $__f, $__cfg, $__e);
}

$lang     = $_GET['lang'] ?? 'ar';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(100, max(1, (int)($_GET['per'] ?? $_GET['limit'] ?? 25)));
$offset   = ($page - 1) * $per;
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

/* -------------------------------------------------------
 * Helper: safe PDO list query
 * ----------------------------------------------------- */
$pdoList = function (string $sql, array $params = []) use ($pdo): array {
    if (!$pdo instanceof PDO) return [];
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
};

$pdoOne = function (string $sql, array $params = []) use ($pdo): ?array {
    if (!$pdo instanceof PDO) return null;
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('[public.php pdoOne] ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 200));
        return null;
    }
};

$pdoCount = function (string $sql, array $params = []) use ($pdo): int {
    if (!$pdo instanceof PDO) return 0;
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

/* -------------------------------------------------------
 * Route: Home / health
 * ----------------------------------------------------- */
if ($first === '' || $first === 'home') {
    ResponseFormatter::success(['ok' => true, 'service' => 'QOOQZ Public API', 'time' => date('c')]);
    exit;
}

/* -------------------------------------------------------
 * Route: Current user (for JS-based auth display)
 * GET /api/public/me
 * Returns logged-in user info from session, or null.
 * Used by public.js to update header login button without
 * relying on PHP session detection in public_context.php.
 * ----------------------------------------------------- */
if ($first === 'me') {
    $sessUser = $_SESSION['user'] ?? null;
    $sessUserId = isset($sessUser['id']) ? (int)$sessUser['id'] : 0;
    if ($sessUser && $sessUserId > 0) {
        ResponseFormatter::success([
            'user' => [
                'id'    => $sessUserId,
                'name'  => $sessUser['name'] ?? $sessUser['username'] ?? '',
                'email' => $sessUser['email'] ?? '',
            ],
        ]);
    } else {
        ResponseFormatter::success(['user' => null]);
    }
    exit;
}

/* -------------------------------------------------------
 * Route dispatcher — maps $first to a sub-file under public/
 * Each sub-file receives all variables defined above:
 *   $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 * ----------------------------------------------------- */
$_pubRoutes = [
    'ui'                 => 'ui',
    'products'           => 'products',
    'categories'         => 'categories',
    'jobs'               => 'jobs',
    'entity'             => 'entity',
    'entities'           => 'entities',
    'tenants'            => 'tenants',
    'vendors'            => 'vendors',
    'entity_types'       => 'entity_types',
    'homepage_sections'  => 'homepage_sections',
    'banners'            => 'banners',
    'discounts'          => 'discounts',
    'brands'             => 'brands',
    'notifications'      => 'notifications',
    'cart'               => 'cart',
    'orders'             => 'orders',
    'job_applications'   => 'job_applications',
    'addresses'          => 'addresses',
    'register'           => 'register',
    'wishlist'           => 'wishlist',
    'recent'             => 'recent',
    'compare'            => 'compare',
    'bundles'            => 'bundles',
    'auctions'           => 'auctions',
];

$_pubFile = isset($_pubRoutes[$first])
    ? __DIR__ . '/public/' . $_pubRoutes[$first] . '.php'
    : null;

if ($_pubFile && file_exists($_pubFile)) {
    require $_pubFile;
    exit;
}

ResponseFormatter::notFound('Public route not found: /' . ($first ?: ''));