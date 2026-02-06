<?php
declare(strict_types=1);

// api/v1/models/font_settings/controllers/FontSettingsController.php

final class FontSettingsController
{
    private FontSettingsService $service;

    public function __construct(FontSettingsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $category = $_GET['category'] ?? null;
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->list($tenantId, $category, $themeId);
    }

    public function getActive(int $tenantId): array
    {
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->getActiveSettings($tenantId, $themeId);
    }

    public function categories(int $tenantId): array
    {
        return $this->service->getCategories($tenantId);
    }

    public function get(int $tenantId, string $key): array
    {
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->get($tenantId, $key, $themeId);
    }

    public function create(int $tenantId, array $data): array
    {
        return $this->service->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id']) && empty($data['setting_key'])) {
            throw new InvalidArgumentException('ID or setting_key is required');
        }

        return $this->service->save($tenantId, $data);
    }

    public function delete(int $tenantId, array $data): void
    {
        $themeId = isset($data['theme_id']) ? (int)$data['theme_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id']);
        } elseif (!empty($data['setting_key'])) {
            $this->service->delete($tenantId, $data['setting_key'], $themeId);
        } else {
            throw new InvalidArgumentException('ID or setting_key is required');
        }
    }

    public function bulkUpdate(int $tenantId, array $data): array
    {
        if (empty($data['settings']) || !is_array($data['settings'])) {
            throw new InvalidArgumentException('Settings array is required');
        }

        $success = $this->service->bulkUpdate($tenantId, $data['settings']);
        return ['success' => $success, 'message' => $success ? 'Bulk update completed' : 'Bulk update failed'];
    }
}