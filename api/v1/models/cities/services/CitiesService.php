<?php
declare(strict_types=1);

// api/v1/models/cities/services/CitiesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoCitiesRepository.php';
require_once __DIR__ . '/../validators/CitiesValidator.php';

final class CitiesService
{
    private PdoCitiesRepository $repo;
    private CitiesValidator $validator;

    public function __construct(
        PdoCitiesRepository $repo,
        CitiesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(string $lang = 'en', ?int $countryId = null, int $page = 1, int $perPage = 20): array
    {
        $data = $this->repo->all($lang, $countryId, $page, $perPage);
        $total = $this->repo->totalCount($countryId);

        return [
            'success'   => true,
            'data'      => $data,
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'pages'     => ceil($total / $perPage)
        ];
    }

    public function get(int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->findWithTranslation($id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('City not found');
        }

        return [
            'success' => true,
            'data'    => $row
        ];
    }

    public function save(array $data): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $cityId = $this->repo->save($data);

        if (!empty($data['translations'])) {
            $this->repo->saveTranslations($cityId, $data['translations']);
        }

        $row = $this->repo->find($cityId);
        if (!$row) {
            throw new RuntimeException('Failed to load saved city');
        }

        return [
            'success' => true,
            'message' => 'City saved successfully',
            'data'    => $row
        ];
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete city');
        }
    }
}