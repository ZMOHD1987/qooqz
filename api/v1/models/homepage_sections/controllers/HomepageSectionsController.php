<?php
declare(strict_types=1);

// api/v1/models/homepage_sections/controllers/HomepageSectionsController.php

final class HomepageSectionsController
{
    private HomepageSectionsService $service;

    public function __construct(HomepageSectionsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $sectionType = $_GET['section_type'] ?? null;
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->list($tenantId, $sectionType, $themeId, $lang);
    }

    public function getActive(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
        return $this->service->getActiveSections($tenantId, $lang, $themeId);
    }

    public function sectionTypes(int $tenantId): array
    {
        return $this->service->getSectionTypes($tenantId);
    }

    public function get(int $tenantId, int $id): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($tenantId, $id, $lang, $allTranslations);
    }

    public function create(int $tenantId, array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null; // Get from query or session
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->service->delete($tenantId, (int) $data['id'], $userId);
    }
}