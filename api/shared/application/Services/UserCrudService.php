<?php
declare(strict_types=1);

namespace Shared\Application\Services;

use PDO;

final class UserCrudService
{
    private CrudService $crud;

    public function __construct(PDO $pdo, array $entities)
    {
        $this->crud = new CrudService($pdo, $entities);
    }

    public function create(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->crud->create('users', $data);
    }

    public function update(int $id, array $data): void
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $this->crud->update('users', $id, $data);
    }

    public function delete(int $id): void
    {
        $this->crud->delete('users', $id);
    }

    public function read(int $id): array
    {
        return $this->crud->read('users', $id);
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->crud->list('users', $limit, $offset);
    }
}
