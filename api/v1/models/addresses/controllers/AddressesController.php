<?php
declare(strict_types=1);

final class AddressesController
{
    private AddressesService $service;

    public function __construct(AddressesService $service)
    {
        $this->service = $service;
    }

    // ================================
    // LIST
    // ================================
    public function list(
        int $limit,
        int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list(
            $limit,
            $offset,
            $filters,
            $orderBy,
            $orderDir
        );
    }

    // ================================
    // GET
    // ================================
    public function get(int $id, string $language = 'ar'): array
    {
        return $this->service->get($id, $language);
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    // ================================
    // UPDATE
    // ================================
    public function update(int $id, array $data): bool
    {
        return $this->service->update($id, $data);
    }

    // ================================
    // DELETE
    // ================================
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}