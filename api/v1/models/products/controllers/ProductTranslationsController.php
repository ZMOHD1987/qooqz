<?php
declare(strict_types=1);

final class ProductTranslationsController
{
    private ProductTranslationsService $service;

    public function __construct(ProductTranslationsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?string $languageCode, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->service->list($tenantId, $languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $tenantId, int $id, ?string $languageCode): ?array
    {
        return $this->service->get($tenantId, $id, $languageCode);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
