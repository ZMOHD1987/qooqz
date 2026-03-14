<?php
declare(strict_types=1);

final class Product_categoriesService
{
    private PdoProduct_categoriesRepository $repository;
    private Product_categoriesValidator $validator;

    public function __construct(PdoProduct_categoriesRepository $repository, Product_categoriesValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        return $this->repository->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    public function get(int $id): array
    {
        $data = $this->repository->find($id);
        if (!$data) {
            throw new RuntimeException('Product category not found');
        }
        return $data;
    }

    public function save(array $data): array
    {
        $isUpdate = !empty($data['id']);
        if (!$this->validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }

        $id = $this->repository->save($data);
        return $this->get($id);
    }

    public function delete(int $id): void
    {
        if (!$this->repository->delete($id)) {
            throw new RuntimeException('Failed to delete product category');
        }
    }
}
