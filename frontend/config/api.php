<?php
/**
 * frontend/config/api.php
 * API configuration for QOOQZ public frontend
 */

return [
    'base_path'    => '/api',
    'timeout'      => 10,
    'token_header' => 'Authorization',

    // Public API endpoints used by the frontend
    'endpoints' => [
        'banners'           => '/public/banners',
        'products'          => '/public/products',
        'categories'        => '/public/categories',
        'brands'            => '/public/brands',
        'entities'          => '/public/entities',
        'tenants'           => '/public/tenants',
        'jobs'              => '/public/jobs',
        'discounts'         => '/public/discounts',
        'homepage_sections' => '/public/homepage_sections',
        'notifications'     => '/notifications',
        'theme'             => '/public/theme',
        'auth'              => '/auth',
        'cart'              => '/cart',
        'wishlist'          => '/wishlist',
        'orders'            => '/orders',
    ],

    // Cache durations (seconds)
    'cache' => [
        'theme'      => 3600,
        'categories' => 1800,
        'products'   => 300,
        'banners'    => 600,
    ],
];