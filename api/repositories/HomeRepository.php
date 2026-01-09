<?php
declare(strict_types=1);

class HomeRepository
{
    private mysqli $conn;
    private string $lang;

    public function __construct(mysqli $conn, string $lang = 'en')
    {
        $this->conn = $conn;
        $this->lang = $lang;
    }

    /* =======================
       UI / THEME
    ======================== */

    public function getActiveTheme(): array
    {
        return [
            'name' => 'default',
            'mode' => 'light'
        ];
    }

    public function getSections(): array
    {
        return [
            'featured_products',
            'new_products',
            'hot_products',
            'featured_vendors'
        ];
    }

    public function getBanners(): array
    {
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

    /* =======================
       PRODUCTS
    ======================== */

    public function getFeaturedProducts(int $limit = 8): array
    {
        $sql = "
            SELECT
                p.id,
                p.slug,
                p.is_featured,
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

    public function getNewProducts(int $limit = 8): array
    {
        $sql = "
            SELECT
                p.id,
                p.slug,
                p.created_at,
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

    public function getHotProducts(int $limit = 8): array
    {
        // لا يوجد نظام عروض حاليًا – نعيد مصفوفة فارغة آمنة
        return [];
    }

    /* =======================
       VENDORS
    ======================== */

    public function getFeaturedVendors(int $limit = 8): array
    {
        $sql = "
            SELECT
                v.id,
                v.store_name,
                v.slug,
                v.logo_url,
                v.rating_average,
                v.total_products
            FROM vendors v
            WHERE v.is_featured = 1
              AND v.status = 'approved'
            ORDER BY v.rating_average DESC
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $limit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
