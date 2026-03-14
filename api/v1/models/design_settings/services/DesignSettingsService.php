<?php
declare(strict_types=1);

// api/v1/models/design_settings/services/DesignSettingsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoDesignSettingsRepository.php';
require_once __DIR__ . '/../validators/DesignSettingsValidator.php';

final class DesignSettingsService
{
    private PdoDesignSettingsRepository $repo;
    private DesignSettingsValidator $validator;

    public function __construct(
        PdoDesignSettingsRepository $repo,
        DesignSettingsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?string $category = null, ?int $themeId = null): array
    {
        return $this->repo->all($tenantId, $category, $themeId);
    }

    public function get(int $tenantId, string $key, ?int $themeId = null): array
    {
        $row = $this->repo->find($tenantId, $key, $themeId);
        if (!$row) {
            throw new RuntimeException('Design setting not found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($tenantId, $data);

        if (isset($data['id'])) {
            $row = $this->repo->findById($tenantId, $id);
        } else {
            $row = $this->repo->find($tenantId, $data['setting_key'], $data['theme_id'] ?? null);
        }

        if (!$row) {
            throw new RuntimeException('Failed to load saved design setting');
        }

        return $row;
    }

    public function delete(int $tenantId, string $key, ?int $themeId = null): void
    {
        $this->repo->delete($tenantId, $key, $themeId);
    }

    public function deleteById(int $tenantId, int $id): void
    {
        $this->repo->deleteById($tenantId, $id);
    }

    public function getCategories(int $tenantId): array
    {
        return $this->repo->getCategories($tenantId);
    }

    public function getActiveSettings(int $tenantId, ?int $themeId = null): array
    {
        return $this->repo->getActiveSettings($tenantId, $themeId);
    }

    public function bulkUpdate(int $tenantId, array $settings): bool
    {
        return $this->repo->bulkUpdate($tenantId, $settings);
    }
}