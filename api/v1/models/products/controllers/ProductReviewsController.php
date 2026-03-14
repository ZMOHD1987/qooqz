<?php
declare(strict_types=1);

final class ProductReviewsController
{
    private ProductReviewsService $service;
    private ProductReviewsValidator $validator;

    public function __construct(ProductReviewsService $service, ProductReviewsValidator $validator) {
        $this->service = $service;
        $this->validator = $validator;
    }

    public function list(array $filters=[], ?int $limit=null, ?int $offset=null, string $orderBy='created_at', string $orderDir='DESC'): array {
        return $this->service->list($filters, $limit, $offset, $orderBy, $orderDir);
    }

    public function get(int $id): ?array {
        return $this->service->get($id);
    }

    public function create(array $data): int {
        $this->validator->validate($data);
        return $this->service->create($data);
    }

    public function update(array $data): int {
        $this->validator->validate($data, true);
        return $this->service->update($data);
    }

    public function delete(int $id): bool {
        return $this->service->delete($id);
    }
}