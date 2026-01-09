<?php
/**
 * api/models/Product.php
 * Product model following Vendor pattern - simple and direct
 */

require_once __DIR__ . '/../config/db.php';
if (is_readable(__DIR__ . '/../helpers/utils.php')) require_once __DIR__ . '/../helpers/utils.php';

if (!function_exists('connectDB')) {
    throw new Exception('connectDB() not found in api/config/db.php');
}

class ProductModel
{
    public $conn;

    public function __construct(mysqli $conn = null)
    {
        if ($conn instanceof mysqli) $this->conn = $conn;
        else $this->conn = connectDB();

        if (!($this->conn instanceof mysqli)) {
            throw new Exception('Database connection failed in ProductModel');
        }
    }

    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    private function log($msg)
    {
        if (class_exists('Utils') && method_exists('Utils', 'log')) return Utils::log($msg);
        error_log('[ProductModel] ' . $msg);
    }

    private function tableExists(string $name): bool
    {
        $r = $this->conn->query("SHOW TABLES LIKE '" . $this->conn->real_escape_string($name) . "'");
        return ($r && $r->num_rows > 0);
    }

    /**
     * Find product by ID
     */
    public function findById(int $id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) return null;

        // Attach translations
        $p['translations'] = [];
        if ($this->tableExists('product_translations')) {
            $res = $this->conn->query("SELECT * FROM product_translations WHERE product_id = " . (int)$id);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $lang = $r['language_code'] ?? 'en';
                    $p['translations'][$lang] = $r;
                }
            }
        }

        // Attach pricing
        $p['pricing'] = [];
        if ($this->tableExists('product_pricing')) {
            $res = $this->conn->query("SELECT * FROM product_pricing WHERE product_id = " . (int)$id);
            if ($res) $p['pricing'] = $res->fetch_all(MYSQLI_ASSOC);
        }

        // Attach media
        $p['media'] = [];
        if ($this->tableExists('product_media')) {
            $res = $this->conn->query("SELECT * FROM product_media WHERE product_id = " . (int)$id . " ORDER BY is_primary DESC, sort_order ASC");
            if ($res) $p['media'] = $res->fetch_all(MYSQLI_ASSOC);
        }

        return $p;
    }

    /**
     * Find product by slug
     */
    public function findBySlug(string $slug)
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE slug = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($p) return $this->findById((int)$p['id']);
        return null;
    }

    /**
     * List products with pagination
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['vendor_id'])) {
            $where[] = 'vendor_id = ?';
            $params[] = (int)$filters['vendor_id'];
            $types .= 'i';
        }

        if (!empty($filters['is_active'])) {
            $where[] = 'is_active = 1';
        }

        if (!empty($filters['search'])) {
            $where[] = '(sku LIKE ? OR slug LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }

        $whereSql = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM products WHERE $whereSql";
        $stmt = $this->conn->prepare($countSql);
        if ($stmt && $types) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt->close();
        } else {
            $res = $this->conn->query($countSql);
            $total = $res ? $res->fetch_assoc()['total'] : 0;
        }

        // Get data
        $sql = "SELECT * FROM products WHERE $whereSql ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Create product
     */
    public function create(array $data): ?array
    {
        $sql = "INSERT INTO products (vendor_id, sku, slug, barcode, product_type, is_active, stock_quantity, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->log("Create prepare failed: " . $this->conn->error);
            return null;
        }

        $vendorId = $data['vendor_id'] ?? 0;
        $sku = $data['sku'] ?? '';
        $slug = $data['slug'] ?? '';
        $barcode = $data['barcode'] ?? null;
        $productType = $data['product_type'] ?? 'simple';
        $isActive = $data['is_active'] ?? 1;
        $stockQty = $data['stock_quantity'] ?? 0;

        $stmt->bind_param('issssii', $vendorId, $sku, $slug, $barcode, $productType, $isActive, $stockQty);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            $this->log("Product created: ID $id");
            return $this->findById($id);
        }

        $this->log("Create failed: " . $stmt->error);
        $stmt->close();
        return null;
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        $types = '';

        foreach (['sku', 'slug', 'barcode', 'product_type'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }

        foreach (['is_active', 'stock_quantity', 'vendor_id'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = (int)$data[$field];
                $types .= 'i';
            }
        }

        if (empty($sets)) return false;

        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) $this->log("Product updated: ID $id");
        return $ok;
    }

    /**
     * Delete product
     */
    public function delete(int $id): bool
    {
        // Delete related data
        if ($this->tableExists('product_translations')) {
            $this->conn->query("DELETE FROM product_translations WHERE product_id = $id");
        }
        if ($this->tableExists('product_pricing')) {
            $this->conn->query("DELETE FROM product_pricing WHERE product_id = $id");
        }
        if ($this->tableExists('product_media')) {
            $this->conn->query("DELETE FROM product_media WHERE product_id = $id");
        }

        $stmt = $this->conn->prepare("DELETE FROM products WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) $this->log("Product deleted: ID $id");
        return $ok;
    }
}

// Alias for compatibility
class_alias('ProductModel', 'Product');
