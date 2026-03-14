<?php
declare(strict_types=1);

// api/v1/models/country_taxes/controllers/CountryTaxesController.php

final class CountryTaxesController
{
    private CountryTaxesService $service;

    public function __construct(CountryTaxesService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : null;
        $taxClassId = isset($_GET['tax_class_id']) ? (int)$_GET['tax_class_id'] : null;
        return $this->service->list($countryId, $taxClassId);
    }

    public function getByCountry(int $countryId): array
    {
        return $this->service->getByCountry($countryId);
    }

    public function getByTaxClass(int $taxClassId): array
    {
        return $this->service->getByTaxClass($taxClassId);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->service->delete((int) $data['id'], $userId);
    }
}