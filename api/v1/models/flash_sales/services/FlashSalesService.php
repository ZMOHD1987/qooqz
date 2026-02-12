<?php
declare(strict_types=1);

class FlashSalesService {
    private PdoFlashSalesRepository $repo;

    public function __construct(PdoFlashSalesRepository $repo) {
        $this->repo = $repo;
    }

    public function list(array $filters = []): array { return $this->repo->list($filters); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function create(array $data): int { return $this->repo->create($data); }
    public function update(int $id, array $data): bool { return $this->repo->update($id, $data); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function stats(): array { return $this->repo->stats(); }

    public function getTranslations(int $id, ?string $lang = null): array { return $this->repo->getTranslations($id, $lang); }
    public function saveTranslation(array $data): bool { return $this->repo->saveTranslation($data); }
    public function deleteTranslation(int $id): bool { return $this->repo->deleteTranslation($id); }
    public function deleteTranslationsByLang(int $fid, string $lang): bool { return $this->repo->deleteTranslationsByLang($fid, $lang); }

    public function getProducts(int $id): array { return $this->repo->getProducts($id); }
    public function addProduct(array $data): int { return $this->repo->addProduct($data); }
    public function updateProduct(int $id, array $data): bool { return $this->repo->updateProduct($id, $data); }
    public function deleteProduct(int $id): bool { return $this->repo->deleteProduct($id); }
}
