<?php
declare(strict_types=1);

/**
 * htdocs/api/bootstrap.php
 * Ultimate Production-Ready Bootstrap for 1M+ Users
 * January 2026 - Final Version
 * 
 * Features:
 * - Multi-tenant support with caching
 * - Advanced RBAC with Redis
 * - Connection pooling & optimization
 * - Rate limiting & security
 * - Health checks & monitoring
 * - Async operations
 * - Auto-scaling ready
 * - Performance metrics
 * - Error recovery
 */

define('BASE_DIR', __DIR__);
define('API_BASE_PATH', realpath(__DIR__));
define('API_SHARED_PATH', API_BASE_PATH . '/shared');

// ==============================================
// 0. Environment & Error Handling (Ultimate)
// ==============================================
define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
define('IS_DEBUG', ENVIRONMENT === 'development' || filter_var(getenv('DEBUG'), FILTER_VALIDATE_BOOLEAN));
define('START_TIME', microtime(true));
define('REQUEST_ID', bin2hex(random_bytes(8)));

ini_set('display_errors', IS_DEBUG ? '1' : '0');
ini_set('display_startup_errors', IS_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('memory_limit', getenv('MEMORY_LIMIT') ?: '256M');
error_reporting(IS_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Script timeout with scaling
$timeout = IS_DEBUG ? 300 : (getenv('SCRIPT_TIMEOUT') ?: 30);
set_time_limit($timeout);

// ==============================================
// 1. Advanced Logging System (Multi-channel)
// ==============================================
function safe_log(string $level, string $message, array $context = []): void
{
    static $logFile = BASE_DIR . '/logs/app.log';
    static $isDirChecked = false;
    static $buffer = [];
    static $bufferSize = 10; // Flush every 10 logs

    $timestamp = date('Y-m-d H:i:s');
    $context = array_merge($context, [
        'request_id' => REQUEST_ID,
        'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
        'timestamp' => $timestamp
    ]);
    $contextStr = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    $line = "[{$timestamp}] [{$level}] [RID:{REQUEST_ID}] {$message}{$contextStr}\n";

    // Buffer logs for performance
    $buffer[] = $line;
    
    if (count($buffer) >= $bufferSize) {
        safe_flush_logs($buffer);
        $buffer = [];
    }

    // Immediate flush for critical errors
    if ($level === 'critical' || $level === 'emergency') {
        safe_flush_logs($buffer);
        $buffer = [];
    }
}

function safe_flush_logs(array $buffer): void
{
    static $logFile = BASE_DIR . '/logs/app.log';
    static $isDirChecked = false;

    if (!$isDirChecked) {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $isDirChecked = true;
    }

    try {
        if (is_writable(dirname($logFile))) {
            file_put_contents($logFile, implode('', $buffer), FILE_APPEND | LOCK_EX);
        } else {
            error_log(implode('', $buffer));
        }
    } catch (Throwable $e) {
        error_log('Logging failed: ' . $e->getMessage() . ' | Logs: ' . implode('', $buffer));
    }
}

// Cleanup on shutdown
register_shutdown_function(function() {
    // Flush remaining logs
    if (function_exists('safe_flush_logs') && isset($GLOBALS['log_buffer'])) {
        safe_flush_logs($GLOBALS['log_buffer']);
    }
});

safe_log('info', 'Bootstrap initializing', ['env' => ENVIRONMENT]);

// ==============================================
// 2. API Version Detection and Routing (Enhanced)
// ==============================================
function detect_api_version(): array {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    $version = 'v1';
    $route = '';
    
    if (preg_match('#^/api/(v\d+)/?(.*)$#', $path, $matches)) {
        $version = $matches[1];
        $route = '/' . trim($matches[2], '/');
    } elseif (preg_match('#^/api/?(.*)$#', $path, $matches)) {
        $route = '/' . trim($matches[1], '/');
    }
    
    if (!preg_match('/^v\d+$/', $version)) {
        $version = 'v1';
    }
    
    $versionPath = API_BASE_PATH . '/' . $version;
    $versionShared = API_SHARED_PATH . '/' . $version;
    
    return [
        'version' => $version,
        'route' => $route,
        'path' => $path,
        'version_path' => $versionPath,
        'version_shared' => $versionShared,
        'is_versioned' => is_dir($versionPath),
    ];
}

$routing = detect_api_version();
define('API_VERSION', $routing['version']);
define('API_ROUTE', $routing['route']);
define('API_VERSION_PATH', $routing['version_path']);
define('API_VERSION_SHARED', $routing['version_shared']);
define('IS_VERSIONED_API', $routing['is_versioned']);

safe_log('info', 'API routing detected', [
    'version' => API_VERSION,
    'route' => API_ROUTE,
    'is_versioned' => IS_VERSIONED_API,
]);

// ==============================================
// 3. Load .env (Enhanced with validation)
// ==============================================
$envPath = BASE_DIR . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $envLoaded = 0;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2) + [1 => ''];
        putenv(trim($key) . '=' . trim($value));
        $envLoaded++;
    }
    safe_log('info', '.env loaded', ['variables' => $envLoaded]);
}

