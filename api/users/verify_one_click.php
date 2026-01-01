<?php
// Dev-only: one-click verification via GET (INSECURE — do NOT leave in production).
// Usage: https://your-host/api/users/verify_one_click.php?jti=...&code=...
header('Content-Type: application/json; charset=utf-8');
if (php_sapi_name() === 'cli') { echo "Run via HTTP\n"; exit; }
$jti = isset($_GET['jti']) ? trim($_GET['jti']) : null;
$code = isset($_GET['code']) ? trim($_GET['code']) : null;
if (!$jti || $code === null) {
    echo json_encode(['success'=>false,'message'=>'jti_and_code_required']); exit;
}
require_once __DIR__ . '/../../api/config/db.php';
$mysqli = @connectDB();
if (!$mysqli) { echo json_encode(['success'=>false,'message'=>'db_connect_failed']); exit; }

try {
    $st = $mysqli->prepare("SELECT id,user_id,token_hash,used,attempts,expires_at,expires_at_local FROM verification_tokens WHERE jti = ? LIMIT 1");
    if (!$st) { echo json_encode(['success'=>false,'message'=>'db_prepare_failed']); exit; }
    $st->bind_param('s',$jti);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'token_not_found']); exit; }
    if (!empty($row['used'])) { echo json_encode(['success'=>false,'message'=>'token_already_used']); exit; }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
        echo json_encode(['success'=>false,'message'=>'token_expired']); exit;
    }
    if (!empty($row['token_hash']) && password_verify($code, $row['token_hash'])) {
        $mysqli->begin_transaction();
        $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ? LIMIT 1");
        $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $upd->bind_param('ssi', $v_ip, $v_ua, $row['id']);
        $upd->execute(); $upd->close();
        $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1");
        $u->bind_param('i', $row['user_id']); $u->execute(); $u->close();
        $mysqli->commit();
        echo json_encode(['success'=>true,'message'=>'verified','user_id'=>(int)$row['user_id']]);
        exit;
    } else {
        // increment attempts
        $attempts = (int)$row['attempts'] + 1;
        $upd = $mysqli->prepare("UPDATE verification_tokens SET attempts = ? WHERE id = ? LIMIT 1");
        $upd->bind_param('ii', $attempts, $row['id']);
        $upd->execute(); $upd->close();
        echo json_encode(['success'=>false,'message'=>'invalid_code','attempts'=>$attempts]);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'exception','details'=>$e->getMessage()]);
    exit;
}
?>