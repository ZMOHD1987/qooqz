<?php
// api/models/IndependentDriver.php
// IndependentDriverModel - CRUD for independent_drivers table (maps full_name -> name)
declare(strict_types=1);

class IndependentDriverModel
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '') return;
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    private function fetchOneFromStmt(mysqli_stmt $stmt): ?array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($res) $res->free();
            return $row ?: null;
        }
        return null;
    }

    private function fetchAllFromStmt(mysqli_stmt $stmt): array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
            return $rows;
        }
        return [];
    }

    // Return a single driver (alias full_name AS name)
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id,
                   full_name AS name,
                   phone, email,
                   vehicle_type, vehicle_number, license_number,
                   license_photo_url, id_photo_url,
                   status, rating_average, rating_count,
                   created_at, updated_at
            FROM `independent_drivers` WHERE `id` = ? LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $this->fetchOneFromStmt($stmt);
        $stmt->close();
        return $row ?: null;
    }

    // Search/paginate (returns ['data'=>[], 'total'=>N])
    public function search(array $filters = [], int $page = 1, int $per = 20): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(`full_name` LIKE ? OR `phone` LIKE ? OR `vehicle_number` LIKE ? OR `email` LIKE ?)";
            $q = '%' . $filters['q'] . '%';
            $types .= 'ssss';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['status'])) {
            $where[] = "`status` = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = "`user_id` = ?";
            $types .= 'i';
            $params[] = (int)$filters['user_id'];
        }

        $offset = max(0, ($page - 1) * $per);
        $sql = "SELECT SQL_CALC_FOUND_ROWS id, user_id, full_name AS name, phone, email, vehicle_type, vehicle_number, license_number, license_photo_url, id_photo_url, status, rating_average, rating_count, created_at, updated_at
                FROM `independent_drivers` WHERE " . implode(' AND ', $where) . " ORDER BY `id` DESC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $per;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];
        $this->bindParams($stmt, $types, $params);
        $stmt->execute();
        $rows = $this->fetchAllFromStmt($stmt);
        $stmt->close();

        $total = 0;
        $res = $this->db->query("SELECT FOUND_ROWS() AS total");
        if ($res) {
            $assoc = $res->fetch_assoc();
            $total = (int)($assoc['total'] ?? count($rows));
            $res->free();
        }
        return ['data' => $rows, 'total' => $total];
    }

    // Create; expects full_name in $data (or map prior)
    public function create(array $data): int
    {
        $allowed = ['user_id','full_name','phone','email','vehicle_type','vehicle_number','license_number','license_photo_url','id_photo_url','status'];
        $fields = array_values(array_intersect($allowed, array_keys($data)));
        if (empty($fields)) return 0;

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $cols = implode('`,`', $fields);
        $colsSql = "`{$cols}`";

        $typesMap = [
            'user_id'=>'i','full_name'=>'s','phone'=>'s','email'=>'s','vehicle_type'=>'s','vehicle_number'=>'s',
            'license_number'=>'s','license_photo_url'=>'s','id_photo_url'=>'s','status'=>'s'
        ];
        $types = '';
        $params = [];
        foreach ($fields as $f) {
            $types .= $typesMap[$f] ?? 's';
            $params[] = $data[$f];
        }

        $sql = "INSERT INTO `independent_drivers` ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        if (!$ok) { $stmt->close(); return 0; }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Update by id (expects full_name key if name change)
    public function update(int $id, array $data): bool
    {
        $allowed = ['user_id','full_name','phone','email','vehicle_type','vehicle_number','license_number','license_photo_url','id_photo_url','status'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) return false;

        $typesMap = [
            'user_id'=>'i','full_name'=>'s','phone'=>'s','email'=>'s','vehicle_type'=>'s','vehicle_number'=>'s',
            'license_number'=>'s','license_photo_url'=>'s','id_photo_url'=>'s','status'=>'s'
        ];
        $sets = []; $types = ''; $params = [];
        foreach ($filtered as $k => $v) {
            $sets[] = "`$k` = ?";
            $types .= $typesMap[$k] ?? 's';
            $params[] = $v;
        }
        $params[] = $id; $types .= 'i';
        $sql = "UPDATE `independent_drivers` SET " . implode(',', $sets) . " WHERE `id` = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `independent_drivers` WHERE `id` = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}