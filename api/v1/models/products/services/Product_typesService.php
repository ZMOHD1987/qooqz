<?php
declare(strict_types=1);

final class Product_typesService
{
    private PdoProduct_typesRepository $repo;
    private Product_typesValidator $validator;

    public function __construct(
        PdoProduct_typesRepository $repo,
        Product_typesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /* =====================================================
     * List + Filters + Pagination
     * ===================================================== */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all(
            $limit,
            $offset,
            $filters,
            $orderBy,
            $orderDir
        );
    }

    public function count(array $filters): int
    {
        return $this->repo->count($filters);
    }

    /* =====================================================
     * Get single by ID
     * ===================================================== */
    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Product type not found');
        }
        return $row;
    }

    /* =====================================================
     * Create / Update
     * ===================================================== */
    public function save(array $data): array
    {
        $isUpdate = !empty($data['id']);

        if (
            !$this->validator->validate(
                $data,
                $isUpdate ? 'update' : 'create'
            )
        ) {
            throw new InvalidArgumentException(
                implode(', ', $this->validator->getErrors())
            );
        }

        $id = $this->repo->save($data);
        return $this->get($id);
    }

    /* =====================================================
     * Delete
     * ===================================================== */
    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete product type');
        }
    }
}
