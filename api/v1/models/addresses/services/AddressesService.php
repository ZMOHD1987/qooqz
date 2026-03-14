<?php
declare(strict_types=1);

final class AddressesService
{
    private PdoAddressesRepository $repo;

    public function __construct(PdoAddressesRepository $repo)
    {
        $this->repo = $repo;
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
        return $this->repo->list(
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
        $item = $this->repo->find($id, $language);
        if (!$item) {
            throw new RuntimeException('Address not found', 404);
        }
        return $item;
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        AddressesValidator::validateCreate($data);
        return $this->repo->create($data);
    }

    // ================================
    // UPDATE
    // ================================
    public function update(int $id, array $data): bool
    {
        AddressesValidator::validateUpdate($data);
        return $this->repo->update($id, $data);
    }

    // ================================
    // DELETE
    // ================================
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}