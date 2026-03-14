<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoTenant_usersRepository.php';
require_once __DIR__ . '/../validators/Tenant_usersValidator.php';

final class Tenant_usersService
{
    private PdoTenant_usersRepository $repo;
    private Tenant_usersValidator $validator;

    public function __construct(PdoTenant_usersRepository $repo, Tenant_usersValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * Create new tenant user (with upsert behavior and robust retrieval).
     *
     * Behavior:
     *  - Validate input (throws InvalidArgumentException for bad input)
     *  - If membership exists: update it and return the updated row
     *  - Else: insert new row and return the created row
     *
     * Important: if the DB insert succeeds but retrieval of the row fails for any reason,
     * the method returns minimal info { id, tenant_id, user_id } and logs the condition,
     * instead of throwing a 500 to the client.
     *
     * @param int $tenantId
     * @param array $data
     * @param int|null $actingUserId for audit logs
     * @return array
     * @throws InvalidArgumentException on validation errors
     * @throws PDOException on hard DB write failure (insert/update fail)
     */
    public function create(int $tenantId, array $data, ?int $actingUserId = null): array
    {
        // allow explicit tenant_id in payload only if provided (use with caution)
        if (isset($data['tenant_id']) && is_numeric($data['tenant_id'])) {
            $tenantId = (int)$data['tenant_id'];
        }

        // Validate input (create mode)
        $errors = Tenant_usersValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $userId = (int)$data['user_id'];
        $roleId = isset($data['role_id']) ? ((string)$data['role_id'] === '' ? null : (int)$data['role_id']) : null;
        $entityId = isset($data['entity_id']) ? ((string)$data['entity_id'] === '' ? null : (int)$data['entity_id']) : null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        // Check global user existence
        if (!$this->repo->userExists($userId)) {
            throw new InvalidArgumentException('User does not exist');
        }

        // If role provided, verify it exists
        if ($roleId !== null && !$this->repo->roleExists($roleId)) {
            throw new InvalidArgumentException('Role does not exist');
        }

        // If membership exists -> update instead of insert (upsert)
        $existing = $this->repo->getByUserAndTenant($tenantId, $userId);
        if ($existing) {
            $update = [
                'id' => (int)$existing['id'],
                'role_id' => $roleId,
                'entity_id' => $entityId,
                'is_active' => $isActive
            ];

            // Save (may throw PDOException if write fails)
            $this->repo->save($tenantId, $update, $actingUserId);

            // Try to retrieve updated row
            $row = $this->repo->find($tenantId, (int)$existing['id']);
            if (!$row) {
                // fallback: try getById
                if (method_exists($this->repo, 'getById')) {
                    $row = $this->repo->getById((int)$existing['id']);
                }
            }

            if ($row) return $row;

            // If retrieval failed, log and return minimal result (do not throw 500)
            safe_log('warning', 'Tenant_usersService::create - updated but failed to re-retrieve row', [
                'tenantId' => $tenantId,
                'id' => (int)$existing['id'],
                'data' => $update
            ]);

            return ['id' => (int)$existing['id'], 'tenant_id' => $tenantId, 'user_id' => $userId];
        }

        // Insert new membership
        try {
            $insertId = $this->repo->save($tenantId, [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $roleId,
                'entity_id' => $entityId,
                'is_active' => $isActive
            ], $actingUserId);
        } catch (PDOException $ex) {
            // Hard DB write failure -> rethrow so router can return 500
            safe_log('error', 'Tenant_usersService::create - save failed', [
                'tenantId' => $tenantId,
                'data' => $data,
                'error' => $ex->getMessage()
            ]);
            throw $ex;
        }

        // Try to fetch created row by tenant+id
        $row = $this->repo->find($tenantId, $insertId);
        if (!$row && method_exists($this->repo, 'getById')) {
            $row = $this->repo->getById($insertId);
        }

        if ($row) {
            return $row;
        }

        // If retrieval still failed, log and return minimal info instead of throwing
        safe_log('warning', 'Tenant_usersService::create - inserted id but failed to re-retrieve', [
            'tenantId' => $tenantId,
            'insertId' => $insertId,
            'data' => $data
        ]);

        return ['id' => $insertId, 'tenant_id' => $tenantId, 'user_id' => $userId];
    }

    /**
     * Update tenant user
     */
    public function update(int $tenantId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // Check if tenant user exists
        $existing = $this->repo->find($tenantId, (int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Tenant user not found');
        }

        // Validate input (update mode)
        $errors = Tenant_usersValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Check if role exists (if provided)
        if (isset($data['role_id']) && $data['role_id'] !== '' && !$this->repo->roleExists((int)$data['role_id'])) {
            throw new InvalidArgumentException('Role does not exist');
        }

        $id = $this->repo->save($tenantId, $data, $userId);
        $row = $this->repo->find($tenantId, $id);
        if (!$row && method_exists($this->repo, 'getById')) {
            $row = $this->repo->getById($id);
        }
        if (!$row) {
            safe_log('warning', 'Tenant_usersService::update - updated but failed to re-retrieve', ['tenantId' => $tenantId, 'id' => $id, 'data' => $data]);
            return ['id' => $id];
        }
        return $row;
    }

    /**
     * Delete tenant user
     */
    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete tenant user');
        }
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(int $tenantId, array $ids, int $isActive, ?int $userId = null): array
    {
        // Validate bulk operation
        $errors = Tenant_usersValidator::validateBulk([
            'ids' => $ids,
            'is_active' => $isActive
        ]);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $affected = $this->repo->bulkUpdateStatus($tenantId, $ids, $isActive, $userId);

        return [
            'affected_count' => $affected,
            'ids' => $ids,
            'is_active' => $isActive
        ];
    }

    /**
     * List method and other helpers unchanged (delegate to repo)
     */
    public function list(int $tenantId, $arg2 = null, $arg3 = null, $arg4 = null): array
    {
        // keep backward compatibility (see earlier implementation)
        $perPage = 10;
        $offset = 0;
        $filters = [];

        if (is_array($arg2) && $arg3 === null && $arg4 === null) {
            $query = $arg2;
            $page = isset($query['page']) && is_numeric($query['page']) && (int)$query['page'] > 0 ? (int)$query['page'] : 1;
            $perPage = isset($query['per_page']) && is_numeric($query['per_page']) && (int)$query['per_page'] > 0 ? (int)$query['per_page'] : 10;
            $offset = ($page - 1) * $perPage;

            if (isset($query['search']) && trim((string)$query['search']) !== '') $filters['search'] = trim((string)$query['search']);
            if (isset($query['user_id']) && is_numeric($query['user_id'])) $filters['user_id'] = (int)$query['user_id'];
            if (isset($query['tenant_id']) && is_numeric($query['tenant_id'])) $filters['tenant_id'] = (int)$query['tenant_id'];
            if (isset($query['entity_id']) && is_numeric($query['entity_id'])) $filters['entity_id'] = (int)$query['entity_id'];
            if (isset($query['is_active']) && ($query['is_active'] === '0' || $query['is_active'] === '1' || $query['is_active'] === 0 || $query['is_active'] === 1)) {
                $filters['is_active'] = (int)$query['is_active'];
            }

            $pageUsed = $page;
        } else {
            $perPage = is_int($arg2) && $arg2 > 0 ? $arg2 : 10;
            $offset = is_int($arg3) && $arg3 >= 0 ? $arg3 : 0;
            $filters = is_array($arg4) ? $arg4 : [];
            $pageUsed = (int)floor($offset / max(1, $perPage)) + 1;
        }

        // Validate filters
        $filterErrors = Tenant_usersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE));
        }

        $items = $this->repo->all($tenantId, $perPage, $offset, $filters);
        $total = $this->repo->count($tenantId, $filters);
        $pages = $perPage > 0 ? (int)ceil($total / $perPage) : 0;

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $pageUsed,
                'per_page' => $perPage,
                'pages' => $pages
            ]
        ];
    }

    public function count(int $tenantId, $arg = [])
    {
        $filters = is_array($arg) ? $arg : [];
        $filterErrors = Tenant_usersValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException('Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE));
        }
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id): array
    {
        $row = $this->repo->find($tenantId, $id);
        if (!$row) throw new RuntimeException('Tenant user not found');
        return $row;
    }

    public function getStats(int $tenantId): array
    {
        return [
            'total_users' => $this->repo->count($tenantId),
            'active_users' => $this->repo->count($tenantId, ['is_active' => 1]),
            'inactive_users' => $this->repo->count($tenantId, ['is_active' => 0])
        ];
    }
}