<?php
// api/products.php
// Full product API with debug and local log file
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

$LOG_FILE = __DIR__ . '/products_api.log';
function log_msg_local($msg) {
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
}

$rawInput = file_get_contents('php://input');
$bodyJson = @json_decode($rawInput, true);
$debugParam = $_GET['debug'] ?? $_POST['debug'] ?? ($bodyJson['debug'] ?? null);
$DEBUG = ($debugParam == '1' || $debugParam === 1);

if ($DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

set_error_handler(function($severity, $message, $file, $line) {
    if (0 === error_reporting()) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function success($data = null, $message = 'OK') { respond(['success' => true, 'message' => $message, 'data' => $data], 200); }
function fail($message = 'Error', $errors = null, $code = 400) { $out = ['success' => false, 'message' => $message]; if ($errors !== null) $out['errors'] = $errors; respond($out, $code); }

$dbFile = __DIR__ . '/config/db.php';
if (!is_readable($dbFile)) {
    log_msg_local("DB config missing at {$dbFile}");
    fail('Server configuration error', null, 500);
}
require_once $dbFile;

if (!function_exists('connectDB')) {
    log_msg_local('connectDB() not found in db.php');
    fail('Server configuration error', null, 500);
}

$conn = connectDB();
if (!($conn instanceof mysqli)) {
    log_msg_local('connectDB() did not return mysqli');
    fail('Database connection failed', null, 500);
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$t}'");
    return ($r && $r->num_rows > 0);
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return ($r && $r->num_rows > 0);
}

function parse_json_field($v) {
    if (is_array($v)) return $v;
    if ($v === null || $v === '') return [];
    $j = @json_decode($v, true);
    return is_array($j) ? $j : [];
}

function current_user(mysqli $conn) {
    if (!empty($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        $id = (int)$_SESSION['user_id'];
        $s = $conn->prepare("SELECT id, username, email, preferred_language, role_id FROM users WHERE id = ? LIMIT 1");
        if ($s) { 
            $s->bind_param('i', $id); 
            $s->execute(); 
            $u = $s->get_result()->fetch_assoc(); 
            $s->close(); 
            if ($u) { 
                $_SESSION['user'] = $u; 
                return $u; 
            } 
        }
    }
    return null;
}

function get_user_vendor_id(mysqli $conn, $user_id) {
    if (!table_exists($conn, 'vendors')) return null;
    $s = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
    if (!$s) return null;
    $s->bind_param('i', $user_id); 
    $s->execute(); 
    $r = $s->get_result()->fetch_assoc(); 
    $s->close();
    return $r ? (int)$r['id'] : null;
}

$raw = $rawInput;
$jsonBody = is_array($bodyJson) ? $bodyJson : [];
$request = $_REQUEST;
foreach ($_POST as $k => $v) $request[$k] = $v;
if (is_array($jsonBody)) foreach ($jsonBody as $k => $v) $request[$k] = $v;
$method = $_SERVER['REQUEST_METHOD'];

try {
    $user = current_user($conn);
    if (!$user) fail('Unauthorized', null, 403);
    
    $is_admin = (int)$user['role_id'] === 1;
    $vendor_id = get_user_vendor_id($conn, (int)$user['id']);

    if ($method === 'GET') {
        if (!empty($_GET['_fetch_row']) && $_GET['_fetch_row'] == '1' && !empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$product) fail('Not found', null, 404);
            
            $product['translations'] = [];
            if (table_exists($conn, 'product_translations')) {
                $res = $conn->query("SELECT * FROM product_translations WHERE product_id = " . (int)$id);
                while ($r = $res->fetch_assoc()) {
                    $lang = $r['language_code'] ?? 'en';
                    unset($r['product_id'], $r['id']);
                    $product['translations'][$lang] = $r;
                }
            }
            
            if (table_exists($conn, 'product_pricing')) {
                $p = $conn->query("SELECT price, compare_at_price, cost_price, currency_code FROM product_pricing WHERE product_id = " . (int)$id . " AND variant_id IS NULL LIMIT 1")->fetch_assoc();
                if ($p) $product['pricing'] = $p;
            }
            
            $product['categories'] = [];
            if (table_exists($conn, 'product_categories')) {
                $res = $conn->query("SELECT category_id, is_primary, sort_order FROM product_categories WHERE product_id = " . (int)$id . " ORDER BY is_primary DESC, sort_order ASC");
                while ($r = $res->fetch_assoc()) $product['categories'][] = $r;
            }
            
            $product['attributes'] = [];
            if (table_exists($conn, 'product_attribute_assignments')) {
                $res = $conn->query("SELECT attribute_id, attribute_value_id, custom_value FROM product_attribute_assignments WHERE product_id = " . (int)$id);
                while ($r = $res->fetch_assoc()) $product['attributes'][] = $r;
            }
            
            $product['variants'] = [];
            if (table_exists($conn, 'product_variants')) {
                $res = $conn->query("SELECT * FROM product_variants WHERE product_id = " . (int)$id);
                while ($r = $res->fetch_assoc()) $product['variants'][] = $r;
            }
            
            $product['media'] = [];
            if (table_exists($conn, 'product_media')) {
                $res = $conn->query("SELECT * FROM product_media WHERE product_id = " . (int)$id . " ORDER BY is_primary DESC, sort_order ASC");
                while ($r = $res->fetch_assoc()) $product['media'][] = $r;
            } elseif (table_exists($conn, 'product_images')) {
                $res = $conn->query("SELECT * FROM product_images WHERE product_id = " . (int)$id . " ORDER BY is_primary DESC, sort_order ASC");
                while ($r = $res->fetch_assoc()) $product['media'][] = $r;
            }
            
            success($product);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = min(200, max(10, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        $q = trim($_GET['q'] ?? '');
        $lang = $_GET['lang'] ?? 'en';
        
        $where = '';
        if (!$is_admin && $vendor_id) $where .= " WHERE p.vendor_id = " . (int)$vendor_id;
        if ($q !== '') {
            $esc = $conn->real_escape_string($q);
            $where .= ($where ? ' AND' : ' WHERE') . " (p.sku LIKE '%{$esc}%' OR pt.name LIKE '%{$esc}%')";
        }
        
        $sql = "SELECT p.id, p.sku, p.slug, p.product_type, p.is_active, p.stock_quantity, 
                COALESCE(pt.name, p.sku) AS title,
                (SELECT file_url FROM product_media WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image_url
                FROM products p
                LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = '" . $conn->real_escape_string($lang) . "'
                {$where}
                GROUP BY p.id
                ORDER BY p.id DESC
                LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
        
        $res = $conn->query($sql);
        $list = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        
        $totalR = $conn->query("SELECT COUNT(DISTINCT p.id) AS cnt FROM products p 
                               LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = '" . $conn->real_escape_string($lang) . "' {$where}");
        $total = $totalR ? (int)$totalR->fetch_assoc()['cnt'] : 0;
        
        success(['data' => $list, 'total' => $total, 'page' => $page, 'per_page' => $per_page]);
    }

    if ($method === 'POST') {
        $action = $request['action'] ?? ($_POST['action'] ?? $_GET['action'] ?? '');
        if (!$action) fail('Missing action', null, 400);
        
        $user_id = (int)$user['id'];

        if ($action === 'delete') {
            $id = (int)($request['id'] ?? 0);
            if (!$id) fail('Invalid id', null, 400);
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ? LIMIT 1");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) { 
                $stmt->close(); 
                success(null, 'Deleted'); 
            } else { 
                $e = $stmt->error; 
                $stmt->close(); 
                throw new Exception('Delete failed: ' . $e); 
            }
        }

        if ($action === 'toggle_active') {
            $id = (int)($request['id'] ?? 0);
            $is_active = (int)($request['is_active'] ?? 0);
            if (!$id) fail('Invalid id', null, 400);
            $stmt = $conn->prepare("UPDATE products SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('iii', $is_active, $user_id, $id);
            if ($stmt->execute()) { 
                $stmt->close(); 
                success(null, 'Status updated'); 
            } else { 
                $e = $stmt->error; 
                $stmt->close(); 
                throw new Exception('Update failed: ' . $e); 
            }
        }

        if ($action === 'save') {
            $id = !empty($request['id']) ? (int)$request['id'] : 0;
            $sku = trim($request['sku'] ?? '');
            if ($sku === '') fail('SKU required', null, 422);
            
            $slug = trim($request['slug'] ?? $sku);
            $vendor_id = isset($request['vendor_id']) && $request['vendor_id'] !== '' ? (int)$request['vendor_id'] : get_user_vendor_id($conn, $user_id) ?? 1;
            $barcode = trim($request['barcode'] ?? '');
            $product_type = $request['product_type'] ?? 'simple';
            $brand_id = isset($request['brand_id']) && $request['brand_id'] !== '' ? (int)$request['brand_id'] : null;
            $manufacturer_id = isset($request['manufacturer_id']) && $request['manufacturer_id'] !== '' ? (int)$request['manufacturer_id'] : null;
            $is_active = isset($request['is_active']) ? 1 : 0;
            $is_featured = isset($request['is_featured']) ? 1 : 0;
            $is_bestseller = isset($request['is_bestseller']) ? 1 : 0;
            $is_new = isset($request['is_new']) ? 1 : 0;
            $stock_quantity = isset($request['stock_quantity']) ? (int)$request['stock_quantity'] : 0;
            $low_stock_threshold = isset($request['low_stock_threshold']) ? (int)$request['low_stock_threshold'] : 5;
            $stock_status = $request['stock_status'] ?? 'in_stock';
            $manage_stock = isset($request['manage_stock']) ? (int)$request['manage_stock'] : 1;
            $allow_backorder = isset($request['allow_backorder']) ? (int)$request['allow_backorder'] : 0;
            $weight = isset($request['weight']) && $request['weight'] !== '' ? (float)$request['weight'] : null;
            $length = isset($request['length']) && $request['length'] !== '' ? (float)$request['length'] : null;
            $width = isset($request['width']) && $request['width'] !== '' ? (float)$request['width'] : null;
            $height = isset($request['height']) && $request['height'] !== '' ? (float)$request['height'] : null;
            $tax_rate = isset($request['tax_rate']) && $request['tax_rate'] !== '' ? (float)$request['tax_rate'] : 15.00;
            $published_at = $request['published_at'] ?? null;
            $translations = parse_json_field($request['translations'] ?? ($request['product_translations'] ?? '{}'));
            $attributes = parse_json_field($request['attributes'] ?? ($request['product_attributes'] ?? '[]'));
            $variants = parse_json_field($request['variants'] ?? '[]');
            
            $price = isset($request['price']) && $request['price'] !== '' ? (float)$request['price'] : null;
            $compare_at_price = isset($request['compare_at_price']) && $request['compare_at_price'] !== '' ? (float)$request['compare_at_price'] : null;
            $cost_price = isset($request['cost_price']) && $request['cost_price'] !== '' ? (float)$request['cost_price'] : null;
            
            $categories = [];
            if (!empty($request['categories']) && is_array($request['categories'])) {
                $categories = array_map('intval', $request['categories']);
            } elseif (!empty($request['categories'])) {
                $tmp = @json_decode($request['categories'], true);
                if (is_array($tmp)) $categories = array_map('intval', $tmp);
            }
            $categories = array_values(array_unique($categories));
            
            $defaultName = trim($request['name'] ?? '');
            $haveName = $defaultName !== '';
            if (!$haveName) {
                foreach ($translations as $lc => $tr) { 
                    if (!empty($tr['name'])) { 
                        $haveName = true; 
                        break; 
                    } 
                }
            }
            if (!$haveName) fail('Product name required (default or translations)', null, 422);
            
            if ($sku !== '') {
                if ($id) {
                    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE sku = ? AND id <> ?");
                    $s->bind_param('si', $sku, $id);
                } else {
                    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE sku = ?");
                    $s->bind_param('s', $sku);
                }
                if ($s) { 
                    $s->execute(); 
                    $cnt = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0); 
                    $s->close(); 
                    if ($cnt > 0) fail('SKU already exists', null, 422); 
                }
            }
            
            $conn->begin_transaction();
            try {
                if ($id) {
                    $pairs = [
                        'vendor_id' => (int)$vendor_id,
                        'sku' => $conn->real_escape_string($sku),
                        'slug' => $conn->real_escape_string($slug),
                        'barcode' => $conn->real_escape_string($barcode),
                        'product_type' => $conn->real_escape_string($product_type),
                        'brand_id' => $brand_id === null ? 'NULL' : (int)$brand_id,
                        'manufacturer_id' => $manufacturer_id === null ? 'NULL' : (int)$manufacturer_id,
                        'is_active' => (int)$is_active,
                        'is_featured' => (int)$is_featured,
                        'is_bestseller' => (int)$is_bestseller,
                        'is_new' => (int)$is_new,
                        'stock_quantity' => (int)$stock_quantity,
                        'low_stock_threshold' => (int)$low_stock_threshold,
                        'stock_status' => $conn->real_escape_string($stock_status),
                        'manage_stock' => (int)$manage_stock,
                        'allow_backorder' => (int)$allow_backorder,
                        'weight' => $weight === null ? 'NULL' : (float)$weight,
                        'length' => $length === null ? 'NULL' : (float)$length,
                        'width' => $width === null ? 'NULL' : (float)$width,
                        'height' => $height === null ? 'NULL' : (float)$height,
                        'tax_rate' => (float)$tax_rate,
                        'published_at' => $published_at === null ? 'NULL' : "'" . $conn->real_escape_string($published_at) . "'",
                        'updated_by' => (int)$user_id
                    ];
                    
                    $sets = [];
                    foreach ($pairs as $col => $val) {
                        if ($val === 'NULL') $sets[] = "`$col` = NULL";
                        elseif (is_int($val) || is_float($val)) $sets[] = "`$col` = " . $val;
                        else $sets[] = "`$col` = '" . $val . "'";
                    }
                    
                    $sql = "UPDATE products SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = " . (int)$id;
                    if (!$conn->query($sql)) throw new Exception('Update product failed: ' . $conn->error);
                } else {
                    $cols = []; $vals = [];
                    $add = function($c, $v) use (&$cols, &$vals, $conn) {
                        $cols[] = "`$c`";
                        if ($v === null) $vals[] = "NULL";
                        elseif (is_int($v) || is_float($v)) $vals[] = $v;
                        else $vals[] = "'" . $conn->real_escape_string($v) . "'";
                    };
                    
                    $add('vendor_id', (int)$vendor_id);
                    $add('sku', $sku);
                    $add('slug', $slug);
                    $add('barcode', $barcode);
                    $add('product_type', $product_type);
                    if ($brand_id !== null) $add('brand_id', (int)$brand_id);
                    if ($manufacturer_id !== null) $add('manufacturer_id', (int)$manufacturer_id);
                    $add('is_active', (int)$is_active);
                    $add('is_featured', (int)$is_featured);
                    $add('is_bestseller', (int)$is_bestseller);
                    $add('is_new', (int)$is_new);
                    $add('stock_quantity', (int)$stock_quantity);
                    $add('low_stock_threshold', (int)$low_stock_threshold);
                    $add('stock_status', $stock_status);
                    $add('manage_stock', (int)$manage_stock);
                    $add('allow_backorder', (int)$allow_backorder);
                    if ($weight !== null) $add('weight', (float)$weight);
                    if ($length !== null) $add('length', (float)$length);
                    if ($width !== null) $add('width', (float)$width);
                    if ($height !== null) $add('height', (float)$height);
                    $add('tax_rate', (float)$tax_rate);
                    if ($published_at !== null) $add('published_at', $published_at);
                    $add('created_by', (int)$user_id);
                    
                    $sql = "INSERT INTO products (" . implode(',', $cols) . ", created_at) VALUES (" . implode(',', $vals) . ", NOW())";
                    if (!$conn->query($sql)) throw new Exception('Insert product failed: ' . $conn->error);
                    $id = $conn->insert_id;
                }

                if (table_exists($conn, 'product_translations')) {
                    $conn->query("DELETE FROM product_translations WHERE product_id = " . (int)$id);
                    if (!empty($translations)) {
                        $insVals = [];
                        foreach ($translations as $lang => $tr) {
                            $langE = $conn->real_escape_string($lang);
                            $n = $conn->real_escape_string($tr['name'] ?? '');
                            $sh = $conn->real_escape_string($tr['short_description'] ?? '');
                            $desc = $conn->real_escape_string($tr['description'] ?? '');
                            $spec = $conn->real_escape_string($tr['specifications'] ?? '');
                            $mt = $conn->real_escape_string($tr['meta_title'] ?? '');
                            $md = $conn->real_escape_string($tr['meta_description'] ?? '');
                            $mk = $conn->real_escape_string($tr['meta_keywords'] ?? '');
                            $insVals[] = "(" . (int)$id . ", '{$langE}', '{$n}', '{$sh}', '{$desc}', '{$spec}', '{$mt}', '{$md}', '{$mk}')";
                        }
                        if (!empty($insVals)) {
                            $sql = "INSERT INTO product_translations (product_id, language_code, name, short_description, description, specifications, meta_title, meta_description, meta_keywords) VALUES " . implode(',', $insVals);
                            if (!$conn->query($sql)) throw new Exception('Insert translations failed: ' . $conn->error);
                        }
                    }
                }

                if (table_exists($conn, 'product_categories')) {
                    $conn->query("DELETE FROM product_categories WHERE product_id = " . (int)$id);
                    if (!empty($categories)) {
                        $values = [];
                        $primary_set = false;
                        foreach ($categories as $idx => $c) {
                            $c = (int)$c;
                            $is_primary = (!$primary_set) ? 1 : 0;
                            $primary_set = true;
                            
                            if (column_exists($conn, 'product_categories', 'created_at')) {
                                $values[] = "(" . (int)$id . ", {$c}, {$is_primary}, 0, NOW())";
                            } else {
                                $values[] = "(" . (int)$id . ", {$c}, {$is_primary}, 0)";
                            }
                        }
                        if (!empty($values)) {
                            if (column_exists($conn, 'product_categories', 'created_at')) {
                                $sql = "INSERT IGNORE INTO product_categories (product_id, category_id, is_primary, sort_order, created_at) VALUES " . implode(',', $values);
                            } else {
                                $sql = "INSERT IGNORE INTO product_categories (product_id, category_id, is_primary, sort_order) VALUES " . implode(',', $values);
                            }
                            if (!$conn->query($sql)) throw new Exception('Insert categories failed: ' . $conn->error);
                        }
                    }
                }

                if (table_exists($conn, 'product_attribute_assignments')) {
                    $conn->query("DELETE FROM product_attribute_assignments WHERE product_id = " . (int)$id);
                    if (!empty($attributes)) {
                        $vals = [];
                        foreach ($attributes as $a) {
                            $aid = (int)($a['attribute_id'] ?? 0);
                            $avid = isset($a['attribute_value_id']) && $a['attribute_value_id'] !== '' ? (int)$a['attribute_value_id'] : null;
                            $custom = $conn->real_escape_string($a['custom_value'] ?? '');
                            if ($avid === null) $vals[] = "(" . (int)$id . ", {$aid}, NULL, '{$custom}', NOW())";
                            else $vals[] = "(" . (int)$id . ", {$aid}, {$avid}, '{$custom}', NOW())";
                        }
                        if (!empty($vals)) {
                            $sql = "INSERT INTO product_attribute_assignments (product_id, attribute_id, attribute_value_id, custom_value, created_at) VALUES " . implode(',', $vals);
                            if (!$conn->query($sql)) throw new Exception('Insert attribute assignments failed: ' . $conn->error);
                        }
                    }
                }

                if (table_exists($conn, 'product_pricing') && $price !== null) {
                    $conn->query("DELETE FROM product_pricing WHERE product_id = " . (int)$id . " AND variant_id IS NULL");
                    $sql = "INSERT INTO product_pricing (product_id, price, compare_at_price, cost_price, currency_code, pricing_type, is_active, created_at) 
                            VALUES (" . (int)$id . ", " . (float)$price . ", " . ($compare_at_price !== null ? (float)$compare_at_price : "NULL") . ", " . ($cost_price !== null ? (float)$cost_price : "NULL") . ", 'USD', 'fixed', 1, NOW())";
                    if (!$conn->query($sql)) throw new Exception('Insert pricing failed: ' . $conn->error);
                }

                if (table_exists($conn, 'product_variants') && !empty($variants) && is_array($variants)) {
                    $conn->query("DELETE FROM product_variants WHERE product_id = " . (int)$id);
                    $vals = [];
                    foreach ($variants as $v) {
                        $vsku = $conn->real_escape_string($v['sku'] ?? '');
                        $vbarcode = $conn->real_escape_string($v['barcode'] ?? '');
                        $vstock = (int)($v['stock_quantity'] ?? 0);
                        $vlow = (int)($v['low_stock_threshold'] ?? 5);
                        $vweight = isset($v['weight']) ? (float)$v['weight'] : 'NULL';
                        $vlength = isset($v['length']) ? (float)$v['length'] : 'NULL';
                        $vwidth = isset($v['width']) ? (float)$v['width'] : 'NULL';
                        $vheight = isset($v['height']) ? (float)$v['height'] : 'NULL';
                        $vactive = isset($v['is_active']) ? (int)$v['is_active'] : 1;
                        $vdefault = isset($v['is_default']) ? (int)$v['is_default'] : 0;
                        $vals[] = "(".(int)$id.", '{$vsku}', '{$vbarcode}', {$vstock}, {$vlow}, ".($vweight==='NULL'?'NULL':$vweight).", ".($vlength==='NULL'?'NULL':$vlength).", ".($vwidth==='NULL'?'NULL':$vwidth).", ".($vheight==='NULL'?'NULL':$vheight).", {$vactive}, {$vdefault}, NOW())";
                    }
                    if (!empty($vals)) {
                        $sql = "INSERT INTO product_variants (product_id, sku, barcode, stock_quantity, low_stock_threshold, weight, length, width, height, is_active, is_default, created_at) VALUES " . implode(',', $vals);
                        if (!$conn->query($sql)) throw new Exception('Insert variants failed: ' . $conn->error);
                    }
                }

                $upload_dir = __DIR__ . '/../uploads/products/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                
                if (table_exists($conn, 'product_media')) $conn->query("DELETE FROM product_media WHERE product_id = " . (int)$id);
                elseif (table_exists($conn, 'product_images')) $conn->query("DELETE FROM product_images WHERE product_id = " . (int)$id);
                
                if (!empty($_FILES['images']) && (!empty($_FILES['images']['name'][0]) || !is_array($_FILES['images']['name']))) {
                    $files = $_FILES['images'];
                    $count = is_array($files['name']) ? count($files['name']) : 1;
                    for ($i = 0; $i < $count; $i++) {
                        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                        $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                        $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                        if ($error !== UPLOAD_ERR_OK) continue;
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $fn = 'p' . $id . '_' . time() . '_' . $i . ($ext ? '.' . $ext : '');
                        $target = $upload_dir . $fn;
                        if (!move_uploaded_file($tmp, $target)) { log_msg_local("move_uploaded_file failed for index {$i}"); continue; }
                        $url = '/uploads/products/' . $fn;
                        $is_primary = $i === 0 ? 1 : 0;
                        $sort = $i;
                        if (table_exists($conn, 'product_media')) {
                            $sql = "INSERT INTO product_media (product_id, media_type, file_url, thumbnail_url, file_size, mime_type, is_primary, sort_order, created_at) VALUES (" . (int)$id . ", 'image', '" . $conn->real_escape_string($url) . "', '" . $conn->real_escape_string($url) . "', " . (int)$size . ", '" . $conn->real_escape_string($type) . "', " . (int)$is_primary . ", " . (int)$sort . ", NOW())";
                            if (!$conn->query($sql)) log_msg_local('Insert product_media failed: ' . $conn->error);
                        } elseif (table_exists($conn, 'product_images')) {
                            $sql = "INSERT INTO product_images (product_id, image_url, is_primary, sort_order, created_at) VALUES (" . (int)$id . ", '" . $conn->real_escape_string($url) . "', " . (int)$is_primary . ", " . (int)$sort . ", NOW())";
                            if (!$conn->query($sql)) log_msg_local('Insert product_images failed: ' . $conn->error);
                        }
                    }
                }
                
                $existing_media = $request['existing_media'] ?? [];
                if (!is_array($existing_media)) $existing_media = [];
                foreach ($existing_media as $mid) {
                    $mid = (int)$mid;
                    $res = $conn->query("SELECT file_url, mime_type, file_size FROM media WHERE id = {$mid} LIMIT 1");
                    if ($r = $res->fetch_assoc()) {
                        $url = $r['file_url'];
                        $type = $r['mime_type'];
                        $size = $r['file_size'];
                        $is_primary = count($existing_media) === 1 ? 1 : 0;
                        $sort = 0;
                        if (table_exists($conn, 'product_media')) {
                            $sql = "INSERT INTO product_media (product_id, media_type, file_url, thumbnail_url, file_size, mime_type, is_primary, sort_order, created_at) VALUES (" . (int)$id . ", 'image', '" . $conn->real_escape_string($url) . "', '" . $conn->real_escape_string($url) . "', " . (int)$size . ", '" . $conn->real_escape_string($type) . "', " . (int)$is_primary . ", " . (int)$sort . ", NOW())";
                            $conn->query($sql);
                        }
                    }
                }
                
                $conn->commit();
                
                $stmt2 = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
                $stmt2->bind_param('i', $id); 
                $stmt2->execute(); 
                $prod = $stmt2->get_result()->fetch_assoc(); 
                $stmt2->close();
                
                $prod['translations'] = [];
                if (table_exists($conn, 'product_translations')) {
                    $res = $conn->query("SELECT * FROM product_translations WHERE product_id = " . (int)$id);
                    while ($r = $res->fetch_assoc()) { 
                        $lang = $r['language_code']; 
                        unset($r['product_id'], $r['id']); 
                        $prod['translations'][$lang] = $r; 
                    }
                }
                
                success(['product' => $prod], 'Saved');
            } catch (Throwable $e) {
                $conn->rollback();
                log_msg_local('Save exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                if ($DEBUG) respond(['success' => false, 'message' => 'Save failed', 'exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()], 500);
                else fail('Save failed', null, 500);
            }
        }
        fail('Unknown action', null, 400);
    }
    fail('Method not allowed', null, 405);
} catch (Throwable $e) {
    log_msg_local('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if ($DEBUG) respond(['success' => false, 'message' => 'Internal error', 'exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()], 500);
    else fail('Internal server error', null, 500);
}
?>