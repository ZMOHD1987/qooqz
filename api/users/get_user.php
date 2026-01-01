<?php
// htdocs/api/users/get_user.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$mysqli = connectDB();
if (!$mysqli || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB not available']);
    exit;
}

// احصل على التوكن من Authorization أو param
$token = null;
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $m)) $token = $m[1];
if (!$token && isset($_GET['token'])) $token = $_GET['token'];
if (!$token && isset($_POST['token'])) $token = $_POST['token'];
if (!$token) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Token required']);
    exit;
}

try {
    $sql = 'SELECT s.user_id, u.username, u.email, u.preferred_language, u.country_id, u.city_id, u.is_verified, u.is_active, u.created_at FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ? AND s.revoked = 0 AND (s.expires_at IS NULL OR s.expires_at > NOW()) LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: '.$mysqli->error);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid or expired token']);
        exit;
    }
    $user_id = $row['user_id'];

    // المستندات
    $docStmt = $mysqli->prepare('SELECT id, filename, storage_key, content_type, file_size, status, uploaded_at FROM documents WHERE owner_type = "user" AND owner_id = ? ORDER BY uploaded_at DESC');
    $docStmt->bind_param('i', $user_id);
    $docStmt->execute();
    $docRes = $docStmt->get_result();
    $documents = $docRes->fetch_all(MYSQLI_ASSOC);
    $docStmt->close();

    // العناوين
    $addrStmt = $mysqli->prepare('SELECT id, label, full_name, phone, country_id, city_id, state, postal_code, street_address, is_default, created_at FROM addresses WHERE user_id = ?');
    $addrStmt->bind_param('i', $user_id);
    $addrStmt->execute();
    $addrRes = $addrStmt->get_result();
    $addresses = $addrRes->fetch_all(MYSQLI_ASSOC);
    $addrStmt->close();

    $user = [
        'id' => (int)$user_id,
        'username' => $row['username'],
        'email' => $row['email'],
        'preferred_language' => $row['preferred_language'],
        'country_id' => $row['country_id'],
        'city_id' => $row['city_id'],
        'is_verified' => (bool)$row['is_verified'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'],
    ];
    echo json_encode(['success'=>true,'user'=>$user,'documents'=>$documents,'addresses'=>$addresses]);
    exit;

} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/../../logs/errors.log', '['.date('c').'] get_user error: '.$e->getMessage().PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
    exit;
}
?>