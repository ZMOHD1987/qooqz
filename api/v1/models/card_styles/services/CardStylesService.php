<?php
declare(strict_types=1);

// api/v1/models/card_styles/services/CardStylesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoCardStylesRepository.php';
require_once __DIR__ . '/../validators/CardStylesValidator.php';

final class CardStylesService
{
    private PdoCardStylesRepository $repo;
    private CardStylesValidator $validator;

    public function __construct(
        PdoCardStylesRepository $repo,
        CardStylesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?string $cardType = null, ?int $themeId = null): array
    {
        return $this->repo->all($tenantId, $cardType, $themeId);
    }

    public function get(int $tenantId, string $slug, ?int $themeId = null): array
    {
        $row = $this->repo->find($tenantId, $slug, $themeId);
        if (!$row) {
            throw new RuntimeException('Card style not found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($tenantId, $data);

        if (isset($data['id'])) {
            $row = $this->repo->findById($tenantId, $id);
        } else {
            $row = $this->repo->find($tenantId, $data['slug'], $data['theme_id'] ?? null);
        }

        if (!$row) {
            throw new RuntimeException('Failed to load saved card style');
        }

        return $row;
    }

    public function delete(int $tenantId, string $slug, ?int $themeId = null): void
    {
        $this->repo->delete($tenantId, $slug, $themeId);
    }

    public function deleteById(int $tenantId, int $id): void
    {
        $this->repo->deleteById($tenantId, $id);
    }

    public function getCardTypes(int $tenantId): array
    {
        return $this->repo->getCardTypes($tenantId);
    }

    public function getActiveStyles(int $tenantId, ?int $themeId = null): array
    {
        return $this->repo->getActiveStyles($tenantId, $themeId);
    }

    public function bulkUpdate(int $tenantId, array $styles): bool
    {
        return $this->repo->bulkUpdate($tenantId, $styles);
    }
}