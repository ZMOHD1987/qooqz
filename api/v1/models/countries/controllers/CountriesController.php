<?php
declare(strict_types=1);

final class CountriesController
{
    private CountriesService $service;

    public function __construct(CountriesService $service)
    {
        $this->service = $service;
    }

    /**
     * List countries with optional filters
     *
     * @param array $filters
     * @return array
     */
    public function list(array $filters = []): array
    {
        return $this->service->list($filters);
    }

    /**
     * Store a country
     *
     * @param array $data
     * @return array
     */
    public function store(array $data): array
    {
        return $this->service->create($data);
    }

    /**
     * Update country
     *
     * @param array $data
     * @return array
     */
    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('id is required for update');
        }
        $id = (int)$data['id'];
        return $this->service->update($id, $data);
    }

    /**
     * Delete country
     *
     * @param array $data
     * @return array
     */
    public function delete(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('id is required for delete');
        }
        $id = (int)$data['id'];
        return $this->service->delete($id);
    }
}