<?php
// htdocs/api/users/regen_verification_token.php
// Dev-only helper to regenerate a numeric 6-digit code for a given verification_tokens.id
// Usage (CLI or browser): /api/users/regen_verification_token.php?token_id=2
// It will:
//  - generate a new 6-digit code
//  - update verification_tokens.token_hash with password_hash(new_code)
//  - log the generated code to logs/register_verification_error.log (temporary)
//  - output the new code (for debugging only)
// IMPORTANT: remove this file or disable logging after use.

require_once __DIR__ . '/../../api/config/db.php';
$mysqli = @connectDB();
if (!$mysqli) { echo "db_connect_failed\n"; exit(1); }

$token_id = isset($_GET['token_id']) ? (int)$_GET['token_id'] : 0;
if (!$token_id) { echo "Usage: ?token_id=ID\n"; exit(1); }

try {
    try { $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); } catch (Throwable $e) { $code = str_pad((string)mt_rand(0,999999),6,'0',STR_PAD_LEFT); }

    $token_hash = password_hash($code, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE verification_tokens SET token_hash = ?, expires_at = ?, expires_at_local = ?, used = 0, attempts = 0 WHERE id = ? LIMIT 1");
    $expires = gmdate('Y-m-d H:i:s', time() + 900);
    // compute local expiry string if needed (set to UTC string for simplicity)
    $expires_local = $expires;
    $stmt->bind_param('sssi', $token_hash, $expires, $expires_local, $token_id);
    if (!$stmt->execute()) {
        echo "update_failed errno={$stmt->errno} error={$stmt->error}\n";
        exit(1);
    }
    $stmt->close();

    // log the generated code (temporary; remove in production)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/register_verification_error.log', date('c') . " regen_code token_id={$token_id} code={$code}\n", FILE_APPEND | LOCK_EX);

    echo "regen_ok token_id={$token_id} code={$code}\n";
    echo "Note: code is logged in htdocs/api/logs/register_verification_error.log\n";
} catch (Throwable $e) {
    echo "exception: " . $e->getMessage() . "\n";
}
?>