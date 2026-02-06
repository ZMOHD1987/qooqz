<?php
declare(strict_types=1);


final class PdoPermissionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE p.tenant_id = :tenant_id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ";

        $params = [':tenant_id' => $tenantId];

        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (p.key_name LIKE :search OR p.display_name LIKE :search OR p.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

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

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM permissions WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (key_name LIKE :search OR display_name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE p.id = :id AND p.tenant_id = :tenant_id
            GROUP BY p.id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByKey(int $tenantId, string $key): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, 
                   COUNT(rp.id) as roles_count,
                   GROUP_CONCAT(r.display_name SEPARATOR ', ') as roles_names
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            WHERE p.key_name = :key_name AND p.tenant_id = :tenant_id
            GROUP BY p.id
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
                throw new RuntimeException('Permission key already exists in this tenant');
            }
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE permissions
                SET key_name = :key_name, display_name = :display_name, description = :description
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':key_name' => $data['key_name'],
                ':display_name' => $data['display_name'],
                ':description' => $data['description'] ?: null,
                ':id' => (int)$data['id'],
                ':tenant_id' => $tenantId
            ]);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO permissions (tenant_id, key_name, display_name, description, created_at)
                VALUES (:tenant_id, :key_name, :display_name, :description, NOW())
            ");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':key_name' => $data['key_name'],
                ':display_name' => $data['display_name'],
                ':description' => $data['description'] ?: null
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
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE permission_id = :permission_id AND tenant_id = :tenant_id");
        $stmt->execute([':permission_id' => $id, ':tenant_id' => $tenantId]);

        // Delete permission
        $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = :id AND tenant_id = :tenant_id");
        $result = $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
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
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (?, ?, 'permission', ?, ?, ?, ?, NOW())
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