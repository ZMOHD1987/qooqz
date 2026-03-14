<?php
declare(strict_types=1);

final class SeoMetaController
{
    private SeoMetaService $service;

    public function __construct(SeoMetaService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getById(int $id): ?array
    {
        return $this->service->getById($id);
    }

    public function getByEntity(string $entityType, int $entityId, ?int $tenantId = null): ?array
    {
        return $this->service->getByEntity($entityType, $entityId, $tenantId);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): int
    {
        return $this->service->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    // Translations
    public function getTranslations(int $seoMetaId): array
    {
        return $this->service->getTranslations($seoMetaId);
    }

    public function saveTranslation(array $data): int
    {
        return $this->service->saveTranslation($data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->service->deleteTranslation($id);
    }

    // Stats
    public function stats(): array
    {
        return $this->service->stats();
    }
}