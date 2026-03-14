<?php
declare(strict_types=1);

// api/v1/models/button_styles/repositories/PdoButtonStylesRepository.php

final class PdoButtonStylesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?string $buttonType = null, ?int $themeId = null): array
    {
        $sql = "
            SELECT id, tenant_id, theme_id, name, slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color, is_active, created_at, updated_at
            FROM button_styles
            WHERE tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId];

        if ($buttonType) {
            $sql .= " AND button_type = :buttonType";
            $params[':buttonType'] = $buttonType;
        }

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY button_type ASC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $slug, ?int $themeId = null): ?array
    {
        $sql = "
            SELECT *
            FROM button_styles
            WHERE tenant_id = :tenantId AND slug = :slug
        ";

        $params = [':tenantId' => $tenantId, ':slug' => $slug];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM button_styles
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");

        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE button_styles
                SET theme_id = :theme_id,
                    name = :name,
                    slug = :slug,
                    button_type = :button_type,
                    background_color = :background_color,
                    text_color = :text_color,
                    border_color = :border_color,
                    border_width = :border_width,
                    border_radius = :border_radius,
                    padding = :padding,
                    font_size = :font_size,
                    font_weight = :font_weight,
                    hover_background_color = :hover_background_color,
                    hover_text_color = :hover_text_color,
                    hover_border_color = :hover_border_color,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':theme_id'               => $data['theme_id'] ?? null,
                ':name'                   => $data['name'],
                ':slug'                   => $data['slug'],
                ':button_type'            => $data['button_type'],
                ':background_color'       => $data['background_color'],
                ':text_color'             => $data['text_color'],
                ':border_color'           => $data['border_color'] ?? null,
                ':border_width'           => (int)($data['border_width'] ?? 0),
                ':border_radius'          => (int)($data['border_radius'] ?? 4),
                ':padding'                => $data['padding'] ?? '10px 20px',
                ':font_size'              => $data['font_size'] ?? '14px',
                ':font_weight'            => $data['font_weight'] ?? 'normal',
                ':hover_background_color' => $data['hover_background_color'] ?? null,
                ':hover_text_color'       => $data['hover_text_color'] ?? null,
                ':hover_border_color'     => $data['hover_border_color'] ?? null,
                ':is_active'              => (int)($data['is_active'] ?? 1),
                ':tenantId'               => $tenantId,
                ':id'                     => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO button_styles
                (tenant_id, theme_id, name, slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color, is_active, created_at)
            VALUES
                (:tenantId, :theme_id, :name, :slug, :button_type, :background_color, :text_color, :border_color, :border_width, :border_radius, :padding, :font_size, :font_weight, :hover_background_color, :hover_text_color, :hover_border_color, :is_active, NOW())
        ");

        $stmt->execute([
            ':tenantId'               => $tenantId,
            ':theme_id'               => $data['theme_id'] ?? null,
            ':name'                   => $data['name'],
            ':slug'                   => $data['slug'],
            ':button_type'            => $data['button_type'],
            ':background_color'       => $data['background_color'],
            ':text_color'             => $data['text_color'],
            ':border_color'           => $data['border_color'] ?? null,
            ':border_width'           => (int)($data['border_width'] ?? 0),
            ':border_radius'          => (int)($data['border_radius'] ?? 4),
            ':padding'                => $data['padding'] ?? '10px 20px',
            ':font_size'              => $data['font_size'] ?? '14px',
            ':font_weight'            => $data['font_weight'] ?? 'normal',
            ':hover_background_color' => $data['hover_background_color'] ?? null,
            ':hover_text_color'       => $data['hover_text_color'] ?? null,
            ':hover_border_color'     => $data['hover_border_color'] ?? null,
            ':is_active'              => (int)($data['is_active'] ?? 1)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, string $slug, ?int $themeId = null): bool
    {
        $sql = "
            DELETE FROM button_styles
            WHERE tenant_id = :tenantId AND slug = :slug
        ";

        $params = [':tenantId' => $tenantId, ':slug' => $slug];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteById(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM button_styles
            WHERE tenant_id = :tenantId AND id = :id
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
    }

    public function getButtonTypes(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT button_type
            FROM button_styles
            WHERE tenant_id = :tenantId
            ORDER BY button_type ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActiveStyles(int $tenantId, ?int $themeId = null): array
    {
        $sql = "
            SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color
            FROM button_styles
            WHERE tenant_id = :tenantId AND is_active = 1
        ";

        $params = [':tenantId' => $tenantId];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY button_type ASC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkUpdate(int $tenantId, array $styles): bool
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($styles as $style) {
                $this->save($tenantId, $style);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}