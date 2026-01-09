<?php
declare(strict_types=1);
/**
 * /api/routes/products.php
 * API endpoint for Products management - Created from scratch
 */

// Start session
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log directory
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
ini_set('error_log', $logDir . '/error_debug.log');

// Simple log function
function log_msg($msg) {
    error_log("[products.php] " . $msg);
}

log_msg("=== NEW REQUEST START ===");
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// Load database
require_once __DIR__ . '/../config/db.php';
$conn = connectDB();
if (!$conn || $conn->connect_error) {
    log_msg("Database connection failed");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

log_msg("Database connected");

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current user
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;

log_msg("User ID: $user_id, Is Admin: " . ($is_admin ? 'Yes' : 'No'));

$method = $_SERVER['REQUEST_METHOD'];

// ==================================================
// GET REQUESTS
// ==================================================
if ($method === 'GET') {
    log_msg("Processing GET request");
    
    // Fetch single product for editing
    if (isset($_GET['_fetch_row']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = (int)$_GET['id'];
        log_msg("Fetching single product: $id");
        
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        // Get translations
        $product['translations'] = [];
        $res = $conn->query("SELECT * FROM product_translations WHERE product_id = $id");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lang = $row['language_code'];
                $product['translations'][$lang] = $row;
            }
        }
        
        // Get pricing
        $product['pricing'] = [];
        $res = $conn->query("SELECT * FROM product_pricing WHERE product_id = $id");
        if ($res) {
            $product['pricing'] = $res->fetch_all(MYSQLI_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $product]);
        exit;
    }
    
    // Fetch all products with filters
    if (isset($_GET['_fetch_all'])) {
        log_msg("Fetching all products");
        
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        if (!empty($_GET['vendor_id'])) {
            $where[] = 'vendor_id = ?';
            $params[] = (int)$_GET['vendor_id'];
            $types .= 'i';
        }
        
        if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
            $where[] = 'is_active = ?';
            $params[] = (int)$_GET['is_active'];
            $types .= 'i';
        }
        
        if (!empty($_GET['search'])) {
            $where[] = '(sku LIKE ? OR slug LIKE ?)';
            $search = '%' . $_GET['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }
        
        $whereSql = implode(' AND ', $where);
        $sql = "SELECT * FROM products WHERE $whereSql ORDER BY id DESC LIMIT 100";
        
        if ($types) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $products = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            $products = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        // Enhance with translations
        foreach ($products as &$p) {
            $p['translations'] = [];
            $res = $conn->query("SELECT * FROM product_translations WHERE product_id = " . $p['id']);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $p['translations'][$row['language_code']] = $row;
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $products, 'total' => count($products)]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid GET request']);
    exit;
}

// ==================================================
// POST REQUESTS
// ==================================================
if ($method === 'POST') {
    log_msg("Processing POST request");
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // Delete product
    if (isset($_GET['_delete']) && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        log_msg("Deleting product: $id");
        
        // Delete related data
        $conn->query("DELETE FROM product_translations WHERE product_id = $id");
        $conn->query("DELETE FROM product_pricing WHERE product_id = $id");
        $conn->query("DELETE FROM product_media WHERE product_id = $id");
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Product deleted' : 'Delete failed']);
        exit;
    }
    
    // Update product
    if (isset($_GET['_update']) && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        log_msg("Updating product: $id");
        
        $sets = [];
        $params = [];
        $types = '';
        
        foreach (['sku', 'slug', 'barcode', 'product_type'] as $field) {
            if (isset($input[$field])) {
                $sets[] = "$field = ?";
                $params[] = $input[$field];
                $types .= 's';
            }
        }
        
        foreach (['is_active', 'stock_quantity', 'vendor_id'] as $field) {
            if (isset($input[$field])) {
                $sets[] = "$field = ?";
                $params[] = (int)$input[$field];
                $types .= 'i';
            }
        }
        
        if (!empty($sets)) {
            $sets[] = "updated_at = NOW()";
            $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $ok = $stmt->execute();
            $stmt->close();
            
            // Update translations
            if (isset($input['translations']) && is_array($input['translations'])) {
                foreach ($input['translations'] as $lang => $trans) {
                    $conn->query("DELETE FROM product_translations WHERE product_id = $id AND language_code = '" . $conn->real_escape_string($lang) . "'");
                    
                    $stmt = $conn->prepare("INSERT INTO product_translations (product_id, language_code, name, description) VALUES (?, ?, ?, ?)");
                    $name = $trans['name'] ?? '';
                    $desc = $trans['description'] ?? '';
                    $stmt->bind_param('isss', $id, $lang, $name, $desc);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Product updated' : 'Update failed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
        }
        exit;
    }
    
    // Create product
    if (isset($_GET['_create'])) {
        log_msg("Creating product");
        
        // Get vendor_id
        $vendorId = 0;
        if ($is_admin && isset($input['vendor_id'])) {
            $vendorId = (int)$input['vendor_id'];
        } else {
            // Find vendor for current user
            $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $vendor = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$vendor) {
                echo json_encode(['success' => false, 'message' => 'Vendor account not found']);
                exit;
            }
            $vendorId = $vendor['id'];
        }
        
        $sku = $input['sku'] ?? '';
        $slug = $input['slug'] ?? '';
        $barcode = $input['barcode'] ?? null;
        $productType = $input['product_type'] ?? 'simple';
        $isActive = $input['is_active'] ?? 1;
        $stockQty = $input['stock_quantity'] ?? 0;
        
        $sql = "INSERT INTO products (vendor_id, sku, slug, barcode, product_type, is_active, stock_quantity, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issssii', $vendorId, $sku, $slug, $barcode, $productType, $isActive, $stockQty);
        
        if ($stmt->execute()) {
            $productId = $stmt->insert_id;
            $stmt->close();
            
            // Insert translations
            if (isset($input['translations']) && is_array($input['translations'])) {
                foreach ($input['translations'] as $lang => $trans) {
                    $stmt = $conn->prepare("INSERT INTO product_translations (product_id, language_code, name, description) VALUES (?, ?, ?, ?)");
                    $name = $trans['name'] ?? '';
                    $desc = $trans['description'] ?? '';
                    $stmt->bind_param('isss', $productId, $lang, $name, $desc);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Product created', 'data' => ['id' => $productId]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Create failed: ' . $stmt->error]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid POST request']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
