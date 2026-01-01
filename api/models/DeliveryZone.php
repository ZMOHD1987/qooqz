<?php
// api/models/DeliveryZone.php
// DeliveryZoneModel - CRUD operations for `delivery_zones` table
// Uses mysqli prepared statements and returns associative arrays.

declare(strict_types=1);

class DeliveryZoneModel
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
            if (!$res) return null;
            $row = $res->fetch_assoc();
            $res->free();
            return $row ?: null;
        }
        return null;
    }

    private function fetchAllFromStmt(mysqli_stmt $stmt): array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if (!$res) return [];
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
            return $rows;
        }
        return [];
    }

    // Find single zone by id
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `delivery_zones` WHERE `id` = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $this->fetchOneFromStmt($stmt);
        $stmt->close();
        return $row ?: null;
    }

    // Search with filters: q (name), status, user_id; returns paginated result & total
    public function search(array $filters = [], int $page = 1, int $per = 20): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "`zone_name` LIKE ?";
            $types .= 's';
            $params[] = '%' . $filters['q'] . '%';
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
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM `delivery_zones` WHERE " . implode(' AND ', $where) . " ORDER BY `id` DESC LIMIT ? OFFSET ?";
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

    // Create new zone. Returns inserted id or 0 on failure.
    public function create(array $data): int
    {
        $allowed = ['user_id','zone_name','zone_type','zone_value','shipping_rate','free_shipping_threshold','estimated_delivery_days','status'];
        $fields = array_values(array_intersect($allowed, array_keys($data)));
        if (empty($fields)) return 0;

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $cols = implode('`,`', $fields);
        $colsSql = "`{$cols}`";

        $typesMap = [
            'user_id'=>'i','zone_name'=>'s','zone_type'=>'s','zone_value'=>'s','shipping_rate'=>'d',
            'free_shipping_threshold'=>'d','estimated_delivery_days'=>'i','status'=>'s'
        ];

        $types = '';
        $params = [];
        foreach ($fields as $f) {
            $types .= $typesMap[$f] ?? 's';
            $params[] = $data[$f];
        }

        $sql = "INSERT INTO `delivery_zones` ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        if (!$ok) { $stmt->close(); return 0; }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Update existing zone. Returns true on success.
    public function update(int $id, array $data): bool
    {
        $allowed = ['user_id','zone_name','zone_type','zone_value','shipping_rate','free_shipping_threshold','estimated_delivery_days','status'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) return false;

        $typesMap = [
            'user_id'=>'i','zone_name'=>'s','zone_type'=>'s','zone_value'=>'s','shipping_rate'=>'d',
            'free_shipping_threshold'=>'d','estimated_delivery_days'=>'i','status'=>'s'
        ];

        $sets = [];
        $types = '';
        $params = [];
        foreach ($filtered as $k => $v) {
            $sets[] = "`$k` = ?";
            $types .= $typesMap[$k] ?? 's';
            $params[] = $v;
        }
        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE `delivery_zones` SET " . implode(',', $sets) . " WHERE `id` = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    // Delete zone
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `delivery_zones` WHERE `id` = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}