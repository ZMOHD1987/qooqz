<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoTenantsRepository.php';
require_once __DIR__ . '/../validators/TenantsValidator.php';

final class TenantsService
{
    private PdoTenantsRepository $repo;
    private TenantsValidator $validator;

    public function __construct(
        PdoTenantsRepository $repo,
        TenantsValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List tenants with pagination and filters
     */
    public function list(int $perPage = 10, int $offset = 0, array $filters = []): array
    {
        // Validate filters
        $filterErrors = TenantsValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException(
                'Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE)
            );
        }

        return $this->repo->all($perPage, $offset, $filters);
    }

    /**
     * Get total count with filters
     */
    public function count(array $filters = []): int
    {
        // Validate filters
        $filterErrors = TenantsValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException(
                'Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE)
            );
        }

        return $this->repo->count($filters);
    }

    /**
     * Get single tenant
     */
    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Tenant not found');
        }
        return $row;
    }

    /**
     * Create new tenant
     */
    public function create(array $data, ?int $userId = null): array
    {
        // Validate input
        $errors = TenantsValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        // Check if owner user exists
        if (!$this->repo->userExists($data['owner_user_id'])) {
            throw new InvalidArgumentException('Owner user does not exist');
        }

        // Check if domain is unique (if provided)
        if (!empty($data['domain']) && $this->repo->domainExists($data['domain'])) {
            throw new InvalidArgumentException('Domain is already in use');
        }

        $id = $this->repo->save($data, $userId);
        $row = $this->repo->find($id);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve created tenant');
        }

        return $row;
    }

    /**
     * Update tenant
     */
    public function update(array $data, int $id, ?int $userId = null): array
    {
        // Check if tenant exists
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Tenant not found');
        }

        // Merge existing data with updates
        $data = array_merge($existing, $data);
        $data['id'] = $id;

        // Validate input
        $errors = TenantsValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        // Check if owner user exists (if changed)
        if (!$this->repo->userExists($data['owner_user_id'])) {
            throw new InvalidArgumentException('Owner user does not exist');
        }

        // Check if domain is unique (if changed)
        if (!empty($data['domain']) && $this->repo->domainExists($data['domain'], $id)) {
            throw new InvalidArgumentException('Domain is already in use by another tenant');
        }

        $id = $this->repo->save($data, $userId);
        $row = $this->repo->find($id);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve updated tenant');
        }

        return $row;
    }

    /**
     * Delete tenant
     */
    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete tenant');
        }
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(array $ids, string $status, ?int $userId = null): array
    {
        // Validate bulk operation
        $errors = TenantsValidator::validateBulk([
            'ids' => $ids,
            'status' => $status
        ]);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $affected = $this->repo->bulkUpdateStatus($ids, $status, $userId);

        return [
            'affected_count' => $affected,
            'ids' => $ids,
            'status' => $status
        ];
    }

    /**
     * Get tenant statistics
     */
    public function getStats(): array
    {
        return $this->repo->getStats();
    }
}