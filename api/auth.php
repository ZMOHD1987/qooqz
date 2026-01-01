<?php
// api/auth.php
// Return current session user (normalized).
// Works if session stores user under $_SESSION['user'] OR under flat keys like user_id, username, role_id.
// TEMP / safe endpoint used by admin fragment and client-side JS.

declare(strict_types=1);
$DEBUG_LOG = __DIR__ . '/error_debug.log';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

try {
    if (session_status() === PHP_SESSION_NONE) @session_start();

    // Debug log (silent)
    @file_put_contents($DEBUG_LOG, "[".date('c')."] auth.php hit - COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? '(none)') . " SID: " . session_id() . PHP_EOL, FILE_APPEND);

    // 1) Prefer nested user array if present
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        $u = $_SESSION['user'];
    } else {
        // 2) Try flat keys fallback (your session shows keys like user_id, username, role_id)
        $flatKeys = ['user_id','id','username','user_name','email','user_email','role_id','role','preferred_language','lang','csrf_token'];
        $hasFlat = false;
        $u = [];
        foreach ($flatKeys as $k) {
            if (isset($_SESSION[$k])) {
                $hasFlat = true;
                break;
            }
        }
        if ($hasFlat) {
            // Map commonly used flat keys into a user array
            $u = [
                'id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['id']) ? (int)$_SESSION['id'] : null),
                'username' => $_SESSION['username'] ?? ($_SESSION['user_name'] ?? null),
                'email' => $_SESSION['email'] ?? ($_SESSION['user_email'] ?? null),
                'role' => $_SESSION['role'] ?? (isset($_SESSION['role_id']) ? $_SESSION['role_id'] : null),
                'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : (isset($_SESSION['role']) ? (int)$_SESSION['role'] : null),
                'preferred_language' => $_SESSION['preferred_language'] ?? ($_SESSION['lang'] ?? null),
            ];
        } else {
            $u = null;
        }
    }

    if (empty($u) || !is_array($u) || (empty($u['id']) && empty($u['username']))) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Build normalized output
    $userOut = [
        'id' => isset($u['id']) ? (int)$u['id'] : null,
        'username' => $u['username'] ?? null,
        'email' => $u['email'] ?? null,
        'role' => $u['role'] ?? (isset($u['role_id']) ? $u['role_id'] : null),
        'role_id' => isset($u['role_id']) ? (int)$u['role_id'] : (isset($u['role']) ? (int)$u['role'] : null),
        'preferred_language' => $u['preferred_language'] ?? null,
    ];

    $permissions = $_SESSION['permissions'] ?? ($u['permissions'] ?? []);
    $sessionInfo = ['session_id' => session_id()];

    echo json_encode(['success' => true, 'user' => $userOut, 'permissions' => $permissions, 'session' => $sessionInfo], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    @file_put_contents($DEBUG_LOG, "[".date('c')."] auth.php exception: ".$e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}