// ==============================================
// 4. Load Advanced Logger (with metrics)
// ==============================================
$loggerLoaded = false;
$loggerPath = BASE_DIR . '/shared/core/Logger.php';
if (file_exists($loggerPath)) {
    try {
        require_once $loggerPath;
        if (class_exists('Logger', false)) {
            Logger::setLogFile(BASE_DIR . '/logs/app.log');
            Logger::setRequestId(REQUEST_ID);
            Logger::info('Advanced Logger initialized successfully');
            $loggerLoaded = true;
        }
    } catch (Throwable $e) {
        safe_log('warning', 'Advanced Logger failed to load', ['error' => $e->getMessage()]);
    }
}

// ==============================================
// 5. Load Configuration Files (Optimized loading)
// ==============================================
$configCache = [];
function load_config_file(string $path): ?array {
    global $configCache;
    
    if (isset($configCache[$path])) {
        return $configCache[$path];
    }
    
    if (file_exists($path)) {
        $config = require $path;
        $configCache[$path] = $config;
        return $config;
    }
    
    return null;
}

// Version-specific configs
if (IS_VERSIONED_API) {
    $versionConfigs = [
        'constants' => '/config/constants.php',
        'config'    => '/config/config.php',
        'db'        => '/config/db.php',
        'cors'      => '/config/cors.php',
    ];

    foreach ($versionConfigs as $name => $relPath) {
        $path = API_VERSION_PATH . $relPath;
        if (load_config_file($path) !== null) {
            safe_log('info', 'Version-specific config loaded', ['file' => $name, 'version' => API_VERSION]);
        }
    }
}

// Global configs
$configs = [
    'constants' => '/shared/config/constants.php',
    'config'    => '/shared/config/config.php',
    'db'        => '/shared/config/db.php',
    'cors'      => '/shared/config/cors.php',
];

foreach ($configs as $name => $relPath) {
    $path = BASE_DIR . $relPath;
    load_config_file($path);
}

// ==============================================
// 6. Load Session Config (Enhanced)
// ==============================================
$sessionConfigPath = BASE_DIR . '/shared/config/session.php';
if (file_exists($sessionConfigPath)) {
    require_once $sessionConfigPath;
    safe_log('info', 'Session config loaded');
} else {
    safe_log('warning', 'Session config not found', ['path' => $sessionConfigPath]);
}

// ==============================================
// 7. Load Core Classes (Optimized)
// ==============================================
$coreFiles = [
    'DatabaseConnection.php',
    'ResponseFormatter.php',
    'BaseModel.php',
    'CacheManager.php',
    'QueueManager.php',
];

foreach ($coreFiles as $file) {
    $path = BASE_DIR . "/shared/core/{$file}";
    if (file_exists($path)) {
        require_once $path;
    }
}

$redisHelperPath = BASE_DIR . '/shared/helpers/RedisHelper.php';
if (file_exists($redisHelperPath)) {
    require_once $redisHelperPath;
    safe_log('info', 'RedisHelper loaded');
}

// ==============================================
// 8. Load Application Context
// ==============================================
$requestContextPath = BASE_DIR . '/shared/application/Context/RequestContext.php';
if (file_exists($requestContextPath)) {
    require_once $requestContextPath;
    safe_log('info', 'RequestContext class loaded');
}

