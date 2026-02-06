<?php
declare(strict_types=1);

use RuntimeException;

final class PdoLanguagesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT l.*
            FROM languages l
            WHERE 1=1
        ";

        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND l.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (l.name LIKE :search OR l.code LIKE :search OR l.native_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY l.name";

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

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM languages l WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND l.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (l.name LIKE :search OR l.code LIKE :search OR l.native_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        // Check uniqueness
        if (!$isUpdate || ($oldData && $oldData['code'] !== $data['code'])) {
            if ($this->findByCode($data['code'])) {
                throw new RuntimeException('Language code already exists');
            }
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE languages
                SET name = :name, code = :code, native_name = :native_name, 
                    direction = :direction, is_active = :is_active, flag_url = :flag_url
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':code' => $data['code'],
                ':native_name' => $data['native_name'] ?: null,
                ':direction' => $data['direction'] ?? 'ltr',
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':flag_url' => $data['flag_url'] ?: null,
                ':id' => (int)$data['id']
            ]);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO languages (name, code, native_name, direction, is_active, flag_url, created_at)
                VALUES (:name, :code, :native_name, :direction, :is_active, :flag_url, NOW())
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':code' => $data['code'],
                ':native_name' => $data['native_name'] ?: null,
                ':direction' => $data['direction'] ?? 'ltr',
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':flag_url' => $data['flag_url'] ?: null
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM languages WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

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
            VALUES (1, :userId, 'language', :entityId, :action, :changes, :ip, NOW())
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':entityId' => $entityId,
            ':action' => $action,
            ':changes' => $changes,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}