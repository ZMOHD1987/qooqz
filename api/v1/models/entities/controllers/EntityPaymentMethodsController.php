<?php
declare(strict_types=1);

final class EntityPaymentMethodsController
{
    private EntityPaymentMethodsService $service;
    private EntityPaymentMethodsValidator $validator;

    public function __construct(EntityPaymentMethodsService $service)
    {
        $this->service = $service;
        $this->validator = new EntityPaymentMethodsValidator();
    }

    public function list(
        int $tenantId,
        int $entityId,
        ?int $limit,
        ?int $offset,
        string $orderBy,
        string $orderDir,
        array $filters = []
    ): array {
        return $this->service->list($tenantId, $entityId, $limit, $offset, $orderBy, $orderDir, $filters);
    }

    public function get(int $tenantId, int $entityId, int $id): ?array
    {
        return $this->service->get($tenantId, $entityId, $id);
    }

    public function save(int $tenantId, int $entityId, array $data): int
    {
        $this->validator->validate($data, !empty($data['id']));
        return $this->service->save($tenantId, $entityId, $data);
    }

    public function delete(int $tenantId, int $entityId, int $id): bool
    {
        return $this->service->delete($tenantId, $entityId, $id);
    }
}