<?php
declare(strict_types=1);

final class BrandsService
{
    private PdoBrandsRepository $repo;
    private BrandsValidator $validator;

    public function __construct(
        PdoBrandsRepository $repo,
        BrandsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, array $filters, string $orderBy, string $orderDir, ?int $limit, ?int $offset, string $lang): array
    {
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, string $slug, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($tenantId, $slug, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Brand not found');
        }
        return $row;
    }

    public function getById(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->findById($tenantId, $id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Brand not found');
        }
        return $row;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);
        $errors = $this->validator->validate($data, $isUpdate);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Normalize translations before passing to repository
        if (!empty($data['translations']) && is_array($data['translations'])) {
            $data['translations'] = BrandsValidator::normalizeTranslations($data['translations']);
        }

        $id = $this->repo->save($tenantId, $data, $userId);

        // جلب السجل المحفوظ مع جميع الترجمات
        $row = $this->repo->findById($tenantId, $id, 'en', true);
        if (!$row) {
            throw new RuntimeException('Failed to load saved brand');
        }

        return $row;
    }

    public function delete(int $tenantId, string $slug, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $slug, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }

    public function deleteById(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->deleteById($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }

    public function getActiveBrands(int $tenantId, string $lang = 'en'): array
    {
        return $this->repo->getActiveBrands($tenantId, $lang);
    }

    public function getFeaturedBrands(int $tenantId, string $lang = 'en'): array
    {
        return $this->repo->getFeaturedBrands($tenantId, $lang);
    }
}