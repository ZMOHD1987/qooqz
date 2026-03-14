<?php
declare(strict_types=1);

final class SeoMetaService
{
    private PdoSeoMetaRepository $repo;

    public function __construct(PdoSeoMetaRepository $repo)
    {
        $this->repo = $repo;
    }

    // ================================
    // CRUD
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    public function getById(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function getByEntity(string $entityType, int $entityId, ?int $tenantId = null): ?array
    {
        return $this->repo->getByEntity($entityType, $entityId, $tenantId);
    }

    public function create(array $data): int
    {
        SeoMetaValidator::validateCreate($data);
        return $this->repo->save($data);
    }

    public function update(int $id, array $data): int
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("SEO meta not found with ID: $id");
        }
        $data['id'] = $id;
        SeoMetaValidator::validateUpdate($data);
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("SEO meta not found with ID: $id");
        }
        return $this->repo->delete($id);
    }

    // ================================
    // Translations
    // ================================
    public function getTranslations(int $seoMetaId): array
    {
        return $this->repo->getTranslations($seoMetaId);
    }

    public function saveTranslation(array $data): int
    {
        SeoMetaValidator::validateTranslation($data);
        return $this->repo->saveTranslation($data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->repo->deleteTranslation($id);
    }

    // ================================
    // Stats
    // ================================
    public function stats(): array
    {
        return $this->repo->stats();
    }
}