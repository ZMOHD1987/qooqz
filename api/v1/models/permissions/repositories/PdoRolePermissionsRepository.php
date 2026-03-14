<?php
declare(strict_types=1);


final class PdoRolePermissionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT rp.*, 
                   r.display_name as role_name, r.key_name as role_key,
                   p.display_name as permission_name, p.key_name as permission_key
            FROM role_permissions rp
            INNER JOIN roles r ON rp.role_id = r.id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.tenant_id = :tenant_id
            ORDER BY rp.created_at DESC
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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE tenant_id = :tenant_id");
        $stmt->execute([':tenant_id' => $tenantId]);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT rp.*, 
                   r.display_name as role_name, r.key_name as role_key,
                   p.display_name as permission_name, p.key_name as permission_key
            FROM role_permissions rp
            INNER JOIN roles r ON rp.role_id = r.id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.id = :id AND rp.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function assign(int $tenantId, int $roleId, int $permissionId, ?int $userId = null): int
    {
        // Check if already exists
        $stmt = $this->pdo->prepare("
            SELECT id FROM role_permissions 
            WHERE tenant_id = :tenant_id AND role_id = :role_id AND permission_id = :permission_id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':role_id' => $roleId,
            ':permission_id' => $permissionId
        ]);
        
        if ($stmt->fetch()) {
            throw new RuntimeException('Role permission already exists');
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO role_permissions (tenant_id, role_id, permission_id, created_at)
            VALUES (:tenant_id, :role_id, :permission_id, NOW())
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':role_id' => $roleId,
            ':permission_id' => $permissionId
        ]);
        $id = (int)$this->pdo->lastInsertId();

        if ($userId) {
            $this->logAction($userId, 'assign', $id, null, [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
        }

        return $id;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($tenantId, $id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE id = :id AND tenant_id = :tenant_id");
        $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    public function deleteByRolePermission(int $tenantId, int $roleId, int $permissionId, ?int $userId = null): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM role_permissions 
            WHERE tenant_id = :tenant_id AND role_id = :role_id AND permission_id = :permission_id
        ");
        $result = $stmt->execute([
            ':tenant_id' => $tenantId,
            ':role_id' => $roleId,
            ':permission_id' => $permissionId
        ]);

        if ($userId) {
            $this->logAction($userId, 'delete_by_ids', 0, null, [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
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

    public function assignMultiple(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
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
            $this->logAction($userId, 'assign_multiple', $roleId, null, ['permission_ids' => $permissionIds]);
        }
    }

    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'assign' && $newData) {
            $changes = json_encode(['assigned' => $newData]);
        } elseif ($action === 'assign_multiple' && $newData) {
            $changes = json_encode($newData);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (?, ?, 'role_permission', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId,
            $userId,
            $entityId,
            $action,
            $changes,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}