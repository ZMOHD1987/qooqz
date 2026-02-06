<?php
declare(strict_types=1);

// api/v1/models/card_styles/repositories/PdoCardStylesRepository.php

final class PdoCardStylesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?string $cardType = null, ?int $themeId = null): array
    {
        $sql = "
            SELECT id, tenant_id, theme_id, name, slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active, created_at, updated_at
            FROM card_styles
            WHERE tenant_id = :tenantId
        ";

        $params = [':tenantId' => $tenantId];

        if ($cardType) {
            $sql .= " AND card_type = :cardType";
            $params[':cardType'] = $cardType;
        }

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY card_type ASC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, string $slug, ?int $themeId = null): ?array
    {
        $sql = "
            SELECT *
            FROM card_styles
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
            FROM card_styles
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
                UPDATE card_styles
                SET theme_id = :theme_id,
                    name = :name,
                    slug = :slug,
                    card_type = :card_type,
                    background_color = :background_color,
                    border_color = :border_color,
                    border_width = :border_width,
                    border_radius = :border_radius,
                    shadow_style = :shadow_style,
                    padding = :padding,
                    hover_effect = :hover_effect,
                    text_align = :text_align,
                    image_aspect_ratio = :image_aspect_ratio,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE tenant_id = :tenantId AND id = :id
            ");

            $stmt->execute([
                ':theme_id'           => $data['theme_id'] ?? null,
                ':name'               => $data['name'],
                ':slug'               => $data['slug'],
                ':card_type'          => $data['card_type'],
                ':background_color'   => $data['background_color'] ?? '#FFFFFF',
                ':border_color'       => $data['border_color'] ?? '#E0E0E0',
                ':border_width'       => (int)($data['border_width'] ?? 1),
                ':border_radius'      => (int)($data['border_radius'] ?? 8),
                ':shadow_style'       => $data['shadow_style'] ?? 'none',
                ':padding'            => $data['padding'] ?? '16px',
                ':hover_effect'       => $data['hover_effect'] ?? 'none',
                ':text_align'         => $data['text_align'] ?? 'left',
                ':image_aspect_ratio' => $data['image_aspect_ratio'] ?? '1:1',
                ':is_active'          => (int)($data['is_active'] ?? 1),
                ':tenantId'           => $tenantId,
                ':id'                 => (int)$data['id']
            ]);

            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO card_styles
                (tenant_id, theme_id, name, slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active, created_at)
            VALUES
                (:tenantId, :theme_id, :name, :slug, :card_type, :background_color, :border_color, :border_width, :border_radius, :shadow_style, :padding, :hover_effect, :text_align, :image_aspect_ratio, :is_active, NOW())
        ");

        $stmt->execute([
            ':tenantId'           => $tenantId,
            ':theme_id'           => $data['theme_id'] ?? null,
            ':name'               => $data['name'],
            ':slug'               => $data['slug'],
            ':card_type'          => $data['card_type'],
            ':background_color'   => $data['background_color'] ?? '#FFFFFF',
            ':border_color'       => $data['border_color'] ?? '#E0E0E0',
            ':border_width'       => (int)($data['border_width'] ?? 1),
            ':border_radius'      => (int)($data['border_radius'] ?? 8),
            ':shadow_style'       => $data['shadow_style'] ?? 'none',
            ':padding'            => $data['padding'] ?? '16px',
            ':hover_effect'       => $data['hover_effect'] ?? 'none',
            ':text_align'         => $data['text_align'] ?? 'left',
            ':image_aspect_ratio' => $data['image_aspect_ratio'] ?? '1:1',
            ':is_active'          => (int)($data['is_active'] ?? 1)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, string $slug, ?int $themeId = null): bool
    {
        $sql = "
            DELETE FROM card_styles
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
            DELETE FROM card_styles
            WHERE tenant_id = :tenantId AND id = :id
        ");

        return $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
    }

    public function getCardTypes(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT card_type
            FROM card_styles
            WHERE tenant_id = :tenantId
            ORDER BY card_type ASC
        ");

        $stmt->execute([':tenantId' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActiveStyles(int $tenantId, ?int $themeId = null): array
    {
        $sql = "
            SELECT slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio
            FROM card_styles
            WHERE tenant_id = :tenantId AND is_active = 1
        ";

        $params = [':tenantId' => $tenantId];

        if ($themeId) {
            $sql .= " AND theme_id = :themeId";
            $params[':themeId'] = $themeId;
        }

        $sql .= " ORDER BY card_type ASC, name ASC";

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