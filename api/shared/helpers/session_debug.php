<?php
// api/helpers/session_debug.php  (TEMP - for debugging only)
if (session_status() === PHP_SESSION_NONE) {
    // try to pick existing cookie name if present
    $possible = ['admin_sid','PHPSESSID', session_name()];
    foreach ($possible as $n) { if ($n && isset($_COOKIE[$n])) { session_name($n); break; } }
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'cookies' => $_COOKIE,
    'session_name' => session_name(),
    'session_id' => session_id(),
    'session_user' => $_SESSION['user'] ?? null,
    'all_session' => $_SESSION
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);