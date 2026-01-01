<?php
// htdocs/api/models/Category.php
// Model للفئات (Categories Model)
// يدعم التسلسل الهرمي (parent-child), CRUD, ربط المنتجات، وترتيب الفئات

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';

class Category
{
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = connectDB();
    }

    /**
     * إنشاء فئة جديدة
     *
     * @param array $data (name, slug, parent_id, description, is_active, sort_order)
     * @return array|false الفئة المنشأة أو false
     */
    public function create($data)
    {
        $name = $data['name'];
        $slug = $data['slug'] ?? Utils::createSlug($name);
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
        $description = $data['description'] ?? null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

        $sql = "INSERT INTO categories (name, slug, parent_id, description, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Category create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        // parent_id may be null -> bind as i with null causes 0; handle via binding as string if null
        if ($parentId === null) {
            $stmt->bind_param('ssssis', $name, $slug, $parentId, $description, $isActive, $sortOrder);
            // Note: binding null to integer param will set 0; to store NULL we will use a query workaround
            // Simpler: use NULL placeholder by adjusting query:
            $stmt->close();
            $sql = "INSERT INTO categories (name, slug, parent_id, description, is_active, sort_order, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('sssiii', $name, $slug, $parentId, $description, $isActive, $sortOrder);
        } else {
            $stmt->bind_param('ssisis', $name, $slug, $parentId, $description, $isActive, $sortOrder);
        }

        // Execute
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            Utils::log("Category created: ID {$id}, Name: {$name}", 'INFO');
            return $this->findById($id);
        }

        $error = $stmt->error;
        $stmt->close();
        Utils::log("Category create failed: " . $error, 'ERROR');
        return false;
    }

    /**
     * إحضار فئة بالـ id
     *
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM categories WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            return null;
        }
        $cat = $res->fetch_assoc();
        $stmt->close();

        // جلب الأبناء المباشرين
        $cat['children'] = $this->getChildren($id);
        return $cat;
    }

    /**
     * إحضار فئة بالـ slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug)
    {
        $sql = "SELECT * FROM categories WHERE slug = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            return null;
        }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $this->findById($row['id']);
    }

    /**
     * الحصول على جميع الفئات (مسطحة أو شجرية)
     *
     * @param array $filters ['is_active' => 1]
     * @param bool $tree
     * @return array
     */
    public function getAll($filters = [], $tree = true)
    {
        $where = [];
        $params = [];
        $types = '';

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = ?';
            $params[] = (int)$filters['is_active'];
            $types .= 'i';
        }

        if (isset($filters['search'])) {
            $where[] = '(name LIKE ? OR slug LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $types .= 'ss';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT * FROM categories {$whereClause} ORDER BY sort_order ASC, name ASC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();

        if (!$tree) {
            return ['data' => $rows, 'total' => count($rows)];
        }

        // بناء الشجرة
        $tree = $this->buildTree($rows);
        return ['data' => $tree, 'total' => count($rows)];
    }

    /**
     * بناء شجرة من قائمة مسطحة
     *
     * @param array $items
     * @return array
     */
    private function buildTree($items)
    {
        $map = [];
        $tree = [];

        foreach ($items as $item) {
            $item['children'] = [];
            $map[$item['id']] = $item;
        }

        foreach ($map as $id => $node) {
            $parentId = $node['parent_id'];
            if ($parentId && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$map[$id];
            } else {
                $tree[] = &$map[$id];
            }
        }

        return $tree;
    }

    /**
     * الحصول على الأبناء المباشرين
     *
     * @param int $parentId
     * @return array
     */
    public function getChildren($parentId)
    {
        $sql = "SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order ASC, name ASC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $children = [];
        while ($r = $res->fetch_assoc()) $children[] = $r;
        $stmt->close();
        return $children;
    }

    /**
     * تحديث فئة
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        $allowed = [
            'name' => 's',
            'slug' => 's',
            'parent_id' => 'i',
            'description' => 's',
            'is_active' => 'i',
            'sort_order' => 'i'
        ];

        foreach ($allowed as $field => $type) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Category update prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) Utils::log("Category updated: ID {$id}", 'INFO');
        return $success;
    }

    /**
     * حذف فئة (soft delete or hard delete)
     *
     * @param int $id
     * @param bool $hard
     * @return bool
     */
    public function delete($id, $hard = false)
    {
        if ($hard) {
            // فصل المنتجات المرتبطة ثم حذف السجل
            $this->detachAllProducts($id);
            $sql = "DELETE FROM categories WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param('i', $id);
            $success = $stmt->execute();
            $stmt->close();
            if ($success) Utils::log("Category hard deleted: ID {$id}", 'WARNING');
            return $success;
        } else {
            // soft: وضع is_active = 0
            return $this->update($id, ['is_active' => 0]);
        }
    }

    /**
     * استعادة فئة (soft restore)
     *
     * @param int $id
     * @return bool
     */
    public function restore($id)
    {
        return $this->update($id, ['is_active' => 1]);
    }

    /**
     * إرفاق منتج إلى فئة
     *
     * @param int $categoryId
     * @param int $productId
     * @param bool $isPrimary
     * @return bool
     */
    public function attachProduct($categoryId, $productId, $isPrimary = false)
    {
        if ($isPrimary) {
            $this->mysqli->query("UPDATE product_categories SET is_primary = 0 WHERE product_id = " . (int)$productId);
        }

        $sql = "INSERT INTO product_categories (product_id, category_id, is_primary, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $isPrimaryInt = $isPrimary ? 1 : 0;
        $stmt->bind_param('iii', $productId, $categoryId, $isPrimaryInt);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) Utils::log("Product {$productId} attached to Category {$categoryId}", 'INFO');
        return $success;
    }

    /**
     * فصل منتج من فئة
     *
     * @param int $categoryId
     * @param int $productId
     * @return bool
     */
    public function detachProduct($categoryId, $productId)
    {
        $sql = "DELETE FROM product_categories WHERE product_id = ? AND category_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ii', $productId, $categoryId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * فصل جميع المنتجات عن فئة
     *
     * @param int $categoryId
     * @return bool
     */
    public function detachAllProducts($categoryId)
    {
        $sql = "DELETE FROM product_categories WHERE category_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $categoryId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * جلب منتجات فئة
     *
     * @param int $categoryId
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getProducts($categoryId, $limit = 20, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT p.* FROM products p
                INNER JOIN product_categories pc ON p.id = pc.product_id
                WHERE pc.category_id = ? AND p.is_active = 1
                ORDER BY pc.is_primary DESC, p.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];
        $stmt->bind_param('iii', $categoryId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $products = [];
        while ($r = $res->fetch_assoc()) $products[] = $r;
        $stmt->close();

        // total count
        $countSql = "SELECT COUNT(*) as total FROM product_categories WHERE category_id = ?";
        $stmt = $this->mysqli->prepare($countSql);
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $cr = $stmt->get_result();
        $total = $cr->fetch_assoc()['total'];
        $stmt->close();

        return ['data' => $products, 'total' => (int)$total, 'page' => $page, 'per_page' => $limit, 'total_pages' => ceil($total / $limit)];
    }

    /**
     * إعادة ترتيب الفئات (ترتيب متعدد)
     *
     * @param array $orderData مصفوفة [categoryId => sortOrder, ...]
     * @return bool
     */
    public function reorder($orderData)
    {
        $this->mysqli->begin_transaction();
        try {
            $sql = "UPDATE categories SET sort_order = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            foreach ($orderData as $id => $order) {
                $stmt->bind_param('ii', $order, $id);
                $stmt->execute();
            }
            $stmt->close();
            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            Utils::log("Category reorder failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * التحقق من تفرد السجل slug
     *
     * @param string $slug
     * @param int|null $exceptId
     * @return bool
     */
    public function isSlugUnique($slug, $exceptId = null)
    {
        $sql = "SELECT COUNT(*) as cnt FROM categories WHERE slug = ?";
        if ($exceptId) {
            $sql .= " AND id != ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('si', $slug, $exceptId);
        } else {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('s', $slug);
        }

        if (!$stmt) return false;
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
        return $count == 0;
    }

    /**
     * إحصائيات للفئات
     *
     * @return array
     */
    public function getStatistics()
    {
        $sql = "SELECT 
                    COUNT(*) as total_categories,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_categories,
                    (SELECT COUNT(*) FROM product_categories) as product_category_links
                FROM categories";
        $res = $this->mysqli->query($sql);
        return $res->fetch_assoc();
    }
}

// تم تحميل Category Model بنجاح
?>