<?php
// htdocs/api/users/users.php
// JSON-first API for users management
// Behavior:
// - Will NOT modify users.is_active on regular save unless the client explicitly sends is_active
// - Uses NULLIF(...,0) for optional FK ints to avoid FK violations when client sends 0
// - Robust error handling and JSON responses
// Save as UTF-8 without BOM.

if (session_status() === PHP_SESSION_NONE) session_start();

// bootstrap (try several reasonable paths for admin/_auth.php, suppress warnings)
$authCandidates = [
    __DIR__ . '/../../admin/_auth.php',
    __DIR__ . '/../admin/_auth.php',
    __DIR__ . '/../../../admin/_auth.php',
];

foreach ($authCandidates as $c) {
    if (@is_readable($c)) {
        require_once $c;
        break;
    }
}

// DB bootstrap
if (empty($conn)) {
    $dbFile = __DIR__ . '/../config/db.php';
    if (@is_readable($dbFile)) require_once $dbFile;
    if (function_exists('connectDB')) $conn = $conn ?? connectDB();
}
if (empty($conn)) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB missing']); exit; }

// permission helper
function require_manage_users() {
    global $rbac;
    if (!empty($_SESSION['user_id'])) {
        if ($rbac && method_exists($rbac,'requirePermission')) {
            try { $rbac->requirePermission(['manage_users']); return true; }
            catch (Throwable $e) { return false; }
        } else return true;
    }
    return false;
}

function wants_json() {
    $h = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($h,'application/json')!==false) return true;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (isset($_GET['format']) && $_GET['format']==='json') return true;
    return false;
}

function json_input() {
    $txt = file_get_contents('php://input');
    if (!$txt) return null;
    $d = json_decode($txt,true);
    return $d===null?null:$d;
}