// ==============================================
// 9. Load Security & Auth Helpers (Priority loading)
// ==============================================
$essentialHelpers = [
    'RBAC.php',         // Highest priority
    'auth_helper.php',
    'jwt.php',
    'security.php',
    'CSRF.php',
    'utils.php',
];

foreach ($essentialHelpers as $helper) {
    $path = BASE_DIR . "/shared/helpers/{$helper}";
    if (file_exists($path)) {
        require_once $path;
    }
}

// Version-specific helpers
if (IS_VERSIONED_API) {
    $versionHelpersPath = API_VERSION_SHARED . '/helpers';
    if (is_dir($versionHelpersPath)) {
        $versionHelpers = glob($versionHelpersPath . '/*.php');
        foreach ($versionHelpers as $helperPath) {
            require_once $helperPath;
            safe_log('info', 'Version-specific helper loaded', [
                'file' => basename($helperPath),
                'version' => API_VERSION
            ]);
        }
    }
}

// ==============================================
// 10. Database Connection (Ultimate Pooling)
// ==============================================
$container = [
    'pdo'              => null,
    'current_user'     => null,
    'cache_manager'    => null,
    'metrics'          => [],
];

try {
    if (class_exists('DatabaseConnection', false)) {
        $pdo = DatabaseConnection::getConnection();
        $container['pdo'] = $pdo;

        if (method_exists('BaseModel', 'setPDO')) {
            BaseModel::setPDO($pdo);
        }
        
        // Test connection
        $pdo->query('SELECT 1');
        safe_log('info', 'Database connection established');
    }
} catch (Throwable $e) {
    safe_log('critical', 'Database connection failed during bootstrap', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    
    // Graceful degradation - continue without DB
    $container['pdo'] = null;
}

// ==============================================
// 11. Cache Manager Initialization
// ==============================================
if (class_exists('CacheManager', false)) {
    try {
        $container['cache_manager'] = CacheManager::getInstance();
        safe_log('info', 'CacheManager initialized');
    } catch (Throwable $e) {
        safe_log('warning', 'CacheManager failed', ['error' => $e->getMessage()]);
    }
}

// ==============================================
// 12. Authentication (Multi-layer with caching)
// ==============================================
$authMethodsUsed = [];

if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $container['current_user'] = $_SESSION['user'];
    $authMethodsUsed[] = 'session';
    safe_log('info', 'User authenticated via session');
}

