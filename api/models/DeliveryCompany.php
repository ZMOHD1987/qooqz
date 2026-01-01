<?php
// api/models/DeliveryCompany.php
// Robust DeliveryCompany with defensive DB helpers and broader PHP compatibility.
// - Uses prepared statements safely
// - Binds params with call_user_func_array fallback for older PHP
// - Supports get_result or fallback to bind_result
// - Provides search, findById, create, update, delete and helper list methods

declare(strict_types=1);

class DeliveryCompany {
    /** @var mysqli */
    private $db;

    public function __construct($db) {
        if (!($db instanceof mysqli)) {
            throw new InvalidArgumentException('DeliveryCompany requires mysqli instance');
        }
        $this->db = $db;
    }

    /* -------------------------
       Internal helpers
       ------------------------- */

    private function bindParams(mysqli_stmt $stmt, string $types, array $params) {
        if ($types === '') return;
        // call_user_func_array requires references
        $bind = [];
        $bind[] = $types;
        for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    private function fetchAllFromStmt(mysqli_stmt $stmt) : array {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res === false) return [];
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (method_exists($res, 'free')) $res->free();
            return $rows;
        }
        // fallback using metadata + bind_result
        $meta = $stmt->result_metadata();
        if (!$meta) return [];
        $row = [];
        $params = [];
        while ($f = $meta->fetch_field()) {
            $row[$f->name] = null;
            $params[] = &$row[$f->name];
        }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $params);
        $out = [];
        while ($stmt->fetch()) {
            $r = [];
            foreach ($row as $k => $v) $r[$k] = $v;
            $out[] = $r;
        }
        return $out;
    }

    private function fetchOneFromStmt(mysqli_stmt $stmt) : ?array {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res === false) return null;
            $row = $res->fetch_assoc();
            if (method_exists($res, 'free')) $res->free();
            return $row ?: null;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return null;
        $row = [];
        $params = [];
        while ($f = $meta->fetch_field()) {
            $row[$f->name] = null;
            $params[] = &$row[$f->name];
        }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $params);
        if ($stmt->fetch()) {
            $r = [];
            foreach ($row as $k => $v) $r[$k] = $v;
            return $r;
        }
        return null;
    }

    /* -------------------------
       Basic CRUD
       ------------------------- */

    public function findById(int $id) : ?array {
        $sql = "SELECT * FROM delivery_companies WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $this->fetchOneFromStmt($stmt);
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Search companies with filters and pagination
     * $filters keys: q, phone, email, is_active, country_id, city_id, parent_id
     * returns ['data'=>[], 'total'=>int]
     */
    public function search(array $filters = [], int $page = 1, int $per = 20, ?string $lang = null) : array {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = "(dc.name LIKE ? OR dc.email LIKE ? OR dc.phone LIKE ? OR dc.slug LIKE ?)";
            $types .= 'ssss';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (!empty($filters['phone'])) { $where[] = "dc.phone LIKE ?"; $types .= 's'; $params[] = '%' . $filters['phone'] . '%'; }
        if (!empty($filters['email'])) { $where[] = "dc.email LIKE ?"; $types .= 's'; $params[] = '%' . $filters['email'] . '%'; }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') { $where[] = "dc.is_active = ?"; $types .= 'i'; $params[] = (int)$filters['is_active']; }
        if (!empty($filters['country_id'])) { $where[] = "dc.country_id = ?"; $types .= 'i'; $params[] = (int)$filters['country_id']; }
        if (!empty($filters['city_id'])) { $where[] = "dc.city_id = ?"; $types .= 'i'; $params[] = (int)$filters['city_id']; }
        if (!empty($filters['parent_id'])) { $where[] = "dc.parent_id = ?"; $types .= 'i'; $params[] = (int)$filters['parent_id']; }

        $offset = max(0, ($page - 1) * $per);

        // Build select with optional translated country/city names handled by router/controller.
        $sql = "SELECT SQL_CALC_FOUND_ROWS dc.* FROM delivery_companies dc WHERE " . implode(' AND ', $where) . " ORDER BY dc.id DESC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $per;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return ['data'=>[], 'total'=>0];
        }

        if ($types !== '') $this->bindParams($stmt, $types, $params);
        $stmt->execute();
        $rows = $this->fetchAllFromStmt($stmt);
        $stmt->close();

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
     */
    public function create(array $data) : int {
        $allowed = ['parent_id','user_id','name','slug','logo_url','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','rating_average','rating_count','sort_order'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) return 0;

        $cols = array_keys($filtered);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colsSql = implode(',', $cols);

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

        $sql = "INSERT INTO delivery_companies ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        if ($types !== '') $this->bindParams($stmt, $types, $params);
        $ok = $stmt->execute();
        if (!$ok) { $stmt->close(); return 0; }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Update company by id.
     */
    public function update(int $id, array $data) : bool {
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
     */
    public function delete(int $id) : bool {
        $sql = "DELETE FROM delivery_companies WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /* -------------------------
       Simple helpers used by controllers
       ------------------------- */

    /**
     * Return parents list (id, name). Does not join translations.
     */
    public function parents(): array {
        $rows = [];
        $res = $this->db->query("SELECT id, name FROM delivery_companies ORDER BY name ASC");
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    /**
     * Return countries referenced by delivery_companies (id,name,iso2)
     */
    public function referencedCountries(): array {
        $rows = [];
        $res = $this->db->query("SELECT DISTINCT c.id, c.name, c.iso2 FROM delivery_companies dc JOIN countries c ON c.id = dc.country_id WHERE dc.country_id IS NOT NULL ORDER BY c.name ASC");
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    /**
     * Return cities referenced by delivery_companies (optionally filtered by country_id)
     */
    public function referencedCities(?int $country_id = null): array {
        $rows = [];
        if ($country_id) {
            $stmt = $this->db->prepare("SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL AND ci.country_id = ? ORDER BY ci.name ASC");
            if ($stmt) {
                $stmt->bind_param('i', $country_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
            }
        } else {
            $res = $this->db->query("SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL ORDER BY ci.name ASC");
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return $rows;
    }
}