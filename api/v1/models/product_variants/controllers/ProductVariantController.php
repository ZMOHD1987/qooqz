<?php
declare(strict_types=1);

final class ProductVariantController
{
    private ProductVariantService $service;

    public function __construct(ProductVariantService $service)
    {
        $this->service = $service;
    }

    public function listWithTranslations(int $tenantId, ?string $languageCode=null, ?int $limit=null, ?int $offset=null, array $filters=[], string $orderBy='id', string $orderDir='DESC'): array
    {
        return $this->service->listWithTranslations($tenantId, $languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function createOrUpdate(int $tenantId, array $data): int
    {
        return $this->service->createOrUpdate($data, $tenantId);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }

    public function saveTranslation(int $variantId, string $languageCode, string $name): void
    {
        $this->service->saveTranslation($variantId, $languageCode, $name);
    }

    public function getTranslations(int $variantId): array
    {
        return $this->service->getTranslations($variantId);
    }
}
