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
            SELECT u.*, c.name AS country_name, ci.name AS city_name, r.display_name AS role_name
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN cities ci ON u.city_id = ci.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE 1=1
        ";

        $params = [];

        // Filters
        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['role_id']) && $filters['role_id']) {
            $sql .= " AND u.role_id = :role_id";
            $params[':role_id'] = $filters['role_id'];
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
        if (isset($filters['role_id']) && $filters['role_id']) {
            $sql .= " AND u.role_id = :role_id";
            $params[':role_id'] = $filters['role_id'];
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
            SELECT u.*, c.name AS country_name, ci.name AS city_name, r.display_name AS role_name
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN cities ci ON u.city_id = ci.id
            LEFT JOIN roles r ON u.role_id = r.id
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
            SELECT u.*, c.name AS country_name, ci.name AS city_name, r.display_name AS role_name
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN cities ci ON u.city_id = ci.id
            LEFT JOIN roles r ON u.role_id = r.id
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
            SELECT u.*, c.name AS country_name, ci.name AS city_name, r.display_name AS role_name
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN cities ci ON u.city_id = ci.id
            LEFT JOIN roles r ON u.role_id = r.id
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
                    preferred_language = :preferred_language, country_id = :country_id, city_id = :city_id,
                    phone = :phone, role_id = :role_id, timezone = :timezone, is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $params = [
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':country_id' => $data['country_id'] ?: null,
                ':city_id' => $data['city_id'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':role_id' => $data['role_id'] ?: null,
                ':timezone' => $data['timezone'] ?? 'UTC',
                ':is_active' => (int)($data['is_active'] ?? 1),
                ':id' => (int)$data['id']
            ];
            if ($passwordHash) $params[':password_hash'] = $passwordHash;
            $stmt->execute($params);
            $id = (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, preferred_language, country_id, city_id, phone, role_id, timezone, is_active, created_at)
                VALUES (:username, :email, :password_hash, :preferred_language, :country_id, :city_id, :phone, :role_id, :timezone, :is_active, NOW())
            ");
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $passwordHash ?: '',
                ':preferred_language' => $data['preferred_language'] ?? 'en',
                ':country_id' => $data['country_id'] ?: null,
                ':city_id' => $data['city_id'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':role_id' => $data['role_id'] ?: null,
                ':timezone' => $data['timezone'] ?? 'UTC',
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