<?php
/**
 * api/DeliveryCompany.php
 *
 * Delivery Company API (improved, fixed)
 * - Endpoints:
 *   GET  ?action=current_user
 *   GET  ?action=parents
 *   GET  ?action=filter_countries[&lang]
 *   GET  ?action=filter_cities[&country_id][&lang]
 *   GET  ?id=<company_id>[&lang]
 *   GET  [?q=&phone=&email=&country_id=&city_id=&is_active=&page=&per_page=&lang=&debug=1]
 *   POST action=create_company
 *   POST action=update_company
 *   POST action=delete_company
 *   POST action=create_company_token
 *
 * Improvements/fixes:
 * - Removed reference to non-existing column `published_at` (caused "Unknown column" error)
 * - Validates parent_id (existence, no self-parenting)
 * - Translation-aware country/city names
 * - Filter endpoints return only referenced countries/cities
 * - Defensive mysqli usage (works without mysqli_stmt::get_result)
 * - Debug support for list via ?debug=1 (temporary, remove after debugging)
 *
 * Backup previous file before replacing.
 */

declare(strict_types=1);

$DEBUG_LOG = __DIR__ . '/error_debug.log';
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);

/* ---------- Error & exception handlers ---------- */
set_exception_handler(function ($e) use ($DEBUG_LOG) {
    $msg = "[".date('c')."] EXCEPTION: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}\n\n";
    @file_put_contents($DEBUG_LOG, $msg, FILE_APPEND);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['success' => false, 'message' => 'Server error (see server log)']);
    exit;
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($DEBUG_LOG) {
    $msg = "[".date('c')."] PHP ERROR: [$errno] $errstr in $errfile:$errline\n";
    @file_put_contents($DEBUG_LOG, $msg, FILE_APPEND);
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/* ---------- Defensive includes ---------- */
if (is_readable(__DIR__ . '/bootstrap.php')) require_once __DIR__ . '/bootstrap.php';
if (is_readable(__DIR__ . '/config/db.php')) @require_once __DIR__ . '/config/db.php';
if (is_readable(__DIR__ . '/helpers/auth_helper.php')) @require_once __DIR__ . '/helpers/auth_helper.php';
if (is_readable(__DIR__ . '/helpers/CSRF.php')) @require_once __DIR__ . '/helpers/CSRF.php';
if (is_readable(__DIR__ . '/helpers/RBAC.php')) @require_once __DIR__ . '/helpers/RBAC.php';

/* ---------- Helpers ---------- */
function json_ok($data = [], $code = 200) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = array_merge(['success' => true], (is_array($data) ? $data : ['data' => $data]));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error($message = 'Error', $code = 400, $extra = []) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = array_merge(['success' => false, 'message' => $message], $extra);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}
function log_debug($msg) {
    $path = __DIR__ . '/error_debug.log';
    @file_put_contents($path, "[".date('c')."] " . trim($msg) . PHP_EOL, FILE_APPEND);
}

/* Portable bind helper */
function bind_params_stmt($stmt, $types, $params) {
    if (empty($types)) return;
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

/* Portable fetch helpers */
function stmt_fetch_one_assoc($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            if (method_exists($res, 'free')) $res->free();
            return $row ?: null;
        }
        return null;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return null;
    $fields = [];
    $row = [];
    while ($f = $meta->fetch_field()) {
        $row[$f->name] = null;
        $fields[] = &$row[$f->name];
    }
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
            if (method_exists($res, 'free')) $res->free();
        }
        return $out;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return $out;
    $fields = [];
    $row = [];
    while ($f = $meta->fetch_field()) {
        $row[$f->name] = null;
        $fields[] = &$row[$f->name];
    }
    $meta->free();
    call_user_func_array([$stmt, 'bind_result'], $fields);
    while ($stmt->fetch()) {
        $r = [];
        foreach ($row as $k => $v) $r[$k] = $v;
        $out[] = $r;
    }
    return $out;
}

