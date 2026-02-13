<?php
declare(strict_types=1);

/**
 * Service layer for stock movement management.
 * Creates repository internally from a single PDO instance.
 */
final class StockMovementsService
{
    private PdoStockMovementsRepository $movements;

    public function __construct(PDO $pdo)
    {
        $this->movements = new PdoStockMovementsRepository($pdo);
    }

    // ================================
    // Stock Movements CRUD
    // ================================

    public function listMovements(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->movements->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function getMovement(int $id): ?array
    {
        return $this->movements->find($id);
    }

    public function createMovement(array $data): int
    {
        return $this->movements->create($data);
    }

    public function deleteMovement(int $id): bool
    {
        $existing = $this->movements->find($id);
        if (!$existing) {
            throw new RuntimeException("Stock movement not found with ID: $id");
        }
        return $this->movements->delete($id);
    }

    public function getByProduct(int $productId): array
    {
        return $this->movements->getByProduct($productId);
    }

    public function movementStats(array $filters = []): array
    {
        return $this->movements->stats($filters);
    }

    public function lookupByBarcode(string $barcode): ?array
    {
        return $this->movements->lookupByBarcode($barcode);
    }
}