function respond($payload,$code=200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ensure permission
if (!require_manage_users()) {
    if (wants_json()) respond(['success'=>false,'message'=>'Unauthorized'],401);
    http_response_code(403); echo "Forbidden"; exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

// helper to bind params dynamically (mysqli requires references)
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Optional debug logging (disable by default)
$DEBUG_LOG = '/tmp/users_api_debug.log';
$ENABLE_DEBUG_LOG = false;
if ($ENABLE_DEBUG_LOG && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @file_put_contents($DEBUG_LOG, "---- " . date('c') . " ----\nREQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\nphp://input:\n" . @file_get_contents('php://input') . "\n\$_POST:\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);
}

// handle requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $stmt = $conn->prepare("SELECT id, username, email, preferred_language, country_id, city_id, phone, role_id, timezone, is_active, created_at, updated_at FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) respond(['success'=>true,'data'=>$row]);
        respond(['success'=>false,'message'=>'Not found'],404);
    } else {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $limit = max(1,min(1000,$limit));
        $offset = max(0,$offset);
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if ($q !== '') {
            $like = '%' . $conn->real_escape_string($q) . '%';
            $stmt = $conn->prepare("SELECT id, username, email, preferred_language, role_id, is_active, created_at FROM users WHERE username LIKE ? OR email LIKE ? LIMIT ? OFFSET ?");
            $stmt->bind_param('ssii',$like,$like,$limit,$offset);
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, preferred_language, role_id, is_active, created_at FROM users ORDER BY id ASC LIMIT ? OFFSET ?");
            $stmt->bind_param('ii',$limit,$offset);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        respond(['success'=>true,'count'=>count($rows),'data'=>$rows]);
    }
}

if ($method === 'POST') {
    $input = json_input();
    if ($input === null) $input = $_POST;
    $action = isset($input['action']) ? strtolower(trim($input['action'])) : '';
    if (!$action) return respond(['success'=>false,'message'=>'Missing action'],400);

    // CSRF
    $token = $input['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($CSRF, (string)$token)) {
        return respond(['success'=>false,'message'=>'Invalid CSRF'],403);
    }

    $getIntOrZero = function($k) use ($input) {
        if (!isset($input[$k]) || $input[$k] === '' || $input[$k] === null) return 0;
        return (int)$input[$k];
    };

    if ($action === 'save') {
        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        $username = trim((string)($input['username'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $preferred_language = trim((string)($input['preferred_language'] ?? ''));
        $role_id = $getIntOrZero('role_id');
        $country_id = $getIntOrZero('country_id');
        $city_id = $getIntOrZero('city_id');
        $phone = trim((string)($input['phone'] ?? ''));
        $timezone = trim((string)($input['timezone'] ?? ''));
        // note: do NOT default is_active here; we'll only update it if provided
        $has_is_active = array_key_exists('is_active', $input);
        $is_active = $has_is_active ? (int)$input['is_active'] : null;

        if ($username === '' || $email === '') return respond(['success'=>false,'message'=>'username and email required'],400);

        // uniqueness checks
        try {
            if ($id) {
                $chk = $conn->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id!=? LIMIT 1");
                $chk->bind_param('ssi',$username,$email,$id);
            } else {
                $chk = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
                $chk->bind_param('ss',$username,$email);
            }
            $chk->execute();
            $exRes = $chk->get_result();
            $ex = $exRes ? $exRes->fetch_assoc() : null;
            $chk->close();
            if ($ex) return respond(['success'=>false,'message'=>'username or email already exists'],409);
        } catch (Throwable $e) {
            return respond(['success'=>false,'message'=>'DB error (uniqueness check)','debug'=>$e->getMessage()],500);
        }

        try {
            if ($id) {
                // Build dynamic UPDATE statement — do not touch is_active unless explicitly provided
                $fields = [];
                $params = [];
                $types = '';

                // required fields to update
                $fields[] = 'username = ?';                     $types .= 's'; $params[] = $username;
                $fields[] = 'email = ?';                        $types .= 's'; $params[] = $email;
                $fields[] = 'preferred_language = ?';           $types .= 's'; $params[] = $preferred_language;
                $fields[] = 'country_id = NULLIF(?,0)';         $types .= 'i'; $params[] = $country_id;
                $fields[] = 'city_id = NULLIF(?,0)';            $types .= 'i'; $params[] = $city_id;
                $fields[] = 'phone = ?';                        $types .= 's'; $params[] = $phone;
                $fields[] = 'role_id = NULLIF(?,0)';            $types .= 'i'; $params[] = $role_id;
                $fields[] = 'timezone = ?';                     $types .= 's'; $params[] = $timezone;

                // only include is_active if provided by client
                if ($has_is_active) {
                    $fields[] = 'is_active = ?';
                    $types .= 'i';
                    $params[] = $is_active;
                }

                $fields[] = 'updated_at = NOW()';

                $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
                $types .= 'i';
                $params[] = $id;

                $stmt = $conn->prepare($sql);
                if (!$stmt) return respond(['success'=>false,'message'=>'DB prepare failed','debug'=>$conn->error],500);

                array_unshift($params, $types);
                $bind_result = @call_user_func_array([$stmt, 'bind_param'], refValues($params));
                if ($bind_result === false) {
                    return respond(['success'=>false,'message'=>'DB bind failed','debug'=>$stmt->error],500);
                }

                $ok = $stmt->execute();
                if ($ok) {
                    $s = $conn->prepare("SELECT id, username, email, preferred_language, country_id, city_id, phone, role_id, timezone, is_active, created_at, updated_at FROM users WHERE id=? LIMIT 1");
                    $s->bind_param('i',$id); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close();
                    $stmt->close();
                    return respond(['success'=>true,'message'=>'Updated','data'=>$row]);
                }
                $err = $stmt->error;
                $stmt->close();
                return respond(['success'=>false,'message'=>'DB error (update)','debug'=>$err],500);
            } else {
                // INSERT: include is_active if provided, otherwise use default defined in DB
                $sql = "INSERT INTO users (username, email, preferred_language, country_id, city_id, phone, role_id, timezone, is_active, created_at) VALUES (?, ?, ?, NULLIF(?,0), NULLIF(?,0), ?, NULLIF(?,0), ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                if (!$stmt) return respond(['success'=>false,'message'=>'DB prepare failed','debug'=>$conn->error],500);

                // If client did not provide is_active, default to 0/1 as per DB; we still bind a value.
                $bind_is_active = $has_is_active ? $is_active : 0;

                $types = 'sssiiisii';
                $params = [$types, $username, $email, $preferred_language, $country_id, $city_id, $phone, $role_id, $timezone, $bind_is_active];

                $bind_result = @call_user_func_array([$stmt, 'bind_param'], refValues($params));
                if ($bind_result === false) {
                    return respond(['success'=>false,'message'=>'DB bind failed','debug'=>$stmt->error],500);
                }

                $ok = $stmt->execute();
                $newId = $stmt->insert_id;
                $stmt->close();
                if ($ok) {
                    $s = $conn->prepare("SELECT id, username, email, preferred_language, country_id, city_id, phone, role_id, timezone, is_active, created_at FROM users WHERE id=? LIMIT 1");
                    $s->bind_param('i',$newId); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close();
                    return respond(['success'=>true,'message'=>'Created','data'=>$row]);
                }
                return respond(['success'=>false,'message'=>'DB error (insert)','debug'=>$conn->error],500);
            }
        } catch (mysqli_sql_exception $mse) {
            return respond(['success'=>false,'message'=>'DB exception','debug'=>$mse->getMessage()],500);
        } catch (Throwable $e) {
            return respond(['success'=>false,'message'=>'Unexpected error','debug'=>$e->getMessage()],500);
        }
    }

    if ($action === 'delete') {
        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) return respond(['success'=>false,'message'=>'Invalid id'],400);
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param('i',$id);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            return respond(['success'=>true,'message'=>'Deleted']);
        }
        return respond(['success'=>false,'message'=>'DB error: '.$conn->error],500);
    }

    if ($action === 'toggle_active') {
        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) return respond(['success'=>false,'message'=>'Invalid id'],400);
        $stmt = $conn->prepare("UPDATE users SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i',$id);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            $s = $conn->prepare("SELECT is_active FROM users WHERE id=? LIMIT 1");
            $s->bind_param('i',$id); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close();
            return respond(['success'=>true,'message'=>'Toggled','data'=>$row]);
        }
        return respond(['success'=>false,'message'=>'DB error: '.$conn->error],500);
    }

    if ($action === 'change_password') {
        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        $new = trim((string)($input['new_password'] ?? ''));
        if (!$id || $new === '') return respond(['success'=>false,'message'=>'Invalid input'],400);
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $hash, $id);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) return respond(['success'=>true,'message'=>'Password changed']);
        return respond(['success'=>false,'message'=>'DB error: '.$conn->error],500);
    }

    return respond(['success'=>false,'message'=>'Unknown action'],400);
}

respond(['success'=>false,'message'=>'Method not allowed'],405);