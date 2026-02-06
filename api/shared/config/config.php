<?php
declare(strict_types=1);

// ---------------------------
// 1. تحميل .env أولاً (مثل السابق)
// ---------------------------
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key && !getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// ---------------------------
// 2. إعدادات ConfigLoader-compatible array
// ---------------------------
return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'development',
        'debug' => getenv('DEBUG') === 'true' || getenv('DEBUG') === '1',
        'domain' => getenv('APP_DOMAIN') ?: 'localhost',
        'name' => getenv('SITE_NAME') ?: 'My Site',
        'company' => getenv('COMPANY_NAME') ?: 'My Company',
        'support_email' => getenv('SUPPORT_EMAIL') ?: 'support@example.com',
        'default_lang' => getenv('DEFAULT_LANG') ?: 'ar',
        'default_currency' => getenv('DEFAULT_CURRENCY') ?: 'SAR',
        'timezone' => getenv('TIMEZONE') ?: 'Asia/Riyadh',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'database',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
    ],
    'api' => [
        'version' => getenv('API_VERSION') ?: 'v1',
        'jwt_secret' => getenv('JWT_SECRET') ?: 'your_default_jwt_secret_change_this',
        'jwt_expiry' => (int)(getenv('JWT_EXPIRY') ?: 3600),
        'rate_limit' => (int)(getenv('API_RATE_LIMIT') ?: 1000),
        'allowed_origins' => getenv('ALLOWED_ORIGINS') ? explode(',', getenv('ALLOWED_ORIGINS')) : ['*'],
    ],
    'upload' => [
        'max_size' => (int)(getenv('MAX_UPLOAD_SIZE') ?: 10485760),
        'dir' => getenv('UPLOAD_DIR') ?: __DIR__ . '/../../uploads/',
        'allowed_types' => ['jpg','png','pdf','docx'],
    ],
    'cache' => [
        'driver' => getenv('CACHE_DRIVER') ?: 'file',
        'redis_host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'redis_port' => (int)(getenv('REDIS_PORT') ?: 6379),
    ],
    'queue' => [
        'driver' => getenv('QUEUE_DRIVER') ?: 'database',
    ],
    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: 'smtp',
        'host' => getenv('MAIL_HOST') ?: 'smtp.example.com',
        'port' => (int)(getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    ],
    'sms' => [
        'driver' => getenv('SMS_DRIVER') ?: 'twilio',
        'api_key' => getenv('SMS_API_KEY') ?: '',
        'from' => getenv('SMS_FROM') ?: '',
    ],
    'rates' => [
        'tax' => (float)(getenv('DEFAULT_TAX_RATE') ?: 0.15),
        'commission' => (float)(getenv('COMMISSION_RATE') ?: 0.05),
    ],
    'security' => [
        'csrf_expiry' => (int)(getenv('CSRF_TOKEN_EXPIRY') ?: 3600),
        'session_lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200),
        'multi_tenant' => getenv('MULTI_TENANT') === 'true',
        'tenant_db_prefix' => getenv('TENANT_DB_PREFIX') ?: 'tenant_',
    ],
    'logs' => [
        'level' => getenv('LOG_LEVEL') ?: 'info',
        'file' => getenv('LOG_FILE') ?: __DIR__ . '/../logs/app.log',
        'notification_driver' => getenv('NOTIFICATION_DRIVER') ?: 'database',
    ],
    'production' => [
        'maintenance_mode' => getenv('MAINTENANCE_MODE') === 'true',
        'backup_enabled' => getenv('BACKUP_ENABLED') === 'true',
    ],
];
