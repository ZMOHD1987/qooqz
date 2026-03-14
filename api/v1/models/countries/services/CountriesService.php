<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoCountriesRepository.php';
require_once __DIR__ . '/../validators/CountriesValidator.php';

final class CountriesService
{
    private PdoCountriesRepository $repo;
    private CountriesValidator $validator;

    public function __construct(PdoCountriesRepository $repo, CountriesValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List countries with filters
     *
     * @param array $filters
     * @return array{items:array,meta:array}
     */
    public function list(array $filters = []): array
    {
        return $this->repo->list($filters);
    }

    /**
     * Create country
     *
     * @param array $data
     * @return array{id:int,created:bool}
     */
    public function create(array $data): array
    {
        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // check uniqueness for iso2
        if ($this->repo->getByIdentifierExists($data['iso2'] ?? null)) {
            throw new RuntimeException('Country with same ISO code already exists');
        }

        $id = $this->repo->insert($data);
        return ['id' => $id, 'created' => true];
    }

    /**
     * Update country
     *
     * @param int $id
     * @param array $data
     * @return array{id:int,updated:bool}
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
            throw new RuntimeException('Country not found');
        }

        // if iso2 changed, ensure uniqueness
        if (!empty($data['iso2']) && $data['iso2'] !== $existing['iso2']) {
            if ($this->repo->getByIdentifierExists($data['iso2'])) {
                throw new RuntimeException('Country with same ISO2 exists');
            }
        }

        $ok = $this->repo->update($id, $data);
        if (!$ok) throw new RuntimeException('Failed to update country');

        return ['id' => $id, 'updated' => true];
    }

    /**
     * Delete country
     *
     * @param int $id
     * @return array{deleted:bool,id:int}
     */
    public function delete(int $id): array
    {
        $existing = $this->repo->getById($id);
        if (!$existing) {
            throw new RuntimeException('Country not found');
        }

        $ok = $this->repo->delete($id);
        if (!$ok) throw new RuntimeException('Failed to delete country');

        return ['deleted' => true, 'id' => $id];
    }
}