/* Save uploaded file helper */
function save_uploaded_file_local($file, $companyId) {
    if (empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;
    $uploadsRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/..'), '/\\') . '/uploads/delivery_companies/' . $companyId;
    if (!is_dir($uploadsRoot)) @mkdir($uploadsRoot, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe = bin2hex(random_bytes(10)) . ($ext ? '.' . preg_replace('/[^a-z0-9]/i', '', $ext) : '');
    $dest = $uploadsRoot . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return '/uploads/delivery_companies/' . $companyId . '/' . $safe;
}

/* ---------- Session & auth ---------- */
if (session_status() === PHP_SESSION_NONE) @session_start();

function get_current_user_full() {
    if (function_exists('auth_check')) {
        try { $u = auth_check(); if (!empty($u) && is_array($u)) return $u; } catch (Throwable $e) { log_debug("auth_check failed: " . $e->getMessage()); }
    }
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : (isset($_SESSION['role']) ? (int)$_SESSION['role'] : null),
            'preferred_language' => $_SESSION['preferred_language'] ?? ($_SESSION['lang'] ?? null),
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    return null;
}
function is_admin_user_full($user) {
    if (function_exists('role_is_admin')) { try { return role_is_admin($user); } catch (Throwable $e) {} }
    if (is_array($user)) {
        if (isset($user['role_id'])) return ((int)$user['role_id'] === 1);
        if (isset($user['role'])) return ((int)$user['role'] === 1);
    }
    return false;
}

/* ---------- DB connection ---------- */
$db = null;
try { if (function_exists('container')) { $tmp = container('db'); if ($tmp instanceof mysqli) $db = $tmp; } } catch (Throwable $e) { log_debug("container('db') error: " . $e->getMessage()); }
if (!$db) {
    if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
    elseif (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) $db = $GLOBALS['db'];
}
if (!$db) { log_debug("No DB connection"); json_error('Database connection error', 500); }

/* Check translation tables */
$hasCountryTrans = false; $hasCityTrans = false;
try {
    $r = $db->query("SHOW TABLES LIKE 'country_translations'"); if ($r && $r->num_rows > 0) $hasCountryTrans = true;
    $r = $db->query("SHOW TABLES LIKE 'city_translations'"); if ($r && $r->num_rows > 0) $hasCityTrans = true;
} catch (Throwable $e) { /* ignore */ }

/* ---------- Router ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : null;

try {
    // requested language: GET lang -> session preferred_language -> user's preferred_language
    $requestedLang = null;
    if (!empty($_GET['lang'])) $requestedLang = trim($_GET['lang']);
    elseif (!empty($_SESSION['preferred_language'])) $requestedLang = $_SESSION['preferred_language'];

    /* ----- current_user ----- */
    if ($action === 'current_user') {
        $u = get_current_user_full();
        if (!$u) return json_error('Unauthorized', 401);
        $out = [
            'user' => [
                'id' => isset($u['id']) ? (int)$u['id'] : null,
                'username' => $u['username'] ?? null,
                'email' => $u['email'] ?? null,
                'role_id' => isset($u['role_id']) ? (int)$u['role_id'] : (isset($u['role']) ? (int)$u['role'] : null),
                'preferred_language' => $u['preferred_language'] ?? null
            ],
            'permissions' => $u['permissions'] ?? [],
            'session' => ['session_id' => session_id()]
        ];
        return json_ok($out);
    }

    /* ----- filter_countries ----- */
    if ($method === 'GET' && $action === 'filter_countries') {
        $rows = [];
        if ($requestedLang && $hasCountryTrans) {
            $sql = "
                SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name
                FROM delivery_companies dc
                JOIN countries c ON c.id = dc.country_id
                LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?
                WHERE dc.country_id IS NOT NULL
                ORDER BY name ASC
            ";
            $stmt = $db->prepare($sql);
            if ($stmt) { bind_params_stmt($stmt, 's', [$requestedLang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
        } else {
            $sql = "
                SELECT DISTINCT c.id, c.name
                FROM delivery_companies dc
                JOIN countries c ON c.id = dc.country_id
                WHERE dc.country_id IS NOT NULL
                ORDER BY c.name ASC
            ";
            $res = $db->query($sql);
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        return json_ok(['data' => $rows]);
    }

    /* ----- filter_cities ----- */
    if ($method === 'GET' && $action === 'filter_cities') {
        $country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
        $rows = [];
        if ($requestedLang && $hasCityTrans) {
            if ($country_id) {
                $sql = "
                    SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name
                    FROM delivery_companies dc
                    JOIN cities ci ON ci.id = dc.city_id
                    LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?
                    WHERE dc.city_id IS NOT NULL AND ci.country_id = ?
                    ORDER BY name ASC
                ";
                $stmt = $db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 'si', [$requestedLang, $country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            } else {
                $sql = "
                    SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name
                    FROM delivery_companies dc
                    JOIN cities ci ON ci.id = dc.city_id
                    LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?
                    WHERE dc.city_id IS NOT NULL
                    ORDER BY name ASC
                ";
                $stmt = $db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 's', [$requestedLang]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            }
        } else {
            if ($country_id) {
                $sql = "
                    SELECT DISTINCT ci.id, ci.name
                    FROM delivery_companies dc
                    JOIN cities ci ON ci.id = dc.city_id
                    WHERE dc.city_id IS NOT NULL AND ci.country_id = ?
                    ORDER BY ci.name ASC
                ";
                $stmt = $db->prepare($sql);
                if ($stmt) { bind_params_stmt($stmt, 'i', [$country_id]); $stmt->execute(); $rows = stmt_fetch_all_assoc($stmt); $stmt->close(); }
            } else {
                $sql = "
                    SELECT DISTINCT ci.id, ci.name
                    FROM delivery_companies dc
                    JOIN cities ci ON ci.id = dc.city_id
                    WHERE dc.city_id IS NOT NULL
                    ORDER BY ci.name ASC
                ";
                $res = $db->query($sql);
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
        }
        return json_ok(['data' => $rows]);
    }

    /* ----- parents ----- */
    if ($method === 'GET' && $action === 'parents') {
        $rows = [];
        $sql = "SELECT id, name FROM delivery_companies ORDER BY name ASC";
        $res = $db->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return json_ok(['data' => $rows]);
    }

    /* ----- GET single company ----- */
    if ($method === 'GET' && !empty($_GET['id'])) {
        $id = (int)$_GET['id'];

        $select = "dc.id, dc.parent_id, dc.user_id, dc.name, dc.slug, dc.logo_url, dc.phone, dc.email, dc.website_url, dc.api_url, dc.api_key, dc.tracking_url, dc.city_id, dc.country_id, dc.is_active, dc.rating_average, dc.rating_count, dc.sort_order, dc.created_at, dc.updated_at";
        $joins = "";
        $bindTypes = "";
        $bindParams = [];

        if ($requestedLang && $hasCountryTrans) {
            $select .= ", COALESCE(ct.name, c.name) AS country_name";
            $joins .= " LEFT JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $requestedLang;
        } else {
            $select .= ", c.name AS country_name";
            $joins .= " LEFT JOIN countries c ON c.id = dc.country_id";
        }

        if ($requestedLang && $hasCityTrans) {
            $select .= ", COALESCE(cit.name, ci.name) AS city_name";
            $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?";
            $bindTypes .= 's'; $bindParams[] = $requestedLang;
        } else {
            $select .= ", ci.name AS city_name";
            $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id";
        }

        $sql = "SELECT {$select} FROM delivery_companies dc {$joins} WHERE dc.id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) { log_debug("prepare failed (get): " . $db->error . " SQL: $sql"); return json_error('Server error', 500); }

        $bindTypesFinal = $bindTypes . 'i';
        $bindParamsFinal = $bindParams; $bindParamsFinal[] = $id;
        bind_params_stmt($stmt, $bindTypesFinal, $bindParamsFinal);
        $stmt->execute();
        $row = stmt_fetch_one_assoc($stmt);
        $stmt->close();
        if (!$row) return json_error('Not found', 404);

        // company translations
        $translations = [];
        $tcheck = $db->query("SHOW TABLES LIKE 'delivery_company_translations'");
        if ($tcheck && $tcheck->num_rows > 0) {
            $tstmt = $db->prepare("SELECT language_code, description, terms, meta_title, meta_description FROM delivery_company_translations WHERE company_id = ?");
            if ($tstmt) {
                bind_params_stmt($tstmt, 'i', [$id]);
                $tstmt->execute();
                $trs = stmt_fetch_all_assoc($tstmt);
                foreach ($trs as $tr) $translations[$tr['language_code']] = $tr;
                $tstmt->close();
            }
        }
        $row['translations'] = $translations;

        return json_ok(['data' => $row]);
    }

    /* ----- LIST companies (with filters) ----- */
    if ($method === 'GET') {
        $debugMode = !empty($_GET['debug']) && $_GET['debug'] == '1';

        try {
            // query params
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $phoneFilter = isset($_GET['phone']) ? trim($_GET['phone']) : '';
            $emailFilter = isset($_GET['email']) ? trim($_GET['email']) : '';
            $is_active = (isset($_GET['is_active']) && $_GET['is_active'] !== '') ? (int)$_GET['is_active'] : null;
            $country_id = (isset($_GET['country_id']) && $_GET['country_id'] !== '') ? (int)$_GET['country_id'] : null;
            $city_id = (isset($_GET['city_id']) && $_GET['city_id'] !== '') ? (int)$_GET['city_id'] : null;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per = min(200, max(5, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $per;

            $select = "dc.id, dc.parent_id, dc.user_id, dc.name, dc.slug, dc.logo_url, dc.phone, dc.email, dc.website_url, dc.api_url, dc.tracking_url, dc.city_id, dc.country_id, dc.is_active, dc.rating_average, dc.rating_count, dc.sort_order, dc.created_at, dc.updated_at";
            $joins = "";
            $bindTypes = "";
            $bindParams = [];

            if ($requestedLang && $hasCountryTrans) {
                $select .= ", COALESCE(ct.name, c.name) AS country_name";
                $joins .= " LEFT JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ?";
                $bindTypes .= 's'; $bindParams[] = $requestedLang;
            } else {
                $select .= ", c.name AS country_name";
                $joins .= " LEFT JOIN countries c ON c.id = dc.country_id";
            }

            if ($requestedLang && $hasCityTrans) {
                $select .= ", COALESCE(cit.name, ci.name) AS city_name";
                $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ?";
                $bindTypes .= 's'; $bindParams[] = $requestedLang;
            } else {
                $select .= ", ci.name AS city_name";
                $joins .= " LEFT JOIN cities ci ON ci.id = dc.city_id";
            }

            $sql = "SELECT SQL_CALC_FOUND_ROWS {$select} FROM delivery_companies dc {$joins}";

            $where = ['1=1'];
            $types = '';
            $params = [];

            if ($q !== '') {
                $where[] = "(dc.name LIKE ? OR dc.email LIKE ? OR dc.phone LIKE ? OR dc.slug LIKE ?)";
                $like = '%' . $q . '%';
                $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= 'ssss';
            }
            if ($phoneFilter !== '') { $where[] = "dc.phone LIKE ?"; $params[] = '%' . $phoneFilter . '%'; $types .= 's'; }
            if ($emailFilter !== '') { $where[] = "dc.email LIKE ?"; $params[] = '%' . $emailFilter . '%'; $types .= 's'; }
            if ($is_active !== null) { $where[] = "dc.is_active = ?"; $params[] = $is_active; $types .= 'i'; }
            if ($country_id !== null) { $where[] = "dc.country_id = ?"; $params[] = $country_id; $types .= 'i'; }
            if ($city_id !== null) { $where[] = "dc.city_id = ?"; $params[] = $city_id; $types .= 'i'; }

            $whereSql = implode(' AND ', $where);
            $sql .= " WHERE {$whereSql} ORDER BY dc.id DESC LIMIT ? OFFSET ?";

            // append pagination params
            $params[] = $per; $params[] = $offset; $types .= 'ii';

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $err = $db->error;
                log_debug("LIST prepare failed: {$err} SQL: {$sql}");
                if ($debugMode) return json_error('Prepare failed: ' . $err, 500, ['sql'=>$sql]);
                return json_error('Server error', 500);
            }

            // final bind types: language binds first then types
            $bindTypesFinal = $bindTypes . $types;
            $bindParamsFinal = array_merge($bindParams, $params);

            if ($bindTypesFinal !== '') {
                try {
                    bind_params_stmt($stmt, $bindTypesFinal, $bindParamsFinal);
                } catch (Throwable $e) {
                    log_debug("LIST bind params failed: " . $e->getMessage() . " SQL: {$sql} TYPES:{$bindTypesFinal} PARAMS:" . json_encode($bindParamsFinal));
                    if ($debugMode) return json_error('Bind params failed: ' . $e->getMessage(), 500, ['sql'=>$sql,'types'=>$bindTypesFinal,'params'=>$bindParamsFinal]);
                    return json_error('Server error', 500);
                }
            }

            if (!$stmt->execute()) {
                $serr = $stmt->error ?: $db->error;
                log_debug("LIST execute failed: {$serr} SQL: {$sql} PARAMS: " . json_encode($bindParamsFinal));
                if ($debugMode) return json_error('Execute failed: ' . $serr, 500, ['sql'=>$sql,'params'=>$bindParamsFinal]);
                return json_error('Server error', 500);
            }

            $rows = stmt_fetch_all_assoc($stmt);
            $stmt->close();

            $totalRes = $db->query("SELECT FOUND_ROWS() as total");
            $total = ($totalRes) ? (int)$totalRes->fetch_assoc()['total'] : count($rows);

            return json_ok(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
        } catch (Throwable $e) {
            log_debug("LIST exception: " . $e->getMessage() . " SQL: " . ($sql ?? ''));
            if (!empty($debugMode)) return json_error('Exception: ' . $e->getMessage(), 500, ['trace'=>$e->getTraceAsString()]);
            return json_error('Server error', 500);
        }
    }

    /* ---------- POST actions (require auth) ---------- */
    $currentUser = get_current_user_full();
    if (!$currentUser) return json_error('Unauthorized', 401);

    /* ----- create_company ----- */
    if ($method === 'POST' && $action === 'create_company') {
        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);

        $allowed = ['parent_id','user_id','name','slug','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','sort_order','rating_average'];
        $data = [];
        foreach ($allowed as $f) if (isset($_POST[$f]) && $_POST[$f] !== '') $data[$f] = $_POST[$f];

        // owner handling
        if (isset($data['user_id']) && is_admin_user_full($currentUser)) {
            $user_id = (int)$data['user_id'];
        } else {
            $user_id = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
        }
        if (!array_key_exists('user_id', $data)) $data['user_id'] = $user_id;

        // normalize numeric fields
        if (isset($data['parent_id'])) $data['parent_id'] = (int)$data['parent_id'];
        if (isset($data['city_id'])) $data['city_id'] = (int)$data['city_id'];
        if (isset($data['country_id'])) $data['country_id'] = (int)$data['country_id'];
        if (isset($data['is_active'])) $data['is_active'] = (int)$data['is_active'];
        if (isset($data['sort_order'])) $data['sort_order'] = (int)$data['sort_order'];
        if (isset($data['rating_average'])) $data['rating_average'] = (float)str_replace(',', '.', $data['rating_average']);

        // Validate parent_id
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] <= 0) {
                unset($data['parent_id']);
            } else {
                $pv = $db->prepare("SELECT id FROM delivery_companies WHERE id = ? LIMIT 1");
                if (!$pv) { log_debug("parent check prepare failed: " . $db->error); return json_error('Server error', 500); }
                bind_params_stmt($pv, 'i', [$data['parent_id']]);
                $pv->execute();
                $prow = stmt_fetch_one_assoc($pv);
                $pv->close();
                if (!$prow) return json_error('Invalid parent_id: referenced company not found', 400);
            }
        }

        $cols = array_keys($data);
        if (empty($cols)) return json_error('No data', 400);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colsSql = implode(',', $cols);
        $sql = "INSERT INTO delivery_companies ({$colsSql}) VALUES ({$placeholders})";

        $typesMap = [
            'parent_id'=>'i','user_id'=>'i','name'=>'s','slug'=>'s','phone'=>'s','email'=>'s',
            'website_url'=>'s','api_url'=>'s','api_key'=>'s','tracking_url'=>'s','city_id'=>'i','country_id'=>'i',
            'is_active'=>'i','sort_order'=>'i','rating_average'=>'d'
        ];
        $types = ''; $params = [];
        foreach ($cols as $c) {
            $types .= $typesMap[$c] ?? 's';
            $params[] = $data[$c];
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) { log_debug("INSERT prepare failed: " . $db->error . " SQL: $sql"); return json_error('Create failed', 500); }
        if (!empty($types)) bind_params_stmt($stmt, $types, $params);
        $ok = $stmt->execute();
        if (!$ok) {
            $err = $stmt->error; $stmt->close();
            log_debug("INSERT ERROR: $err SQL: $sql DATA: " . json_encode($data));
            return json_error('Create failed', 500);
        }
        $newId = (int)$stmt->insert_id; $stmt->close();

        // logo upload
        if (!empty($_FILES['logo'])) {
            $url = save_uploaded_file_local($_FILES['logo'], $newId);
            if ($url) {
                $ust = $db->prepare("UPDATE delivery_companies SET logo_url = ? WHERE id = ? LIMIT 1");
                if ($ust) { bind_params_stmt($ust, 'si', [$url, $newId]); $ust->execute(); $ust->close(); }
            }
        }

        // translations
        if (!empty($_POST['translations'])) {
            $trs = json_decode($_POST['translations'], true);
            if (is_array($trs)) {
                $ins = $db->prepare("INSERT INTO delivery_company_translations (company_id, language_code, description, terms, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
                if ($ins) {
                    foreach ($trs as $lang => $d) {
                        $desc = $d['description'] ?? null;
                        $terms = $d['terms'] ?? null;
                        $mt = $d['meta_title'] ?? null;
                        $md = $d['meta_description'] ?? null;
                        bind_params_stmt($ins, 'isssss', [$newId, $lang, $desc, $terms, $mt, $md]);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
        }

        // audit log if exists
        $t = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($t && $t->num_rows > 0) {
            $ast = $db->prepare("INSERT INTO audit_logs (entity_type, entity_id, user_id, action, payload) VALUES ('delivery_company', ?, ?, 'create', ?)");
            if ($ast) {
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
                $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
                bind_params_stmt($ast, 'iis', [$newId, $uid, $payload]);
                $ast->execute(); $ast->close();
            }
        }

        return json_ok(['id' => $newId, 'message' => 'Created']);
    }

    /* ----- update_company ----- */
    if ($method === 'POST' && $action === 'update_company') {
        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) return json_error('Invalid id', 400);

        $stmt = $db->prepare("SELECT * FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$stmt) return json_error('Server error', 500);
        bind_params_stmt($stmt, 'i', [$id]);
        $stmt->execute();
        $existing = stmt_fetch_one_assoc($stmt);
        $stmt->close();
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

        // normalize numeric
        if (isset($data['parent_id'])) $data['parent_id'] = (int)$data['parent_id'];
        if (isset($data['city_id'])) $data['city_id'] = (int)$data['city_id'];
        if (isset($data['country_id'])) $data['country_id'] = (int)$data['country_id'];
        if (isset($data['is_active'])) $data['is_active'] = (int)$data['is_active'];
        if (isset($data['sort_order'])) $data['sort_order'] = (int)$data['sort_order'];
        if (isset($data['rating_average'])) $data['rating_average'] = (float)str_replace(',', '.', $data['rating_average']);
        if (isset($data['rating_count'])) $data['rating_count'] = (int)$data['rating_count'];

        // Validate parent_id: prevent self-parent, validate existence, remove zero/empty
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] === $id) return json_error('Invalid parent_id: company cannot be parent of itself', 400);
            if ($data['parent_id'] <= 0) {
                unset($data['parent_id']);
            } else {
                $pv = $db->prepare("SELECT id FROM delivery_companies WHERE id = ? LIMIT 1");
                if (!$pv) { log_debug("parent check prepare failed: " . $db->error); return json_error('Server error', 500); }
                bind_params_stmt($pv, 'i', [$data['parent_id']]);
                $pv->execute();
                $prow = stmt_fetch_one_assoc($pv);
                $pv->close();
                if (!$prow) return json_error('Invalid parent_id: referenced company not found', 400);
            }
        }

        // Logo upload handling
        if (!empty($_FILES['logo'])) {
            $url = save_uploaded_file_local($_FILES['logo'], $id);
            if ($url) $data['logo_url'] = $url;
        }

        if (!empty($data)) {
            $sets = []; $types = ''; $params = [];
            foreach ($data as $k => $v) {
                $sets[] = "`{$k}` = ?";
                if (in_array($k, ['parent_id','user_id','city_id','country_id','is_active','sort_order','rating_count'])) { $types .= 'i'; $params[] = (int)$v; }
                elseif ($k === 'rating_average') { $types .= 'd'; $params[] = (float)$v; }
                else { $types .= 's'; $params[] = (string)$v; }
            }
            $params[] = $id; $types .= 'i';
            $sql = "UPDATE delivery_companies SET " . implode(',', $sets) . " WHERE id = ? LIMIT 1";
            $ust = $db->prepare($sql);
            if (!$ust) { log_debug("UPDATE prepare failed: " . $db->error . " SQL: $sql"); return json_error('Update failed', 500); }
            bind_params_stmt($ust, $types, $params);
            $ok = $ust->execute();
            if (!$ok) {
                log_debug("UPDATE ERROR: " . $ust->error . " SQL: $sql");
                $ust->close();
                return json_error('Update failed', 500);
            }
            $ust->close();
        }

        // translations
        if (isset($_POST['translations'])) {
            $trs = json_decode($_POST['translations'], true);
            if (is_array($trs)) {
                $del = $db->prepare("DELETE FROM delivery_company_translations WHERE company_id = ?");
                if ($del) { bind_params_stmt($del, 'i', [$id]); $del->execute(); $del->close(); }
                $ins = $db->prepare("INSERT INTO delivery_company_translations (company_id, language_code, description, terms, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
                if ($ins) {
                    foreach ($trs as $lang => $d) {
                        $desc = $d['description'] ?? null;
                        $terms = $d['terms'] ?? null;
                        $mt = $d['meta_title'] ?? null;
                        $md = $d['meta_description'] ?? null;
                        bind_params_stmt($ins, 'isssss', [$id, $lang, $desc, $terms, $mt, $md]);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
        }

        // audit
        $t = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($t && $t->num_rows > 0) {
            $ast = $db->prepare("INSERT INTO audit_logs (entity_type, entity_id, user_id, action, payload) VALUES ('delivery_company', ?, ?, 'update', ?)");
            if ($ast) {
                $payload = json_encode(['changes' => $data], JSON_UNESCAPED_UNICODE);
                $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
                bind_params_stmt($ast, 'iis', [$id, $uid, $payload]);
                $ast->execute(); $ast->close();
            }
        }

        return json_ok(['message' => 'Updated']);
    }

    /* ----- delete_company ----- */
    if ($method === 'POST' && $action === 'delete_company') {
        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) return json_error('Invalid id', 400);

        $stmt = $db->prepare("SELECT user_id FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$stmt) return json_error('Server error', 500);
        bind_params_stmt($stmt, 'i', [$id]);
        $stmt->execute();
        $row = stmt_fetch_one_assoc($stmt);
        $stmt->close();
        if (!$row) return json_error('Not found', 404);

        $ownerId = (int)$row['user_id'];
        $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
        if (!is_admin_user_full($currentUser) && $ownerId !== $uid) return json_error('Forbidden', 403);

        $d = $db->prepare("DELETE FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$d) return json_error('Server error', 500);
        bind_params_stmt($d, 'i', [$id]);
        $ok = $d->execute();
        $d->close();

        $t = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($t && $t->num_rows > 0) {
            $ast = $db->prepare("INSERT INTO audit_logs (entity_type, entity_id, user_id, action, payload) VALUES ('delivery_company', ?, ?, 'delete', ?)");
            if ($ast) {
                $payload = json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
                bind_params_stmt($ast, 'iis', [$id, $uid, $payload]);
                $ast->execute(); $ast->close();
            }
        }

        return json_ok(['deleted' => (bool)$ok]);
    }

    /* ----- create_company_token ----- */
    if ($method === 'POST' && $action === 'create_company_token') {
        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) return json_error('Invalid CSRF', 403);
        $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        if (!$companyId) return json_error('Invalid company id', 400);

        $stmt = $db->prepare("SELECT user_id FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$stmt) return json_error('Server error', 500);
        bind_params_stmt($stmt, 'i', [$companyId]);
        $stmt->execute();
        $row = stmt_fetch_one_assoc($stmt);
        $stmt->close();
        if (!$row) return json_error('Not found', 404);

        $ownerId = (int)$row['user_id'];
        $uid = isset($currentUser['id']) ? (int)$currentUser['id'] : 0;
        if (!is_admin_user_full($currentUser) && $ownerId !== $uid) return json_error('Forbidden', 403);

        $name = substr(trim((string)($_POST['name'] ?? 'token')), 0, 100);
        $scopes = substr(trim((string)($_POST['scopes'] ?? '')), 0, 255);
        $expires_in = isset($_POST['expires_in']) ? (int)$_POST['expires_in'] : 0;
        $expires_at = $expires_in > 0 ? date('Y-m-d H:i:s', time() + $expires_in) : null;
        $token = bin2hex(random_bytes(32));
        $ins = $db->prepare("INSERT INTO delivery_company_tokens (company_id, token, name, scopes, expires_at) VALUES (?, ?, ?, ?, ?)");
        if (!$ins) return json_error('Server error', 500);
        bind_params_stmt($ins, 'issss', [$companyId, $token, $name, $scopes, $expires_at]);
        $ok = $ins->execute(); $ins->close();
        if (!$ok) { log_debug("TOKEN INSERT ERROR: " . $db->error); return json_error('Token creation failed', 500); }

        $t = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($t && $t->num_rows > 0) {
            $ast = $db->prepare("INSERT INTO audit_logs (entity_type, entity_id, user_id, action, payload) VALUES ('delivery_company', ?, ?, 'create_token', ?)");
            if ($ast) {
                $payload = json_encode(['token_name' => $name, 'scopes' => $scopes], JSON_UNESCAPED_UNICODE);
                bind_params_stmt($ast, 'iis', [$companyId, $uid, $payload]);
                $ast->execute(); $ast->close();
            }
        }

        return json_ok(['token' => $token]);
    }

    return json_error('Invalid action', 400);

} catch (Throwable $e) {
    log_debug("Router exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    log_debug($e->getTraceAsString());
    json_error('Server error', 500);
}