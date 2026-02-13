<?php
declare(strict_types=1);

/**
 * Thin controller pass-through to DiscountsService.
 */
final class DiscountsController
{
    private DiscountsService $service;

    public function __construct(DiscountsService $service)
    {
        $this->service = $service;
    }

    // ================================
    // Discounts CRUD
    // ================================

    public function listDiscounts(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->listDiscounts($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getDiscount(int $id): ?array
    {
        return $this->service->getDiscount($id);
    }

    public function createDiscount(array $data): int
    {
        return $this->service->createDiscount($data);
    }

    public function updateDiscount(int $id, array $data): bool
    {
        return $this->service->updateDiscount($id, $data);
    }

    public function deleteDiscount(int $id): bool
    {
        return $this->service->deleteDiscount($id);
    }

    public function discountStats(): array
    {
        return $this->service->discountStats();
    }

    // ================================
    // Translations
    // ================================

    public function listTranslations(int $discountId): array
    {
        return $this->service->listTranslations($discountId);
    }

    public function findTranslation(int $id): ?array
    {
        return $this->service->findTranslation($id);
    }

    public function upsertTranslation(int $discountId, string $langCode, array $data): int
    {
        return $this->service->upsertTranslation($discountId, $langCode, $data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->service->deleteTranslation($id);
    }

    public function deleteTranslationsByDiscount(int $discountId): bool
    {
        return $this->service->deleteTranslationsByDiscount($discountId);
    }

    // ================================
    // Scopes
    // ================================

    public function listScopes(int $discountId): array
    {
        return $this->service->listScopes($discountId);
    }

    public function createScope(array $data): int
    {
        return $this->service->createScope($data);
    }

    public function deleteScope(int $id): bool
    {
        return $this->service->deleteScope($id);
    }

    // ================================
    // Conditions
    // ================================

    public function listConditions(int $discountId): array
    {
        return $this->service->listConditions($discountId);
    }

    public function createCondition(array $data): int
    {
        return $this->service->createCondition($data);
    }

    public function updateCondition(int $id, array $data): bool
    {
        return $this->service->updateCondition($id, $data);
    }

    public function deleteCondition(int $id): bool
    {
        return $this->service->deleteCondition($id);
    }

    // ================================
    // Actions
    // ================================

    public function listActions(int $discountId): array
    {
        return $this->service->listActions($discountId);
    }

    public function createAction(array $data): int
    {
        return $this->service->createAction($data);
    }

    public function updateAction(int $id, array $data): bool
    {
        return $this->service->updateAction($id, $data);
    }

    public function deleteAction(int $id): bool
    {
        return $this->service->deleteAction($id);
    }

    // ================================
    // Redemptions
    // ================================

    public function listRedemptions(int $discountId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->service->listRedemptions($discountId, $limit, $offset);
    }

    public function createRedemption(array $data): int
    {
        return $this->service->createRedemption($data);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->service->redemptionStats($discountId);
    }

    // ================================
    // Exclusions
    // ================================

    public function listExclusions(int $discountId): array
    {
        return $this->service->listExclusions($discountId);
    }

    public function createExclusion(int $discountId, int $excludedDiscountId): int
    {
        return $this->service->createExclusion($discountId, $excludedDiscountId);
    }

    public function deleteExclusion(int $id): bool
    {
        return $this->service->deleteExclusion($id);
    }
}
