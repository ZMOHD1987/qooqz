<?php
// htdocs/api/users/me.php
// Diagnostic / robust endpoint to return current user info + preferred language.
// Usage for debugging: /api/users/me.php?debug=1
// IMPORTANT: remove debug output or restrict access on production.

header('Content-Type: application/json; charset=utf-8');

// enable debug mode via query param
$DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';

// show errors in debug
if ($DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// load DB connector
$configPath = __DIR__ . '/../config/db.php';
if (!is_readable($configPath)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server misconfiguration: DB config not found', 'hint'=>$configPath]);
    exit;
}
require_once $configPath;

$mysqli = null;
try {
    $mysqli = function_exists('connectDB') ? connectDB() : null;
} catch (Throwable $e) {
    $mysqli = null;
}
if (!$mysqli || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database not available or connectDB() failed']);
    exit;
}

// session
if (session_status() === PHP_SESSION_NONE) session_start();

// get raw token from cookie
$rawToken = $_COOKIE['session_token'] ?? null;
if (!$rawToken) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Not authenticated - no session_token cookie']);
    exit;
}
$tokenHash = hash('sha256', $rawToken);

// helper: query list of tables
function existingTables(mysqli $m) {
    $tables = [];
    $res = $m->query("SHOW TABLES");
    if ($res) {
        while ($r = $res->fetch_row()) {
            $tables[] = $r[0];
        }
        $res->free();
    }
    return $tables;
}

// helper: check table exists
$allTables = existingTables($mysqli);

// candidate names (common variants)
$candidates = [
    'sessions' => ['user_sessions','sessions','auth_sessions','session_tokens','user_tokens'],
    'users' => ['users','user','app_users','accounts'],
    'permissions' => ['permissions','permission','auth_permissions'],
    'role_permissions' => ['role_permissions','roles_permissions','role_perm','role_permission'],
    'user_permissions' => ['user_permissions','user_perms','user_permission','user_permission_map'],
    'role_table' => ['roles','role','user_roles'],
];

// find first existing name from candidates
function find_table(array $candidates, array $allTables) {
    foreach ($candidates as $name) {
        if (in_array($name, $allTables, true)) return $name;
    }
    return null;
}

// flatten helper for each category
function pick_from_list(array $list, array $allTables) {
    foreach ($list as $t) {
        if (in_array($t, $allTables, true)) return $t;
    }
    return null;
}

$tbl_user_sessions = pick_from_list($candidates['sessions'], $allTables);
$tbl_users = pick_from_list($candidates['users'], $allTables);
$tbl_permissions = pick_from_list($candidates['permissions'], $allTables);
$tbl_role_permissions = pick_from_list($candidates['role_permissions'], $allTables);
$tbl_user_permissions = pick_from_list($candidates['user_permissions'], $allTables);
$tbl_roles = pick_from_list($candidates['role_table'], $allTables);

// if we couldn't find user_sessions, return diagnostic
if (!$tbl_user_sessions || !$tbl_users) {
    http_response_code(500);
    $resp = [
        'success'=>false,
        'message'=>'Required tables not found',
        'found_tables' => $allTables,
        'needed_examples' => [
            'user_sessions' => $candidates['sessions'],
            'users' => $candidates['users']
        ]
    ];
    if ($DEBUG) $resp['hint'] = 'Create/rename tables or edit me.php to match your schema.';
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

// Now validate that user_sessions has a token column (we try common column names)
$possible_token_cols = ['token','session_token','token_hash','hash','value'];
$session_token_col = null;
foreach ($possible_token_cols as $col) {
    $q = $mysqli->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if ($q) {
        $q->bind_param('ss', $tbl_user_sessions, $col);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->fetch_assoc()) {
            $session_token_col = $col;
            $q->close();
            break;
        }
        $q->close();
    }
}
if (!$session_token_col) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'No token column found in user_sessions table', 'table'=>$tbl_user_sessions, 'candidates'=>$possible_token_cols]);
    exit;
}

// verify we can query session row by hashed token
// prepare dynamic query
$query = "SELECT user_id FROM `{$tbl_user_sessions}` WHERE `{$session_token_col}` = ? AND (revoked = 0 OR revoked IS NULL) ";
$query .= "AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1";
$stmt = $mysqli->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Failed to prepare session query', 'query'=>$query, 'error'=>$mysqli->error]);
    exit;
}
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$res = $stmt->get_result();
$sessionRow = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$sessionRow || empty($sessionRow['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Invalid or expired session token']);
    exit;
}
$user_id = (int)$sessionRow['user_id'];

