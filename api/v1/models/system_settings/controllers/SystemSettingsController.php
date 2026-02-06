<?php
declare(strict_types=1);

// api/v1/models/system_settings/controllers/SystemSettingsController.php

final class SystemSettingsController
{
    private SystemSettingsService $service;

    public function __construct(SystemSettingsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $category = $_GET['category'] ?? null;
        return $this->service->list($tenantId, $category);
    }

    public function getPublic(int $tenantId): array
    {
        return $this->service->getPublicSettings($tenantId);
    }

    public function categories(int $tenantId): array
    {
        return $this->service->getCategories($tenantId);
    }

    public function get(int $tenantId, string $key): array
    {
        return $this->service->get($tenantId, $key);
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
        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id']);
        } elseif (!empty($data['setting_key'])) {
            $this->service->delete($tenantId, $data['setting_key']);
        } else {
            throw new InvalidArgumentException('ID or setting_key is required');
        }
    }
}