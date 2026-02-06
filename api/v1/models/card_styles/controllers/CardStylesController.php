<?php
declare(strict_types=1);

// api/v1/models/card_styles/controllers/CardStylesController.php

final class CardStylesController
{
    private CardStylesService $service;

    public function __construct(CardStylesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $cardType = $_GET['card_type'] ?? null;
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->list($tenantId, $cardType, $themeId);
    }

    public function getActive(int $tenantId): array
    {
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->getActiveStyles($tenantId, $themeId);
    }

    public function cardTypes(int $tenantId): array
    {
        return $this->service->getCardTypes($tenantId);
    }

    public function get(int $tenantId, string $slug): array
    {
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->get($tenantId, $slug, $themeId);
    }

    public function create(int $tenantId, array $data): array
    {
        return $this->service->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id']) && empty($data['slug'])) {
            throw new InvalidArgumentException('ID or slug is required');
        }

        return $this->service->save($tenantId, $data);
    }

    public function delete(int $tenantId, array $data): void
    {
        $themeId = isset($data['theme_id']) ? (int)$data['theme_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id']);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($tenantId, $data['slug'], $themeId);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }

    public function bulkUpdate(int $tenantId, array $data): array
    {
        if (empty($data['styles']) || !is_array($data['styles'])) {
            throw new InvalidArgumentException('Styles array is required');
        }

        $success = $this->service->bulkUpdate($tenantId, $data['styles']);
        return ['success' => $success, 'message' => $success ? 'Bulk update completed' : 'Bulk update failed'];
    }
}