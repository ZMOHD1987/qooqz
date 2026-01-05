<?php
// api/models/Permissions.php
declare(strict_types=1);

/**
 * PermissionModel and static Permissions wrapper
 * Matches the DB schema you provided.
 */

class PermissionModel
{
    protected ?mysqli $db = null;
    protected string $logPath;

    public function __construct($db = null)
    {
        $this->logPath = __DIR__ . '/../error_log.txt';

        if ($db instanceof mysqli) { $this->db = $db; return; }
        if (!empty($GLOBALS['ADMIN_DB']) && $GLOBALS['ADMIN_DB'] instanceof mysqli) { $this->db = $GLOBALS['ADMIN_DB']; return; }
        if (!empty($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) { $this->db = $GLOBALS['db']; return; }

        if (function_exists('connectDB')) {
            try { $maybe = @connectDB(); if ($maybe instanceof mysqli) { $this->db = $maybe; return; } } catch (Throwable $e) { $this->log('connectDB error: '.$e->getMessage()); }
        }

        $cfgPath = __DIR__ . '/../config/db.php';
        if (is_readable($cfgPath)) {
            $cfg = require $cfgPath;
            $host = $cfg['host'] ?? $cfg['DB_HOST'] ?? null;
            $user = $cfg['user'] ?? $cfg['DB_USER'] ?? null;
            $pass = $cfg['pass'] ?? $cfg['DB_PASS'] ?? null;
            $name = $cfg['name'] ?? $cfg['DB_NAME'] ?? null;
            $port = isset($cfg['port']) ? (int)$cfg['port'] : 3306;
            if ($host && $user && $name) {
                $mysqli = @new mysqli($host, $user, $pass, $name, $port);
                if ($mysqli && !$mysqli->connect_errno) { @$mysqli->set_charset('utf8mb4'); $this->db = $mysqli; return; }
                $this->log('mysqli connect failed (config): ' . ($mysqli->connect_error ?? 'unknown'));
            } else {
                $this->log('DB config incomplete in config/db.php');
            }
        }

        $this->log('No DB connection available for PermissionModel');
    }

    protected function log(string $msg): void
    {
        @file_put_contents($this->logPath, '['.date('c').'] PermissionModel: '.$msg.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    protected function prepare(string $sql, array $params = [], string $types = '')
    {
        if (!$this->db) return false;
        $stmt = $this->db->prepare($sql);
        if (!$stmt) { $this->log('prepare failed: '.$this->db->error.' | SQL: '.$sql); return false; }
        if (!empty($params)) {
            if ($types === '') {
                $t = '';
                foreach ($params as $p) $t .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
                $types = $t;
            }
            $bind = array_merge([$types], $params);
            $refs = [];
            foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        if (!$stmt->execute()) { $this->log('execute failed: '.$stmt->error.' | SQL: '.$sql); $stmt->close(); return false; }
        return $stmt;
    }

    public function all(): array
    {
        if (!$this->db) return [];
        $sql = "SELECT id, key_name, display_name, description, created_at FROM permissions ORDER BY id ASC";
        $stmt = $this->prepare($sql);
        if ($stmt === false) return [];
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->free();
        $stmt->close();
        return $rows;
    }

    public function allWithOptions(array $opts = []): array
    {
        if (!$this->db) return [];
        $q = $opts['q'] ?? null;
        $limit = isset($opts['limit']) ? (int)$opts['limit'] : null;
        $offset = isset($opts['offset']) ? (int)$opts['offset'] : null;

        $params = [];
        $where = '';
        if ($q !== null && $q !== '') {
            $where = " WHERE (key_name LIKE ? OR display_name LIKE ? OR description LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT id, key_name, display_name, description, created_at FROM permissions" . $where . " ORDER BY id ASC";
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . intval($limit);
            if ($offset !== null && $offset >= 0) $sql .= " OFFSET " . intval($offset);
        }

        $stmt = $this->prepare($sql, $params);
        if ($stmt === false) return [];
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->free();
        $stmt->close();
        return $rows;
    }

    public function find(int $id): ?array
    {
        if (!$this->db) return null;
        $sql = "SELECT id, key_name, display_name, description, created_at FROM permissions WHERE id = ? LIMIT 1";
        $stmt = $this->prepare($sql, [$id], 'i');
        if ($stmt === false) return null;
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        return $row ?: null;
    }

    public function findByKey(string $key): ?array
    {
        if (!$this->db) return null;
        $sql = "SELECT id, key_name, display_name, description, created_at FROM permissions WHERE key_name = ? LIMIT 1";
        $stmt = $this->prepare($sql, [$key], 's');
        if ($stmt === false) return null;
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        return $row ?: null;
    }

    public function create(array $data): ?int
    {
        if (!$this->db) return null;
        $key = trim((string)($data['key_name'] ?? ''));
        $display = trim((string)($data['display_name'] ?? ''));
        $desc = isset($data['description']) ? $data['description'] : null;

        $sql = "INSERT INTO permissions (key_name, display_name, description, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->prepare($sql, [$key, $display, $desc], 'sss');
        if ($stmt === false) return null;
        $id = (int)$this->db->insert_id;
        $stmt->close();
        return $id > 0 ? $id : null;
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->db) return false;
        $key = trim((string)($data['key_name'] ?? ''));
        $display = trim((string)($data['display_name'] ?? ''));
        $desc = isset($data['description']) ? $data['description'] : null;

        $sql = "UPDATE permissions SET key_name = ?, display_name = ?, description = ? WHERE id = ? LIMIT 1";
        $stmt = $this->prepare($sql, [$key, $display, $desc, $id], 'sssi');
        if ($stmt === false) return false;
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function save(array $data)
    {
        $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : 0;
        if ($id > 0) {
            $ok = $this->update($id, $data);
            return $ok ? $id : null;
        }
        return $this->create($data);
    }

    public function delete(int $id): bool
    {
        if (!$this->db) return false;
        $this->removeRoleAssociations($id);
        $sql = "DELETE FROM permissions WHERE id = ? LIMIT 1";
        $stmt = $this->prepare($sql, [$id], 'i');
        if ($stmt === false) return false;
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    protected function removeRoleAssociations(int $permissionId): void
    {
        if (!$this->db) return;
        $sql = "DELETE FROM role_permissions WHERE permission_id = ?";
        $stmt = $this->prepare($sql, [$permissionId], 'i');
        if ($stmt !== false) $stmt->close();
    }

    public function assignToRole(int $permissionId, int $roleId): bool
    {
        if (!$this->db) return false;
        $p = $this->find($permissionId);
        if (!$p) return false;
        $rStmt = $this->prepare("SELECT id FROM roles WHERE id = ? LIMIT 1", [$roleId], 'i');
        if ($rStmt === false) return false;
        $res = $rStmt->get_result();
        $roleRow = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $rStmt->close();
        if (!$roleRow) return false;
        $chk = $this->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1", [$roleId, $permissionId], 'ii');
        if ($chk === false) return false;
        $resChk = $chk->get_result();
        $exists = $resChk ? $resChk->fetch_assoc() : null;
        if ($resChk) $resChk->free();
        $chk->close();
        if ($exists) return true;
        $ins = $this->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())", [$roleId, $permissionId], 'ii');
        if ($ins === false) return false;
        $ins->close();
        return true;
    }

    public function removeFromRole(int $permissionId, int $roleId): bool
    {
        if (!$this->db) return false;
        $stmt = $this->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1", [$roleId, $permissionId], 'ii');
        if ($stmt === false) return false;
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

// Static compatibility
if (!class_exists('Permissions')) {
    class Permissions
    {
        protected static ?PermissionModel $m = null;
        protected static function model(): PermissionModel
        {
            if (self::$m === null) self::$m = new PermissionModel();
            return self::$m;
        }
        public static function all($opts = []) { $m = self::model(); if (!empty($opts)) return $m->allWithOptions($opts); return $m->all(); }
        public static function find($id) { return self::model()->find((int)$id); }
        public static function findByKey($k) { return self::model()->findByKey((string)$k); }
        public static function save($data) { return self::model()->save((array)$data); }
        public static function create($data) { return self::model()->create((array)$data); }
        public static function update($id, $data) { return self::model()->update((int)$id, (array)$data); }
        public static function delete($id) { return self::model()->delete((int)$id); }
        public static function assignToRole($permId, $roleId) { return self::model()->assignToRole((int)$permId, (int)$roleId); }
        public static function removeFromRole($permId, $roleId) { return self::model()->removeFromRole((int)$permId, (int)$roleId); }
    }
}
