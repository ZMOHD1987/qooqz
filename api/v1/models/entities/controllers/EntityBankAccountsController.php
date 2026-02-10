<?php
declare(strict_types=1);

final class EntityBankAccountsController
{
    private EntityBankAccountsService $service;
    private EntityBankAccountsValidator $validator;

    public function __construct(EntityBankAccountsService $service)
    {
        $this->service = $service;
        $this->validator = new EntityBankAccountsValidator();
    }

    public function list(...$args): array
    {
        return $this->service->list(...$args);
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
