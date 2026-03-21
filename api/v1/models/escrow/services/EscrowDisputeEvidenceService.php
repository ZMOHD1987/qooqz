<?php
declare(strict_types=1);

final class EscrowDisputeEvidenceService
{
    private PdoEscrowDisputeEvidenceRepository $repo;

    public function __construct(PdoEscrowDisputeEvidenceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id): array
    {
        $data = $this->repo->find($tenantId, $id);
        if (!$data) {
            throw new RuntimeException('Escrow dispute evidence not found');
        }
        return $data;
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validate($data, 'create');
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    private function validate(array $data, string $scenario): void
    {
        $validator = new EscrowDisputeEvidenceValidator();
        if (!$validator->validate($data, $scenario)) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
    }
}