// find users table columns: try to get preferred_language column name if exists
$possible_pref_cols = ['preferred_language','language','locale','lang'];
$pref_col = null;
foreach ($possible_pref_cols as $c) {
    $q = $mysqli->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if ($q) {
        $q->bind_param('ss', $tbl_users, $c);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->fetch_assoc()) {
            $pref_col = $c;
            $q->close();
            break;
        }
        $q->close();
    }
}
// also check for role_id; column names could be role_id or role
$role_col = null;
foreach (['role_id','role'] as $c) {
    $q = $mysqli->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if ($q) {
        $q->bind_param('ss', $tbl_users, $c);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->fetch_assoc()) {
            $role_col = $c;
            $q->close();
            break;
        }
        $q->close();
    }
}

// fetch user row with preferred language if possible
$cols = ['id','username','email'];
if ($role_col) $cols[] = $role_col;
if ($pref_col) $cols[] = $pref_col;
$cols_sql = implode(', ', array_map(function($c){ return "`$c`"; }, $cols));

$stmt = $mysqli->prepare("SELECT {$cols_sql} FROM `{$tbl_users}` WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Failed to prepare user query', 'error'=>$mysqli->error, 'query'=>"SELECT {$cols_sql} FROM `{$tbl_users}` WHERE id = ?"]);
    exit;
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$user) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'User not found in users table', 'user_id'=>$user_id, 'table'=>$tbl_users]);
    exit;
}

// derive preferred language
$preferred_language = 'en';
if ($pref_col && !empty($user[$pref_col])) {
    $preferred_language = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string)$user[$pref_col]));
} elseif (!empty($_SESSION['preferred_language'])) {
    $preferred_language = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string)$_SESSION['preferred_language']));
}
if ($preferred_language === '') $preferred_language = 'en';
$_SESSION['preferred_language'] = $preferred_language;

// determine direction
$rtl_langs = ['ar','he','fa','ur'];
$direction = in_array($preferred_language, $rtl_langs, true) ? 'rtl' : 'ltr';

// collect permissions:
// strategy: prefer role_permissions join if role table + role_permissions exist,
// else try user_permissions table, else return empty list.
$permissions = [];

if ($role_col && $tbl_roles && $tbl_role_permissions && $tbl_permissions) {
    // we need to identify permission key column in permissions table (try common names)
    $perm_key_col = null;
    foreach (['key_name','name','perm_key','permission_key','code'] as $c) {
        $q = $mysqli->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        if ($q) {
            $q->bind_param('ss', $tbl_permissions, $c);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->fetch_assoc()) {
                $perm_key_col = $c;
                $q->close();
                break;
            }
            $q->close();
        }
    }
    // prepare query if we have permission key column
    if ($perm_key_col) {
        // determine role_id value for this user
        $role_id = isset($user[$role_col]) ? (int)$user[$role_col] : 0;
        if ($role_id) {
            $qsql = "
                SELECT p.`{$perm_key_col}` AS perm_key
                FROM `{$tbl_role_permissions}` rp
                JOIN `{$tbl_permissions}` p ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ";
            $s = $mysqli->prepare($qsql);
            if ($s) {
                $s->bind_param('i', $role_id);
                $s->execute();
                $r = $s->get_result();
                while ($row = $r->fetch_assoc()) {
                    if (!empty($row['perm_key'])) $permissions[] = $row['perm_key'];
                }
                $s->close();
            }
        }
    }
}

// fallback: direct user_permissions table
if (empty($permissions) && $tbl_user_permissions && $tbl_permissions) {
    // try to find permission key column
    $perm_key_col = null;
    foreach (['key_name','name','perm_key','permission_key','code'] as $c) {
        $q = $mysqli->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        if ($q) {
            $q->bind_param('ss', $tbl_permissions, $c);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->fetch_assoc()) {
                $perm_key_col = $c;
                $q->close();
                break;
            }
            $q->close();
        }
    }
    if ($perm_key_col) {
        $qsql = "
            SELECT p.`{$perm_key_col}` AS perm_key
            FROM `{$tbl_user_permissions}` up
            JOIN `{$tbl_permissions}` p ON p.id = up.permission_id
            WHERE up.user_id = ?
        ";
        $s = $mysqli->prepare($qsql);
        if ($s) {
            $s->bind_param('i', $user_id);
            $s->execute();
            $r = $s->get_result();
            while ($row = $r->fetch_assoc()) {
                if (!empty($row['perm_key'])) $permissions[] = $row['perm_key'];
            }
            $s->close();
        }
    }
}

// unique
$permissions = array_values(array_unique($permissions));

// prepare response user object
$responseUser = [
    'id' => (int)$user['id'],
    'username' => $user['username'] ?? null,
    'email' => $user['email'] ?? null,
    'role' => isset($user[$role_col]) ? $user[$role_col] : null,
    'preferred_language' => $preferred_language,
    'direction' => $direction
];

$session_info = [
    'session_id' => session_id(),
    'cookie_session_token' => $_COOKIE['session_token'] ?? null
];

echo json_encode([
    'success' => true,
    'user' => $responseUser,
    'permissions' => $permissions,
    'session' => $session_info
], JSON_UNESCAPED_UNICODE);
exit;
?>