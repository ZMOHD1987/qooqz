<?php
declare(strict_types=1);

// api/v1/models/card_styles/services/CardStylesService.php

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

    public function getById(int $tenantId, int $id): array
    {
        $row = $this->repo->findById($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Card style not found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data): array
    {
        // Auto-derive card_type from slug when the stored value is empty.
        // This handles legacy DB rows that were inserted before the card_type enum
        // was properly set (e.g. card_type = '' in existing rows).
        if (empty($data['card_type']) && !empty($data['slug'])) {
            $firstSegment = strtolower(explode('-', trim($data['slug']))[0]);
            if ($firstSegment !== '' && in_array($firstSegment, CardStylesValidator::getAllowedCardTypes(), true)) {
                $data['card_type'] = $firstSegment;
            }
        }

        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $isUpdate = !empty($data['id']);
        $id = $this->repo->save($tenantId, $data);

        // For updates use the returned id (same as input); for inserts use the new lastInsertId.
        $row = $this->repo->findById($tenantId, $id);

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