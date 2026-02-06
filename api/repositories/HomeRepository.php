<?php
declare(strict_types=1);

/**
 * HomeRepository - QOOQZ Platform
 * المسؤول عن تنفيذ الاستعلامات المباشرة لقاعدة البيانات لبيانات الصفحة الرئيسية
 */
class HomeRepository
{
    private mysqli $conn;
    private string $lang;

    public function __construct(mysqli $conn, string $lang = 'en')
    {
        $this->conn = $conn;
        $this->lang = $lang;
    }

    /* =========================================
       1) إعدادات الواجهة (UI & Theme)
    ============================================ */

    public function getActiveTheme(): array
    {
        return [
            'name' => 'default',
            'mode' => 'light'
        ];
    }

    public function getSections(): array
    {
        // ترتيب الأقسام كما ستظهر في الصفحة الرئيسية
        return [
            'featured_products',
            'new_products',
            'hot_products',
            'featured_vendors'
        ];
    }

    public function getBanners(): array
    {
        // يمكنك لاحقاً ربطها بجدول banners في قاعدة البيانات
        return [];
    }

    public function getColors(): array
    {
        return [
            'primary'    => '#0d6efd',
            'secondary'  => '#6c757d',
            'background' => '#ffffff'
        ];
    }

    public function getFonts(): array
    {
        return [
            'base' => 'Cairo, sans-serif'
        ];
    }

    public function getButtons(): array
    {
        return [
            'radius' => '6px'
        ];
    }

    public function getCards(): array
    {
        return [
            'shadow' => true
        ];
    }

    public function getDesignSettings(): array
    {
        return [
            'layout'  => 'default',
            'rtl'     => in_array($this->lang, ['ar', 'fa', 'ur', 'he'], true),
            'spacing' => 'normal'
        ];
    }

    /* =========================================
       2) المنتجات (Products)
    ============================================ */

    /**
     * جلب المنتجات المميزة
     */
    public function getFeaturedProducts(int $limit = 8): array
    {
        $sql = "
            SELECT 
                p.id, 
                p.slug, 
                p.is_featured, 
                p.image_url,
                COALESCE(pt.name, p.slug) AS name
            FROM products p
            LEFT JOIN product_translations pt 
                ON pt.product_id = p.id 
               AND pt.language_code = ?
            WHERE p.is_active = 1 
              AND p.is_featured = 1
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('si', $this->lang, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * جلب أحدث المنتجات
     */
    public function getNewProducts(int $limit = 8): array
    {
        $sql = "
            SELECT 
                p.id, 
                p.slug, 
                p.created_at, 
                p.image_url,
                COALESCE(pt.name, p.slug) AS name
            FROM products p
            LEFT JOIN product_translations pt 
                ON pt.product_id = p.id 
               AND pt.language_code = ?
            WHERE p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('si', $this->lang, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getHotProducts(): array
    {
        // مخصص للعروض الترويجية مستقبلاً
        return [];
    }

    /* =========================================
       3) التجار (Vendors)
    ============================================ */

    /**
     * جلب التجار المعتمدين (المميزين أولاً)
     */
    public function getFeaturedVendors(int $limit = 6): array
    {
        $sql = "
            SELECT 
                v.id, 
                v.store_name, 
                v.slug, 
                v.logo_url, 
                v.rating_average, 
                v.total_products,
                v.is_featured,
                COALESCE(vt.description, '') AS description
            FROM vendors v
            LEFT JOIN vendor_translations vt 
                ON vt.vendor_id = v.id 
               AND vt.language_code = ?
            WHERE v.status = 'approved' 
              AND v.is_branch = 0
            ORDER BY v.is_featured DESC, v.rating_average DESC 
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('si', $this->lang, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * جلب جميع التجار المعتمدين (لصفحة المتاجر الشاملة)
     */
    public function getAllPublicVendors(): array
    {
        $sql = "
            SELECT 
                v.id, 
                v.store_name, 
                v.slug, 
                v.logo_url, 
                v.cover_image_url, 
                v.rating_average, 
                v.total_products,
                v.is_featured,
                COALESCE(vt.description, '') AS description
            FROM vendors v
            LEFT JOIN vendor_translations vt 
                ON vt.vendor_id = v.id 
               AND vt.language_code = ?
            WHERE v.status = 'approved'
            ORDER BY v.is_featured DESC, v.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('s', $this->lang);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}