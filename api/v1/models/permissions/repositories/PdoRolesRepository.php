<?php
declare(strict_types=1);


final class PdoRolesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.tenant_id = :tenant_id
            GROUP BY r.id
            ORDER BY r.created_at DESC
        ";

        $params = [':tenant_id' => $tenantId];

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles WHERE tenant_id = :tenant_id");
        $stmt->execute([':tenant_id' => $tenantId]);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.id = :id AND r.tenant_id = :tenant_id
            GROUP BY r.id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByKey(int $tenantId, string $key): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, 
                   COUNT(rp.id) as permissions_count,
                   GROUP_CONCAT(p.display_name SEPARATOR ', ') as permissions_names
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.key_name = :key_name AND r.tenant_id = :tenant_id
            GROUP BY r.id
            LIMIT 1
        ");
        $stmt->execute([':key_name' => $key, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find($tenantId, (int)$data['id']) : null;

        // Check uniqueness
        if (!$isUpdate || ($oldData && $oldData['key_name'] !== $data['key_name'])) {
            if ($this->findByKey($tenantId, $data['key_name'])) {
                throw new RuntimeException('Role key already exists in this tenant');
            }
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE roles
                SET key_name = :key_name, display_name = :display_name
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':key_name' => $data['key_name'],
                ':display_name' => $data['display_name'],
                ':id' => (int)$data['id'],
                ':tenant_id' => $tenantId
            ]);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO roles (tenant_id, key_name, display_name, created_at)
                VALUES (:tenant_id, :key_name, :display_name, NOW())
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':key_name' => $data['key_name'],
                ':display_name' => $data['display_name']
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($tenantId, $id);
        if (!$oldData) return false;

        // Delete role permissions first
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
        $stmt->execute([':role_id' => $id, ':tenant_id' => $tenantId]);

        // Delete role
        $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = :id AND tenant_id = :tenant_id");
        $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function getRolePermissions(int $tenantId, int $roleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id AND rp.tenant_id = :tenant_id
            ORDER BY p.display_name
        ");
        $stmt->execute([':role_id' => $roleId, ':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignPermissions(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        // Delete existing
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND tenant_id = :tenant_id");
        $stmt->execute([':role_id' => $roleId, ':tenant_id' => $tenantId]);

        // Insert new
        if (!empty($permissionIds)) {
            $stmt = $this->pdo->prepare("
                INSERT INTO role_permissions (tenant_id, role_id, permission_id, created_at)
                VALUES (:tenant_id, :role_id, :permission_id, NOW())
            ");

            foreach ($permissionIds as $permId) {
                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':role_id' => $roleId,
                    ':permission_id' => (int)$permId
                ]);
            }
        }

        if ($userId) {
            $this->logAction($userId, 'assign_permissions', $roleId, null, ['permission_ids' => $permissionIds]);
        }
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        } elseif ($action === 'assign_permissions' && $newData) {
            $changes = json_encode($newData);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (?, ?, 'role', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            null, // tenant_id for roles is per tenant, but log globally
            $userId,
            $entityId,
            $action,
            $changes,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}