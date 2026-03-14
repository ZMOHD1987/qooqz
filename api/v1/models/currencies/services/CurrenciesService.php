<?php
declare(strict_types=1);

final class CurrenciesService
{
    private PdoCurrenciesRepository $repository;
    private CurrenciesValidator $validator;

    public function __construct(PdoCurrenciesRepository $repository, CurrenciesValidator $validator)
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

    public function get(string $code): array
    {
        $data = $this->repository->findByCode($code);
        if (!$data) {
            throw new RuntimeException('Currency not found');
        }
        return $data;
    }

    public function save(array $data): array
    {
        if (!$this->validator->validate($data)) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }

        $code = $this->repository->save($data);
        return $this->get($code);
    }

    public function delete(array $data): void
    {
        if (empty($data['code'])) {
            throw new InvalidArgumentException('Code is required');
        }
        if (!$this->repository->delete($data['code'])) {
            throw new RuntimeException('Failed to delete currency');
        }
    }
}