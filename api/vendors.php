<?php
/**
 * api/vendors.php
 * Unified Vendors API — direct DB save/update including translations & file handling
 *
 * - Uses robust session resolution and permission checks (RBAC/session)
 * - CSRF validation (auth helper -> CSRF::validate -> hash_equals fallback)
 * - GET endpoints: current_user, list, _fetch_row, parents
 * - POST actions: save (insert/update), delete, toggle_verify, delete_image
 * - Save now performs direct DB INSERT/UPDATE for `vendors` and upserts `vendor_translations`
 * - Handles uploaded files (logo, cover, banner) into /uploads/vendors/{id}/ and stores URLs in DB
 *
 * التعديلات:
 * 1. إضافة دعم الفلاتر المتقدمة في قسم GET (الحالة، التحقق، البلد، المدينة، الهاتف، الإيميل)
 * 2. تحسين استعلام SQL ليشمل جميع الفلاتر
 *
 * NOTE:
 * - Adjust UPLOAD_BASE_DIR and UPLOAD_BASE_URL if your environment differs.
 * - This file logs detailed debug info to api/error_debug.log (remove or reduce after diagnosis).
 *
 * Requirements:
 * - config/db.php must provide connectDB() returning mysqli
 * - vendor_translations table exists (schema provided by user)
 */

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (!defined('LOG_ENABLED')) define('LOG_ENABLED', false);

// CORS policy (adjust in production)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Logging helper
define('VENDORS_DEBUG_LOG', __DIR__ . '/error_debug.log');
function vendors_log(string $msg, array $ctx = []): void {
    $line = '[' . date('c') . '] ' . $msg;
    if (!empty($ctx)) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $line .= PHP_EOL;
    @file_put_contents(VENDORS_DEBUG_LOG, $line, FILE_APPEND | LOCK_EX);
}
function vendors_log_request(): void {
    $u = $_SERVER['REQUEST_URI'] ?? '';
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $p = $_POST ? json_encode(array_map(function($v){ return is_string($v) && strlen($v) > 200 ? substr($v,0,200).'...' : $v; }, $_POST), JSON_UNESCAPED_UNICODE) : '{}';
    $fkeys = $_FILES ? array_keys($_FILES) : [];
    vendors_log("REQUEST $m $u?{$qs} from {$ip} UA={$ua}");
    vendors_log("POST: {$p}");
    vendors_log("FILES keys: " . json_encode($fkeys));
}

