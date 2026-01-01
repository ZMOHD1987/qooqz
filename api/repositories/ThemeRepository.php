<?php
// api/repositories/ThemeRepository.php
// Repository for themes + helper to duplicate theme settings across related tables.

require_once __DIR__ . '/GenericRepository.php';

class ThemeRepository {
    private $db;
    private $table = 'themes';

    public function __construct($mysqli) {
        if (!$mysqli) throw new RuntimeException('DB required');
        $this->db = $mysqli;
    }

    /**
     * Paginate themes
     */
    public function paginate(int $page = 1, int $per = 20, ?string $q = null): array {
        $offset = max(0, ($page - 1) * $per);
        $where = '';
        $params = [];
        if ($q) {
            $where = "WHERE name LIKE ? OR slug LIKE ?";
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }

        // total
        $totalSql = "SELECT COUNT(*) AS cnt FROM `{$this->table}` " . ($where ?: '');
        $stmt = $this->db->prepare($totalSql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total = (int)($res['cnt'] ?? 0);
        $stmt->close();

        // data
        $sql = "SELECT * FROM `{$this->table}` " . ($where ?: '') . " ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        if (!empty($params)) {
            $types = str_repeat('s', count($params)) . 'ii';
            $bindParams = array_merge($params, [$per, $offset]);
            $stmt->bind_param($types, ...$bindParams);
        } else {
            $stmt->bind_param('ii', $per, $offset);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $per,
                'total' => $total,
            ],
        ];
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $r;
    }

    public function create(array $data): array {
        $cols = ['name','slug','description','thumbnail_url','preview_url','version','author','is_active','is_default'];
        $insertCols = [];
        $vals = [];
        $placeholders = [];
        foreach ($cols as $c) {
            if (array_key_exists($c, $data)) {
                $insertCols[] = "`$c`";
                $vals[] = $data[$c];
                $placeholders[] = '?';
            }
        }
        if (empty($insertCols)) throw new InvalidArgumentException('No theme data to insert');
        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $insertCols) . ", created_at, updated_at) VALUES (" . implode(', ', $placeholders) . ", NOW(), NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        // build types
        $types = '';
        foreach ($vals as $v) {
            if (is_int($v)) $types .= 'i';
            elseif (is_float($v)) $types .= 'd';
            else $types .= 's';
        }
        $bind = [];
        $bind[] = $types;
        foreach ($vals as $k => $v) $bind[] = &$vals[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
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

    public function update(int $id, array $data): ?array {
        $allowed = ['name','slug','description','thumbnail_url','preview_url','version','author','is_active','is_default'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = ?";
                $vals[] = $data[$f];
            }
        }
        if (empty($sets)) return $this->find($id);
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        $vals[] = $id;
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        // types
        $types = '';
        foreach ($vals as $v) {
            if (is_int($v)) $types .= 'i';
            elseif (is_float($v)) $types .= 'd';
            else $types .= 's';
        }
        $bind = [];
        $bind[] = $types;
        foreach ($vals as $k => $v) $bind[] = &$vals[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
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
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Activate a theme (set is_active on this theme and optionally deactivate others)
     */
    public function activate(int $id): void {
        $this->db->begin_transaction();
        try {
            // set others inactive
            $this->db->query("UPDATE `{$this->table}` SET is_active = 0");
            $stmt = $this->db->prepare("UPDATE `{$this->table}` SET is_active = 1, updated_at = NOW() WHERE id = ?");
            if (!$stmt) throw new RuntimeException('Prepare failed: ' . $this->db->error);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Duplicate a theme and copy related settings tables.
     * Returns new theme row.
     */
    public function duplicate(int $id, ?string $newName = null): array {
        $this->db->begin_transaction();
        try {
            $orig = $this->find($id);
            if (!$orig) throw new RuntimeException('Original theme not found');

            $new = $orig;
            unset($new['id'], $new['created_at'], $new['updated_at']);
            $new['name'] = $newName ?? ($orig['name'] . ' (copy)');
            // make slug unique by appending timestamp
            $baseSlug = $orig['slug'] ?? 'theme';
            $new['slug'] = $baseSlug . '-copy-' . time();
            // ensure is_active false by default
            $new['is_active'] = 0;
            $new['is_default'] = 0;

            // insert new theme
            $created = $this->create($new);
            $newId = (int)$created['id'];

            // tables to copy (only copying settings tied to theme_id)
            $tablesToCopy = [
                'color_settings',
                'button_styles',
                'card_styles',
                'font_settings',
                'design_settings'
            ];

            foreach ($tablesToCopy as $tbl) {
                // get rows
                $res = $this->db->query("SELECT * FROM `{$tbl}` WHERE theme_id = " . (int)$id);
                if (!$res) continue;
                while ($row = $res->fetch_assoc()) {
                    // remove PK and timestamps, set theme_id to newId
                    unset($row['id']);
                    if (isset($row['created_at'])) unset($row['created_at']);
                    if (isset($row['updated_at'])) unset($row['updated_at']);
                    $row['theme_id'] = $newId;

                    // use GenericRepository to insert (build allowed from keys)
                    $allowed = array_keys($row);
                    $repo = new GenericRepository($this->db, $tbl);
                    try {
                        $repo->create($row, $allowed);
                    } catch (Throwable $e) {
                        // ignore per-row failures but log
                        @file_put_contents(__DIR__ . '/../error_log.txt', "[".date('c')."] Duplicate warn: {$tbl} insert failed: ".$e->getMessage().PHP_EOL, FILE_APPEND | LOCK_EX);
                    }
                }
            }

            $this->db->commit();
            return $this->find($newId);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}