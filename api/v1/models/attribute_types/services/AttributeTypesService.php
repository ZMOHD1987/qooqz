<?php
declare(strict_types=1);

// api/v1/models/attribute_types/services/AttributeTypesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoAttributeTypesRepository.php';
require_once __DIR__ . '/../validators/AttributeTypesValidator.php';

final class AttributeTypesService
{
    private PdoAttributeTypesRepository $repo;
    private AttributeTypesValidator $validator;

    public function __construct(
        PdoAttributeTypesRepository $repo,
        AttributeTypesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(bool $activeOnly = false): array
    {
        return $this->repo->all($activeOnly);
    }

    public function get(string $code): array
    {
        $row = $this->repo->find($code);
        if (!$row) {
            throw new RuntimeException('Attribute type not found');
        }

        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($data, $userId);

        $row = $this->repo->findById($id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved attribute type');
        }

        return $row;
    }

    public function delete(string $code, ?int $userId = null): void
    {
        if (!$this->repo->delete($code, $userId)) {
            throw new RuntimeException('Failed to delete attribute type');
        }
    }

    public function deleteById(int $id, ?int $userId = null): void
    {
        if (!$this->repo->deleteById($id, $userId)) {
            throw new RuntimeException('Failed to delete attribute type');
        }
    }

    public function getActive(): array
    {
        return $this->repo->getActive();
    }
}