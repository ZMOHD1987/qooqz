<?php
declare(strict_types=1);

namespace App\Models\Permissions\Repositories;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Production-ready repository for resource_permissions with safe upsert fallback.
 */
class PdoResourcePermissionsRepository
{
    private PDO $pdo;
    private array $flagCols = ['can_view_all','can_view_own','can_view_tenant','can_create','can_edit_all','can_edit_own','can_delete_all','can_delete_own'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // Ensure exceptions on error to simplify error handling
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function list(array $filters = []): array
    {
        $sql = "SELECT * FROM resource_permissions rp WHERE 1=1";
        $params = [];

        if (array_key_exists('tenant_id', $filters)) {
            if ($filters['tenant_id'] === null) {
                $sql .= " AND rp.tenant_id IS NULL";
            } else {
                $sql .= " AND (rp.tenant_id IS NULL OR rp.tenant_id = :tenant_id)";
                $params[':tenant_id'] = (int)$filters['tenant_id'];
            }
        }

        if (!empty($filters['role_id'])) {
            $sql .= " AND (rp.role_id IS NULL OR rp.role_id = :role_id)";
            $params[':role_id'] = (int)$filters['role_id'];
        }

        if (!empty($filters['resource_type'])) {
            $sql .= " AND rp.resource_type = :resource_type";
            $params[':resource_type'] = $filters['resource_type'];
        }

        if (!empty($filters['permission_id'])) {
            $sql .= " AND rp.permission_id = :permission_id";
            $params[':permission_id'] = (int)$filters['permission_id'];
        }

        $sql .= " ORDER BY rp.permission_id, rp.resource_type, rp.role_id IS NULL DESC, rp.tenant_id IS NULL DESC, rp.id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r['id'] = isset($r['id']) ? (int)$r['id'] : null;
            $r['permission_id'] = isset($r['permission_id']) ? (int)$r['permission_id'] : null;
            $r['role_id'] = array_key_exists('role_id', $r) && $r['role_id'] !== null ? (int)$r['role_id'] : null;
            $r['tenant_id'] = array_key_exists('tenant_id', $r) && $r['tenant_id'] !== null ? (int)$r['tenant_id'] : null;
            foreach ($this->flagCols as $f) {
                $r[$f] = isset($r[$f]) ? (int)$r[$f] : 0;
            }
        }
        unset($r);

        return $rows;
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM resource_permissions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['id'] = (int)$row['id'];
        foreach ($this->flagCols as $f) $row[$f] = isset($row[$f]) ? (int)$row[$f] : 0;
        return $row;
    }

