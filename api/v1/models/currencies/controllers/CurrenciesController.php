<?php
declare(strict_types=1);

final class CurrenciesController
{
    private CurrenciesService $service;

    public function __construct(CurrenciesService $service)
    {
        $this->service = $service;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        return $this->service->list($limit, $offset, $filters);
    }

    public function get(string $code): array
    {
        return $this->service->get($code);
    }

    public function create(array $data): array
    {
        return $this->service->save($data);
    }

    public function update(array $data): array
    {
        if (empty($data['code'])) {
            throw new InvalidArgumentException('Code is required for update');
        }
        return $this->service->save($data);
    }

    public function delete(array $data): void
    {
        $this->service->delete($data);
    }
}