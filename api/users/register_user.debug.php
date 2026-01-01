<?php
// Temporary debug: run single user creation and return detailed DB errors.
// Use only for debugging then REMOVE.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$mysqli = connectDB();
if (!$mysqli) { echo json_encode(['success'=>false,'message'=>'DB connection error']); exit; }

$log = __DIR__ . '/../../logs/errors.log';
function dbg($m){ global $log; file_put_contents($log, '['.date('c').'] DEBUG: '.$m.PHP_EOL, FILE_APPEND); }

try {
    // read minimal fields
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $role_key = $_POST['role_key'] ?? 'customer';
    if (!$username || !$email || !$password) { echo json_encode(['success'=>false,'message'=>'username,email,password required']); exit; }

    // resolve role_id
    $role_id = null;
    $r = $mysqli->prepare('SELECT id FROM roles WHERE key_name=? LIMIT 1');
    if ($r) { $r->bind_param('s',$role_key); $r->execute(); $r->bind_result($rid); if ($r->fetch()) $role_id = (int)$rid; $r->close(); }
    if (!$role_id) { echo json_encode(['success'=>false,'message'=>'role not found','role_key'=>$role_key]); exit; }

    // build simple insert (no location formatting to avoid ST_GeomFromText issues)
    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username,email,password_hash,role_id,is_verified,is_active,created_at,updated_at) VALUES (?,?,?,?,0,1,NOW(),NOW())";
    dbg("Preparing SQL: $sql");
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $err = $mysqli->error;
        dbg("prepare failed: $err");
        echo json_encode(['success'=>false,'stage'=>'prepare','db_error'=>$err]); exit;
    }
    if (!$stmt->bind_param('sssi', $username, $email, $pw_hash, $role_id)) {
        $err = $stmt->error; dbg("bind_param failed: $err");
        echo json_encode(['success'=>false,'stage'=>'bind','stmt_error'=>$err]); exit;
    }
    if (!$stmt->execute()) {
        $err = $stmt->error; dbg("execute failed: $err");
        echo json_encode(['success'=>false,'stage'=>'execute','stmt_error'=>$err]); exit;
    }
    $insert_id = $mysqli->insert_id;
    dbg("insert success id=$insert_id");
    echo json_encode(['success'=>true,'message'=>'debug user created','user_id'=>$insert_id]);
    exit;

} catch (Exception $ex) {
    dbg('Exception: '.$ex->getMessage());
    echo json_encode(['success'=>false,'message'=>'Exception','exception'=>$ex->getMessage()]);
    exit;
}
?>