<?php
declare(strict_types=1);

final class LanguagesService
{
    private PdoLanguagesRepository $repository;
    private LanguagesValidator $validator;

    public function __construct(PdoLanguagesRepository $repository, LanguagesValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        return $this->repository->all($limit, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    public function get(int $id): array
    {
        $data = $this->repository->find($id);
        if (!$data) {
            throw new RuntimeException('Language not found');
        }
        return $data;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);

        if (!$this->validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }

        $id = $this->repository->save($data, $userId);
        return $this->get($id);
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repository->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete language');
        }
    }
}