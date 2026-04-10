<?php
/**
 * frontend/config/seo.php
 * SEO configuration for QOOQZ public frontend
 */

// Global SEO defaults
define('SEO_DEFAULT_TITLE',       'QOOQZ — منصة التسوق العالمية');
define('SEO_DEFAULT_DESCRIPTION', 'أفضل منصة للتسوق الإلكتروني — منتجات، متاجر، عروض، وظائف');
define('SEO_DEFAULT_KEYWORDS',    'تسوق, متجر إلكتروني, منتجات, عروض, وظائف, مزادات, خدمات');

// Page-specific SEO configuration
return [
    'pages' => [
        'home' => [
            'title'       => 'QOOQZ — منصة التسوق العالمية',
            'description' => 'اكتشف أفضل المنتجات والعروض من آلاف المتاجر',
        ],
        'products' => [
            'title'       => 'المنتجات — QOOQZ',
            'description' => 'تصفح آلاف المنتجات من أفضل المتاجر',
        ],
        'categories' => [
            'title'       => 'التصنيفات — QOOQZ',
            'description' => 'تصفح جميع التصنيفات والأقسام',
        ],
        'jobs' => [
            'title'       => 'الوظائف — QOOQZ',
            'description' => 'ابحث عن فرص عمل مميزة',
        ],
        'entities' => [
            'title'       => 'المتاجر — QOOQZ',
            'description' => 'اكتشف المتاجر والبائعين المعتمدين',
        ],
        'auctions' => [
            'title'       => 'المزادات — QOOQZ',
            'description' => 'شارك في المزادات واحصل على أفضل الصفقات',
        ],
        'discounts' => [
            'title'       => 'العروض والخصومات — QOOQZ',
            'description' => 'أحدث العروض والخصومات الحصرية',
        ],
    ],

    // Structured data (JSON-LD)
    'organization' => [
        '@type' => 'Organization',
        'name'  => 'QOOQZ',
        'url'   => 'https://qooqz.com',
    ],
];