<?php
declare(strict_types=1);

/**
 * PdoTenant_usersRepository
 *
 * Robust repository for tenant_users with:
 * - flexible filtering (search, user_id, tenant_id, role_id, is_active)
 * - safe binding and normalized results
 * - helper methods: getByUserAndTenant, userExistsInTenant, userExists, roleExists
 * - save() that supports create and update with careful NULL handling and error logging
 */

final class PdoTenant_usersRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all tenant users with pagination and filters
     *
     * $filters may include:
     *  - search (string)
     *  - is_active (0|1)
     *  - role_id (int)
     *  - user_id (int)
     *  - tenant_id (int)  <-- if provided will override $tenantId
     *
     * NOTE: If $tenantId is 0, it means "all tenants" (super admin only)
     *
     * Returns array of associative rows.
     */
    public function all(int $tenantId, int $perPage = 10, int $offset = 0, array $filters = []): array
    {
        $effectiveTenantId = isset($filters['tenant_id']) && is_numeric($filters['tenant_id']) ? (int)$filters['tenant_id'] : $tenantId;

        $whereParts = [];
        $params = [];

        // Only filter by tenant_id if > 0 (0 means super admin viewing all tenants)
        if ($effectiveTenantId > 0) {
            $whereParts[] = 'tu.tenant_id = :tenantId';
            $params[':tenantId'] = $effectiveTenantId;
        }

        if (!empty($filters['search'])) {
            // use distinct parameter names for each LIKE occurrence to avoid PDO named-parameter repetition issues
            $whereParts[] = '(u.username LIKE :search_u OR u.email LIKE :search_e OR t.name LIKE :search_t OR e.store_name LIKE :search_e_name)';
            $params[':search_u'] = '%' . $filters['search'] . '%';
            $params[':search_e'] = '%' . $filters['search'] . '%';
            $params[':search_t'] = '%' . $filters['search'] . '%';
            $params[':search_e_name'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $whereParts[] = 'tu.is_active = :is_active';
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (!empty($filters['role_id'])) {
            $whereParts[] = 'tu.role_id = :role_id';
            $params[':role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['user_id'])) {
            $whereParts[] = 'tu.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['entity_id'])) {
            $whereParts[] = 'tu.entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        $whereSql = '';
        if (!empty($whereParts)) {
            $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $sql = "
            SELECT
                tu.id,
                tu.user_id,
                u.username,
                u.email,
                tu.tenant_id,
                t.name AS tenant_name,
                tu.role_id,
                COALESCE(r.display_name, r.key_name, '') AS role_name,
                tu.entity_id,
                e.store_name AS entity_name,
                e.slug AS entity_slug,
                tu.joined_at,
                tu.is_active,
                tu.updated_at
            FROM tenant_users tu
            JOIN users u ON tu.user_id = u.id
            LEFT JOIN roles r ON tu.role_id = r.id
            JOIN tenants t ON tu.tenant_id = t.id
            LEFT JOIN entities e ON tu.entity_id = e.id
            {$whereSql}
            ORDER BY tu.joined_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        // bind filter params
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        // bind limit/offset
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // normalize types
        foreach ($rows as &$r) {
            $r['id'] = isset($r['id']) ? (int)$r['id'] : null;
            $r['user_id'] = isset($r['user_id']) ? (int)$r['user_id'] : null;
            $r['tenant_id'] = isset($r['tenant_id']) ? (int)$r['tenant_id'] : null;
            $r['role_id'] = isset($r['role_id']) && $r['role_id'] !== null ? (int)$r['role_id'] : null;
            $r['entity_id'] = isset($r['entity_id']) && $r['entity_id'] !== null ? (int)$r['entity_id'] : null;
            $r['is_active'] = isset($r['is_active']) ? (int)$r['is_active'] : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * Count tenant users with filters
     * 
     * NOTE: If $tenantId is 0, it means "all tenants" (super admin only)
     */
    public function count(int $tenantId, array $filters = []): int
    {
        $effectiveTenantId = isset($filters['tenant_id']) && is_numeric($filters['tenant_id']) ? (int)$filters['tenant_id'] : $tenantId;

        $whereParts = [];
        $params = [];

        // Only filter by tenant_id if > 0 (0 means super admin viewing all tenants)
        if ($effectiveTenantId > 0) {
            $whereParts[] = 'tu.tenant_id = :tenantId';
            $params[':tenantId'] = $effectiveTenantId;
        }

        if (!empty($filters['search'])) {
            // same distinct placeholders here
            $whereParts[] = '(u.username LIKE :search_u OR u.email LIKE :search_e OR t.name LIKE :search_t OR e.store_name LIKE :search_e_name)';
            $params[':search_u'] = '%' . $filters['search'] . '%';
            $params[':search_e'] = '%' . $filters['search'] . '%';
            $params[':search_t'] = '%' . $filters['search'] . '%';
            $params[':search_e_name'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $whereParts[] = 'tu.is_active = :is_active';
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (!empty($filters['role_id'])) {
            $whereParts[] = 'tu.role_id = :role_id';
            $params[':role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['user_id'])) {
            $whereParts[] = 'tu.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['entity_id'])) {
            $whereParts[] = 'tu.entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        $whereSql = '';
        if (!empty($whereParts)) {
            $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
        }

        // NOTE: include tenants join because we reference t.name in search
        $sql = "
            SELECT COUNT(*) AS total
            FROM tenant_users tu
            JOIN users u ON tu.user_id = u.id
            LEFT JOIN roles r ON tu.role_id = r.id
            JOIN tenants t ON tu.tenant_id = t.id
            LEFT JOIN entities e ON tu.entity_id = e.id
            {$whereSql}
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }

    /**
     * Find single tenant user
     */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                tu.id,
                tu.user_id,
                u.username,
                u.email,
                tu.tenant_id,
                t.name AS tenant_name,
                tu.role_id,
                COALESCE(r.display_name, r.key_name, '') AS role_name,
                tu.entity_id,
                e.store_name AS entity_name,
                e.slug AS entity_slug,
                tu.joined_at,
                tu.is_active,
                tu.updated_at
            FROM tenant_users tu
            JOIN users u ON tu.user_id = u.id
            LEFT JOIN roles r ON tu.role_id = r.id
            JOIN tenants t ON tu.tenant_id = t.id
            LEFT JOIN entities e ON tu.entity_id = e.id
            WHERE tu.tenant_id = :tenantId AND tu.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['id'] = isset($row['id']) ? (int)$row['id'] : null;
        $row['user_id'] = isset($row['user_id']) ? (int)$row['user_id'] : null;
        $row['tenant_id'] = isset($row['tenant_id']) ? (int)$row['tenant_id'] : null;
        $row['role_id'] = isset($row['role_id']) && $row['role_id'] !== null ? (int)$row['role_id'] : null;
        $row['entity_id'] = isset($row['entity_id']) && $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
        $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 0;

        return $row;
    }

    /**
     * Get membership row by tenant_id + user_id
     */
    public function getByUserAndTenant(int $tenantId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tenant_users
            WHERE tenant_id = :tenantId AND user_id = :userId
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':userId' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['id'] = isset($row['id']) ? (int)$row['id'] : null;
        $row['user_id'] = isset($row['user_id']) ? (int)$row['user_id'] : null;
        $row['tenant_id'] = isset($row['tenant_id']) ? (int)$row['tenant_id'] : null;
        $row['role_id'] = isset($row['role_id']) && $row['role_id'] !== null ? (int)$row['role_id'] : null;
        $row['entity_id'] = isset($row['entity_id']) && $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
        $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 0;

        return $row;
    }

    /**
     * Check if user exists in tenant
     */
    public function userExistsInTenant(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM tenant_users
            WHERE tenant_id = :tenantId AND user_id = :userId
        ");
        $stmt->execute([':tenantId' => $tenantId, ':userId' => $userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($r['cnt']) && (int)$r['cnt'] > 0;
    }

    /**
     * Check if role exists
     */
    public function roleExists(int $roleId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM roles WHERE id = :id");
        $stmt->execute([':id' => $roleId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($r['cnt']) && (int)$r['cnt'] > 0;
    }

    /**
     * Check if user exists in users table
     */
    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($r['cnt']) && (int)$r['cnt'] > 0;
    }

    /**
     * Save tenant user (create or update)
     *
     * - If 'id' present in $data -> update
     * - Else -> insert
     * - role_id may be null
     *
     * Returns inserted/updated id.
     *
     * Throws PDOException on DB errors (caller should handle).
     */
    public function save(int $tenantId, array $data, ?int $actingUserId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find($tenantId, (int)$data['id']) : null;

        try {
            if ($isUpdate) {
                $sql = "
                    UPDATE tenant_users
                    SET role_id = :role_id, entity_id = :entity_id, is_active = :is_active, updated_at = NOW()
                    WHERE tenant_id = :tenantId AND id = :id
                ";
                $stmt = $this->pdo->prepare($sql);

                // role_id nullable handling
                if (array_key_exists('role_id', $data) && ($data['role_id'] === null || $data['role_id'] === '')) {
                    $stmt->bindValue(':role_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':role_id', isset($data['role_id']) ? (int)$data['role_id'] : null, PDO::PARAM_INT);
                }

                // entity_id nullable handling
                if (array_key_exists('entity_id', $data) && ($data['entity_id'] === null || $data['entity_id'] === '')) {
                    $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':entity_id', isset($data['entity_id']) ? (int)$data['entity_id'] : null, PDO::PARAM_INT);
                }

                $stmt->bindValue(':is_active', isset($data['is_active']) ? (int)$data['is_active'] : 1, PDO::PARAM_INT);
                $stmt->bindValue(':tenantId', $tenantId, PDO::PARAM_INT);
                $stmt->bindValue(':id', (int)$data['id'], PDO::PARAM_INT);

                $stmt->execute();
                $id = (int)$data['id'];
            } else {
                $sql = "
                    INSERT INTO tenant_users (tenant_id, user_id, role_id, entity_id, joined_at, is_active)
                    VALUES (:tenantId, :user_id, :role_id, :entity_id, NOW(), :is_active)
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':tenantId', $tenantId, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', (int)$data['user_id'], PDO::PARAM_INT);

                if (array_key_exists('role_id', $data) && ($data['role_id'] === null || $data['role_id'] === '')) {
                    $stmt->bindValue(':role_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':role_id', isset($data['role_id']) ? (int)$data['role_id'] : null, PDO::PARAM_INT);
                }

                // entity_id nullable handling
                if (array_key_exists('entity_id', $data) && ($data['entity_id'] === null || $data['entity_id'] === '')) {
                    $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':entity_id', isset($data['entity_id']) ? (int)$data['entity_id'] : null, PDO::PARAM_INT);
                }

                $stmt->bindValue(':is_active', isset($data['is_active']) ? (int)$data['is_active'] : 1, PDO::PARAM_INT);

                $stmt->execute();
                $id = (int)$this->pdo->lastInsertId();
            }

            // Log action (non-fatal)
            if ($actingUserId) {
                $this->logAction($tenantId, $actingUserId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
            }

            return $id;
        } catch (PDOException $ex) {
            // Log helpful context for debugging
            error_log('[PdoTenant_usersRepository::save] PDOException: ' . $ex->getMessage() . ' | Tenant: ' . $tenantId . ' | Data: ' . json_encode($data));
            throw $ex;
        }
    }

    /**
     * Delete tenant user
     */
    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($tenantId, $id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("
            DELETE FROM tenant_users
            WHERE tenant_id = :tenantId AND id = :id
        ");
        $result = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

        if ($userId) {
            $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
        }

        return (bool)$result;
    }

    /**
     * Bulk activate/deactivate
     */
    public function bulkUpdateStatus(int $tenantId, array $ids, int $isActive, ?int $userId = null): int
    {
        if (empty($ids)) return 0;

        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        $sql = "
            UPDATE tenant_users
            SET is_active = ?, updated_at = NOW()
            WHERE tenant_id = ? AND id IN ({$placeholders})
        ";
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([(int)$isActive, $tenantId], array_values($ids));
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        if ($userId && $affected > 0) {
            $this->logAction($tenantId, $userId, 'bulk_status_update', 0, null, [
                'ids' => $ids,
                'is_active' => $isActive,
                'affected_count' => $affected
            ]);
        }

        return (int)$affected;
    }

    /**
     * Log actions to entity_logs table (best-effort)
     */
    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        } elseif ($action === 'bulk_status_update' && $newData) {
            $changes = json_encode($newData);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
                VALUES (:tenantId, :userId, 'tenant_user', :entityId, :action, :changes, :ip, NOW())
            ");
            $stmt->execute([
                ':tenantId' => $tenantId,
                ':userId'   => $userId,
                ':entityId' => $entityId,
                ':action'   => $action,
                ':changes'  => $changes,
                ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            // Do not let logging failures break main flow; record for ops
            error_log('[PdoTenant_usersRepository::logAction] ' . $e->getMessage());
        }
    }
}