<?php
    //htdocs/api/services/ThemeService.php
declare(strict_types=1);

class ThemeService
{
    private PDO $db;
    private int $themeId;

    public function __construct(PDO $db, ?int $themeId = null)
    {
        $this->db = $db;
        $this->themeId = $themeId ?? $this->getDefaultThemeId();
    }

    private function getDefaultThemeId(): int
    {
        $stmt = $this->db->query("SELECT id FROM themes WHERE is_default = 1 LIMIT 1");
        return (int)($stmt->fetchColumn() ?: 1);
    }

    public function getAll(): array
    {
        return [
            'theme'        => $this->getTheme(),
            'colors'       => $this->getColors(),
            'buttons'      => $this->getButtons(),
            'cards'        => $this->getCards(),
            'fonts'        => $this->getFonts(),
            'design'       => $this->getDesignSettings(),
            'system'       => $this->getSystemSettings(),
            'banners'      => $this->getBanners()
        ];
    }

    private function getTheme(): array
    {
        $q = $this->db->prepare("SELECT * FROM themes WHERE id=? AND is_active=1");
        $q->execute([$this->themeId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function getColors(): array
    {
        return $this->fetchAll(
            "SELECT setting_key, color_value, category 
             FROM color_settings 
             WHERE is_active=1 AND (theme_id=? OR theme_id IS NULL)
             ORDER BY sort_order",
            [$this->themeId]
        );
    }

    private function getButtons(): array
    {
        return $this->fetchAll(
            "SELECT * FROM button_styles 
             WHERE is_active=1 AND (theme_id=? OR theme_id IS NULL)",
            [$this->themeId]
        );
    }

    private function getCards(): array
    {
        return $this->fetchAll(
            "SELECT * FROM card_styles 
             WHERE is_active=1 AND (theme_id=? OR theme_id IS NULL)",
            [$this->themeId]
        );
    }

    private function getFonts(): array
    {
        return $this->fetchAll(
            "SELECT setting_key, font_family, font_size, font_weight, line_height, category 
             FROM font_settings 
             WHERE is_active=1 AND (theme_id=? OR theme_id IS NULL)
             ORDER BY sort_order",
            [$this->themeId]
        );
    }

    private function getDesignSettings(): array
    {
        return $this->fetchKeyValue(
            "SELECT setting_key, setting_value 
             FROM design_settings 
             WHERE is_active=1 AND (theme_id=? OR theme_id IS NULL)",
            [$this->themeId]
        );
    }

    private function getSystemSettings(): array
    {
        return $this->fetchKeyValue(
            "SELECT setting_key, setting_value 
             FROM system_settings 
             WHERE is_public=1"
        );
    }

    private function getBanners(): array
    {
        return $this->fetchAll(
            "SELECT * FROM banners 
             WHERE is_active=1 
             AND (theme_id=? OR theme_id IS NULL)
             AND (start_date IS NULL OR start_date <= NOW())
             AND (end_date IS NULL OR end_date >= NOW())
             ORDER BY sort_order",
            [$this->themeId]
        );
    }

    // ---------- Helpers ----------
    private function fetchAll(string $sql, array $params = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchKeyValue(string $sql, array $params = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($params);
        $out = [];
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }
}
