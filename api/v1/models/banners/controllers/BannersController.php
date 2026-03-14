<?php
declare(strict_types=1);

// api/v1/models/banners/controllers/BannersController.php

final class BannersController
{
    private BannersService $service;

    public function __construct(BannersService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $position = isset($_GET['position']) && $_GET['position'] !== '' ? $_GET['position'] : null;
        $themeId  = isset($_GET['theme_id'])  && is_numeric($_GET['theme_id'])  ? (int)$_GET['theme_id']  : null;
        $isActive = isset($_GET['is_active']) && is_numeric($_GET['is_active']) ? (int)$_GET['is_active'] : null;
        $lang     = $_GET['lang'] ?? 'en';
        return $this->service->list($tenantId, $position, $themeId, $isActive, $lang);
    }

    public function getActive(int $tenantId, string $position): array
    {
        $lang    = $_GET['lang'] ?? 'en';
        $themeId = isset($_GET['theme_id']) && is_numeric($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->getActiveBanners($tenantId, $position, $lang, $themeId);
    }

    public function positions(int $tenantId): array
    {
        return $this->service->getPositions($tenantId);
    }

    public function get(int $tenantId, int $id): array
    {
        $lang           = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($tenantId, $id, $lang, $allTranslations);
    }

    public function create(int $tenantId, array $data): array
    {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $this->service->delete($tenantId, (int)$data['id'], $userId);
    }
}