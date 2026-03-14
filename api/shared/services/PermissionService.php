<?php
declare(strict_types=1);
///api/shared/services/PermissionService.php

/**
 * PermissionService.php
 *
 * Enhanced PermissionService that:
 * - Is resilient when session does not contain permissions/roles (will load from DB)
 * - Resolves permission keys to ids and checks role_permissions when needed
 * - Builds resource-level effective permissions by merging resource_permissions rows
 *   (supports role_id and tenant_id, with override precedence)
 * - Provides row-level and list-level checks and safe WHERE clause builder
 *
 * Assumptions:
 * - Tables: permissions(id,key_name), role_permissions(permission_id,role_id,tenant_id),
 *   resource_permissions(permission_id,resource_type,role_id,tenant_id,can_...,created_at)
 * - tenant_users contains user -> role mapping (user_id, tenant_id, role_id)
 *
 * Notes:
 * - This file does not depend on RBAC helper; it contains its own safe merge logic
 *   filtered by permission_id (so it stays focused and backwards-compatible).
 */

final class PermissionService
{
    private PDO $pdo;
    private ?int $currentUserId = null;
    private ?int $currentTenantId = null;
    private array $userPermissions = []; // permission key strings
    private array $userRoles = [];       // role ids or names
    private array $resourcePermissionsCache = [];
    private array $permissionIdCache = [];
    private array $columnExistsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadCurrentUser();
    }

    private function loadCurrentUser(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $this->currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $this->currentTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;

        $this->userPermissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
        $this->userRoles = is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];

        // Normalize role ids if they are numeric strings
        $this->userRoles = array_map(function ($r) {
            if (is_numeric($r)) return (int)$r;
            return $r;
        }, $this->userRoles);
    }

    /**
     * Is super admin (fast path)
     */
    public function isSuperAdmin(): bool
    {
        if (in_array('super_admin', $this->userRoles, true)) return true;
        // also allow role id 1 as classic super-role
        foreach ($this->userRoles as $r) {
            if (is_int($r) && $r === 1) return true;
        }
        return false;
    }

    /**
     * Resolve permission key -> id (cached)
     */
    private function resolvePermissionId(string $permissionKey): ?int
    {
        if (isset($this->permissionIdCache[$permissionKey])) {
            return $this->permissionIdCache[$permissionKey];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM permissions WHERE key_name = :k LIMIT 1");
            $stmt->execute([':k' => $permissionKey]);
            $id = $stmt->fetchColumn();
            $id = $id !== false ? (int)$id : null;
            $this->permissionIdCache[$permissionKey] = $id;
            return $id;
        } catch (Throwable $e) {
            error_log('[PermissionService] resolvePermissionId error: ' . $e->getMessage());
            $this->permissionIdCache[$permissionKey] = null;
            return null;
        }
    }

    /**
     * Has permission (flexible):
     * - Super admin => true
     * - If permission key present in session permissions => true
     * - If session contains permission ids, accept them
     * - Otherwise resolve permission id and check role_permissions for user's roles
     */
    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) return true;

        // 1) direct textual permission in session
        if (in_array($permissionKey, $this->userPermissions, true)) {
            return true;
        }

        // 2) if userPermissions contains numeric ids, resolve permission id and compare
        foreach ($this->userPermissions as $p) {
            if (is_numeric($p)) {
                $pid = (int)$p;
                $resolved = $this->resolvePermissionId($permissionKey);
                if ($resolved !== null && $pid === $resolved) {
                    return true;
                }
            }
        }

        // 3) resolve permission id and check role_permissions via DB using user's roles
        $permissionId = $this->resolvePermissionId($permissionKey);
        if (!$permissionId) return false;

        // resolve user's role ids if roles are textual by looking up roles table
        $roleIds = $this->resolveRoleIds($this->userRoles);
        if (empty($roleIds)) {
            return false;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $sql = "SELECT COUNT(*) FROM role_permissions rp WHERE rp.permission_id = ? AND rp.role_id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $params = array_merge([$permissionId], $roleIds);
            $stmt->execute($params);
            $cnt = (int)$stmt->fetchColumn();
            return $cnt > 0;
        } catch (Throwable $e) {
            error_log('[PermissionService] hasPermission DB error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve mixed userRoles (names or ids) to numeric role ids.
     */
    private function resolveRoleIds(array $roles): array
    {
        $ids = [];
        $names = [];
        foreach ($roles as $r) {
            if ($r === null || $r === '') continue;
            if (is_int($r) || ctype_digit((string)$r)) {
                $ids[] = (int)$r;
            } else {
                $names[] = (string)$r;
            }
        }
        if (!empty($names)) {
            try {
                $placeholders = implode(',', array_fill(0, count($names), '?'));
                $sql = "SELECT id FROM roles WHERE name IN ({$placeholders}) OR key_name IN ({$placeholders})";
                // bind names twice? prepare with merged params
                $stmt = $this->pdo->prepare($sql);
                $params = array_merge($names, $names);
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
                    $ids[] = (int)$row;
                }
            } catch (Throwable $e) {
                error_log('[PermissionService] resolveRoleIds error: ' . $e->getMessage());
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Build effective resource permissions for a given resource + permissionKey.
     * Merge precedence: global -> role-global -> tenant-global -> role+tenant (override).
     *
     * Returns array with boolean flags.
     */
    public function getResourcePermissions(string $resource, string $permissionKey): array
    {
        // cache key per resource + permission + tenant + user
        $cacheKey = "{$resource}:{$permissionKey}:t:" . ($this->currentTenantId ?? 'null') . ":u:" . ($this->currentUserId ?? 'null');
        if (isset($this->resourcePermissionsCache[$cacheKey])) {
            return $this->resourcePermissionsCache[$cacheKey];
        }

        $permissionId = $this->resolvePermissionId($permissionKey);
        if (!$permissionId) {
            $effective = $this->emptyResourcePermissions();
            $this->resourcePermissionsCache[$cacheKey] = $effective;
            return $effective;
        }

        // default structure
        $effective = $this->emptyResourcePermissions();

        // find user's role id in current tenant (if any)
        $roleId = $this->getUserRoleId($this->currentUserId, $this->currentTenantId);

        // helper to apply a db row (override semantics)
        $applyRow = function (array $row) use (&$effective) {
            $flags = array_keys($effective);
            foreach ($flags as $f) {
                if (array_key_exists($f, $row) && $row[$f] !== null) {
                    $effective[$f] = (bool)((int)$row[$f]);
                }
            }
        };

        try {
            // 1) global rows (role_id IS NULL, tenant_id IS NULL)
            $stmt = $this->pdo->prepare("
                SELECT * FROM resource_permissions
                WHERE permission_id = :pid AND resource_type = :resource
                  AND role_id IS NULL AND tenant_id IS NULL
            ");
            $stmt->execute([':pid' => $permissionId, ':resource' => $resource]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $applyRow($r);

            // if no tenant context, return global-only effective
            if (empty($this->currentTenantId)) {
                $this->resourcePermissionsCache[$cacheKey] = $effective;
                return $effective;
            }

            // 2) role-global (role-specific but tenant IS NULL)
            if ($roleId !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM resource_permissions
                    WHERE permission_id = :pid AND resource_type = :resource
                      AND role_id = :role AND tenant_id IS NULL
                ");
                $stmt->execute([':pid' => $permissionId, ':resource' => $resource, ':role' => $roleId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $applyRow($r);
            }

            // 3) tenant-global (role_id IS NULL AND tenant_id = current)
            $stmt = $this->pdo->prepare("
                SELECT * FROM resource_permissions
                WHERE permission_id = :pid AND resource_type = :resource
                  AND role_id IS NULL AND tenant_id = :tenant
            ");
            $stmt->execute([':pid' => $permissionId, ':resource' => $resource, ':tenant' => $this->currentTenantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $applyRow($r);

            // 4) role+tenant (most specific)
            if ($roleId !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM resource_permissions
                    WHERE permission_id = :pid AND resource_type = :resource
                      AND role_id = :role AND tenant_id = :tenant
                ");
                $stmt->execute([':pid' => $permissionId, ':resource' => $resource, ':role' => $roleId, ':tenant' => $this->currentTenantId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $applyRow($r);
            }

            $this->resourcePermissionsCache[$cacheKey] = $effective;
            return $effective;
        } catch (Throwable $e) {
            error_log('[PermissionService] getResourcePermissions error: ' . $e->getMessage());
            $this->resourcePermissionsCache[$cacheKey] = $effective;
            return $effective;
        }
    }

    /**
     * Can view resource?
     */
    public function canView(string $resource, string $permissionKey, ?int $resourceOwnerId = null, ?int $resourceTenantId = null): bool
    {
        if ($this->isSuperAdmin()) return true;

        $perms = $this->getResourcePermissions($resource, $permissionKey);

        if ($perms['can_view_all']) return true;

        // tenants special-case: owner_user_id vs tenant id
        if ($resource === 'tenants') {
            if ($perms['can_view_own'] && $resourceOwnerId !== null && $resourceOwnerId === $this->currentUserId) {
                return true;
            }
            if ($perms['can_view_tenant'] && $resourceTenantId !== null && $resourceTenantId === $this->currentTenantId) {
                return true;
            }
            return false;
        }

        // other resources
        if ($perms['can_view_own'] && $resourceOwnerId !== null && $resourceOwnerId === $this->currentUserId) return true;
        if ($perms['can_view_tenant'] && $resourceTenantId !== null && $resourceTenantId === $this->currentTenantId) return true;

        return false;
    }

    public function canCreate(string $resource, string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) return true;
        $perms = $this->getResourcePermissions($resource, $permissionKey);
        return (bool)$perms['can_create'];
    }

    public function canEdit(string $resource, string $permissionKey, ?int $resourceOwnerId = null): bool
    {
        if ($this->isSuperAdmin()) return true;
        $perms = $this->getResourcePermissions($resource, $permissionKey);
        if ($perms['can_edit_all']) return true;
        if ($perms['can_edit_own'] && $resourceOwnerId !== null && $resourceOwnerId === $this->currentUserId) return true;
        return false;
    }

    public function canDelete(string $resource, string $permissionKey, ?int $resourceOwnerId = null): bool
    {
        if ($this->isSuperAdmin()) return true;
        $perms = $this->getResourcePermissions($resource, $permissionKey);
        if ($perms['can_delete_all']) return true;
        if ($perms['can_delete_own'] && $resourceOwnerId !== null && $resourceOwnerId === $this->currentUserId) return true;
        return false;
    }

    /**
     * Build WHERE clause for lists given the resource and permissionKey.
     * Returns ['where' => string, 'params' => array]
     */
    public function buildListWhereClause(string $resource, string $permissionKey, string $tableAlias = ''): array
    {
        $prefix = $tableAlias ? ($tableAlias . '.') : '';
        $perms = $this->getResourcePermissions($resource, $permissionKey);

        // super admin or can_view_all => no filter
        if ($this->isSuperAdmin() || $perms['can_view_all']) {
            return ['where' => '', 'params' => []];
        }

        $conditions = [];
        $params = [];

        // tenant special-case
        if ($resource === 'tenants') {
            if ($perms['can_view_tenant'] && $this->currentTenantId) {
                if ($this->columnExists('tenants', 'id')) {
                    $conditions[] = "{$prefix}id = :current_tenant_id";
                    $params[':current_tenant_id'] = $this->currentTenantId;
                }
            }
            if ($perms['can_view_own'] && $this->currentUserId) {
                if ($this->columnExists('tenants', 'owner_user_id')) {
                    $conditions[] = "{$prefix}owner_user_id = :current_user_id";
                    $params[':current_user_id'] = $this->currentUserId;
                }
            }
        } else {
            // tenant scoping
            $tenantCol = $this->getTenantColumn($resource);
            if ($perms['can_view_tenant'] && $this->currentTenantId && $this->columnExists($resource, $tenantCol)) {
                $conditions[] = "{$prefix}{$tenantCol} = :current_tenant_id";
                $params[':current_tenant_id'] = $this->currentTenantId;
            }

            // ownership flexible columns
            if ($perms['can_view_own'] && $this->currentUserId) {
                $ownerCandidates = array_merge([$this->getOwnershipColumn($resource)], ['user_id','created_by','owner_id','owner_user_id']);
                $ownerConds = [];
                foreach ($ownerCandidates as $col) {
                    if ($this->columnExists($resource, $col)) {
                        $ownerConds[] = "{$prefix}{$col} = :current_user_id";
                    }
                }
                if (!empty($ownerConds)) {
                    $conditions[] = '(' . implode(' OR ', $ownerConds) . ')';
                    $params[':current_user_id'] = $this->currentUserId;
                }
            }
        }

        if (empty($conditions)) {
            // no permissions
            return ['where' => '1 = 0', 'params' => []];
        }

        $where = '(' . implode(' OR ', $conditions) . ')';
        return ['where' => $where, 'params' => $params];
    }

    // -----------------------
    // Helpers & small utilities
    // -----------------------

    private function emptyResourcePermissions(): array
    {
        return [
            'can_view_all' => false,
            'can_view_own' => false,
            'can_view_tenant' => false,
            'can_create' => false,
            'can_edit_all' => false,
            'can_edit_own' => false,
            'can_delete_all' => false,
            'can_delete_own' => false,
        ];
    }

    private function getUserRoleId(?int $userId, ?int $tenantId): ?int
    {
        if (empty($userId) || empty($tenantId)) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT role_id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$userId, $tenantId]);
            $r = $stmt->fetchColumn();
            return $r !== false ? (int)$r : null;
        } catch (Throwable $e) {
            error_log('[PermissionService] getUserRoleId error: ' . $e->getMessage());
            return null;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (isset($this->columnExistsCache[$key])) return $this->columnExistsCache[$key];
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col
            ");
            $stmt->execute([':table' => $table, ':col' => $column]);
            $exists = (bool)$stmt->fetchColumn();
            $this->columnExistsCache[$key] = $exists;
            return $exists;
        } catch (Throwable $e) {
            error_log('[PermissionService] columnExists error: ' . $e->getMessage());
            $this->columnExistsCache[$key] = false;
            return false;
        }
    }

    /**
     * Return default tenant column for resources
     */
    private function getTenantColumn(string $resource): string
    {
        return $resource === 'tenants' ? 'id' : 'tenant_id';
    }

    /**
     * Ownership mapping (can be extended)
     */
    private function getOwnershipColumn(string $resource): string
    {
        static $map = [
            'tenants' => 'owner_user_id',
            'users' => 'id',
            'tenant_users' => 'user_id',
            'orders' => 'user_id',
            'products' => 'created_by',
        ];
        return $map[$resource] ?? 'created_by';
    }
}