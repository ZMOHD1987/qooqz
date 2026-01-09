<?php
/**
 * api/controllers/ProductController.php 
 * Following Vendor pattern - simple and direct
 */

declare(strict_types=1);

class ProductController
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
        require_once __DIR__ . '/../models/Product.php';
        $this->model = new ProductModel($this->conn);

        // Initialize session and user
        $this->initializeSession();
        $this->loadCurrentUser();
        $this->checkPermissions();
    }

    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
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
            if (in_array('manage_products', $permissions, true)) {
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

    /**
     * List products with pagination
     */
    public function index(): void
    {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
        
        $filters = [];
        if (isset($_GET['vendor_id'])) $filters['vendor_id'] = (int)$_GET['vendor_id'];
        if (isset($_GET['is_active'])) $filters['is_active'] = (bool)$_GET['is_active'];
        if (isset($_GET['search'])) $filters['search'] = trim($_GET['search']);

        $result = $this->model->list($filters, $page, $perPage);
        
        $this->json([
            'success' => true,
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages']
        ]);
    }

    /**
     * Get single product
     */
    public function show(int $id): void
    {
        $product = $this->model->findById($id);
        if (!$product) {
            $this->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $this->json(['success' => true, 'data' => $product]);
    }

    /**
     * Create product
     */
    public function create(): void
    {
        if (!$this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Get input data
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // Basic validation
        if (empty($input['sku'])) {
            $this->json(['success' => false, 'message' => 'SKU is required'], 400);
        }
        
        if (empty($input['slug'])) {
            $this->json(['success' => false, 'message' => 'Slug is required'], 400);
        }

        // Set vendor_id
        if (!$this->hasManagePermission) {
            // Get vendor for current user
            $stmt = $this->conn->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
            $stmt->bind_param('i', $this->currentUserId);
            $stmt->execute();
            $vendor = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$vendor) {
                $this->json(['success' => false, 'message' => 'Vendor account not found'], 403);
            }
            
            $input['vendor_id'] = $vendor['id'];
        }

        $product = $this->model->create($input);
        
        if ($product) {
            $this->json(['success' => true, 'message' => 'Product created', 'data' => $product], 201);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to create product'], 500);
        }
    }

    /**
     * Update product
     */
    public function update(int $id): void
    {
        if (!$this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            $this->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        // Check permission
        if (!$this->hasManagePermission) {
            // Check if user owns this product
            $stmt = $this->conn->prepare("SELECT id FROM vendors WHERE id = ? AND user_id = ? LIMIT 1");
            $vendorId = (int)$existing['vendor_id'];
            $stmt->bind_param('ii', $vendorId, $this->currentUserId);
            $stmt->execute();
            $vendor = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$vendor) {
                $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }

        // Get input data
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $ok = $this->model->update($id, $input);
        
        if ($ok) {
            $updated = $this->model->findById($id);
            $this->json(['success' => true, 'message' => 'Product updated', 'data' => $updated]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update product'], 500);
        }
    }

    /**
     * Delete product
     */
    public function delete(int $id): void
    {
        if (!$this->currentUserId) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            $this->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        // Check permission
        if (!$this->hasManagePermission) {
            $stmt = $this->conn->prepare("SELECT id FROM vendors WHERE id = ? AND user_id = ? LIMIT 1");
            $vendorId = (int)$existing['vendor_id'];
            $stmt->bind_param('ii', $vendorId, $this->currentUserId);
            $stmt->execute();
            $vendor = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$vendor) {
                $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }

        $ok = $this->model->delete($id);
        
        if ($ok) {
            $this->json(['success' => true, 'message' => 'Product deleted']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete product'], 500);
        }
    }
}
