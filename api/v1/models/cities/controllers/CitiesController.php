<?php
declare(strict_types=1);

/**
 * Cities Controller - FIXED FOR TRANSLATION SUPPORT
 */

final class CitiesController
{
    private CitiesService $service;

    public function __construct(CitiesService $service)
    {
        $this->service = $service;
    }

    // ✅ إصلاح: استلام filters بدلاً من قراءة $_GET مباشرة
    public function list(array $filters = []): array
    {
        $lang = $filters['lang'] ?? 'en';
        $countryId = isset($filters['country_id']) ? (int)$filters['country_id'] : null;
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 20);

        return $this->service->list($lang, $countryId, $page, $perPage);
    }

    // ✅ إصلاح: استلام lang من parameters
    public function show(int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        return $this->service->get($id, $lang, $allTranslations);
    }

    public function create(array $data): array
    {
        return $this->service->save($data);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        return $this->service->save($data);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $this->service->delete((int) $data['id']);
    }
}