<?php
/**
 * api/routes/shipping.php
 *
 * Delivery Company API (final corrected)
 *
 * Endpoints:
 *   GET  ?action=current_user
 *   GET  ?action=parents
 *   GET  ?action=filter_countries[&lang]
 *   GET  ?action=all_countries[&lang]
 *   GET  ?action=filter_cities[&country_id][&lang]
 *   GET  ?action=all_cities[&country_id][&lang]
 *   GET  ?action=get&id=<company_id>[&lang]
 *   GET  ?action=list[&q=&phone=&email=&country_id=&city_id=&is_active=&page=&per_page=&lang=&debug=1]
 *   POST action=create_company
 *   POST action=update_company
 *   POST action=delete_company
 *   POST action=create_company_token
 *
 * Improvements:
 * - Clear separation between "filter" endpoints (referenced values) and "all" endpoints (for form selects).
 * - Defensive checks for translation tables/columns.
 * - Frees mysqli_result after usage to avoid "Commands out of sync" errors.
 * - Full create/update/delete implementations with validation and file upload support.
 *
 * Backup previous file before replacing.
 */

declare(strict_types=1);

$DEBUG_LOG = __DIR__ . '/../error_debug.log';
function log_debug(string $m) { @file_put_contents(__DIR__ . '/../error_debug.log', "[".date('c')."] " . trim($m) . PHP_EOL, FILE_APPEND); }
function json_ok($d = [], int $code = 200) { if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code); $out = is_array($d) ? array_merge(['success' => true], $d) : ['success' => true, 'data' => $d]; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }
function json_error(string $m = 'Error', int $code = 400, array $extra = []) { if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code); $out = array_merge(['success' => false, 'message' => $m], $extra); echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }

// portable bind helper (uses references)
function bind_params_stmt($stmt, string $types, array $params) {
    if ($types === '') return;
    $bind_names = [$types];
    for ($i = 0; $i < count($params); $i++) $bind_names[] = &$params[$i];
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

// portable fetch helpers that ensure result is freed
function stmt_fetch_one_assoc($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if (!$res) return null;
        $row = $res->fetch_assoc();
        $res->free();
        return $row ?: null;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return null;
    $row = [];
    $fields = [];
    while ($f = $meta->fetch_field()) { $row[$f->name] = null; $fields[] = &$row[$f->name]; }
    $meta->free();
    call_user_func_array([$stmt, 'bind_result'], $fields);
    if ($stmt->fetch()) return $row;
    return null;
}
function stmt_fetch_all_assoc($stmt) {
    $out = [];
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) $out[] = $r;
            $res->free();
        }
        return $out;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return $out;
    $row = []; $fields = [];
    while ($f = $meta->fetch_field()) { $row[$f->name] = null; $fields[] = &$row[$f->name]; }
    $meta->free();
    call_user_func_array([$stmt, 'bind_result'], $fields);
    while ($stmt->fetch()) {
        $r = [];
        foreach ($row as $k => $v) $r[$k] = $v;
        $out[] = $r;
    }
    return $out;
}

// free mysqli_result if provided
function free_if_result($res) { if ($res instanceof mysqli_result) $res->free(); }

// start session
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

// acquire DB (robust)
function acquire_db() {
    if (function_exists('container')) {
        try { $tmp = container('db'); if ($tmp instanceof mysqli) return $tmp; } catch (Throwable $e) { log_debug("container() error: ".$e->getMessage()); }
    }
    foreach (['conn','db','mysqli'] as $n) if (!empty($GLOBALS[$n]) && $GLOBALS[$n] instanceof mysqli) return $GLOBALS[$n];
    if (function_exists('connectDB')) {
        try { $tmp = connectDB(); if ($tmp instanceof mysqli) return $tmp; } catch (Throwable $e) { log_debug("connectDB() error: ".$e->getMessage()); }
    }
    $cfg = __DIR__ . '/../config/db.php';
    if (is_readable($cfg)) {
        try { require_once $cfg; } catch (Throwable $e) { log_debug("include db.php failed: ".$e->getMessage()); }
        if (function_exists('connectDB')) {
            try { $tmp = connectDB(); if ($tmp instanceof mysqli) return $tmp; } catch (Throwable $e) { log_debug("connectDB() after include error: ".$e->getMessage()); }
        }
        if (!empty($conn) && $conn instanceof mysqli) return $conn;
        if (!empty($db) && $db instanceof mysqli) return $db;
    }
    return null;
}