// Exception & error handling
set_exception_handler(function(Throwable $e){
    vendors_log("UNCAUGHT EXCEPTION: " . get_class($e) . ': ' . $e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine()]);
    vendors_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error (see debug log)']);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline){
    vendors_log("PHP ERROR: [$errno] $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Includes
require_once __DIR__ . '/config/db.php';
if (is_readable(__DIR__ . '/helpers/auth_helper.php')) require_once __DIR__ . '/helpers/auth_helper.php';
if (is_readable(__DIR__ . '/helpers/CSRF.php')) require_once __DIR__ . '/helpers/CSRF.php';
if (is_readable(__DIR__ . '/helpers/RBAC.php')) require_once __DIR__ . '/helpers/RBAC.php';

vendors_log("=== API /api/vendors.php loaded ===");
vendors_log_request();

// Upload settings — adjust if needed
define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/vendors'); // physical path
define('UPLOAD_BASE_URL', '/uploads/vendors'); // URL base used in DB fields

function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Start best session (probe cookie names to find the one with user)
function start_best_session(): void {
    $candidateNames = array_unique(array_merge(['admin_sid','PHPSESSID'], array_keys($_COOKIE), [session_name()]));
    foreach ($candidateNames as $name) {
        if (empty($name) || !isset($_COOKIE[$name])) continue;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_name($name);
        session_id($_COOKIE[$name]);
        session_start([
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
        $hasUser = (isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id'])) || !empty($_SESSION['user_id']) || !empty($_SESSION['username']);
        vendors_log('session probe', ['cookie_name'=>$name,'session_id'=>session_id(),'hasUser'=>$hasUser]);
        if ($hasUser) return;
        session_write_close();
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
        vendors_log('fallback session started', ['session_name'=>session_name(),'session_id'=>session_id()]);
    }
}
start_best_session();

// normalize session user
if ((empty($_SESSION['user']) || !is_array($_SESSION['user'])) && !empty($_SESSION['user_id'])) {
    $_SESSION['user'] = [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
    ];
}
$currentUser = $_SESSION['user'] ?? null;
$currentUserId = (int)($currentUser['id'] ?? 0);
$isAdmin = ($currentUser && isset($currentUser['role_id']) && (int)$currentUser['role_id'] === 1);

// RBAC helper
function has_manage_vendors($rbac, $currentUser, int $userId): bool {
    if (!empty($currentUser['permissions']) && is_array($currentUser['permissions']) && in_array('manage_vendors', $currentUser['permissions'], true)) return true;
    if ($rbac && method_exists($rbac, 'hasPermission')) {
        try {
            // try both possible signatures
            if ($rbac->hasPermission('manage_vendors', $userId)) return true;
        } catch (Throwable $e) {
            try { if ($rbac->hasPermission($userId, 'manage_vendors')) return true; } catch (Throwable $e2) {}
        }
    }
    return false;
}
$rbac = class_exists('RBAC') ? (function() {
    try {
        $ref = new ReflectionClass('RBAC');
        $ctor = $ref->getConstructor();
        $params = $ctor ? $ctor->getNumberOfParameters() : 0;
        if ($params === 0) return new RBAC();
        if ($params === 1) return new RBAC(connectDB());
        $args = []; while (count($args) < $params) $args[] = null; return $ref->newInstanceArgs($args);
    } catch (Throwable $e) { vendors_log('RBAC init failed: '.$e->getMessage()); return null; }
})() : null;

$hasManage = $isAdmin || has_manage_vendors($rbac, $currentUser, $currentUserId);
vendors_log('permissions', ['isAdmin'=>$isAdmin, 'hasManage'=>$hasManage, 'user_id'=>$currentUserId]);

// CSRF validation
function validate_csrf(): bool {
    if (function_exists('auth_validate_csrf')) {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        try { if (auth_validate_csrf($token)) return true; } catch (Throwable $e) { vendors_log('auth_validate_csrf err: '.$e->getMessage()); }
    }
    if (class_exists('CSRF') && method_exists('CSRF','validate')) {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        try { if (CSRF::validate($token)) return true; } catch (Throwable $e) { vendors_log('CSRF::validate err: '.$e->getMessage()); }
    }
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return ($sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token));
}

// connect DB
$conn = connectDB();
if (!($conn instanceof mysqli)) {
    vendors_log('DB connect failed');
    json_out(['success'=>false,'message'=>'DB connection failed'],500);
}

// Helper: sanitize and map incoming field values according to DESCRIBE
function coerce_int_nullable($v) { if ($v === '' || $v === null) return null; return (int)$v; }
function coerce_decimal_nullable($v) { if ($v === '' || $v === null) return null; return (string)$v; /* keep as string for binding */ }

// Helper: ensure upload path exists
function ensure_upload_dir($vendorId) {
    $dir = rtrim(UPLOAD_BASE_DIR, '/\\') . '/' . (int)$vendorId;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

// Helper: move uploaded file and return URL or null
function handle_uploaded_file($fieldName, $vendorId) {
    if (empty($_FILES[$fieldName]) || empty($_FILES[$fieldName]['tmp_name'])) return null;
    $f = $_FILES[$fieldName];
    if ($f['error'] !== UPLOAD_ERR_OK) { vendors_log("Upload error for $fieldName", ['error'=>$f['error']]); return null; }
    $dir = ensure_upload_dir($vendorId);
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $base = bin2hex(random_bytes(8));
    $targetName = $base . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
    $targetPath = $dir . '/' . $targetName;
    if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
        vendors_log("Failed to move uploaded file $fieldName", ['tmp'=>$f['tmp_name'],'target'=>$targetPath]);
        return null;
    }
    // compute URL
    $url = rtrim(UPLOAD_BASE_URL, '/') . '/' . (int)$vendorId . '/' . $targetName;
    return $url;
}

// Read method and route
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'current_user') {
        if (empty($_SESSION['csrf_token'])) {
            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
        }
        $csrf = (string)($_SESSION['csrf_token']);
        $permissions = $_SESSION['permissions'] ?? [];
        return json_out(['success'=>true,'user'=>$currentUser ?? null,'csrf_token'=>$csrf,'permissions'=>$permissions]);
    }

    if (!empty($_GET['_fetch_row']) && $_GET['_fetch_row'] == '1' && !empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $withStats = !empty($_GET['with_stats']);
        // fetch vendor row with translations
        $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $v = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$v) return json_out(['success'=>false,'message'=>'Not found'],404);
        if (!$hasManage && $v['user_id'] != $currentUserId) return json_out(['success'=>false,'message'=>'Forbidden'],403);
        // load translations
        $tstmt = $conn->prepare("SELECT language_code, description, return_policy, shipping_policy, meta_title, meta_description FROM vendor_translations WHERE vendor_id = ?");
        $tstmt->bind_param('i', $id);
        $tstmt->execute();
        $trs = $tstmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tstmt->close();
        $translations = [];
        foreach ($trs as $r) $translations[$r['language_code']] = [
            'description'=>$r['description'],
            'return_policy'=>$r['return_policy'],
            'shipping_policy'=>$r['shipping_policy'],
            'meta_title'=>$r['meta_title'],
            'meta_description'=>$r['meta_description'],
        ];
        $v['translations'] = $translations;
        return json_out(['success'=>true,'data'=>$v]);
    }

    if (isset($_GET['parents']) && $_GET['parents'] == '1') {
        if ($hasManage) {
            $stmt = $conn->prepare("SELECT id, store_name, slug FROM vendors WHERE is_branch = 0 ORDER BY store_name ASC");
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return json_out(['success'=>true,'data'=>$rows]);
        } else {
            $stmt = $conn->prepare("SELECT id, store_name, slug FROM vendors WHERE user_id = ? AND is_branch = 0 ORDER BY store_name ASC");
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return json_out(['success'=>true,'data'=>$rows]);
        }
    }

    // list with filters (محدثة لدعم الفلاتر المتقدمة)
    $filters = [];
    if (!$hasManage) $filters['user_id'] = $currentUserId;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(200, max(5, (int)($_GET['per_page'] ?? 20)));
    
    // الفلاتر المتقدمة
    $filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $filterVerified = isset($_GET['is_verified']) ? trim((string)$_GET['is_verified']) : '';
    $filterCountry = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
    $filterCity = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
    $filterPhone = isset($_GET['phone']) ? trim((string)$_GET['phone']) : '';
    $filterEmail = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
    
    // البحث العادي
    $search = trim((string)($_GET['search'] ?? ''));
    
    // بناء الاستعلام الديناميكي
    $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM vendors WHERE 1=1 ";
    $params = [];
    $types = '';
    
    // فلترة حسب الصلاحيات
    if (!$hasManage) { 
        $sql .= " AND user_id = ?"; 
        $params[] = $currentUserId; 
        $types .= 'i'; 
    }
    
    // فلترة البحث العادي
    if ($search !== '') { 
        $sql .= " AND (store_name LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR slug LIKE CONCAT('%',?,'%'))"; 
        $params[] = $search; 
        $params[] = $search; 
        $params[] = $search; 
        $types .= 'sss'; 
    }
    
    // الفلاتر المتقدمة
    if ($filterStatus !== '') { 
        $sql .= " AND status = ?"; 
        $params[] = $filterStatus; 
        $types .= 's'; 
    }
    
    if ($filterVerified !== '') { 
        $sql .= " AND is_verified = ?"; 
        $params[] = ($filterVerified === '1') ? 1 : 0; 
        $types .= 'i'; 
    }
    
    if ($filterCountry > 0) { 
        $sql .= " AND country_id = ?"; 
        $params[] = $filterCountry; 
        $types .= 'i'; 
    }
    
    if ($filterCity > 0) { 
        $sql .= " AND city_id = ?"; 
        $params[] = $filterCity; 
        $types .= 'i'; 
    }
    
    if ($filterPhone !== '') { 
        $sql .= " AND phone LIKE CONCAT('%',?,'%')"; 
        $params[] = $filterPhone; 
        $types .= 's'; 
    }
    
    if ($filterEmail !== '') { 
        $sql .= " AND email LIKE CONCAT('%',?,'%')"; 
        $params[] = $filterEmail; 
        $types .= 's'; 
    }
    
    $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
    $limit = $per; 
    $offset = ($page-1)*$per;
    $params[] = $limit; 
    $params[] = $offset; 
    $types .= 'ii';
    
    vendors_log('List query', [
        'sql' => $sql,
        'params' => $params,
        'types' => $types,
        'filters' => [
            'status' => $filterStatus,
            'verified' => $filterVerified,
            'country' => $filterCountry,
            'city' => $filterCity,
            'phone' => $filterPhone,
            'email' => $filterEmail,
            'search' => $search
        ]
    ]);
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        vendors_log('Prepare failed', ['sql'=>$sql,'err'=>$conn->error]);
        return json_out(['success'=>false,'message'=>'Database error'],500);
    }
    
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    
    $total = $conn->query("SELECT FOUND_ROWS() as total")->fetch_assoc()['total'] ?? count($rows);
    return json_out([
        'success'=>true,
        'data'=>$rows,
        'total'=> (int)$total,
        'page'=>$page,
        'per_page'=>$per,
        'filters_applied' => [
            'status' => $filterStatus,
            'is_verified' => $filterVerified,
            'country_id' => $filterCountry,
            'city_id' => $filterCity,
            'phone' => $filterPhone,
            'email' => $filterEmail,
            'search' => $search
        ]
    ]);
}

