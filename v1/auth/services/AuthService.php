<?php
declare(strict_types=1);

/**
 * AuthService (MVC STRICT)
 * - NO session handling
 * - NO cookies
 * - NO headers
 * - Business logic only
 */

require_once __DIR__ . '/../../../shared/core/Logger.php';
require_once __DIR__ . '/../../../shared/core/DatabaseConnection.php';

class AuthService
{
    private PDO $pdo;

    public function __construct()
    {
        try {
            $this->pdo = DatabaseConnection::getConnection();
        } catch (Throwable $e) {
            Logger::error('AuthService DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database unavailable');
        }
    }

    /**
     * Login user by identifier + password
     * Returns sanitized user array or null
     */
    public function login(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, username, email, phone, password_hash, is_active, role_id
                 FROM users
                 WHERE username = :u OR email = :e OR phone = :p
                 LIMIT 1"
            );

            $stmt->execute([
                ':u' => $identifier,
                ':e' => $identifier,
                ':p' => $identifier,
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return null;
            }

            if ((int)$user['is_active'] !== 1) {
                return null;
            }

            if (empty($user['password_hash'])) {
                return null;
            }

            // Password verify
            if (!password_verify($password, $user['password_hash'])) {

                // Legacy MD5 migration (optional)
                if (
                    preg_match('/^[a-f0-9]{32}$/i', $user['password_hash']) &&
                    md5($password) === $user['password_hash']
                ) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $this->upgradePasswordHash((int)$user['id'], $newHash);
                } else {
                    return null;
                }
            }

            // Sanitize output
            return [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role_id'  => isset($user['role_id']) ? (int)$user['role_id'] : null,
            ];

        } catch (Throwable $e) {
            Logger::error('AuthService::login error: ' . $e->getMessage());
            return null;
        }
    }

    private function upgradePasswordHash(int $userId, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = :h WHERE id = :id"
        );
        $stmt->execute([
            ':h'  => $hash,
            ':id' => $userId,
        ]);
    }
}