$db = acquire_db();
if (!($db instanceof mysqli)) { log_debug("No DB available"); json_error('Database connection error', 500); }

// detect translation tables & company translations name column
$hasCountryTrans = ($db->query("SHOW TABLES LIKE 'country_translations'") ? $db->query("SHOW TABLES LIKE 'country_translations'")->num_rows : 0) > 0;
$hasCityTrans = ($db->query("SHOW TABLES LIKE 'city_translations'") ? $db->query("SHOW TABLES LIKE 'city_translations'")->num_rows : 0) > 0;
$hasCompanyTrans = ($db->query("SHOW TABLES LIKE 'delivery_company_translations'") ? $db->query("SHOW TABLES LIKE 'delivery_company_translations'")->num_rows : 0) > 0;
$companyTransHasName = false;
if ($hasCompanyTrans) {
    $colCheck = $db->query("SHOW COLUMNS FROM delivery_company_translations LIKE 'name'");
    if ($colCheck && $colCheck->num_rows > 0) $companyTransHasName = true;
    if ($colCheck) $colCheck->free();
}

// helpers
function get_requested_lang(): string {
    if (!empty($_GET['lang'])) return trim((string)$_GET['lang']);
    if (!empty($_SESSION['preferred_language'])) return (string)$_SESSION['preferred_language'];
    if (!empty($_SESSION['user']['preferred_language'])) return (string)$_SESSION['user']['preferred_language'];
    return 'en';
}
function get_current_user_full() {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        return [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : (isset($_SESSION['role']) ? (int)$_SESSION['role'] : null),
            'preferred_language' => $_SESSION['preferred_language'] ?? null,
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    return null;
}
function is_admin_user_full($user): bool {
    if (!is_array($user)) return false;
    if (isset($user['role_id'])) return ((int)$user['role_id'] === 1);
    if (isset($user['role'])) return ((int)$user['role'] === 1);
    return false;
}

// small file upload helper
function save_uploaded_logo(array $file, int $companyId): ?string {
    if (empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;
    $uploadsRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/..'), '/\\') . '/uploads/delivery_companies/' . $companyId;
    if (!is_dir($uploadsRoot)) @mkdir($uploadsRoot, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe = bin2hex(random_bytes(10)) . ($ext ? '.' . preg_replace('/[^a-z0-9]/i', '', $ext) : '');
    $dest = $uploadsRoot . '/' . $safe;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) return null;
    return '/uploads/delivery_companies/' . $companyId . '/' . $safe;
}

// router
$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// current_user
if ($method === 'GET' && $action === 'current_user') {
    $u = get_current_user_full();
    if (!$u) return json_error('Unauthorized', 401);
    return json_ok(['user' => $u, 'session_id' => session_id()]);
}

// Countries: all_countries for form selects, filter_countries for table filters
if ($method === 'GET' && in_array($action, ['all_countries', 'filter_countries'], true)) {
    $lang = get_requested_lang();
    $scopeAll = ($action === 'all_countries');
    try {
        $rows = [];
        if ($scopeAll) {
            if ($hasCountryTrans && $lang) {
                $stmt = $db->prepare("SELECT c.id, COALESCE(ct.name, c.name) AS name, c.iso2 FROM countries c LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ? ORDER BY name ASC");
                bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
            } else {
                $res = $db->query("SELECT id, name, iso2 FROM countries ORDER BY name ASC");
                if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
            }
        } else {
            if ($hasCountryTrans && $lang) {
                $stmt = $db->prepare("SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name, c.iso2 FROM delivery_companies dc JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ? WHERE dc.country_id IS NOT NULL ORDER BY name ASC");
                bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
            } else {
                $res = $db->query("SELECT DISTINCT c.id, c.name, c.iso2 FROM delivery_companies dc JOIN countries c ON c.id = dc.country_id WHERE dc.country_id IS NOT NULL ORDER BY c.name ASC");
                if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
            }
        }
        return json_ok(['data' => $rows]);
    } catch (Throwable $e) { log_debug("countries error: " . $e->getMessage()); return json_error('Server error', 500); }
}

// Cities: all_cities for form selects, filter_cities for table filters
if ($method === 'GET' && in_array($action, ['all_cities', 'filter_cities'], true)) {
    $lang = get_requested_lang();
    $country_id = isset($_GET['country_id']) && $_GET['country_id'] !== '' ? (int)$_GET['country_id'] : null;
    $scopeAll = ($action === 'all_cities');
    try {
        $rows = [];
        if ($scopeAll) {
            if ($hasCityTrans && $lang) {
                if ($country_id) {
                    $stmt = $db->prepare("SELECT ci.id, COALESCE(cit.name, ci.name) AS name FROM cities ci LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE ci.country_id = ? ORDER BY name ASC");
                    bind_params_stmt($stmt, 'si', [$lang, $country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                } else {
                    $stmt = $db->prepare("SELECT ci.id, COALESCE(cit.name, ci.name) AS name FROM cities ci LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? ORDER BY name ASC");
                    bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                }
            } else {
                if ($country_id) {
                    $stmt = $db->prepare("SELECT id, name FROM cities WHERE country_id = ? ORDER BY name ASC");
                    bind_params_stmt($stmt, 'i', [$country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                } else {
                    $res = $db->query("SELECT id, name FROM cities ORDER BY name ASC");
                    if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
                }
            }
        } else {
            if ($hasCityTrans && $lang) {
                if ($country_id) {
                    $stmt = $db->prepare("SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE dc.city_id IS NOT NULL AND ci.country_id = ? ORDER BY name ASC");
                    bind_params_stmt($stmt, 'si', [$lang, $country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                } else {
                    $stmt = $db->prepare("SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE dc.city_id IS NOT NULL ORDER BY name ASC");
                    bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                }
            } else {
                if ($country_id) {
                    $stmt = $db->prepare("SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL AND ci.country_id = ? ORDER BY ci.name ASC");
                    bind_params_stmt($stmt, 'i', [$country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
                } else {
                    $res = $db->query("SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL ORDER BY ci.name ASC");
                    if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
                }
            }
        }
        return json_ok(['data' => $rows]);
    } catch (Throwable $e) { log_debug("cities error: " . $e->getMessage()); return json_error('Server error', 500); }
}

// Parents list for parent select in form — defensive re: translations
if ($method === 'GET' && $action === 'parents') {
    $lang = get_requested_lang();
    try {
        $rows = [];
        if ($hasCompanyTrans && $companyTransHasName && $lang) {
            $stmt = $db->prepare("SELECT dc.id, COALESCE(dct.name, dc.name) AS name FROM delivery_companies dc LEFT JOIN delivery_company_translations dct ON dct.company_id = dc.id AND dct.language_code = ? ORDER BY name ASC");
            bind_params_stmt($stmt, 's', [$lang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close();
        } else {
            $res = $db->query("SELECT id, name FROM delivery_companies ORDER BY name ASC");
            if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
        }
        return json_ok(['data' => $rows]);
    } catch (Throwable $e) { log_debug("parents error: " . $e->getMessage()); return json_error('Server error', 500); }
}

// GET single company (with translated country/city names if available)
if ($method === 'GET' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (!$id) return json_error('Invalid id', 400);
    $lang = get_requested_lang();
    try {
        $select = "dc.*";
        $joins = "";
        $bindTypes = ""; $bindParams = [];
        if ($hasCountryTrans && $lang) {
            $select .= ", COALESCE(ct.name, c.name) AS country_name";
            $joins .= " LEFT JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $lang;
        } else {
            $select .= ", c.name AS country_name";
            $joins .= " LEFT JOIN countries c ON c.id = dc.country_id";
        }
        if ($hasCityTrans && $lang) {
            $select .= ", COALESCE(cit.name, ci.name) AS city_name";
            $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $lang;
        } else {
            $select .= ", ci.name AS city_name";
            $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id";
        }
        $sql = "SELECT {$select} FROM delivery_companies dc {$joins} WHERE dc.id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) { log_debug("get prepare failed: " . $db->error); return json_error('Server error', 500); }
        $finalTypes = $bindTypes . 'i'; $finalParams = $bindParams; $finalParams[] = $id;
        bind_params_stmt($stmt, $finalTypes, $finalParams);
        $stmt->execute();
        $row = stmt_fetch_one_assoc($stmt); $stmt->close();
        if (!$row) return json_error('Not found', 404);

        // load translations if any
        $row['translations'] = [];
        if ($hasCompanyTrans) {
            $tstmt = $db->prepare("SELECT language_code, description, terms, meta_title, meta_description FROM delivery_company_translations WHERE company_id = ?");
            if ($tstmt) { bind_params_stmt($tstmt, 'i', [$id]); $tstmt->execute(); $trs = stmt_fetch_all_assoc($tstmt); $tstmt->close(); foreach ($trs as $tr) $row['translations'][$tr['language_code']] = $tr; }
        }

        return json_ok(['data' => $row]);
    } catch (Throwable $e) { log_debug("get company error: " . $e->getMessage()); return json_error('Server error', 500); }
}

// LIST (search + filters)
if ($method === 'GET' && ($action === 'list' || $action === null)) {
    $debugMode = !empty($_GET['debug']) && $_GET['debug'] == '1';
    try {
        $q = trim((string)($_GET['q'] ?? ''));
        $phone = trim((string)($_GET['phone'] ?? ''));
        $email = trim((string)($_GET['email'] ?? ''));
        $is_active = isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null;
        $country_id = isset($_GET['country_id']) && $_GET['country_id'] !== '' ? (int)$_GET['country_id'] : null;
        $city_id = isset($_GET['city_id']) && $_GET['city_id'] !== '' ? (int)$_GET['city_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = min(200, max(5, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $per;
        $lang = get_requested_lang();

        $select = "dc.*";
        $joins = "";
        $bindTypes = ""; $bindParams = [];
        if ($hasCountryTrans && $lang) {
            $select .= ", COALESCE(ct.name, c.name) AS country_name";
            $joins .= " LEFT JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $lang;
        } else {
            $select .= ", c.name AS country_name"; $joins .= " LEFT JOIN countries c ON c.id = dc.country_id";
        }
        if ($hasCityTrans && $lang) {
            $select .= ", COALESCE(cit.name, ci.name) AS city_name";
            $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $lang;
        } else {
            $select .= ", ci.name AS city_name"; $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id";
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS {$select} FROM delivery_companies dc {$joins}";

        $where = ['1=1']; $types = ''; $params = [];
        if ($q !== '') { $where[] = "(dc.name LIKE ? OR dc.email LIKE ? OR dc.phone LIKE ? OR dc.slug LIKE ?)"; $like = '%'.$q.'%'; $params = array_merge($params, [$like,$like,$like,$like]); $types .= 'ssss'; }
        if ($phone !== '') { $where[] = "dc.phone LIKE ?"; $params[] = '%'.$phone.'%'; $types .= 's'; }
        if ($email !== '') { $where[] = "dc.email LIKE ?"; $params[] = '%'.$email.'%'; $types .= 's'; }
        if ($is_active !== null) { $where[] = "dc.is_active = ?"; $params[] = $is_active; $types .= 'i'; }
        if ($country_id !== null) { $where[] = "dc.country_id = ?"; $params[] = $country_id; $types .= 'i'; }
        if ($city_id !== null) { $where[] = "dc.city_id = ?"; $params[] = $city_id; $types .= 'i'; }

        $whereSql = implode(' AND ', $where);
        $sql .= " WHERE {$whereSql} ORDER BY dc.id DESC LIMIT ? OFFSET ?";

        $params[] = $per; $params[] = $offset; $types .= 'ii';

        $stmt = $db->prepare($sql);
        if (!$stmt) { log_debug("LIST prepare failed: ".$db->error); if ($debugMode) return json_error('Prepare failed: '.$db->error, 500, ['sql'=>$sql]); return json_error('Server error', 500); }
        $bindTypesFinal = $bindTypes . $types;
        $bindParamsFinal = array_merge($bindParams, $params);
        if ($bindTypesFinal !== '') bind_params_stmt($stmt, $bindTypesFinal, $bindParamsFinal);

        if (!$stmt->execute()) { $serr = $stmt->error ?: $db->error; log_debug("LIST execute failed: {$serr} SQL: {$sql} PARAMS:".json_encode($bindParamsFinal)); if ($debugMode) return json_error('Execute failed: '.$serr, 500, ['sql'=>$sql,'params'=>$bindParamsFinal]); return json_error('Server error', 500); }

        $rows = stmt_fetch_all_assoc($stmt);
        $stmt->close();

        $totalRes = $db->query("SELECT FOUND_ROWS() AS total");
        $total = 0;
        if ($totalRes) { $tr = $totalRes->fetch_assoc(); $total = isset($tr['total']) ? (int)$tr['total'] : count($rows); $totalRes->free(); }

        return json_ok(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
    } catch (Throwable $e) { log_debug("LIST exception: ".$e->getMessage()); return json_error('Server error', 500); }
}

// POST endpoints require user
$currentUser = get_current_user_full();
if ($method === 'POST' && in_array($action, ['create_company','update_company','delete_company','create_company_token'], true) && !$currentUser) {
    return json_error('Unauthorized', 401);
}

/* ---------- CREATE ---------- */
if ($method === 'POST' && $action === 'create_company') {
    if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);

    $allowed = ['parent_id','user_id','name','slug','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','sort_order','rating_average'];
    $data = [];
    foreach ($allowed as $f) if (isset($_POST[$f]) && $_POST[$f] !== '') $data[$f] = $_POST[$f];
    if (empty($data)) return json_error('No data', 400);

    // user id
    if (isset($data['user_id']) && is_admin_user_full($currentUser)) $data['user_id'] = (int)$data['user_id'];
    else $data['user_id'] = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;

    foreach (['parent_id','city_id','country_id','is_active','sort_order'] as $n) if (isset($data[$n])) $data[$n] = (int)$data[$n];
    if (isset($data['rating_average'])) $data['rating_average'] = (float)str_replace(',', '.', $data['rating_average']);

    // validate parent
    if (isset($data['parent_id']) && $data['parent_id'] > 0) {
        $pv = $db->prepare("SELECT id FROM delivery_companies WHERE id = ? LIMIT 1");
        if ($pv) { bind_params_stmt($pv, 'i', [$data['parent_id']]); $pv->execute(); $prow = stmt_fetch_one_assoc($pv); $pv->close(); if (!$prow) return json_error('Invalid parent_id', 400); }
    } else unset($data['parent_id']);

    $cols = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colsSql = implode(',', $cols);
    $typesMap = ['parent_id'=>'i','user_id'=>'i','name'=>'s','slug'=>'s','phone'=>'s','email'=>'s','website_url'=>'s','api_url'=>'s','api_key'=>'s','tracking_url'=>'s','city_id'=>'i','country_id'=>'i','is_active'=>'i','sort_order'=>'i','rating_average'=>'d'];
    $types = ''; $params = [];
    foreach ($cols as $c) { $types .= $typesMap[$c] ?? 's'; $params[] = $data[$c]; }

    $sql = "INSERT INTO delivery_companies ({$colsSql}) VALUES ({$placeholders})";
    $stmt = $db->prepare($sql);
    if (!$stmt) { log_debug("INSERT prepare failed: ".$db->error); return json_error('Create failed', 500); }
    if (!empty($types)) bind_params_stmt($stmt, $types, $params);
    if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); log_debug("INSERT failed: $err SQL: $sql"); return json_error('Create failed', 500); }
    $newId = (int)$stmt->insert_id; $stmt->close();

    // logo upload
    if (!empty($_FILES['logo'])) {
        $url = save_uploaded_logo($_FILES['logo'], $newId);
        if ($url) {
            $ust = $db->prepare("UPDATE delivery_companies SET logo_url = ? WHERE id = ? LIMIT 1");
            if ($ust) { bind_params_stmt($ust, 'si', [$url, $newId]); $ust->execute(); $ust->close(); }
        }
    }

    if (!empty($_POST['translations'])) {
        $trs = json_decode($_POST['translations'], true);
        if (is_array($trs)) {
            $ins = $db->prepare("INSERT INTO delivery_company_translations (company_id, language_code, description, terms, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
            if ($ins) {
                foreach ($trs as $lang => $d) {
                    $desc = $d['description'] ?? null; $terms = $d['terms'] ?? null; $mt = $d['meta_title'] ?? null; $md = $d['meta_description'] ?? null;
                    bind_params_stmt($ins, 'isssss', [$newId, $lang, $desc, $terms, $mt, $md]);
                    $ins->execute();
                }
                $ins->close();
            }
        }
    }

    return json_ok(['id' => $newId, 'message' => 'Created']);
}

/* ---------- UPDATE ---------- */
if ($method === 'POST' && $action === 'update_company') {
    if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) return json_error('Invalid id', 400);

    // fetch existing
    $s = $db->prepare("SELECT * FROM delivery_companies WHERE id = ? LIMIT 1");
    if (!$s) return json_error('Server error', 500);
    bind_params_stmt($s, 'i', [$id]); $s->execute(); $existing = stmt_fetch_one_assoc($s); $s->close();
    if (!$existing) return json_error('Not found', 404);

    if (!is_admin_user_full($currentUser) && (int)$existing['user_id'] !== (isset($currentUser['id']) ? (int)$currentUser['id'] : 0)) {
        return json_error('Forbidden', 403);
    }

    $allowedAdmin = ['parent_id','user_id','name','slug','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','sort_order','rating_average','rating_count','logo_url'];
    $allowedOwner = ['name','phone','email','website_url','tracking_url','city_id','country_id'];
    $allowed = is_admin_user_full($currentUser) ? $allowedAdmin : $allowedOwner;

    $data = [];
    foreach ($allowed as $f) if (isset($_POST[$f])) $data[$f] = $_POST[$f];
    if (isset($data['user_id']) && !is_admin_user_full($currentUser)) unset($data['user_id']);

    foreach (['parent_id','city_id','country_id','is_active','sort_order','rating_count'] as $n) if (isset($data[$n])) $data[$n] = (int)$data[$n];
    if (isset($data['rating_average'])) $data['rating_average'] = (float)str_replace(',', '.', $data['rating_average']);

    // validate parent
    if (isset($data['parent_id'])) {
        if ((int)$data['parent_id'] === $id) return json_error('Invalid parent_id: cannot be self', 400);
        if ($data['parent_id'] <= 0) unset($data['parent_id']);
        else {
            $pv = $db->prepare("SELECT id FROM delivery_companies WHERE id = ? LIMIT 1");
            if (!$pv) { log_debug("parent check prepare failed: ".$db->error); return json_error('Server error', 500); }
            bind_params_stmt($pv, 'i', [$data['parent_id']]); $pv->execute(); $prow = stmt_fetch_one_assoc($pv); $pv->close();
            if (!$prow) return json_error('Invalid parent_id', 400);
        }
    }

    // logo upload
    if (!empty($_FILES['logo'])) {
        $url = save_uploaded_logo($_FILES['logo'], $id);
        if ($url) $data['logo_url'] = $url;
    }

    if (!empty($data)) {
        $sets = []; $types = ''; $params = [];
        foreach ($data as $k => $v) {
            $sets[] = "`{$k}` = ?";
            if (in_array($k, ['parent_id','user_id','city_id','country_id','is_active','sort_order','rating_count'], true)) { $types .= 'i'; $params[] = (int)$v; }
            elseif ($k === 'rating_average') { $types .= 'd'; $params[] = (float)$v; }
            else { $types .= 's'; $params[] = (string)$v; }
        }
        $params[] = $id; $types .= 'i';
        $sql = "UPDATE delivery_companies SET " . implode(',', $sets) . " WHERE id = ? LIMIT 1";
        $ust = $db->prepare($sql);
        if (!$ust) { log_debug("update prepare failed: " . $db->error . " SQL: $sql"); return json_error('Update failed', 500); }
        bind_params_stmt($ust, $types, $params);
        if (!$ust->execute()) { log_debug("UPDATE error: ".$ust->error." SQL: $sql"); $ust->close(); return json_error('Update failed', 500); }
        $ust->close();
    }

    // translations replace
    if (isset($_POST['translations'])) {
        $trs = json_decode($_POST['translations'], true);
        if (is_array($trs)) {
            $del = $db->prepare("DELETE FROM delivery_company_translations WHERE company_id = ?");
            if ($del) { bind_params_stmt($del, 'i', [$id]); $del->execute(); $del->close(); }
            $ins = $db->prepare("INSERT INTO delivery_company_translations (company_id, language_code, description, terms, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
            if ($ins) {
                foreach ($trs as $lang => $d) {
                    $desc = $d['description'] ?? null; $terms = $d['terms'] ?? null; $mt = $d['meta_title'] ?? null; $md = $d['meta_description'] ?? null;
                    bind_params_stmt($ins, 'isssss', [$id, $lang, $desc, $terms, $mt, $md]);
                    if (!$ins->execute()) log_debug("translation insert failed: " . $ins->error);
                }
                $ins->close();
            }
        }
    }

    return json_ok(['message' => 'Updated']);
}

/* ---------- DELETE ---------- */
if ($method === 'POST' && $action === 'delete_company') {
    if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) return json_error('Invalid id', 400);

    $stmt = $db->prepare("SELECT user_id FROM delivery_companies WHERE id = ? LIMIT 1");
    if (!$stmt) return json_error('Server error', 500);
    bind_params_stmt($stmt, 'i', [$id]); $stmt->execute(); $row = stmt_fetch_one_assoc($stmt); $stmt->close();
    if (!$row) return json_error('Not found', 404);

    $ownerId = (int)$row['user_id']; $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
    if (!is_admin_user_full($currentUser) && $ownerId !== $uid) return json_error('Forbidden', 403);

    $d = $db->prepare("DELETE FROM delivery_companies WHERE id = ? LIMIT 1");
    if (!$d) return json_error('Server error', 500);
    bind_params_stmt($d, 'i', [$id]); $ok = $d->execute(); $d->close();

    return json_ok(['deleted' => (bool)$ok]);
}

/* ---------- create_company_token ---------- */
if ($method === 'POST' && $action === 'create_company_token') {
    if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
    $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    if (!$companyId) return json_error('Invalid company id', 400);

    $stmt = $db->prepare("SELECT user_id FROM delivery_companies WHERE id = ? LIMIT 1");
    if (!$stmt) return json_error('Server error', 500);
    bind_params_stmt($stmt, 'i', [$companyId]); $stmt->execute(); $row = stmt_fetch_one_assoc($stmt); $stmt->close();
    if (!$row) return json_error('Not found', 404);

    $ownerId = (int)$row['user_id']; $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
    if (!is_admin_user_full($currentUser) && $ownerId !== $uid) return json_error('Forbidden', 403);

    $name = substr(trim((string)($_POST['name'] ?? 'token')), 0, 100);
    $scopes = substr(trim((string)($_POST['scopes'] ?? '')), 0, 255);
    $expires_in = isset($_POST['expires_in']) ? (int)$_POST['expires_in'] : 0;
    $expires_at = $expires_in > 0 ? date('Y-m-d H:i:s', time() + $expires_in) : null;
    $token = bin2hex(random_bytes(32));

    $ins = $db->prepare("INSERT INTO delivery_company_tokens (company_id, token, name, scopes, expires_at) VALUES (?, ?, ?, ?, ?)");
    if (!$ins) return json_error('Server error', 500);
    bind_params_stmt($ins, 'issss', [$companyId, $token, $name, $scopes, $expires_at]);
    if (!$ins->execute()) { log_debug("TOKEN INSERT ERROR: " . $ins->error); $ins->close(); return json_error('Token creation failed', 500); }
    $ins->close();

    return json_ok(['token' => $token]);
}

return json_error('Invalid action', 400);