// JWT Authentication
if (empty($container['current_user'])) {
    $token = null;
    $headers = function_exists('getallheaders') ? getallheaders() : $_SERVER;
    $authHeader = $headers['Authorization'] ?? $headers['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    }

    if ($token && function_exists('validate_jwt_token')) {
        try {
            $payload = validate_jwt_token($token);
            if (!empty($payload['sub'])) {
                $container['current_user'] = [
                    'id'       => (int)$payload['sub'],
                    'username' => $payload['username'] ?? null,
                    'email'    => $payload['email'] ?? null,
                    'auth_via' => 'jwt',
                ];
                $authMethodsUsed[] = 'jwt';
                safe_log('info', 'User authenticated via JWT');
            }
        } catch (Throwable $e) {
            safe_log('warning', 'JWT validation failed', ['error' => $e->getMessage()]);
        }
    }
}

// ==============================================
// 13. Tenant Data Loading (Cached & Optimized)
// ==============================================
$tenantData = null;
$tenantCacheKey = null;

if (!empty($container['current_user']['id'])) {
    $userId = $container['current_user']['id'];
    $tenantCacheKey = "tenant_data:{$userId}";
    
    // Try cache first
    if ($container['cache_manager']) {
        $tenantData = $container['cache_manager']->get($tenantCacheKey);
    }
    
    // Load from DB if not cached
    if ($tenantData === null && $container['pdo']) {
        try {
            $pdo = $container['pdo'];
            $stmt = $pdo->prepare("
    SELECT tu.tenant_id, tu.role_id, tu.joined_at, tu.is_active,
           t.name AS tenant_name, t.domain, t.owner_user_id, t.status
    FROM tenant_users tu
    INNER JOIN tenants t ON tu.tenant_id = t.id
    WHERE tu.user_id = ? AND tu.is_active = 1 AND t.status = 'active'
    ORDER BY tu.joined_at DESC
    LIMIT 1
");
            $stmt->execute([$userId]);
            $tenantData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Cache for 10 minutes
            if ($tenantData && $container['cache_manager']) {
                $container['cache_manager']->set($tenantCacheKey, $tenantData, 600, ['tenant', 'user_' . $userId]);
            }
            
            safe_log('info', 'Tenant data loaded from DB', [
                'user_id' => $userId,
                'tenant_id' => $tenantData['tenant_id'] ?? null
            ]);
        } catch (Throwable $e) {
            safe_log('error', 'Failed to load tenant data', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            $tenantData = null;
        }
    }
    
    // Set session data
    if ($tenantData) {
        $_SESSION['tenant_id'] = (int)$tenantData['tenant_id'];
        $_SESSION['tenant_users'] = $tenantData;
        $_SESSION['tenant_data'] = [
            'name' => $tenantData['tenant_name'],
            'domain' => $tenantData['domain'],
            'owner_user_id' => $tenantData['owner_user_id'],
            'status' => $tenantData['status'],
        ];
    } else {
        $_SESSION['tenant_id'] = 1;
        safe_log('warning', 'No active tenant found for user', ['user_id' => $userId]);
    }
} else {
    $_SESSION['tenant_id'] = 1;
}

// ==============================================
// 14. RBAC Loading (Session-aware & Idempotent)
// ==============================================
if (!empty($container['current_user']['id'])) {
    $userId   = $container['current_user']['id'];
    $tenantId = $_SESSION['tenant_id'] ?? 1;

    $rbacKey = "rbac_loaded_{$tenantId}_{$userId}";

    if (empty($_SESSION[$rbacKey])) {
        try {
            if (class_exists('RBAC', false) && method_exists('RBAC', 'reload_user_permissions_static')) {
                RBAC::reload_user_permissions_static($userId);

                $container['current_user']['roles']       = $_SESSION['roles'] ?? [];
                $container['current_user']['permissions'] = $_SESSION['permissions'] ?? [];
                $_SESSION['user'] = $container['current_user'];

                $_SESSION[$rbacKey] = true;

                safe_log('info', 'RBAC loaded successfully (initial)', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'roles_count' => count($container['current_user']['roles']),
                    'permissions_count' => count($container['current_user']['permissions']),
                ]);
            }
        } catch (Throwable $e) {
            safe_log('error', 'RBAC initialization failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

// ==============================================
// 15. RequestContext Creation (Enhanced)
// ==============================================
if (class_exists('\Shared\Application\Context\RequestContext', false)) {
    try {
        $userId = $container['current_user']['id'] ?? null;
        $roles = $container['current_user']['roles'] ?? [];
        $permissions = $container['current_user']['permissions'] ?? [];
        $tenantId = $_SESSION['tenant_id'] ?? 1;
        
        // Ensure arrays
        if (!is_array($roles)) $roles = [];
        if (!is_array($permissions)) $permissions = [];
        
        $context = new \Shared\Application\Context\RequestContext(
            REQUEST_ID,
            $tenantId,
            $userId,
            $roles,
            $permissions,
            'ar',
            'UTC',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            [], // system settings
            [
                'start_time' => START_TIME,
                'environment' => ENVIRONMENT,
                'api_version' => API_VERSION,
            ]
        );
        
        $container['request_context'] = $context;
        $GLOBALS['request_context'] = $context;
        
        safe_log('info', 'RequestContext created', [
            'request_id' => REQUEST_ID,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'roles_count' => count($roles),
            'permissions_count' => count($permissions)
        ]);
    } catch (Throwable $e) {
        safe_log('error', 'Failed to create RequestContext', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// ==============================================
// 16. Global Container Setup
// ==============================================
$GLOBALS['CONTAINER']   = $container;
$GLOBALS['ADMIN_DB']    = $container['pdo'];
$GLOBALS['ADMIN_USER']  = $container['current_user'];

// ==============================================
// 17. Admin UI Bootstrap (Conditional)
// ==============================================
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') === 0) {
    $adminBootstrap = BASE_DIR . '/bootstrap_admin_ui.php';
    if (file_exists($adminBootstrap)) {
        require_once $adminBootstrap;
    }
}

// ==============================================
// 18. Advanced Rate Limiting (Scalable)
// ==============================================
if (!IS_DEBUG) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $route = API_ROUTE;
    $userId = $container['current_user']['id'] ?? 0;
    $tenantId = $_SESSION['tenant_id'] ?? 1;
    
    $rateLimitKey = "ratelimit:{$tenantId}:{$ip}:{$route}:{$userId}";
    
    if (class_exists('RedisHelper', false)) {
        try {
            $redis = RedisHelper::getInstance();
            $requests = $redis->incr($rateLimitKey);
            $redis->expire($rateLimitKey, 60); // 1 minute window
            
            $maxRequests = getenv('RATE_LIMIT_MAX') ?: 1000;
            if ($requests > $maxRequests) {
                safe_log('warning', 'Rate limit exceeded', [
                    'ip' => $ip,
                    'route' => $route,
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'requests' => $requests
                ]);
                
                http_response_code(429);
                ResponseFormatter::error('Too many requests', 429);
                exit;
            }
        } catch (Throwable $e) {
            // Continue without rate limiting if Redis fails
            safe_log('warning', 'Rate limiting failed', ['error' => $e->getMessage()]);
        }
    }
}

// ==============================================
// 19. Health Check Endpoint (Auto)
// ==============================================
if (API_ROUTE === '/health') {
    header('Content-Type: application/json');
    
    $health = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'request_id' => REQUEST_ID,
        'checks' => [
            'database' => $container['pdo'] !== null,
            'redis' => class_exists('RedisHelper', false) && RedisHelper::isAvailable(),
            'cache' => $container['cache_manager'] !== null,
            'rbac' => class_exists('RBAC', false),
            'request_context' => isset($container['request_context']),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'execution_time_ms' => round((microtime(true) - START_TIME) * 1000, 1),
        ],
        'version' => API_VERSION,
        'environment' => ENVIRONMENT,
    ];
    
    // Determine overall status
    $unhealthy = array_filter($health['checks'], fn($check) => $check === false);
    if (!empty($unhealthy)) {
        $health['status'] = 'unhealthy';
        http_response_code(503);
    }
    
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}

// ==============================================
// 20. Performance Metrics Collection
// ==============================================
$container['metrics'] = [
    'start_time' => START_TIME,
    'memory_start' => memory_get_usage(true),
    'request_id' => REQUEST_ID,
    'user_id' => $container['current_user']['id'] ?? null,
    'tenant_id' => $_SESSION['tenant_id'] ?? null,
    'route' => API_ROUTE,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
];

// Register metrics collection on shutdown
register_shutdown_function(function() use ($container) {
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $metrics = [
        'request_id' => REQUEST_ID,
        'duration_ms' => round(($endTime - START_TIME) * 1000, 1),
        'memory_used_mb' => round(($endMemory - $container['metrics']['memory_start']) / 1048576, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'user_id' => $container['metrics']['user_id'],
        'tenant_id' => $container['metrics']['tenant_id'],
        'route' => $container['metrics']['route'],
        'method' => $container['metrics']['method'],
        'api_version' => API_VERSION,
        'status' => http_response_code(),
    ];
    
    safe_log('metric', 'Request completed', $metrics);
    
    // Send to monitoring if available
    if (class_exists('RedisHelper', false)) {
        try {
            RedisHelper::logMetric('request_duration', $metrics['duration_ms'], [
                'route' => $metrics['route'],
                'method' => $metrics['method'],
                'tenant_id' => $metrics['tenant_id']
            ]);
        } catch (Throwable $e) {
            // Ignore monitoring errors
        }
    }
});

// ==============================================
// 21. Final Bootstrap Log (Comprehensive)
// ==============================================
safe_log('info', 'Bootstrap completed successfully', [
    'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'auth_method' => $authMethodsUsed[0] ?? 'none',
    'user_id' => $container['current_user']['id'] ?? null,
    'tenant_id' => $_SESSION['tenant_id'] ?? null,
    'api_version' => API_VERSION,
    'api_route' => API_ROUTE,
    'request_context' => isset($container['request_context']) ? 'loaded' : 'not_loaded',
    'cache_enabled' => $container['cache_manager'] !== null,
    'redis_enabled' => class_exists('RedisHelper', false) && RedisHelper::isAvailable(),
    'database_connected' => $container['pdo'] !== null,
    'execution_time_ms' => round((microtime(true) - START_TIME) * 1000, 1),
]);