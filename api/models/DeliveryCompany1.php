<?php
// api/models/DeliveryCompany.php
// Robust DeliveryCompanyModel with defensive DB helpers and broader PHP compatibility.
// - Uses prepared statements safely
// - Binds params with call_user_func_array fallback for older PHP
// - Supports get_result or fallback to bind_result
// - Avoids PHP 7.4+ typed property promotion or other syntax that may break older PHP

declare(strict_types=1);

class DeliveryCompanyModel {
    /** @var mysqli */
    private $db;

    public function __construct($db) {
        if (!($db instanceof mysqli)) {
            throw new InvalidArgumentException('DeliveryCompanyModel requires mysqli instance');
        }
        $this->db = $db;
    }

    /* -------------------------
       Utility helpers
       ------------------------- */

    /**
     * Bind params to a prepared statement in a way that works on many PHP versions.
     * @param mysqli_stmt $stmt
     * @param string $types
     * @param array $params
     * @return void
     */
    private function bindParams($stmt, $types, array $params) {
        if (empty($types)) return;
        // call_user_func_array requires references
        $bindNames = [];
        $bindNames[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            // ensure each param is a variable (not expression)
            $bindNames[] = &$params[$i];
        }
        // php 5.6+ supports $stmt->bind_param(...$params) but we use call_user_func_array for compatibility
        call_user_func_array([$stmt, 'bind_param'], $bindNames);
    }

    /**
     * Fetch all rows from a statement (works with get_result if available, else fallback)
     * @param mysqli_stmt $stmt
     * @return array
     */
    private function fetchAllFromStmt($stmt) {
        // prefer get_result (requires mysqlnd)
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res === false) return [];
            return $res->fetch_all(MYSQLI_ASSOC);
        }
        // fallback: use metadata + bind_result
        $meta = $stmt->result_metadata();
        if (!$meta) return [];
        $fields = [];
        $row = [];
        $out = [];
        $params = [];
        while ($field = $meta->fetch_field()) {
            $params[] = &$row[$field->name];
        }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $params);
        while ($stmt->fetch()) {
            $assoc = [];
            foreach ($row as $k => $v) {
                // copy value (since $row references will change)
                $assoc[$k] = $v;
            }
            $out[] = $assoc;
        }
        return $out;
    }

    /* -------------------------
       Basic CRUD
       ------------------------- */

    /**
     * Find company by id
     * @param int $id
     * @return array|null
     */
    public function findById(int $id) {
        $sql = "SELECT * FROM delivery_companies WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        // fetch single row
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            return $row ?: null;
        }
        // fallback
        $meta = $stmt->result_metadata();
        if (!$meta) { $stmt->close(); return null; }
        $cols = [];
        $params = [];
        while ($f = $meta->fetch_field()) {
            $params[] = &$cols[$f->name];
        }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $params);
        $ret = null;
        if ($stmt->fetch()) {
            $row = [];
            foreach ($cols as $k => $v) $row[$k] = $v;
            $ret = $row;
        }
        $stmt->close();
        return $ret ?: null;
    }

    /**
     * Search companies with filters and pagination
     * @param array $filters
     * @param int $page
     * @param int $per
     * @return array ['data'=>[], 'total'=>int]
     */
    public function search(array $filters = [], int $page = 1, int $per = 20) {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR slug LIKE ?)";
            $types .= 'ssss';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = "is_active = ?";
            $types .= 'i';
            $params[] = (int)$filters['is_active'];
        }

        if (isset($filters['parent_id']) && $filters['parent_id'] !== '') {
            $where[] = "parent_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['parent_id'];
        }

        $offset = max(0, ($page - 1) * $per);

        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM delivery_companies WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT ? OFFSET ?";
        // append limit params
        $types .= 'ii';
        $params[] = $per;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            // prepare failed
            return ['data'=>[], 'total'=>0];
        }

        // bind if types non-empty
        $this->bindParams($stmt, $types, $params);

        $stmt->execute();
        $rows = $this->fetchAllFromStmt($stmt);
        $stmt->close();

        // get total
        $total = 0;
        $r = $this->db->query("SELECT FOUND_ROWS() as total");
        if ($r) {
            $assoc = $r->fetch_assoc();
            $total = isset($assoc['total']) ? (int)$assoc['total'] : count($rows);
        } else {
            $total = count($rows);
        }

        return ['data'=>$rows, 'total'=>$total];
    }

    /**
     * Create a delivery company. Returns inserted id or 0 on failure.
     * @param array $data
     * @return int
     */
    public function create(array $data) {
        $allowed = ['parent_id','user_id','name','slug','logo_url','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','rating_average','rating_count','sort_order'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) return 0;

        $cols = array_keys($filtered);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colsSql = implode(',', $cols);
        $sql = "INSERT INTO delivery_companies ($colsSql) VALUES ($placeholders)";

        $typesMap = [
            'parent_id'=>'i','user_id'=>'i','name'=>'s','slug'=>'s','logo_url'=>'s','phone'=>'s','email'=>'s',
            'website_url'=>'s','api_url'=>'s','api_key'=>'s','tracking_url'=>'s','city_id'=>'i','country_id'=>'i',
            'is_active'=>'i','rating_average'=>'d','rating_count'=>'i','sort_order'=>'i'
        ];

        $types = '';
        $params = [];
        foreach ($cols as $c) {
            $types .= $typesMap[$c] ?? 's';
            $params[] = $filtered[$c];
        }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        if (!$ok) {
            $stmt->close();
            return 0;
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Update company by id.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data) {
        $allowed = ['parent_id','user_id','name','slug','logo_url','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','rating_average','rating_count','sort_order'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) return false;

        $typesMap = [
            'parent_id'=>'i','user_id'=>'i','name'=>'s','slug'=>'s','logo_url'=>'s','phone'=>'s','email'=>'s',
            'website_url'=>'s','api_url'=>'s','api_key'=>'s','tracking_url'=>'s','city_id'=>'i','country_id'=>'i',
            'is_active'=>'i','rating_average'=>'d','rating_count'=>'i','sort_order'=>'i'
        ];

        $sets = [];
        $params = [];
        $types = '';
        foreach ($filtered as $k => $v) {
            $sets[] = "`$k` = ?";
            $types .= $typesMap[$k] ?? 's';
            $params[] = $v;
        }
        // add id param
        $types .= 'i';
        $params[] = $id;

        $sql = "UPDATE delivery_companies SET " . implode(',', $sets) . " WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Delete company by id.
     * @param int $id
     * @return bool
     */
    public function delete(int $id) {
        $sql = "DELETE FROM delivery_companies WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}