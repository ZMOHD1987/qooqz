<?php
/**
 * api/controllers/VendorControllerNew.php
 * Refactored Vendor Controller - migrated from api/vendors.php
 * 
 * Handles all vendor management actions with proper MVC structure:
 * - CRUD operations for vendors
 * - Translation support (vendor_translations)
 * - File upload handling (logo, cover, banner)
 * - RBAC permission checks
 * - Advanced filtering
 */

declare(strict_types=1);

class VendorControllerNew
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
        
        // Check if user is admin (role_id = 1)
        $roleId = $_SESSION['role_id'] ?? ($this->currentUser['role_id'] ?? 0);
        if ((int)$roleId === 1) {
            $this->hasManagePermission = true;
            return;
        }

        // Check specific permission
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

    /**
     * GET /api/vendors?action=current_user
     * Returns current user info + CSRF token
     */
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
     * GET /api/vendors?_fetch_row=1&id=123
     * Fetch single vendor with translations
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

        // Check permissions
        if (!$this->hasManagePermission && $vendor['user_id'] != $this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Load translations
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
            $translations[$row['language_code']] = [
                'description' => $row['description'],
                'return_policy' => $row['return_policy'],
                'shipping_policy' => $row['shipping_policy'],
                'meta_title' => $row['meta_title'],
                'meta_description' => $row['meta_description']
            ];
        }
        $stmt->close();

        $vendor['translations'] = $translations;
        $this->json(['success' => true, 'data' => $vendor]);
    }

    /**
     * GET /api/vendors?parents=1
     * List parent vendors (for branch selection)
     */
    public function listParents(): void
    {
        if ($this->hasManagePermission) {
            $stmt = $this->conn->prepare("
                SELECT id, store_name, slug 
                FROM vendors 
                WHERE is_branch = 0 
                ORDER BY store_name ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $this->conn->prepare("
                SELECT id, store_name, slug 
                FROM vendors 
                WHERE user_id = ? AND is_branch = 0 
                ORDER BY store_name ASC
            ");
            $stmt->bind_param('i', $this->currentUserId);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $this->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /api/vendors (list with filters)
     * Advanced filtering: status, verified, country, city, phone, email, search
     */
    public function listVendors(): void
    {
        $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $perPage = min(200, max(5, isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20));
        
        // Build filters
        $filters = [];
        $params = [];
        $types = '';
        
        // Permission-based filtering
        if (!$this->hasManagePermission) {
            $filters[] = "user_id = ?";
            $params[] = $this->currentUserId;
            $types .= 'i';
        }
        
        // Status filter
        if (!empty($_GET['status'])) {
            $filters[] = "status = ?";
            $params[] = trim($_GET['status']);
            $types .= 's';
        }
        
        // Verified filter
        if (isset($_GET['is_verified']) && $_GET['is_verified'] !== '') {
            $filters[] = "is_verified = ?";
            $params[] = (int)$_GET['is_verified'];
            $types .= 'i';
        }
        
        // Country filter
        if (!empty($_GET['country_id'])) {
            $filters[] = "country_id = ?";
            $params[] = (int)$_GET['country_id'];
            $types .= 'i';
        }
        
        // City filter
        if (!empty($_GET['city_id'])) {
            $filters[] = "city_id = ?";
            $params[] = (int)$_GET['city_id'];
            $types .= 'i';
        }
        
        // Phone filter
        if (!empty($_GET['phone'])) {
            $filters[] = "(phone LIKE ? OR mobile LIKE ?)";
            $phone = '%' . trim($_GET['phone']) . '%';
            $params[] = $phone;
            $params[] = $phone;
            $types .= 'ss';
        }
        
        // Email filter
        if (!empty($_GET['email'])) {
            $filters[] = "email LIKE ?";
            $params[] = '%' . trim($_GET['email']) . '%';
            $types .= 's';
        }
        
        // Search filter
        if (!empty($_GET['search'])) {
            $search = '%' . trim($_GET['search']) . '%';
            $filters[] = "(store_name LIKE ? OR email LIKE ? OR slug LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }
        
        // Build SQL
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM vendors WHERE 1=1";
        if (!empty($filters)) {
            $sql .= " AND " . implode(" AND ", $filters);
        }
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        
        // Execute query
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get total count
        $totalResult = $this->conn->query("SELECT FOUND_ROWS() as total");
        $total = $totalResult->fetch_assoc()['total'];
        
        $this->json([
            'success' => true,
            'data' => $data,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage
        ]);
    }

    /**
     * POST /api/vendors?action=toggle_verify
     * Admin only: Toggle vendor verification
     */
    public function toggleVerify(): void
    {
        if (!$this->hasManagePermission) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $value = isset($_POST['value']) ? (int)$_POST['value'] : 0;
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Invalid ID'], 400);
        }
        
        $stmt = $this->conn->prepare("
            UPDATE vendors 
            SET is_verified = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->bind_param('ii', $value, $id);
        $success = $stmt->execute();
        $stmt->close();
        
        $this->json(['success' => $success]);
    }

    /**
     * POST /api/vendors?action=delete
     * Delete vendor (with permission check)
     */
    public function deleteVendor(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Invalid ID'], 400);
        }
        
        // Check ownership
        $vendor = $this->model->findById($id, false);
        if (!$vendor) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
        }
        
        if (!$this->hasManagePermission && $vendor['user_id'] != $this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        $success = $this->model->delete($id);
        $this->json(['success' => $success]);
    }

    /**
     * POST /api/vendors?action=delete_image
     * Delete vendor image (logo/cover/banner)
     */
    public function deleteImage(): void
    {
        $imageUrl = $_POST['image_url'] ?? '';
        $vendorId = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
        
        if (!$imageUrl || !$vendorId) {
            $this->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }
        
        // Check ownership
        $vendor = $this->model->findById($vendorId, false);
        if (!$vendor) {
            $this->json(['success' => false, 'message' => 'Not found'], 404);
        }
        
        if (!$this->hasManagePermission && $vendor['user_id'] != $this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        // Determine which column to clear
        $column = null;
        if ($vendor['logo_url'] === $imageUrl) $column = 'logo_url';
        elseif ($vendor['cover_image_url'] === $imageUrl) $column = 'cover_image_url';
        elseif ($vendor['banner_url'] === $imageUrl) $column = 'banner_url';
        
        if (!$column) {
            $this->json(['success' => false, 'message' => 'Image not found'], 404);
        }
        
        $stmt = $this->conn->prepare("
            UPDATE vendors 
            SET $column = NULL, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->bind_param('i', $vendorId);
        $success = $stmt->execute();
        $stmt->close();
        
        $this->json(['success' => $success]);
    }

    /**
     * POST /api/vendors?action=save
     * Create or update vendor with translations and file uploads
     */
    public function save(): void
    {
        // Accept JSON or POST data
        $data = $_POST;
        $body = file_get_contents('php://input');
        $jsonData = @json_decode($body, true);
        if (is_array($jsonData) && empty($data)) {
            $data = $jsonData;
        }
        
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $isEdit = $id > 0;
        
        // Check permissions
        if ($isEdit) {
            $existing = $this->model->findById($id, false);
            if (!$existing) {
                $this->json(['success' => false, 'message' => 'Not found'], 404);
            }
            if (!$this->hasManagePermission && $existing['user_id'] != $this->currentUserId) {
                $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        } else {
            if (!$this->currentUserId) {
                $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            $data['user_id'] = $this->currentUserId;
        }
        
        // Extract translations if present
        $translations = [];
        if (isset($data['translations']) && is_array($data['translations'])) {
            $translations = $data['translations'];
            unset($data['translations']);
        }
        
        // Save vendor
        $savedId = $this->model->save($data, $translations, $_FILES ?? []);
        
        if ($savedId) {
            $vendor = $this->model->findById((int)$savedId, false);
            $this->json([
                'success' => true,
                'data' => $vendor,
                'message' => $isEdit ? 'Vendor updated successfully' : 'Vendor created successfully'
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Save failed'], 500);
        }
    }

    /**
     * Main dispatcher - routes to appropriate action
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'GET') {
            $action = $_GET['action'] ?? '';
            
            if ($action === 'current_user') {
                $this->getCurrentUser();
            } elseif (isset($_GET['_fetch_row']) && $_GET['_fetch_row'] == '1') {
                $this->fetchRow();
            } elseif (isset($_GET['parents']) && $_GET['parents'] == '1') {
                $this->listParents();
            } else {
                $this->listVendors();
            }
        } elseif ($method === 'POST') {
            if (!$this->validateCSRF()) {
                $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            }
            
            $action = $_POST['action'] ?? 'save';
            
            switch ($action) {
                case 'toggle_verify':
                    $this->toggleVerify();
                    break;
                case 'delete':
                    $this->deleteVendor();
                    break;
                case 'delete_image':
                    $this->deleteImage();
                    break;
                case 'save':
                default:
                    $this->save();
                    break;
            }
        } else {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
        }
    }
}
