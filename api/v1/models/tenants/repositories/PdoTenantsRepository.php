<?php
declare(strict_types=1);

final class PdoTenantsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all tenants with pagination and filters
     */
    public function all(int $perPage = 10, int $offset = 0, array $filters = []): array
    {
        $where = "WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($filters['search'])) {
            $where .= " AND (t.name LIKE :search OR t.domain LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $where .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['owner_user_id'])) {
            $where .= " AND t.owner_user_id = :owner_user_id";
            $params[':owner_user_id'] = (int)$filters['owner_user_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   u.username AS owner_username, 
                   u.email AS owner_email
            FROM tenants t
            JOIN users u ON t.owner_user_id = u.id
            {$where}
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count tenants with filters
     */
    public function count(array $filters = []): int
    {
        $where = "WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($filters['search'])) {
            $where .= " AND (t.name LIKE :search OR t.domain LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $where .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['owner_user_id'])) {
            $where .= " AND t.owner_user_id = :owner_user_id";
            $params[':owner_user_id'] = (int)$filters['owner_user_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total
            FROM tenants t
            JOIN users u ON t.owner_user_id = u.id
            {$where}
        ");
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    /**
     * Find single tenant
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   u.username AS owner_username, 
                   u.email AS owner_email
            FROM tenants t
            JOIN users u ON t.owner_user_id = u.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if domain exists
     */
    public function domainExists(string $domain, ?int $excludeId = null): bool
    {
        $where = "WHERE domain = :domain";
        $params = [':domain' => $domain];

        if ($excludeId) {
            $where .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM tenants {$where}");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }

    /**
     * Check if user exists
     */
    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }

    /**
     * Save tenant (create or update)
     */
    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE tenants
                SET name = :name, domain = :domain, owner_user_id = :owner_user_id, 
                    status = :status, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':domain' => $data['domain'],
                ':owner_user_id' => $data['owner_user_id'],
                ':status' => $data['status'] ?? 'active',
                ':id' => (int)$data['id']
            ]);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO tenants (name, domain, owner_user_id, status, created_at, updated_at)
                VALUES (:name, :domain, :owner_user_id, :status, NOW(), NOW())
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':domain' => $data['domain'],
                ':owner_user_id' => $data['owner_user_id'],
                ':status' => $data['status'] ?? 'active'
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    /**
     * Delete tenant
     */
    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM tenants WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        // Log the action
        if ($userId) {
            $this->logAction($userId, 'delete', $id, $oldData, null);
        }

        return $result;
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(array $ids, string $status, ?int $userId = null): int
    {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $this->pdo->prepare("
            UPDATE tenants
            SET status = ?, updated_at = NOW()
            WHERE id IN ({$placeholders})
        ");
        
        $params = array_merge([$status], $ids);
        $stmt->execute($params);
        
        $affected = $stmt->rowCount();
        
        // Log bulk action
        if ($userId && $affected > 0) {
            $this->logAction($userId, 'bulk_status_update', 0, null, [
                'ids' => $ids,
                'status' => $status,
                'affected_count' => $affected
            ]);
        }
        
        return $affected;
    }

    /**
     * Get tenant statistics
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_tenants,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tenants,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_tenants
            FROM tenants
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Log actions to entity_logs table
     */
    private function logAction(int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
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
                VALUES (NULL, :userId, 'tenant', :entityId, :action, :changes, :ip, NOW())
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':entityId' => $entityId,
                ':action' => $action,
                ':changes' => $changes,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            // Log to error log if entity_logs table doesn't exist
            error_log('Entity logs not available: ' . $e->getMessage());
        }
    }
}