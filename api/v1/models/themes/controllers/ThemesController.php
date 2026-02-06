<?php
declare(strict_types=1);

// api/v1/models/themes/controllers/ThemesController.php

final class ThemesController
{
    private ThemesService $service;

    public function __construct(ThemesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $activeOnly = isset($_GET['active']) && $_GET['active'] === '1';
        return $this->service->list($tenantId, $activeOnly);
    }

    public function get(int $tenantId, string $slug): array
    {
        return $this->service->get($tenantId, $slug);
    }

    public function getActive(int $tenantId): array
    {
        return $this->service->getActive($tenantId);
    }

    public function getDefault(int $tenantId): array
    {
        return $this->service->getDefault($tenantId);
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
        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id']);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($tenantId, $data['slug']);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }

    public function activate(int $tenantId, array $data): array
    {
        if (empty($data['slug'])) {
            throw new InvalidArgumentException('Slug is required');
        }

        $this->service->activate($tenantId, $data['slug']);
        return ['success' => true, 'message' => 'Theme activated'];
    }

    public function setDefault(int $tenantId, array $data): array
    {
        if (empty($data['slug'])) {
            throw new InvalidArgumentException('Slug is required');
        }

        $this->service->setDefault($tenantId, $data['slug']);
        return ['success' => true, 'message' => 'Theme set as default'];
    }
}