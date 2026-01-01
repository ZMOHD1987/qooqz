<?php
// htdocs/api/users/logout.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$mysqli = connectDB();
if (!$mysqli || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB not available']);
    exit;
}

$cookieName = 'session_token';
$token = $_COOKIE[$cookieName] ?? null;
if ($token) {
    $tokenHash = hash('sha256', $token);
    $upd = $mysqli->prepare('UPDATE user_sessions SET revoked = 1 WHERE token = ?');
    if ($upd) {
        $upd->bind_param('s', $tokenHash);
        $upd->execute();
        $upd->close();
    }
}

// remove cookie on client
setcookie($cookieName, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

echo json_encode(['success'=>true]);
exit;
?>