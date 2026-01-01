<?php
// api/repositories/GenericRepository.php
// Generic repository that works with mysqli and a table + allowed fields
// Usage: new GenericRepository($db, 'color_settings', 'id');

class GenericRepository {
    private $db;
    private $table;
    private $pk;

    /**
     * @param mysqli $mysqli
     * @param string $table
     * @param string $primaryKey
     */
    public function __construct($mysqli, string $table, string $primaryKey = 'id') {
        if (!$mysqli) throw new RuntimeException('DB required');
        $this->db = $mysqli;
        $this->table = $table;
        $this->pk = $primaryKey;
    }

    public function listByTheme(int $themeId, int $limit = 1000): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE theme_id = ? ORDER BY sort_order ASC, id ASC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        $stmt->bind_param('ii', $themeId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE {$this->pk} = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $r;
    }

    public function create(array $data, array $allowed): array {
        $cols = [];
        $vals = [];
        $placeholders = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $cols[] = "`$k`";
                $vals[] = $data[$k];
                $placeholders[] = '?';
            }
        }
        if (empty($cols)) throw new InvalidArgumentException('No data to insert');
        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $cols) . ", created_at, updated_at) VALUES (" . implode(', ', $placeholders) . ", NOW(), NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        require_once __DIR__ . '/../helpers/db_utils.php';
        mysqli_bind_params($stmt, $vals);
        $stmt->execute();
        if ($stmt->errno) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Insert failed: ' . $err);
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $this->find($id);
    }

    public function update(int $id, array $data, array $allowed): ?array {
        $sets = [];
        $vals = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "`$k` = ?";
                $vals[] = $data[$k];
            }
        }
        if (empty($sets)) return $this->find($id);
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE {$this->pk} = ?";
        $vals[] = $id;
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        require_once __DIR__ . '/../helpers/db_utils.php';
        mysqli_bind_params($stmt, $vals);
        $stmt->execute();
        if ($stmt->errno) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Update failed: ' . $err);
        }
        $stmt->close();
        return $this->find($id);
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE {$this->pk} = ?");
        if (!$stmt) return;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Bulk upsert: expects items array of associative arrays. Each item may contain pk to update, otherwise inserted.
     * Allowed fields define which keys are accepted.
     * Runs in a transaction.
     * @param int $themeId
     * @param array $items
     * @param array $allowed
     * @return array
     * @throws Throwable
     */
    public function bulkUpsertByTheme(int $themeId, array $items, array $allowed): array {
        $this->db->begin_transaction();
        try {
            $ids = ['created'=>[], 'updated'=>[]];
            foreach ($items as $it) {
                $it['theme_id'] = $themeId;
                if (!empty($it[$this->pk])) {
                    $res = $this->update((int)$it[$this->pk], $it, $allowed);
                    if ($res) $ids['updated'][] = (int)$res[$this->pk];
                } else {
                    $res = $this->create($it, $allowed);
                    if ($res) $ids['created'][] = (int)$res[$this->pk];
                }
            }
            $this->db->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function exportByTheme(int $themeId): array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE theme_id = ? ORDER BY sort_order ASC, id ASC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $themeId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function importForTheme(int $themeId, array $items, array $allowed, bool $clearExisting = false): array {
        $this->db->begin_transaction();
        try {
            if ($clearExisting) {
                $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE theme_id = ?");
                $stmt->bind_param('i', $themeId);
                $stmt->execute();
                $stmt->close();
            }
            $created = [];
            foreach ($items as $it) {
                $it['theme_id'] = $themeId;
                $row = $this->create($it, $allowed);
                $created[] = $row;
            }
            $this->db->commit();
            return $created;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}