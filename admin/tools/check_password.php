<?php
declare(strict_types=1);
// Temporary debug script — REMOVE after use
// Usage (CLI): php admin/tools/check_password.php identifier password
// Usage (browser): /admin/tools/check_password.php?id=identifier&pw=password

// Adjust these paths if your project layout differs
require_once __DIR__ . '/../../api/shared/config/config.php';
require_once __DIR__ . '/../../api/shared/config/db.php';
require_once __DIR__ . '/../../api/shared/core/DatabaseConnection.php';

if (php_sapi_name() === 'cli') {
    $identifier = $argv[1] ?? '';
    $password   = $argv[2] ?? '';
} else {
    $identifier = $_GET['id'] ?? $_POST['id'] ?? '';
    $password   = $_GET['pw'] ?? $_POST['pw'] ?? '';
}

if ($identifier === '' || $password === '') {
    echo "Usage: provide identifier and password. e.g. ?id=admin&pw=secret\n";
    exit;
}

try {
    $pdo = DatabaseConnection::getConnection();
} catch (Throwable $e) {
    echo "DB connect error: " . $e->getMessage() . PHP_EOL;
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email, phone, password_hash, is_active FROM users WHERE username = :i OR email = :i OR phone = :i LIMIT 1");
$stmt->bindValue(':i', $identifier, PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found for identifier '{$identifier}'\n";
    exit;
}

$hash = $user['password_hash'] ?? '';
echo "Found user id={$user['id']} username={$user['username']} email={$user['email']} phone={$user['phone']} is_active={$user['is_active']}\n";
echo "Hash preview: " . substr($hash, 0, 30) . (strlen($hash) > 30 ? "..." : "") . "\n";

$ok = password_verify($password, $hash) ? 'YES' : 'NO';
echo "password_verify result: {$ok}\n";

if ($ok === 'NO' && preg_match('/^[a-f0-9]{32}$/i', $hash)) {
    echo "Stored hash looks like MD5. md5(password) === stored ? " . (md5($password) === $hash ? 'YES' : 'NO') . "\n";
}