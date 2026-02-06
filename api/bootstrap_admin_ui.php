<?php
declare(strict_types=1);

/**
 * htdocs/api/bootstrap_admin_ui.php
 * Fully safe version: fills all ADMIN_UI fields automatically
 */

$logFile = __DIR__ . '/error_log.txt';
function _aui_log(string $m): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('c') . '] bootstrap_admin_ui: ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Start session if not active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_secure' => !empty($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// Load admin context if exists
$ctx = __DIR__ . '/bootstrap_admin_context.php';
if (file_exists($ctx)) {
    @require_once $ctx;
}

// Primary DB
$db = $GLOBALS['ADMIN_DB'] ?? null;

// If no DB, try DatabaseConnection or config
if (empty($db)) {
    $candidates = [
        __DIR__ . '/shared/core/DatabaseConnection.php',
        __DIR__ . '/../shared/core/DatabaseConnection.php',
        __DIR__ . '/../../shared/core/DatabaseConnection.php',
    ];
    foreach ($candidates as $c) {
        if (!class_exists('DatabaseConnection') && file_exists($c)) {
            @require_once $c;
        }
    }
    if (empty($db) && class_exists('DatabaseConnection')) {
        try {
            $maybe = DatabaseConnection::getConnection();
            if ($maybe instanceof PDO) { $db = $maybe; $GLOBALS['ADMIN_DB'] = $db; _aui_log('Connected via DatabaseConnection::getConnection()'); }
        } catch (Throwable $e) { _aui_log('DB connection failed: ' . $e->getMessage()); }
    }
    if (empty($db)) {
        $cfgPaths = [__DIR__.'/config/db.php', __DIR__.'/shared/config/db.php', __DIR__.'/../shared/config/db.php'];
        foreach ($cfgPaths as $cfg) {
            if (!file_exists($cfg)) continue;
            try {
                $cfgRet = @include $cfg;
                if (is_array($cfgRet)) {
                    $user = $cfgRet['username'] ?? ($cfgRet['user'] ?? null);
                    $pass = $cfgRet['password'] ?? ($cfgRet['pass'] ?? null);
                    $dsn = $cfgRet['dsn'] ?? ("mysql:host={$cfgRet['host']};dbname={$cfgRet['name']};charset=utf8mb4");
                    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                    $GLOBALS['ADMIN_DB'] = $db;
                    _aui_log("Created PDO from config {$cfg}");
                    break;
                }
            } catch (Throwable $e) { _aui_log("Failed PDO from {$cfg}: ".$e->getMessage()); }
        }
    }
}

$dbType = $db instanceof PDO ? 'pdo' : null;

// ----- Load user from session or DB -----
$ADMIN_UI = [
    'user' => [
        'id' => null, 'username' => null, 'email' => null, 'role_id' => null,
        'roles' => [], 'permissions' => [], 'is_active' => false,
        'preferred_language' => 'en', 'tenant_id' => null
    ],
    'settings' => [], 'lang' => 'en', 'direction' => 'ltr', 'theme' => []
];

$currentUser = $_SESSION['user'] ?? null;

// If DB available, ensure session user is loaded
if ($db instanceof PDO) {
    try {
        $userId = $currentUser['id'] ?? 0;
        if (!$userId && !empty($_SESSION['user_id'])) { $userId = (int)$_SESSION['user_id']; }
        
        if ($userId > 0) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbUser) {
                // Roles
                $roles = [];
                $permissions = [];
                $isSuperAdmin = false;
                $roleId = $dbUser['role_id'] ?? null;
                
                if ($roleId) {
                    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
                    $stmt->execute([$roleId]);
                    $role = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($role) {
                        $roles = [$role['key_name']];
                        $isSuperAdmin = ($role['key_name'] === 'super_admin');
                        // Permissions
                        if ($isSuperAdmin) {
                            $stmt = $db->prepare("SELECT key_name FROM permissions WHERE tenant_id = ?");
                            $stmt->execute([$dbUser['tenant_id']]);
                            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } else {
                            $stmt = $db->prepare("SELECT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=? AND rp.tenant_id=?");
                            $stmt->execute([$roleId, $dbUser['tenant_id']]);
                            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        }
                    }
                }
                
                $_SESSION['user'] = [
                    'id' => $dbUser['id'],
                    'username' => $dbUser['username'],
                    'email' => $dbUser['email'],
                    'role_id' => $dbUser['role_id'],
                    'tenant_id' => $dbUser['tenant_id'],
                    'preferred_language' => $dbUser['preferred_language'] ?? 'en',
                    'is_active' => !empty($dbUser['is_active'])
                ];
                $_SESSION['roles'] = $roles;
                $_SESSION['permissions'] = $permissions;
                
                $currentUser = $_SESSION['user'];
            }
        }
    } catch (Throwable $e) {
        _aui_log('Failed to load user from DB: '.$e->getMessage());
    }
}

// Fill ADMIN_UI from $currentUser
if (!empty($currentUser)) {
    $ADMIN_UI['user'] = [
        'id' => $currentUser['id'] ?? null,
        'username' => $currentUser['username'] ?? null,
        'email' => $currentUser['email'] ?? null,
        'role_id' => $currentUser['role_id'] ?? null,
        'roles' => $_SESSION['roles'] ?? [],
        'permissions' => $_SESSION['permissions'] ?? [],
        'is_active' => $currentUser['is_active'] ?? false,
        'preferred_language' => $currentUser['preferred_language'] ?? 'en',
        'tenant_id' => $currentUser['tenant_id'] ?? 1
    ];
}

// Language & direction
$lang = $ADMIN_UI['user']['preferred_language'] ?? 'en';
$ADMIN_UI['lang'] = $lang;
$ADMIN_UI['direction'] = in_array(substr($lang,0,2), ['ar','fa','he','ur','ps','sd','ku'], true) ? 'rtl' : 'ltr';

// CSRF
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$ADMIN_UI['csrf_token'] = $_SESSION['csrf_token'];

// Load theme using AdminUiThemeLoader if available
if ($db instanceof PDO) {
    try {
        require_once __DIR__.'/shared/ui/AdminUiThemeLoader.php';
        $loader = new AdminUiThemeLoader($db);
        $tenantId = $ADMIN_UI['user']['tenant_id'] ?? 1;
        $ADMIN_UI['theme'] = $loader->getFullThemeData($tenantId);
    } catch (Throwable $e) { _aui_log('Theme load failed: '.$e->getMessage()); }
}

$GLOBALS['ADMIN_UI'] = $ADMIN_UI;

// Debug endpoint
if (!empty($_GET['__admin_ui_debug']) && $_GET['__admin_ui_debug']==='1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'db_connected' => (bool)$db,
        'db_type' => $dbType,
        'admin_ui' => $ADMIN_UI,
        'session' => [
            'id' => session_id(),
            'name' => session_name(),
            'cookie_received' => $_COOKIE[session_name()] ?? null,
            'keys' => array_keys($_SESSION)
        ]
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    exit;
}

return;
