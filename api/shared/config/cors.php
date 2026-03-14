<?php
// htdocs/api/shared/config/cors.php
// Returns array for ConfigLoader compatibility

// 1. قائمة الأصول المسموح بها (يمكن تعديلها حسب الموقع)
$allowedOrigins = getenv('ALLOWED_ORIGINS') 
    ? array_map('trim', explode(',', getenv('ALLOWED_ORIGINS')))
    : [
        'https://hcsfcs.top',
        'https://www.hcsfcs.top',
        'https://api.hcsfcs.top',
        'https://admin.hcsfcs.top'
    ];

// 2. تحقق من البيئة الحالية بأمان
$env = getenv('APP_ENV') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'production');
$isDev = $env === 'development';

// 3. إعدادات CORS
return [
    'allowed_origins' => $allowedOrigins,
    'allow_credentials' => true,
    'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    'allow_headers' => 'Content-Type, Authorization, X-CSRF-Token, X-Requested-With',
    'expose_headers' => 'X-CSRF-Token',
    'max_age' => 86400,
    'development_allow_all' => $isDev, // true فقط في بيئة التطوير
];
