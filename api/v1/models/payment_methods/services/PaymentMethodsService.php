<?php
declare(strict_types=1);

final class PaymentMethodsService
{
    private PdoPaymentMethodsRepository $repo;

    public function __construct(PDO $pdo)
    {
        $this->repo = new PdoPaymentMethodsRepository($pdo);
    }

    public function list(?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->repo->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $data): int
    {
        $v = PaymentMethodsValidator::validateCreate($data);
        if (!$v['valid']) {
            throw new \InvalidArgumentException(implode(', ', $v['errors']));
        }
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $v = PaymentMethodsValidator::validateUpdate($data);
        if (!$v['valid']) {
            throw new \InvalidArgumentException(implode(', ', $v['errors']));
        }
        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
