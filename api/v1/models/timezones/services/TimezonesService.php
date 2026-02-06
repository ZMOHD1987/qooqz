<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoTimezonesRepository.php';
require_once __DIR__ . '/../validators/TimezonesValidator.php';

final class TimezonesService
{
    private PdoTimezonesRepository $repo;
    private TimezonesValidator $validator;

    public function __construct(PdoTimezonesRepository $repo, TimezonesValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List all timezones
     *
     * @return array<int,array>
     */
    public function list(): array
    {
        return $this->repo->all();
    }

    /**
     * Create new timezone
     *
     * @param array $data
     * @return array{id:int, created:bool}
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $data): array
    {
        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // ensure unique timezone
        if ($this->repo->getByTimezone($data['timezone'])) {
            throw new RuntimeException('Timezone already exists');
        }

        $id = $this->repo->insert($data);
        return ['id' => $id, 'created' => true];
    }

    /**
     * Update timezone
     *
     * @param int $id
     * @param array $data
     * @return array{id:int, updated:bool}
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, array $data): array
    {
        $dataWithId = array_merge(['id' => $id], $data);
        $errors = $this->validator->validateUpdate($dataWithId);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $existing = $this->repo->getById($id);
        if (!$existing) {
            throw new RuntimeException('Timezone not found');
        }

        // If timezone changed, ensure uniqueness
        if (isset($data['timezone']) && $data['timezone'] !== $existing['timezone']) {
            if ($this->repo->getByTimezone($data['timezone'])) {
                throw new RuntimeException('Timezone already exists');
            }
        }

        $ok = $this->repo->update($id, $data);
        if (!$ok) {
            throw new RuntimeException('Failed to update timezone');
        }

        return ['id' => $id, 'updated' => true];
    }

    /**
     * Delete timezone
     *
     * @param int $id
     * @return array{deleted:bool, id:int}
     * @throws RuntimeException
     */
    public function delete(int $id): array
    {
        $existing = $this->repo->getById($id);
        if (!$existing) {
            throw new RuntimeException('Timezone not found');
        }

        $ok = $this->repo->delete($id);
        if (!$ok) {
            throw new RuntimeException('Failed to delete timezone');
        }

        return ['deleted' => true, 'id' => $id];
    }
}