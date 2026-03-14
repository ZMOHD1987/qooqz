<?php
declare(strict_types=1);

class FlashSalesController {
    private FlashSalesService $service;

    public function __construct(FlashSalesService $service) {
        $this->service = $service;
    }

    public function list(array $filters = []): array { return $this->service->list($filters); }
    public function find(int $id): ?array { return $this->service->find($id); }
    public function create(array $data): int { return $this->service->create($data); }
    public function update(int $id, array $data): bool { return $this->service->update($id, $data); }
    public function delete(int $id): bool { return $this->service->delete($id); }
    public function stats(): array { return $this->service->stats(); }

    public function getTranslations(int $id, ?string $lang = null): array { return $this->service->getTranslations($id, $lang); }
    public function saveTranslation(array $data): bool { return $this->service->saveTranslation($data); }
    public function deleteTranslation(int $id): bool { return $this->service->deleteTranslation($id); }
    public function deleteTranslationsByLang(int $fid, string $lang): bool { return $this->service->deleteTranslationsByLang($fid, $lang); }

    public function getProducts(int $id): array { return $this->service->getProducts($id); }
    public function addProduct(array $data): int { return $this->service->addProduct($data); }
    public function updateProduct(int $id, array $data): bool { return $this->service->updateProduct($id, $data); }
    public function deleteProduct(int $id): bool { return $this->service->deleteProduct($id); }
}