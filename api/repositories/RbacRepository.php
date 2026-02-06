<?php
declare(strict_types=1);

class RbacRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = connectDB();
    }

    public function getUserRoleId(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT role_id FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);
        $roleId = $stmt->fetchColumn();
        return $roleId ? (int)$roleId : null;
    }

    public function getPermissionsByRole(int $roleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.key_name
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :rid"
        );
        $stmt->execute(['rid' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
