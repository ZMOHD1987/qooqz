<?php
/**
 * api/controllers/VendorController.php 
 * المطور: QOOQZ Platform
 * كود كامل مكتمل يدعم العرض العام ولوحة التحكم
 */

declare(strict_types=1);

class VendorController
{
    private $model;
    private $conn;
    private $currentUser;
    private $currentUserId;
    private $hasManagePermission;

    public function __construct(mysqli $conn = null)
    {
        if ($conn instanceof mysqli) {
            $this->conn = $conn;
        } else {
            require_once __DIR__ . '/../config/db.php';
            $this->conn = connectDB();
        }

        // Load model
        require_once __DIR__ . '/../models/Vendor.php';
        $this->model = new VendorModel($this->conn);

        // Initialize session and user
        $this->initializeSession();
        $this->loadCurrentUser();
        $this->checkPermissions();
    }

    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $candidateNames = array_unique(array_merge(
                ['admin_sid', 'PHPSESSID'],
                array_keys($_COOKIE),
                [session_name()]
            ));
            
            $found = false;
            foreach ($candidateNames as $name) {
                if (empty($_COOKIE[$name])) continue;
                @session_name($name);
                if (@session_start()) {
                    if (!empty($_SESSION['user_id'])) {
                        $found = true;
                        break;
                    }
                    session_write_close();
                }
            }
            
            if (!$found) {
                @session_name('PHPSESSID');
                @session_start();
            }
        }
    }

    private function loadCurrentUser(): void
    {
        $this->currentUser = $_SESSION['user'] ?? null;
        $this->currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        
        if (!$this->currentUser && $this->currentUserId) {
            $this->currentUser = ['id' => $this->currentUserId];
        }
    }

    private function checkPermissions(): void
    {
        $this->hasManagePermission = false;
        
        $roleId = $_SESSION['role_id'] ?? ($this->currentUser['role_id'] ?? 0);
        if ((int)$roleId === 1) {
            $this->hasManagePermission = true;
            return;
        }

        if (!empty($_SESSION['permissions'])) {
            $permissions = is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
            if (in_array('manage_vendors', $permissions, true) || 
                in_array('vendors_manage', $permissions, true)) {
                $this->hasManagePermission = true;
            }
        }
    }

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function validateCSRF(): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postToken = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '';
        
        if (empty($sessionToken) || empty($postToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $postToken);
    }

    public function getCurrentUser(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        
        $this->json([
            'success' => true,
            'user' => $this->currentUser,
            'csrf_token' => $_SESSION['csrf_token'],
            'permissions' => $_SESSION['permissions'] ?? []
        ]);
    }

    /**
     * جلب بيانات متجر واحد
     * متاح للعامة إذا كان المتجر معتمد
     */
    public function fetchRow(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Invalid ID'], 400);
        }

        $vendor = $this->model->findById($id, false);
        if (!$vendor) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // حماية البيانات: إذا لم يكن المتجر معتمداً، لا يراه إلا صاحبه أو الأدمن
        if ($vendor['status'] !== 'approved' && !$this->hasManagePermission && $vendor['user_id'] != $this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // جلب الترجمات
        $translations = [];
        $stmt = $this->conn->prepare("
            SELECT language_code, description, return_policy, shipping_policy, meta_title, meta_description 
            FROM vendor_translations 
            WHERE vendor_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $translations[$row['language_code']] = $row;
        }
        $stmt->close();

        $vendor['translations'] = $translations;
        $this->json(['success' => true, 'data' => $vendor]);
    }

    public function listParents(): void
    {
        if ($this->hasManagePermission) {
            $stmt = $this->conn->prepare("SELECT id, store_name, slug FROM vendors WHERE is_branch = 0 ORDER BY store_name ASC");
        } else {
            $stmt = $this->conn->prepare("SELECT id, store_name, slug FROM vendors WHERE user_id = ? AND is_branch = 0 ORDER BY store_name ASC");
            $stmt->bind_param('i', $this->currentUserId);
        }
        $stmt->execute();
        $this->json(['success' => true, 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }

    /**
     * عرض المتاجر مع الفلترة
     * معدل: يسمح للزوار برؤية المتاجر المعتمدة
     */
    public function listVendors(): void
    {
        $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $perPage = min(200, max(5, isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20));
        
        $filters = [];
        $params = [];
        $types = '';
        
        // إذا لم يكن المدير، نظهر فقط المتاجر المعتمدة (للزوار) أو المتاجر الخاصة بالمستخدم
        if (!$this->hasManagePermission) {
            if ($this->currentUserId > 0 && !isset($_GET['public'])) {
                $filters[] = "user_id = ?";
                $params[] = $this->currentUserId;
                $types .= 'i';
            } else {
                $filters[] = "status = 'approved'";
            }
        } elseif (!empty($_GET['status'])) {
            $filters[] = "status = ?";
            $params[] = trim($_GET['status']);
            $types .= 's';
        }

        if (!empty($_GET['search'])) {
            $search = '%' . trim($_GET['search']) . '%';
            $filters[] = "(store_name LIKE ? OR slug LIKE ?)";
            $params[] = $search; $params[] = $search;
            $types .= 'ss';
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM vendors WHERE 1=1";
        if (!empty($filters)) $sql .= " AND " . implode(" AND ", $filters);
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage; $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $total = $this->conn->query("SELECT FOUND_ROWS() as t")->fetch_assoc()['t'];
        
        $this->json([
            'success' => true,
            'data' => $data,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    public function toggleVerify(): void
    {
        if (!$this->hasManagePermission) $this->json(['success' => false], 403);
        $id = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['value'] ?? 0);
        $stmt = $this->conn->prepare("UPDATE vendors SET is_verified = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ii', $val, $id);
        $this->json(['success' => $stmt->execute()]);
    }

    public function deleteVendor(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $vendor = $this->model->findById($id, false);
        if (!$vendor || (!$this->hasManagePermission && $vendor['user_id'] != $this->currentUserId)) {
            $this->json(['success' => false], 403);
        }
        $this->json(['success' => $this->model->delete($id)]);
    }

    public function save(): void
    {
        $data = $_POST;
        if (empty($data)) $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $existing = $this->model->findById($id, false);
            if (!$existing || (!$this->hasManagePermission && $existing['user_id'] != $this->currentUserId)) {
                $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        } else {
            $data['user_id'] = $this->currentUserId;
        }

        $translations = $data['translations'] ?? [];
        unset($data['translations']);
        
        $savedId = $this->model->save($data, $translations, $_FILES ?? []);
        $this->json(['success' => (bool)$savedId, 'id' => $savedId]);
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $action = $_GET['action'] ?? '';
            if ($action === 'current_user') $this->getCurrentUser();
            elseif (isset($_GET['id'])) $this->fetchRow();
            elseif (isset($_GET['parents'])) $this->listParents();
            else $this->listVendors();
        } elseif ($method === 'POST') {
            if (!$this->validateCSRF()) $this->json(['success' => false, 'message' => 'CSRF Fail'], 403);
            $action = $_POST['action'] ?? 'save';
            if ($action === 'toggle_verify') $this->toggleVerify();
            elseif ($action === 'delete') $this->deleteVendor();
            else $this->save();
        }
    }
}
