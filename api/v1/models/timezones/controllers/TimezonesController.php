<?php
declare(strict_types=1);

final class TimezonesController
{
    private TimezonesService $service;

    public function __construct(TimezonesService $service)
    {
        $this->service = $service;
    }

    /**
     * List all timezones
     *
     * @return array<int,array>
     */
    public function list(): array
    {
        return $this->service->list();
    }

    /**
     * Store a new timezone
     *
     * @param array $data
     * @return array{id:int, created:bool}
     */
    public function store(array $data): array
    {
        return $this->service->create($data);
    }

    /**
     * Update a timezone
     *
     * @param array $data
     * @return array{id:int, updated:bool}
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
     * Delete a timezone
     *
     * @param array $data
     * @return array{deleted:bool, id:int}
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