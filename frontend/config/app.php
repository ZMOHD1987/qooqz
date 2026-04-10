<?php
/**
 * frontend/config/app.php
 * Application configuration for QOOQZ public frontend
 */

return [
    'name'         => 'QOOQZ',
    'default_lang' => 'ar',
    'fallback_lang'=> 'en',
    'currency'     => 'USD',
    'timezone'     => 'UTC',

    // Public frontend paths
    'base_path'    => '/frontend/public',
    'assets_path'  => '/frontend/assets',
    'login_path'   => '/frontend/login.php',
    'register_path'=> '/frontend/register.php',
    'profile_path' => '/frontend/profile.php',
    'logout_path'  => '/frontend/logout.php',

    // Feature flags
    'features' => [
        'cart'          => true,
        'wishlist'      => true,
        'compare'       => true,
        'notifications' => true,
        'auctions'      => true,
        'jobs'          => true,
    ],

    // Pagination defaults
    'per_page'     => 12,
    'max_per_page' => 100,
];