<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/Tenant_usersService.php';
require_once __DIR__ . '/../validators/Tenant_usersValidator.php';

final class Tenant_usersController
{
    private Tenant_usersService $service;

    public function __construct(Tenant_usersService $service)
    {
        $this->service = $service;
    }

    /**
     * List tenant users with pagination and filters.
     *
     * Expects $query to be an array (typically $_GET) and supports:
     *  - page, per_page, search, user_id, tenant_id, is_active
     *
     * @param int $tenantId
     * @param array $query
     * @return array ['items'=>..., 'meta'=>...]
     */
    public function list(int $tenantId, array $query = []): array
    {
        return $this->service->list($tenantId, $query);
    }

    /**
     * Get total count with filters
     *
     * @param int $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int
    {
        return $this->service->count($tenantId, $filters);
    }

    /**
     * Get single tenant user
     *
     * @param int $tenantId
     * @param int $id
     * @return array
     */
    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }

    /**
     * Create new tenant user
     *
     * The router can pass an optional $userId (acting user for audit).
     * If not provided, the controller will try to read from $_GET['user_id'] as fallback.
     *
     * @param int $tenantId
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function create(int $tenantId, array $data, ?int $userId = null): array
    {
        if ($userId === null && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        }

        return $this->service->create($tenantId, $data, $userId);
    }

    /**
     * Update tenant user
     *
     * @param int $tenantId
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    public function update(int $tenantId, array $data, ?int $userId = null): array
    {
        if ($userId === null && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        }

        return $this->service->update($tenantId, $data, $userId);
    }

    /**
     * Delete tenant user
     *
     * @param int $tenantId
     * @param array $data expects ['id' => int, 'user_id' => optional int]
     * @return void
     */
    public function delete(int $tenantId, array $data): void
    {
        if (empty($data['id']) || !is_numeric($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = null;
        if (isset($data['user_id']) && is_numeric($data['user_id'])) {
            $userId = (int)$data['user_id'];
        } elseif (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        }

        $this->service->delete($tenantId, (int)$data['id'], $userId);
    }

    /**
     * Bulk update status
     *
     * @param int $tenantId
     * @param array $data expects ['ids'=>[], 'is_active'=>0|1]
     * @return array
     */
    public function bulkUpdateStatus(int $tenantId, array $data): array
    {
        if (empty($data['ids']) || !is_array($data['ids'])) {
            throw new InvalidArgumentException('IDs array is required');
        }

        $userId = null;
        if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        }

        return $this->service->bulkUpdateStatus(
            $tenantId,
            $data['ids'],
            (int)$data['is_active'],
            $userId
        );
    }

    /**
     * Get statistics
     *
     * @param int $tenantId
     * @return array
     */
    public function getStats(int $tenantId): array
    {
        return $this->service->getStats($tenantId);
    }
}