    public function updateById(int $id, array $fields): bool
    {
        if (empty($fields)) return true;
        $set = [];
        $params = [];
        foreach ($fields as $k => $v) {
            if ($k === 'id') continue;
            $set[] = "`$k` = :$k";
            $params[":$k"] = $v;
        }
        $params[':id'] = $id;
        $sql = "UPDATE resource_permissions SET " . implode(', ', $set) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Safe upsert:
     * - Try INSERT ... ON DUPLICATE KEY UPDATE (fast path) with id = LAST_INSERT_ID(id)
     * - If it fails for unexpected reason, fallback to transactional SELECT ... FOR UPDATE then UPDATE/INSERT
     *
     * Returns the id of the inserted/updated row (>0).
     */
    public function upsertByUnique(array $item): int
    {
        $norm = $this->normalizeUpsertItem($item);

        // VALIDATION: Check if permission_id exists before attempting insert
        if ($norm['permission_id']) {
            $stmt = $this->pdo->prepare("SELECT id FROM permissions WHERE id = ?");
            $stmt->execute([$norm['permission_id']]);
            if (!$stmt->fetch()) {
                throw new \InvalidArgumentException("Permission ID {$norm['permission_id']} does not exist in permissions table");
            }
        } else {
            throw new \InvalidArgumentException("permission_id is required");
        }

        // VALIDATION: Check if resource_type is not empty
        if (empty($norm['resource_type'])) {
            throw new \InvalidArgumentException("resource_type is required and cannot be empty");
        }

        // Build columns
        $cols = ['resource_type','permission_id','role_id','tenant_id'];
        foreach ($this->flagCols as $f) $cols[] = $f;

        $insertCols = array_map(fn($c) => "`$c`", $cols);
        $placeholders = array_map(fn($c) => ":$c", $cols);

        // Use LAST_INSERT_ID(id) trick so lastInsertId() returns the id whether new or existing
        $updateSet = array_map(fn($c) => "`$c` = VALUES(`$c`)", $cols);
        $sql = "INSERT INTO resource_permissions (" . implode(',', $insertCols) . ")
                VALUES (" . implode(',', $placeholders) . ")
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), " . implode(', ', $updateSet);

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind values with correct types (NULL handling)
            // tenant_id may be null
            if ($norm['tenant_id'] === null) {
                $stmt->bindValue(':tenant_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':tenant_id', (int)$norm['tenant_id'], PDO::PARAM_INT);
            }

            // role_id may be null
            if ($norm['role_id'] === null) {
                $stmt->bindValue(':role_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':role_id', (int)$norm['role_id'], PDO::PARAM_INT);
            }

            $stmt->bindValue(':resource_type', $norm['resource_type'], PDO::PARAM_STR);
            $stmt->bindValue(':permission_id', (int)$norm['permission_id'], PDO::PARAM_INT);
            foreach ($this->flagCols as $f) {
                $stmt->bindValue(":$f", (int)$norm[$f], PDO::PARAM_INT);
            }

            // optional logging for traceability (if safe_log exists)
            if (function_exists('safe_log')) {
                safe_log('info', 'rp.upsert.attempt', ['payload' => $norm]);
            }

            $stmt->execute();
            // thanks to id = LAST_INSERT_ID(id) above, lastInsertId returns inserted id or existing id
            $id = (int)$this->pdo->lastInsertId();
            if ($id <= 0) {
                // Defensive: if lastInsertId is 0 (unlikely with trick), try to fetch the row id
                $existing = $this->findByUnique($norm['role_id'], $norm['resource_type'], $norm['tenant_id']);
                if ($existing) $id = (int)$existing['id'];
            }

            if (function_exists('safe_log')) {
                safe_log('info', 'rp.upsert.success', ['id' => $id, 'payload' => $norm]);
            }

            return $id;
        } catch (Throwable $e) {
            // Fallback transactional approach with SELECT ... FOR UPDATE to avoid race
            if (function_exists('safe_log')) {
                safe_log('error', 'rp.upsert.failed', ['error' => $e->getMessage(), 'payload' => $norm]);
            }

            try {
                $this->pdo->beginTransaction();

                // lock candidate row (if exists)
                $existing = $this->findByUniqueForUpdate($norm['role_id'], $norm['resource_type'], $norm['tenant_id']);
                if ($existing) {
                    $this->updateById((int)$existing['id'], $this->filterUpdatableFields($norm));
                    $this->pdo->commit();
                    if (function_exists('safe_log')) safe_log('info', 'rp.upsert.fallback.updated', ['id' => $existing['id']]);
                    return (int)$existing['id'];
                }

                // insert new
                $insertSql = "INSERT INTO resource_permissions (resource_type, permission_id, role_id, tenant_id, " . implode(',', $this->flagCols) . ", created_at)
                              VALUES (:resource_type, :permission_id, :role_id, :tenant_id, " . implode(',', array_map(fn($f)=>":$f", $this->flagCols)) . ", NOW())";
                $stmt2 = $this->pdo->prepare($insertSql);

                // bind for insert
                if ($norm['tenant_id'] === null) $stmt2->bindValue(':tenant_id', null, PDO::PARAM_NULL);
                else $stmt2->bindValue(':tenant_id', (int)$norm['tenant_id'], PDO::PARAM_INT);

                if ($norm['role_id'] === null) $stmt2->bindValue(':role_id', null, PDO::PARAM_NULL);
                else $stmt2->bindValue(':role_id', (int)$norm['role_id'], PDO::PARAM_INT);

                $stmt2->bindValue(':resource_type', $norm['resource_type'], PDO::PARAM_STR);
                $stmt2->bindValue(':permission_id', (int)$norm['permission_id'], PDO::PARAM_INT);
                foreach ($this->flagCols as $f) $stmt2->bindValue(":$f", (int)$norm[$f], PDO::PARAM_INT);

                $stmt2->execute();
                $newId = (int)$this->pdo->lastInsertId();
                $this->pdo->commit();

                if (function_exists('safe_log')) safe_log('info', 'rp.upsert.fallback.inserted', ['id' => $newId, 'payload' => $norm]);

                return $newId;
            } catch (Throwable $e2) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if (function_exists('safe_log')) safe_log('error', 'rp.upsert.fallback.failed', ['error' => $e2->getMessage(), 'payload' => $norm]);
                throw new RuntimeException('Upsert fallback failed: ' . $e2->getMessage(), 0, $e2);
            }
        }
    }

