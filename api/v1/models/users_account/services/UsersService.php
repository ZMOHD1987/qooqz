<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoUsersRepository.php';
require_once __DIR__ . '/../validators/UsersValidator.php';

final class UsersService
{
    private PdoUsersRepository $repo;
    private UsersValidator $validator;

    public function __construct(
        PdoUsersRepository $repo,
        UsersValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $filterErrors = UsersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Filter validation failed: ' . json_encode($filterErrors));
        }

        return $this->repo->all($limit, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        $filterErrors = UsersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Filter validation failed: ' . json_encode($filterErrors));
        }

        return $this->repo->count($filters);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('User not found');
        }

        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);
        $errors = UsersValidator::validate($data, $isUpdate);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($data, $userId);

        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved user');
        }

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete user');
        }
    }
}