<?php
declare(strict_types=1);

final class ProductReviewsService
{
    private PdoProductReviewsRepository $repo;

    public function __construct(PdoProductReviewsRepository $repo) {
        $this->repo = $repo;
    }

    public function list(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy='created_at', string $orderDir='DESC'): array {
        $items = $this->repo->all($filters, $limit, $offset, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items'=>$items,'total'=>$total];
    }

    public function get(int $id): ?array {
        return $this->repo->find($id);
    }

    public function create(array $data): int {
        return $this->repo->save($data);
    }

    public function update(array $data): int {
        if (empty($data['id'])) throw new InvalidArgumentException("ID required");
        return $this->repo->save($data);
    }

    public function delete(int $id): bool {
        return $this->repo->delete($id);
    }
}