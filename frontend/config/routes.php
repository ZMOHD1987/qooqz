<?php
/**
 * frontend/config/routes.php
 * Route definitions for QOOQZ public frontend pages
 */

return [
    // Public pages
    ['method' => 'GET', 'path' => '/',                'handler' => 'public/index.php'],
    ['method' => 'GET', 'path' => '/products',        'handler' => 'public/products.php'],
    ['method' => 'GET', 'path' => '/categories',      'handler' => 'public/categories.php'],
    ['method' => 'GET', 'path' => '/catalog',         'handler' => 'public/catalog.php'],
    ['method' => 'GET', 'path' => '/discounts',       'handler' => 'public/discounts.php'],
    ['method' => 'GET', 'path' => '/jobs',            'handler' => 'public/jobs.php'],
    ['method' => 'GET', 'path' => '/job',             'handler' => 'public/job.php'],
    ['method' => 'GET', 'path' => '/entities',        'handler' => 'public/entities.php'],
    ['method' => 'GET', 'path' => '/entity',          'handler' => 'public/entity.php'],
    ['method' => 'GET', 'path' => '/tenants',         'handler' => 'public/tenants.php'],
    ['method' => 'GET', 'path' => '/auctions',        'handler' => 'public/auctions.php'],
    ['method' => 'GET', 'path' => '/auction',         'handler' => 'public/auction.php'],
    ['method' => 'GET', 'path' => '/cart',            'handler' => 'public/cart.php'],
    ['method' => 'GET', 'path' => '/checkout',        'handler' => 'public/checkout.php'],
    ['method' => 'GET', 'path' => '/wishlist',        'handler' => 'public/wishlist.php'],
    ['method' => 'GET', 'path' => '/compare',         'handler' => 'public/compare.php'],
    ['method' => 'GET', 'path' => '/notifications',   'handler' => 'public/notifications.php'],
    ['method' => 'GET', 'path' => '/bundles',         'handler' => 'public/bundles.php'],

    // Auth
    ['method' => 'GET',  'path' => '/login',          'handler' => 'login.php'],
    ['method' => 'POST', 'path' => '/auth/login',     'handler' => ['AuthController', 'login']],
    ['method' => 'GET',  'path' => '/register',       'handler' => 'register.php'],
    ['method' => 'GET',  'path' => '/logout',         'handler' => 'logout.php'],
    ['method' => 'GET',  'path' => '/profile',        'handler' => 'profile.php'],

    // Join flows
    ['method' => 'GET',  'path' => '/join-entity',    'handler' => 'public/join_entity.php'],
    ['method' => 'GET',  'path' => '/join-tenant',    'handler' => 'public/join_tenant.php'],

    // User account pages (require login)
    ['method' => 'GET',  'path' => '/orders',         'handler' => 'public/orders.php'],
    ['method' => 'GET',  'path' => '/tickets',        'handler' => 'public/tickets.php'],
    ['method' => 'GET',  'path' => '/returns',        'handler' => 'public/returns.php'],
];