<?php
declare(strict_types=1);

// api/v1/models/attribute_types/controllers/AttributeTypesController.php

final class AttributeTypesController
{
    private AttributeTypesService $service;

    public function __construct(AttributeTypesService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $activeOnly = isset($_GET['active']) && $_GET['active'] === '1';
        return $this->service->list($activeOnly);
    }

    public function get(string $code): array
    {
        return $this->service->get($code);
    }

    public function getActive(): array
    {
        return $this->service->getActive();
    }

    public function create(array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id']) && empty($data['code'])) {
            throw new InvalidArgumentException('ID or code is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById((int) $data['id'], $userId);
        } elseif (!empty($data['code'])) {
            $this->service->delete($data['code'], $userId);
        } else {
            throw new InvalidArgumentException('ID or code is required');
        }
    }
}