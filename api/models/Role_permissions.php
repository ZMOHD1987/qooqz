<?php
// api/models/Role_permissions.php
// Model for role_permissions table (role <-> permission assignments)
// Provides static Role_permissions proxy used by routes/controller.

declare(strict_types=1);

if (!class_exists('Role_permissions')) {

class Role_permissions
{
    private static function getDB()
    {
        // Try common DB providers
        if (function_exists('container')) {
            try { $db = container('db'); if ($db instanceof mysqli) return $db; } catch (Throwable $e) {}
        }
        if (!empty($GLOBALS['CONTAINER']['db']) && $GLOBALS['CONTAINER']['db'] instanceof mysqli) return $GLOBALS['CONTAINER']['db'];
        foreach (['ADMIN_DB', 'db', 'mysqli', 'conn'] as $k) {
            if (!empty($GLOBALS[$k]) && $GLOBALS[$k] instanceof mysqli) return $GLOBALS[$k];
        }
        if (function_exists('connectDB')) {
            try { $maybe = @connectDB(); if ($maybe instanceof mysqli) return $maybe; } catch (Throwable $e) {}
        }
        if (function_exists('get_db')) {
            try { $maybe = @get_db(); if ($maybe instanceof mysqli) return $maybe; } catch (Throwable $e) {}
        }
        throw new Exception('Database connection not available');
    }

    private static function refValues(array &$arr)
    {
        $refs = [];
        foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
        return $refs;
    }

    public static function all(array $opts = []): array
    {
        $db = self::getDB();
        $where = [];
        $params = [];
        $types = '';

        if (!empty($opts['role_id'])) {
            $where[] = 'rp.role_id = ?';
            $params[] = (int)$opts['role_id'];
            $types .= 'i';
        }
        if (!empty($opts['permission_id'])) {
            $where[] = 'rp.permission_id = ?';
            $params[] = (int)$opts['permission_id'];
            $types .= 'i';
        }

        $sql = "SELECT rp.id, rp.role_id, r.key_name AS role_key, r.display_name AS role_display, rp.permission_id, p.key_name AS permission_key, p.display_name AS permission_display, rp.created_at
                FROM role_permissions rp
                LEFT JOIN roles r ON r.id = rp.role_id
                LEFT JOIN permissions p ON p.id = rp.permission_id";

        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY rp.id DESC';

        if (!empty($opts['limit'])) {
            $sql .= ' LIMIT ' . intval($opts['limit']);
            if (!empty($opts['offset'])) $sql .= ' OFFSET ' . intval($opts['offset']);
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        if ($params) {
            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->free();
        $stmt->close();
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT rp.id, rp.role_id, r.key_name AS role_key, r.display_name AS role_display, rp.permission_id, p.key_name AS permission_key, p.display_name AS permission_display, rp.created_at
                              FROM role_permissions rp
                              LEFT JOIN roles r ON r.id = rp.role_id
                              LEFT JOIN permissions p ON p.id = rp.permission_id
                              WHERE rp.id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        return $row ?: null;
    }

    public static function save(array $data)
    {
        $db = self::getDB();
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $role_id = (int)($data['role_id'] ?? 0);
        $permission_id = (int)($data['permission_id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE role_permissions SET role_id = ?, permission_id = ? WHERE id = ? LIMIT 1");
            if (!$stmt) throw new Exception('DB prepare failed (update): ' . $db->error);
            $stmt->bind_param('iii', $role_id, $permission_id, $id);
            $stmt->execute();
            $stmt->close();
            return $id;
        } else {
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
            if (!$stmt) throw new Exception('DB prepare failed (insert): ' . $db->error);
            $stmt->bind_param('ii', $role_id, $permission_id);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        }
    }

    public static function delete(int $id): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (delete): ' . $db->error);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public static function assign(int $role_id, int $permission_id): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
        if (!$stmt) throw new Exception('DB prepare failed (assign): ' . $db->error);
        $stmt->bind_param('ii', $role_id, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public static function remove(int $role_id, int $permission_id): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        if (!$stmt) throw new Exception('DB prepare failed (remove): ' . $db->error);
        $stmt->bind_param('ii', $role_id, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

} // end class_exists