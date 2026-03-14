<?php
declare(strict_types=1);

final class AuctionsService
{
    private PdoAuctionsRepository $repo;

    public function __construct(PdoAuctionsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): array
    {
        $data = $this->repo->find($tenantId, $id, $lang);
        if (!$data) {
            throw new RuntimeException('Auction not found');
        }
        return $data;
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validate($data, false);
        return $this->repo->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }
        $this->validate($data, true);
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    private function validate(array $data, bool $isUpdate): void
    {
        $validator = new AuctionsValidator();
        if (!$validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
    }
}

