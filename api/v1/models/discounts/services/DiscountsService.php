<?php
declare(strict_types=1);

/**
 * Service layer for discount management.
 * Creates all sub-repositories internally from a single PDO instance.
 */
final class DiscountsService
{
    private PdoDiscountsRepository $discounts;
    private PdoDiscountTranslationsRepository $translations;
    private PdoDiscountScopesRepository $scopes;
    private PdoDiscountConditionsRepository $conditions;
    private PdoDiscountActionsRepository $actions;
    private PdoDiscountRedemptionsRepository $redemptions;
    private PdoDiscountExclusionsRepository $exclusions;

    public function __construct(PDO $pdo)
    {
        $this->discounts    = new PdoDiscountsRepository($pdo);
        $this->translations = new PdoDiscountTranslationsRepository($pdo);
        $this->scopes       = new PdoDiscountScopesRepository($pdo);
        $this->conditions   = new PdoDiscountConditionsRepository($pdo);
        $this->actions      = new PdoDiscountActionsRepository($pdo);
        $this->redemptions  = new PdoDiscountRedemptionsRepository($pdo);
        $this->exclusions   = new PdoDiscountExclusionsRepository($pdo);
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
        return $this->discounts->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getDiscount(int $id): ?array
    {
        return $this->discounts->find($id);
    }

    public function createDiscount(array $data): int
    {
        return $this->discounts->create($data);
    }

    public function updateDiscount(int $id, array $data): bool
    {
        $existing = $this->discounts->find($id);
        if (!$existing) {
            throw new RuntimeException("Discount not found with ID: $id");
        }
        return $this->discounts->update($id, $data);
    }

    public function deleteDiscount(int $id): bool
    {
        $existing = $this->discounts->find($id);
        if (!$existing) {
            throw new RuntimeException("Discount not found with ID: $id");
        }
        return $this->discounts->delete($id);
    }

    public function discountStats(): array
    {
        return $this->discounts->stats();
    }

    // ================================
    // Translations
    // ================================

    public function listTranslations(int $discountId): array
    {
        return $this->translations->listByDiscount($discountId);
    }

    public function findTranslation(int $id): ?array
    {
        return $this->translations->find($id);
    }

    public function upsertTranslation(int $discountId, string $langCode, array $data): int
    {
        return $this->translations->upsert($discountId, $langCode, $data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->translations->delete($id);
    }

    public function deleteTranslationsByDiscount(int $discountId): bool
    {
        return $this->translations->deleteByDiscount($discountId);
    }

    // ================================
    // Scopes
    // ================================

    public function listScopes(int $discountId): array
    {
        return $this->scopes->listByDiscount($discountId);
    }

    public function createScope(array $data): int
    {
        return $this->scopes->create($data);
    }

    public function deleteScope(int $id): bool
    {
        return $this->scopes->delete($id);
    }

    // ================================
    // Conditions
    // ================================

    public function listConditions(int $discountId): array
    {
        return $this->conditions->listByDiscount($discountId);
    }

    public function createCondition(array $data): int
    {
        return $this->conditions->create($data);
    }

    public function updateCondition(int $id, array $data): bool
    {
        return $this->conditions->update($id, $data);
    }

    public function deleteCondition(int $id): bool
    {
        return $this->conditions->delete($id);
    }

    // ================================
    // Actions
    // ================================

    public function listActions(int $discountId): array
    {
        return $this->actions->listByDiscount($discountId);
    }

    public function createAction(array $data): int
    {
        return $this->actions->create($data);
    }

    public function updateAction(int $id, array $data): bool
    {
        return $this->actions->update($id, $data);
    }

    public function deleteAction(int $id): bool
    {
        return $this->actions->delete($id);
    }

    // ================================
    // Redemptions
    // ================================

    public function listRedemptions(int $discountId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->redemptions->listByDiscount($discountId, $limit, $offset);
    }

    public function createRedemption(array $data): int
    {
        return $this->redemptions->create($data);
    }

    public function redemptionStats(int $discountId): array
    {
        return $this->redemptions->stats($discountId);
    }

    // ================================
    // Exclusions
    // ================================

    public function listExclusions(int $discountId): array
    {
        return $this->exclusions->listByDiscount($discountId);
    }

    public function createExclusion(int $discountId, int $excludedDiscountId): int
    {
        return $this->exclusions->create($discountId, $excludedDiscountId);
    }

    public function deleteExclusion(int $id): bool
    {
        return $this->exclusions->delete($id);
    }
}