// POST handling
if ($method === 'POST') {
    vendors_log('POST received', ['action'=>$_POST['action'] ?? null]);

    if (!validate_csrf()) {
        vendors_log('CSRF failed', ['session'=>($_SESSION['csrf_token'] ?? null),'post'=>($_POST['csrf_token'] ?? null)]);
        return json_out(['success'=>false,'message'=>'Invalid CSRF token'],403);
    }

    $action = $_POST['action'] ?? 'save';

    if ($action === 'toggle_verify') {
        if (!$hasManage) return json_out(['success'=>false,'message'=>'Forbidden'],403);
        $vid = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['value'] ?? 0);
        if (!$vid) return json_out(['success'=>false,'message'=>'Invalid id'],400);
        $stmt = $conn->prepare("UPDATE vendors SET is_verified = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1");
        $stmt->bind_param('ii', $val, $vid);
        $ok = $stmt->execute();
        $stmt->close();
        return json_out(['success'=>$ok]);
    }

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if (!$delId) return json_out(['success'=>false,'message'=>'Invalid id'],400);
        $vendor = $conn->query("SELECT user_id FROM vendors WHERE id = " . (int)$delId)->fetch_assoc();
        if (!$vendor) return json_out(['success'=>false,'message'=>'Not found'],404);
        if (!$hasManage && (int)$vendor['user_id'] !== $currentUserId) return json_out(['success'=>false,'message'=>'Forbidden'],403);
        $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $delId);
        $ok = $stmt->execute();
        $stmt->close();
        return json_out(['success'=>$ok]);
    }

    if ($action === 'delete_image') {
        $img = $_POST['image_url'] ?? '';
        $vid = (int)($_POST['vendor_id'] ?? 0);
        if (!$img || !$vid) return json_out(['success'=>false,'message'=>'Missing params'],400);
        $vendor = $conn->query("SELECT user_id, logo_url, cover_image_url, banner_url FROM vendors WHERE id = " . (int)$vid)->fetch_assoc();
        if (!$vendor) return json_out(['success'=>false,'message'=>'Not found'],404);
        if (!$hasManage && (int)$vendor['user_id'] !== $currentUserId) return json_out(['success'=>false,'message'=>'Forbidden'],403);
        // determine which column
        $column = null;
        if ($vendor['logo_url'] === $img) $column = 'logo_url';
        elseif ($vendor['cover_image_url'] === $img) $column = 'cover_image_url';
        elseif ($vendor['banner_url'] === $img) $column = 'banner_url';
        if ($column === null) return json_out(['success'=>false,'message'=>'Image not found'],404);
        $stmt = $conn->prepare("UPDATE vendors SET $column = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $vid);
        $ok = $stmt->execute();
        $stmt->close();
        return json_out(['success'=>$ok]);
    }

    if ($action === 'save') {
        // accept JSON body if posted
        $raw = $_POST;
        $body = file_get_contents('php://input');
        $jsonB = @json_decode($body, true);
        if (is_array($jsonB) && empty($raw)) $raw = $jsonB;

        $id = isset($raw['id']) ? (int)$raw['id'] : 0;
        $isEdit = $id > 0;

        if ($isEdit) {
            $row = $conn->query("SELECT * FROM vendors WHERE id = " . (int)$id)->fetch_assoc();
            if (!$row) return json_out(['success'=>false,'message'=>'Not found'],404);
            if (!$hasManage && (int)$row['user_id'] !== $currentUserId) return json_out(['success'=>false,'message'=>'Forbidden'],403);
        } else {
            if (!$currentUserId) return json_out(['success'=>false,'message'=>'Unauthorized'],403);
            $raw['user_id'] = $currentUserId;
        }

        // allowed fields from DESCRIBE vendors
        $allowed = [
            'parent_vendor_id','is_branch','branch_code','inherit_settings','inherit_products','inherit_commission',
            'user_id','store_name','vendor_type','slug','store_type','registration_number','tax_number',
            'phone','mobile','email','website_url','country_id','city_id','address','postal_code',
            'latitude','longitude','commission_rate','service_radius','accepts_online_booking','average_response_time',
            'status','suspension_reason','is_verified','is_featured','approved_at'
        ];

        // Build data array mapping DB columns
        $data = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $raw)) {
                $val = $raw[$f];
                // cast/normalize types where appropriate
                if (in_array($f, ['parent_vendor_id','user_id','country_id','city_id','service_radius','average_response_time','is_branch','accepts_online_booking','is_verified','is_featured'], true)) {
                    $data[$f] = ($val === '' || $val === null) ? null : (int)$val;
                } elseif (in_array($f, ['commission_rate','latitude','longitude'], true)) {
                    $data[$f] = ($val === '' || $val === null) ? null : $val; // keep numeric/decimal as string
                } else {
                    $data[$f] = $val;
                }
            }
        }

        // server-side basic validation
        $errors = [];
        foreach (['store_name','phone','email','country_id'] as $req) {
            if (empty($data[$req]) && $data[$req] !== 0) $errors[$req][] = ucfirst(str_replace('_',' ',$req)) . ' is required';
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'][] = 'Invalid email';
        if (!empty($data['parent_vendor_id'])) {
            $p = $conn->query("SELECT id, is_branch FROM vendors WHERE id = " . (int)$data['parent_vendor_id'])->fetch_assoc();
            if (!$p) $errors['parent_vendor_id'][] = 'Parent vendor not found';
            elseif (!empty($p['is_branch'])) $errors['parent_vendor_id'][] = 'Parent vendor cannot be a branch';
        }
        if (!empty($errors)) return json_out(['success'=>false,'message'=>'Validation failed','errors'=>$errors],422);

        // If non-admin, remove admin-only fields
        if (!$hasManage) {
            foreach (['status','suspension_reason','is_verified','is_featured','approved_at'] as $af) if (isset($data[$af])) unset($data[$af]);
            $data['user_id'] = $currentUserId;
        }

        // Process files after insert/update (we may need vendor id)
        $files_to_handle = ['logo'=>'logo_url','cover'=>'cover_image_url','banner'=>'banner_url'];
        // Note: input file fields in UI are vendor_logo, vendor_cover, vendor_banner — check both
        $fileFieldMap = [
            'logo' => 'vendor_logo',
            'cover' => 'vendor_cover',
            'banner' => 'vendor_banner'
        ];

        // Insert or update vendors table
        if ($isEdit) {
            // build update SQL dynamically
            $sets = [];
            $params = [];
            $types = '';
            foreach ($data as $col => $val) {
                $sets[] = "`$col` = ?";
                if (is_int($val)) { $types .= 'i'; $params[] = $val; }
                else { $types .= 's'; $params[] = $val; }
            }
            $sets[] = "updated_at = CURRENT_TIMESTAMP";
            $sql = "UPDATE vendors SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
            $params[] = $id; $types .= 'i';
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                vendors_log('Prepare update failed', ['sql'=>$sql,'err'=>$conn->error]);
                return json_out(['success'=>false,'message'=>'Save failed','db_error'=>$conn->error ?? null],500);
            }
            $stmt->bind_param($types, ...$params);
            $ok = $stmt->execute();
            if (!$ok) {
                vendors_log('Update execute failed', ['err'=>$stmt->error]);
                return json_out(['success'=>false,'message'=>'Save failed','db_error'=>$stmt->error],500);
            }
            $stmt->close();
            $vendorId = $id;
        } else {
            // insert
            $cols = array_keys($data);
            $placeholders = array_fill(0, count($cols), '?');
            $types = '';
            $params = [];
            foreach ($cols as $c) {
                $v = $data[$c];
                if (is_int($v)) { $types .= 'i'; $params[] = $v; }
                else { $types .= 's'; $params[] = $v; }
            }
            // set created_at/updated_at via DB defaults
            $sql = "INSERT INTO vendors (" . implode(',', array_map(function($c){ return "`$c`"; }, $cols)) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                vendors_log('Prepare insert failed', ['sql'=>$sql,'err'=>$conn->error]);
                return json_out(['success'=>false,'message'=>'Save failed','db_error'=>$conn->error ?? null],500);
            }
            $stmt->bind_param($types, ...$params);
            $ok = $stmt->execute();
            if (!$ok) {
                vendors_log('Insert execute failed', ['err'=>$stmt->error]);
                return json_out(['success'=>false,'message'=>'Save failed','db_error'=>$stmt->error],500);
            }
            $vendorId = (int)$stmt->insert_id;
            $stmt->close();
        }

        // Handle uploaded files — move and update DB columns if files provided
        $updatedCols = [];
        foreach ($fileFieldMap as $key => $col) {
            $inputName = $fileFieldMap[$key]; // vendor_logo etc.
            if (!empty($_FILES[$inputName]) && !empty($_FILES[$inputName]['tmp_name'])) {
                $url = handle_uploaded_file($inputName, $vendorId);
                if ($url !== null) {
                    $uStmt = $conn->prepare("UPDATE vendors SET `$col` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1");
                    $uStmt->bind_param('si', $url, $vendorId);
                    $uStmt->execute();
                    $uStmt->close();
                    $updatedCols[$col] = $url;
                }
            }
        }

        // Translations: replace existing translations for this vendor (simple approach)
        if (!empty($raw['translations'])) {
            $tr = is_string($raw['translations']) ? @json_decode($raw['translations'], true) : $raw['translations'];
            if (is_array($tr)) {
                // remove existing translations for vendor
                $del = $conn->prepare("DELETE FROM vendor_translations WHERE vendor_id = ?");
                $del->bind_param('i', $vendorId); $del->execute(); $del->close();
                // insert provided translations
                $ins = $conn->prepare("INSERT INTO vendor_translations (vendor_id, language_code, description, return_policy, shipping_policy, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($tr as $lang => $tdata) {
                    $desc = $tdata['description'] ?? null;
                    $ret = $tdata['return_policy'] ?? null;
                    $ship = $tdata['shipping_policy'] ?? null;
                    $mt = $tdata['meta_title'] ?? null;
                    $md = $tdata['meta_description'] ?? null;
                    $ins->bind_param('issssss', $vendorId, $lang, $desc, $ret, $ship, $mt, $md);
                    $ins->execute();
                }
                $ins->close();
            }
        }

        // Success: return saved record
        $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $vendorId); $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // attach translations
        $tstmt = $conn->prepare("SELECT language_code, description, return_policy, shipping_policy, meta_title, meta_description FROM vendor_translations WHERE vendor_id = ?");
        $tstmt->bind_param('i', $vendorId); $tstmt->execute();
        $trs = $tstmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tstmt->close();
        $translations = [];
        foreach ($trs as $r) $translations[$r['language_code']] = [
            'description'=>$r['description'],
            'return_policy'=>$r['return_policy'],
            'shipping_policy'=>$r['shipping_policy'],
            'meta_title'=>$r['meta_title'],
            'meta_description'=>$r['meta_description'],
        ];
        $vendor['translations'] = $translations;

        vendors_log('Saved vendor success', ['vendor_id'=>$vendorId, 'updated_files'=>$updatedCols]);
        return json_out(['success'=>true,'data'=>$vendor,'message'=>'Saved']);
    }

    return json_out(['success'=>false,'message'=>'Invalid action'],400);
}

// fallback
json_out(['success'=>false,'message'=>'Method not allowed'],405);