    /**
     * Find existing by composite unique (role_id, resource_type, tenant_id)
     * Returns row or null
     */
    public function findByUnique(?int $roleId, string $resourceType, $tenantId): ?array
    {
        $sql = "SELECT * FROM resource_permissions WHERE resource_type = :resource_type";
        $params = [':resource_type' => $resourceType];

        if ($roleId === null) {
            $sql .= " AND role_id IS NULL";
        } else {
            $sql .= " AND role_id = :role_id";
            $params[':role_id'] = $roleId;
        }

        if ($tenantId === null) {
            $sql .= " AND tenant_id IS NULL";
        } else {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$tenantId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['id'] = (int)$row['id'];
        foreach ($this->flagCols as $f) $row[$f] = isset($row[$f]) ? (int)$row[$f] : 0;
        return $row;
    }

    /**
     * Same as findByUnique but locks the row FOR UPDATE (used in fallback)
     */
    private function findByUniqueForUpdate(?int $roleId, string $resourceType, $tenantId): ?array
    {
        $sql = "SELECT * FROM resource_permissions WHERE resource_type = :resource_type";
        $params = [':resource_type' => $resourceType];

        if ($roleId === null) {
            $sql .= " AND role_id IS NULL";
        } else {
            $sql .= " AND role_id = :role_id";
            $params[':role_id'] = $roleId;
        }

        if ($tenantId === null) {
            $sql .= " AND tenant_id IS NULL";
        } else {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$tenantId;
        }

        $sql .= " LIMIT 1 FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['id'] = (int)$row['id'];
        foreach ($this->flagCols as $f) $row[$f] = isset($row[$f]) ? (int)$row[$f] : 0;
        return $row;
    }

    public function bulkUpsert(array $items): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $this->pdo->beginTransaction();
            foreach ($items as $idx => $item) {
                try {
                    if (!empty($item['id'])) {
                        $id = (int)$item['id'];
                        $fields = $this->filterUpdatableFields($item);
                        if (!empty($fields)) {
                            $ok = $this->updateById($id, $fields);
                            if ($ok) $updated++;
                            else $errors[] = "Index {$idx}: update failed for id {$id}";
                        } else {
                            $skipped++;
                        }
                    } else {
                        $newId = $this->upsertByUnique($item);
                        if ($newId > 0) $inserted++;
                        else $updated++; // defensive (should not happen now)
                    }
                } catch (Throwable $e) {
                    $errors[] = "Index {$idx}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                $this->pdo->rollBack();
                return ['inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors];
            }

            $this->pdo->commit();
            return ['inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped,'errors'=>[]];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM resource_permissions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    private function filterUpdatableFields(array $item): array
    {
        $allowed = array_merge(['resource_type','permission_id','role_id','tenant_id'], $this->flagCols);
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $item)) {
                // Convert 0 to null for tenant_id to match your DB semantics (if you expect 0 => global)
                if ($k === 'tenant_id' && ($item[$k] === 0 || $item[$k] === '0')) {
                    $out[$k] = null;
                } else {
                    $out[$k] = $item[$k] === '' ? null : $item[$k];
                }
            }
        }
        return $out;
    }

    private function normalizeUpsertItem(array $item): array
    {
        $out = [];
        $out['resource_type'] = isset($item['resource_type']) ? (string)$item['resource_type'] : '';
        $out['permission_id'] = isset($item['permission_id']) ? (int)$item['permission_id'] : null;
        $out['role_id'] = array_key_exists('role_id',$item) && $item['role_id'] !== '' ? (int)$item['role_id'] : null;

        // Convert 0 to null for tenant_id (treat 0 as global)
        if (array_key_exists('tenant_id',$item)) {
            if ($item['tenant_id'] === '' || $item['tenant_id'] === null || $item['tenant_id'] === 0 || $item['tenant_id'] === '0') {
                $out['tenant_id'] = null;
            } else {
                $out['tenant_id'] = (int)$item['tenant_id'];
            }
        } else {
            $out['tenant_id'] = null;
        }

        foreach ($this->flagCols as $f) {
            $out[$f] = isset($item[$f]) ? ((int)$item[$f] ? 1 : 0) : 0;
        }
        return $out;
    }
}