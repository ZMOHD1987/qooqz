<?php
declare(strict_types=1);

/**
 * /api/routes/vendors.php
 * API endpoint for Vendors management - SIMPLIFIED AND FIXED
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
    error_log("[vendors.php] " . $msg);
}

log_msg("=== NEW REQUEST START ===");
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);
log_msg("Time: " . date('Y-m-d H:i:s'));

// Set headers
header('Content-Type: application/json; charset=utf-8');

// Simple CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load database
require_once __DIR__ . '/../config/db.php';
$conn = connectDB();
if (!$conn || $conn->connect_error) {
    log_msg("Database connection failed: " . ($conn->connect_error ?? 'Unknown'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

log_msg("Database connected successfully");

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    log_msg("Generated new CSRF token");
}

// Get current user
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;

log_msg("User ID: $user_id, Is Admin: " . ($is_admin ? 'Yes' : 'No'));

$method = $_SERVER['REQUEST_METHOD'];

// ==============================================
// === GET REQUESTS - ALWAYS RETURN DATA =======
// ==============================================
if ($method === 'GET') {
    log_msg("Processing GET request");
    
    // Get single vendor for editing
    if (isset($_GET['_fetch_row']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = (int)$_GET['id'];
        log_msg("Fetching single vendor ID: $id");
        
        $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor = $result->fetch_assoc();
            $stmt->close();
            
            if ($vendor) {
                // Get translations
                $tstmt = $conn->prepare("SELECT language_code, description, return_policy, shipping_policy, meta_title, meta_description FROM vendor_translations WHERE vendor_id = ?");
                $tstmt->bind_param('i', $id);
                $tstmt->execute();
                $tresult = $tstmt->get_result();
                $translations = [];
                while ($tr = $tresult->fetch_assoc()) {
                    $translations[$tr['language_code']] = $tr;
                }
                $tstmt->close();
                $vendor['translations'] = $translations;
                
                log_msg("Successfully fetched vendor: $id");
                echo json_encode(['success' => true, 'data' => $vendor]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Vendor not found']);
        exit;
    }
    
    // Get parent vendors for dropdown
    if (isset($_GET['parents']) && $_GET['parents'] == '1') {
        log_msg("Fetching parent vendors");
        $sql = "SELECT id, store_name, slug FROM vendors WHERE is_branch = 0 ORDER BY store_name";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        log_msg("Found " . count($rows) . " parent vendors");
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
    
    // ==============================================
    // === GET VENDORS LIST - MAIN FUNCTION =========
    // ==============================================
    log_msg("Fetching vendors list");
    
    // Build base query - SELECT ONLY EXISTING COLUMNS
    $sql = "SELECT 
        id, store_name, slug, email, phone, vendor_type, store_type, 
        status, is_verified, is_featured, country_id, city_id,
        created_at, updated_at
        FROM vendors 
        WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Apply user filter if not admin
    if (!$is_admin) {
        $sql .= " AND user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }
    
    // Apply search filter
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $sql .= " AND (store_name LIKE ? OR email LIKE ? OR slug LIKE ? OR phone LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= 'ssss';
    }
    
    // Apply filters
    $filters = [
        'status' => 'status',
        'country_id' => 'country_id',
        'city_id' => 'city_id',
        'is_verified' => 'is_verified'
    ];
    
    foreach ($filters as $get_key => $db_field) {
        if (!empty($_GET[$get_key])) {
            $sql .= " AND $db_field = ?";
            $params[] = $_GET[$get_key];
            $types .= is_numeric($_GET[$get_key]) ? 'i' : 's';
        }
    }
    
    // Email and phone filters
    if (!empty($_GET['email'])) {
        $sql .= " AND email LIKE ?";
        $params[] = '%' . $_GET['email'] . '%';
        $types .= 's';
    }
    
    if (!empty($_GET['phone'])) {
        $sql .= " AND phone LIKE ?";
        $params[] = '%' . $_GET['phone'] . '%';
        $types .= 's';
    }
    
    $sql .= " ORDER BY id DESC";  // ORDER BY id NOT other columns
    
    log_msg("SQL: $sql");
    log_msg("Params: " . json_encode($params));
    
    // Execute query
    $rows = [];
    if ($types) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Bind parameters properly
            if (!empty($params)) {
                $bind_params = [$types];
                foreach ($params as &$param) {
                    $bind_params[] = &$param;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }
    
    log_msg("Found " . count($rows) . " vendors");
    
    // Return data in consistent format
    echo json_encode([
        'success' => true,
        'data' => $rows,
        'total' => count($rows),
        'message' => 'Vendors loaded successfully'
    ]);
    exit;
}

// ==============================================
// === POST REQUESTS ============================
// ==============================================
if ($method === 'POST') {
    log_msg("Processing POST request");
    
    // Parse input
    $input = $_POST;
    log_msg("POST keys: " . implode(', ', array_keys($input)));
    
    // Simple CSRF check
    $csrf_token = $input['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        log_msg("CSRF validation failed");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $action = $input['action'] ?? 'save';
    log_msg("Action: $action");
    
    // Handle actions
    switch ($action) {
        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if ($id > 0) {
                // Check permission
                $check = $conn->query("SELECT user_id FROM vendors WHERE id = $id")->fetch_assoc();
                if ($check && ($is_admin || $check['user_id'] == $user_id)) {
                    $conn->query("DELETE FROM vendor_translations WHERE vendor_id = $id");
                    $conn->query("DELETE FROM vendors WHERE id = $id");
                    log_msg("Deleted vendor $id");
                    echo json_encode(['success' => true, 'message' => 'Vendor deleted']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                }
            }
            exit;
            
        case 'toggle_verify':
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            $id = (int)($input['id'] ?? 0);
            $value = (int)($input['value'] ?? 0);
            $conn->query("UPDATE vendors SET is_verified = $value WHERE id = $id");
            log_msg("Toggled verification for vendor $id to $value");
            echo json_encode(['success' => true, 'message' => 'Verification updated']);
            exit;
            
        case 'save':
            // ==============================================
            // === SAVE VENDOR ==============================
            // ==============================================
            $id = (int)($input['id'] ?? 0);
            $is_edit = $id > 0;
            
            log_msg("Saving vendor. ID: $id, Is Edit: " . ($is_edit ? 'Yes' : 'No'));
            
            // Check permissions
            if ($is_edit) {
                $check = $conn->query("SELECT user_id FROM vendors WHERE id = $id")->fetch_assoc();
                if (!$check || (!$is_admin && $check['user_id'] != $user_id)) {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                    exit;
                }
            } else {
                if ($user_id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                }
            }
            
            // Prepare data - only use fields that exist in table
            $data = [];
            $fields = [
                'store_name', 'slug', 'vendor_type', 'store_type', 'is_branch', 'parent_vendor_id',
                'branch_code', 'phone', 'mobile', 'email', 'website_url', 'registration_number',
                'tax_number', 'country_id', 'city_id', 'address', 'postal_code', 'latitude',
                'longitude', 'commission_rate', 'service_radius', 'average_response_time',
                'inherit_settings', 'inherit_products', 'inherit_commission',
                'accepts_online_booking'
            ];
            
            foreach ($fields as $field) {
                if (isset($input[$field])) {
                    $value = $input[$field];
                    if ($value === '' && !in_array($field, ['store_name', 'phone', 'email', 'country_id'])) {
                        $data[$field] = null;
                    } else {
                        $data[$field] = $conn->real_escape_string($value);
                    }
                }
            }
            
            // Handle checkboxes
            $checkbox_fields = ['is_branch', 'accepts_online_booking', 'inherit_settings', 'inherit_products', 'inherit_commission'];
            foreach ($checkbox_fields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = ($data[$field] === '1') ? 1 : 0;
                }
            }
            
            // Admin fields
            if ($is_admin) {
                if (isset($input['status'])) {
                    $data['status'] = in_array($input['status'], ['pending', 'approved', 'suspended', 'rejected']) 
                        ? $conn->real_escape_string($input['status']) 
                        : 'pending';
                }
                if (isset($input['is_verified'])) {
                    $data['is_verified'] = ($input['is_verified'] === '1') ? 1 : 0;
                }
                if (isset($input['is_featured'])) {
                    $data['is_featured'] = ($input['is_featured'] === '1') ? 1 : 0;
                }
            }
            
            // Handle file uploads
            $image_fields = ['logo_url', 'cover_image_url', 'banner_url'];
            $file_field_map = [
                'vendor_logo' => 'logo_url',
                'vendor_cover' => 'cover_image_url',
                'vendor_banner' => 'banner_url'
            ];
            
            foreach ($file_field_map as $file_input => $db_field) {
                if (!empty($_FILES[$file_input]['name'])) {
                    $file = $_FILES[$file_input];
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../../uploads/vendors/' . ($is_edit ? $id : 'temp');
                    
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
                        $data[$db_field] = '/uploads/vendors/' . ($is_edit ? $id : 'temp') . '/' . $filename;
                        log_msg("File uploaded: $db_field = " . $data[$db_field]);
                    }
                }
            }
            
            // Generate unique slug if empty
            if (empty($data['slug'])) {
                $slug = generateSlug($data['store_name'], $conn, $id);
                $data['slug'] = $slug;
                log_msg("Generated slug: $slug");
            }
            
            // Build and execute SQL
            if ($is_edit) {
                $sets = [];
                foreach ($data as $key => $value) {
                    if ($value === null) {
                        $sets[] = "$key = NULL";
                    } else {
                        $sets[] = "$key = '" . $value . "'";
                    }
                }
                $sql = "UPDATE vendors SET " . implode(', ', $sets) . " WHERE id = $id";
            } else {
                $data['user_id'] = $user_id;
                $cols = array_keys($data);
                $vals = array_values($data);
                $val_strings = array_map(function($v) {
                    return $v === null ? 'NULL' : "'$v'";
                }, $vals);
                
                $sql = "INSERT INTO vendors (" . implode(',', $cols) . ") VALUES (" . implode(',', $val_strings) . ")";
            }
            
            log_msg("Save SQL: $sql");
            
            if ($conn->query($sql)) {
                $vendor_id = $is_edit ? $id : $conn->insert_id;
                log_msg("Vendor saved successfully. ID: $vendor_id");
                
                // Handle translations
                if (!empty($input['translations'])) {
                    $translations = json_decode($input['translations'], true);
                    if (is_array($translations)) {
                        // Delete existing
                        $conn->query("DELETE FROM vendor_translations WHERE vendor_id = $vendor_id");
                        
                        // Insert new
                        $stmt = $conn->prepare("INSERT INTO vendor_translations (vendor_id, language_code, description, return_policy, shipping_policy, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        if ($stmt) {
                            foreach ($translations as $lang => $trans) {
                                $vendor_id_param = $vendor_id;
                                $lang_param = $lang;
                                $desc = $trans['description'] ?? null;
                                $return_policy = $trans['return_policy'] ?? null;
                                $shipping_policy = $trans['shipping_policy'] ?? null;
                                $meta_title = $trans['meta_title'] ?? null;
                                $meta_desc = $trans['meta_description'] ?? null;
                                
                                $stmt->bind_param('issssss', 
                                    $vendor_id_param,
                                    $lang_param,
                                    $desc,
                                    $return_policy,
                                    $shipping_policy,
                                    $meta_title,
                                    $meta_desc
                                );
                                $stmt->execute();
                            }
                            $stmt->close();
                        }
                    }
                }
                
                // ==============================================
                // === FIXED: RETURN UPDATED DATA ===============
                // ==============================================
                // After saving, return success message ONLY
                // JavaScript will reload the list separately
                log_msg("Save completed successfully");
                
                echo json_encode([
                    'success' => true,
                    'message' => $is_edit ? 'Vendor updated successfully' : 'Vendor created successfully',
                    'id' => $vendor_id,
                    'slug' => $data['slug'] ?? '',
                    'action' => 'saved'
                ]);
                
            } else {
                $error = $conn->error;
                log_msg("Save failed: $error");
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

// ==============================================
// === HELPER FUNCTIONS =========================
// ==============================================
function generateSlug($text, $conn, $exclude_id = 0) {
    // Simple slug generation
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (empty($slug)) {
        $slug = 'vendor-' . uniqid();
    }
    
    // Check for uniqueness
    $counter = 1;
    $original_slug = $slug;
    
    while (true) {
        $check_sql = "SELECT id FROM vendors WHERE slug = '$slug'";
        if ($exclude_id > 0) {
            $check_sql .= " AND id != $exclude_id";
        }
        
        $result = $conn->query($check_sql);
        if (!$result || $result->num_rows === 0) {
            break;
        }
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// If we reach here, it's an invalid method
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

log_msg("=== REQUEST COMPLETED ===");
