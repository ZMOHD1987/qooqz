<?php
declare(strict_types=1);

final class CartsService
{
    private PdoCartsRepository $repo;

    public function __construct(PdoCartsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        return $this->repo->all(
            $tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang
        );
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    public function getBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        return $this->repo->findBySession($tenantId, $sessionId, $entityId);
    }

    public function getByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        return $this->repo->findByUser($tenantId, $userId, $entityId);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->repo->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    public function convertToOrder(int $tenantId, int $cartId, int $orderId): bool
    {
        return $this->repo->convertToOrder($tenantId, $cartId, $orderId);
    }
}
