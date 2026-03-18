<?php
declare(strict_types=1);

final class PdoUsersRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $sql = "
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE 1=1
        ";

        $params = [];

        // Filters
        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY u.created_at DESC";

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
        $sql = "SELECT COUNT(*) FROM users u WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['search']) && $filters['search']) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.username = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email, u.preferred_language, u.phone, u.is_active, u.created_at, u.updated_at
            FROM users u
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->find((int)$data['id']) : null;

        // Check username/email uniqueness
        if (!$isUpdate || ($oldData && $oldData['username'] !== $data['username'])) {
            if ($this->findByUsername($data['username'])) {
                throw new RuntimeException('Username already exists');
            }
        }
        if (!$isUpdate || ($oldData && $oldData['email'] !== $data['email'])) {
            if ($this->findByEmail($data['email'])) {
                throw new RuntimeException('Email already exists');
            }
        }

        $passwordHash = isset($data['password']) && $data['password'] ? password_hash($data['password'], PASSWORD_DEFAULT) : null;

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET username = :username, email = :email, " . ($passwordHash ? "password_hash = :password_hash, " : "") . "
                    preferred_language = :preferred_language,
                    phone = :phone, is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $params = [
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':phone' => $data['phone'] ?: null,
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':id' => (int)$data['id']
            ];
            if ($passwordHash) $params[':password_hash'] = $passwordHash;
            $stmt->execute($params);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, preferred_language, phone, is_active, created_at)
                VALUES (:username, :email, :password_hash, :preferred_language, :phone, :is_active, NOW())
            ");
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $passwordHash ?: '',
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':phone' => $data['phone'] ?: null,
                ':is_active' => (int)($data['is_active'] ?? 1)
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        // Log the action
        if ($userId) {
            $this->logAction($userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $oldData = $this->find($id);
        if (!$oldData) return false;

        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        // Log the action
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
            VALUES (1, :userId, 'user', :entityId, :action, :changes, :ip, NOW())
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