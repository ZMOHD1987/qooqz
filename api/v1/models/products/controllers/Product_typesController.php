<?php
declare(strict_types=1);

final class Product_typesController
{
    private Product_typesService $service;

    public function __construct(Product_typesService $service)
    {
        $this->service = $service;
    }

    /* =====================================================
     * GET /product_types
     * ===================================================== */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return [
            'data'  => $this->service->list(
                $limit,
                $offset,
                $filters,
                $orderBy,
                $orderDir
            ),
            'total' => $this->service->count($filters),
        ];
    }

    /* =====================================================
     * GET /product_types/{id}
     * ✅ الدالة التي كانت ناقصة
     * ===================================================== */
    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    /* =====================================================
     * POST
     * ===================================================== */
    public function create(array $data): array
    {
        return $this->service->save($data);
    }

    /* =====================================================
     * PUT
     * ===================================================== */
    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        return $this->service->save($data);
    }

    /* =====================================================
     * DELETE
     * ===================================================== */
    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $this->service->delete((int)$data['id']);
    }
}
