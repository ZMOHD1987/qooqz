<?php
declare(strict_types=1);

/**
 * Interface ProductRelationsRepositoryInterface
 *
 * Contract for ProductRelations data access layer.
 * Any implementation (PDO, Eloquent, test mock, etc.) must satisfy this contract.
 *
 * Location: api/v1/modules/products/Contracts/ProductRelationsRepositoryInterface.php
 */
interface ProductRelationsRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of product relations for a tenant.
     *
     * @param int         $tenantId  Tenant scope
     * @param int|null    $limit     Max rows to return
     * @param int|null    $offset    Rows to skip
     * @param array       $filters   Keyed filters: product_id, related_product_id, relation_type
     * @param string      $orderBy   Column to sort by (validated against whitelist)
     * @param string      $orderDir  'ASC' or 'DESC'
     * @param string      $lang      Language code, e.g. 'ar' or 'en'
     * @return array<int, array<string, mixed>>
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'pr.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count total product relations for a tenant, optionally filtered.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a single product relation by ID within a tenant scope.
     *
     * @param int    $tenantId
     * @param int    $id
     * @param string $lang
     * @return array<string, mixed>|null  null if not found or not accessible
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    /**
     * Create a new product relation.
     * Implementations must validate tenant ownership of both products
     * and reject duplicate relations before inserting.
     *
     * @param int   $tenantId
     * @param array $data  Keys: product_id, related_product_id, relation_type, sort_order (optional)
     * @return int  The new relation's ID
     * @throws InvalidArgumentException if ownership check or duplicate check fails
     */
    public function create(int $tenantId, array $data): int;

    /**
     * Update an existing product relation.
     * Implementations must verify the relation belongs to the tenant.
     *
     * @param int   $tenantId
     * @param array $data  Must include 'id'. Other keys are optional partial updates.
     * @return bool
     * @throws InvalidArgumentException if relation not found or access denied
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a single product relation by ID.
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool;

    /**
     * Delete all relations for a specific product, optionally filtered by type.
     *
     * @param int         $tenantId
     * @param int         $productId
     * @param string|null $relationType  If null, deletes all relation types
     * @return bool
     * @throws InvalidArgumentException if product does not belong to tenant
     */
    public function deleteByProduct(int $tenantId, int $productId, ?string $relationType = null): bool;

    /**
     * Get all related products for a given product, optionally filtered by relation type.
     *
     * @param int         $tenantId
     * @param int         $productId
     * @param string|null $relationType
     * @param string      $lang
     * @return array<int, array<string, mixed>>
     */
    public function getRelatedProducts(
        int $tenantId,
        int $productId,
        ?string $relationType = null,
        string $lang = 'ar'
    ): array;
}