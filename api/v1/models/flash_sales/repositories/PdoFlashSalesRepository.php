<?php
declare(strict_types=1);

class PdoFlashSalesRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /* ── Flash Sales CRUD ── */

    public function list(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['is_active'])) {
            $where[] = 'fs.is_active = :is_active';
            $params[':is_active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['status'])) {
            $now = date('Y-m-d H:i:s');
            switch ($filters['status']) {
                case 'upcoming': $where[] = 'fs.start_date > :now'; $params[':now'] = $now; break;
                case 'active':   $where[] = 'fs.start_date <= :now1 AND fs.end_date >= :now2'; $params[':now1'] = $now; $params[':now2'] = $now; break;
                case 'ended':    $where[] = 'fs.end_date < :now'; $params[':now'] = $now; break;
            }
        }
        if (!empty($filters['search'])) {
            $where[] = '(fs.sale_name LIKE :search OR fs.description LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit  = max(1, min(100, (int)($filters['limit'] ?? 25)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $countSQL = "SELECT COUNT(*) FROM flash_sales fs $whereSQL";
        $stmt = $this->pdo->prepare($countSQL);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT fs.* FROM flash_sales fs $whereSQL ORDER BY fs.created_at DESC LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
        ];
    }

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM flash_sales WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int {
        $cols = ['sale_name','description','start_date','end_date','discount_type','discount_value',
                 'max_discount_amount','is_active','banner_image'];
        $filtered = array_intersect_key($data, array_flip($cols));
        $keys = array_keys($filtered);
        $placeholders = array_map(fn($k) => ':' . $k, $keys);

        $sql = "INSERT INTO flash_sales (" . implode(',', $keys) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $cols = ['sale_name','description','start_date','end_date','discount_type','discount_value',
                 'max_discount_amount','is_active','banner_image'];
        $filtered = array_intersect_key($data, array_flip($cols));
        if (empty($filtered)) return false;

        $sets = array_map(fn($k) => "$k = :$k", array_keys($filtered));
        $sql = "UPDATE flash_sales SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sales WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ── Translations ── */

    public function getTranslations(int $flashSaleId, ?string $lang = null): array {
        $sql = "SELECT * FROM flash_sales_translations WHERE flash_sale_id = :fid";
        $params = [':fid' => $flashSaleId];
        if ($lang) {
            $sql .= " AND language_code = :lang";
            $params[':lang'] = $lang;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTranslation(array $data): bool {
        $sql = "INSERT INTO flash_sales_translations (flash_sale_id, language_code, field_name, value)
                VALUES (:fid, :lang, :field, :val)
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':fid'   => $data['flash_sale_id'],
            ':lang'  => $data['language_code'],
            ':field' => $data['field_name'],
            ':val'   => $data['value'],
        ]);
    }

    public function deleteTranslation(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sales_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function deleteTranslationsByLang(int $flashSaleId, string $lang): bool {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sales_translations WHERE flash_sale_id = :fid AND language_code = :lang");
        return $stmt->execute([':fid' => $flashSaleId, ':lang' => $lang]);
    }

    /* ── Flash Sale Products ── */

    public function getProducts(int $flashSaleId): array {
        $lang = $_GET['lang'] ?? $_SESSION['user']['preferred_language'] ?? 'ar';
        if (!preg_match('/^[a-z]{2,8}$/i', $lang)) { $lang = 'ar'; }
        $sql = "SELECT fsp.*, p.sku AS product_sku, p.slug AS product_slug,
                       COALESCE(pt.name, p.slug) AS product_name
                FROM flash_sale_products fsp
                LEFT JOIN products p ON p.id = fsp.product_id
                LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
                WHERE fsp.flash_sale_id = :fid
                ORDER BY fsp.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fid' => $flashSaleId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addProduct(array $data): int {
        $cols = ['flash_sale_id','product_id','entity_id','original_price','sale_price',
                 'discount_percentage','stock_quantity','max_quantity_per_user','is_active'];
        $filtered = array_intersect_key($data, array_flip($cols));
        $keys = array_keys($filtered);
        $placeholders = array_map(fn($k) => ':' . $k, $keys);

        $sql = "INSERT INTO flash_sale_products (" . implode(',', $keys) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function updateProduct(int $id, array $data): bool {
        $cols = ['original_price','sale_price','discount_percentage','stock_quantity',
                 'max_quantity_per_user','is_active','sold_quantity'];
        $filtered = array_intersect_key($data, array_flip($cols));
        if (empty($filtered)) return false;

        $sets = array_map(fn($k) => "$k = :$k", array_keys($filtered));
        $sql = "UPDATE flash_sale_products SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteProduct(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM flash_sale_products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ── Stats ── */

    public function stats(): array {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 AND start_date <= :n1 AND end_date >= :n2 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN start_date > :n3 THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN end_date < :n4 THEN 1 ELSE 0 END) as ended,
            COALESCE(SUM(total_sales), 0) as total_revenue
            FROM flash_sales";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':n1' => $now, ':n2' => $now, ':n3' => $now, ':n4' => $now]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
