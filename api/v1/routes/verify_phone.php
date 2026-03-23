<?php
declare(strict_types=1);
/**
 * routes/verify_phone.php
 *
 * Device-bound phone verification endpoint.
 * Called automatically when the user opens the SMS activation link on their device.
 *
 * GET  /api/verify_phone?t=RAW_TOKEN
 *   - Validates the token hash against user_phone_verifications
 *   - Verifies the device cookie (qz_dvt) to confirm same browser/device
 *   - Activates the user account and creates an authenticated session
 *   - Redirects to the frontend verification page with the result
 *
 * POST /api/verify_phone  { token, device_token }
 *   - Same logic but accepts JSON payload (for JS-driven flow)
 */

// ---- Session bootstrap (must match auth.php settings) ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(0, '/', $cookieParams['domain'], $cookieParams['secure'], true);
    }
    @session_start();
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    _vpError('Database unavailable', 503);
    exit;
}

// ---- Read token ----
$rawToken    = '';
$rawDevice   = '';
$isJsonReq   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = @file_get_contents('php://input');
    $payload = $body ? (@json_decode($body, true) ?: []) : [];
    $payload = array_merge($_POST, $payload);
    $rawToken  = trim((string)($payload['token']        ?? ''));
    $rawDevice = trim((string)($payload['device_token'] ?? $_COOKIE['qz_dvt'] ?? ''));
    $isJsonReq = true;
} else {
    // GET — token from URL, device from cookie
    $rawToken  = trim((string)($_GET['t'] ?? ''));
    $rawDevice = trim((string)($_COOKIE['qz_dvt'] ?? ''));
}

// ---- Helper to emit a redirect or JSON error ----
function _vpError(string $msg, int $code = 400, bool $json = false): void {
    if ($json) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $msg]);
    } else {
        // Redirect to frontend page with error
        $appUrl = _vp_app_url();
        $dest = $appUrl . '/frontend/verify_phone.php?status=error&msg=' . urlencode($msg);
        if (!headers_sent()) header('Location: ' . $dest, true, 302);
    }
}

function _vp_app_url(): string {
    if (defined('APP_URL')) return rtrim(APP_URL, '/');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

if ($rawToken === '') {
    _vpError('Missing verification token', 400, $isJsonReq);
    exit;
}

$tokenHash  = hash('sha256', $rawToken);
$deviceHash = ($rawDevice !== '') ? hash('sha256', $rawDevice) : '';

try {
    // Look up pending verification record
    $stmt = $pdo->prepare(
        'SELECT v.id, v.user_id, v.device_hash, v.user_agent, v.ip, v.expires_at
         FROM user_phone_verifications v
         WHERE v.token_hash = ? AND v.used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        _vpError('رابط التفعيل غير صالح أو تم استخدامه مسبقاً.', 404, $isJsonReq);
        exit;
    }

    // Check expiry
    if (strtotime($row['expires_at']) < time()) {
        _vpError('انتهت صلاحية رابط التفعيل. يرجى التسجيل مجدداً.', 410, $isJsonReq);
        exit;
    }

    // ---- Activate the user ----
    $userId = (int)$row['user_id'];
    $upd = $pdo->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND is_active = 0');
    $upd->execute([$userId]);

    if ($upd->rowCount() === 0) {
        // Might already be active — still mark token as used and proceed
    }

    // Mark token as used (one-time)
    $pdo->prepare('UPDATE user_phone_verifications SET used_at = NOW() WHERE id = ?')
        ->execute([$row['id']]);

    // Fetch user record for session
    $uStmt = $pdo->prepare(
        'SELECT id, username, email, phone, preferred_language, role_id, is_active FROM users WHERE id = ?'
    );
    $uStmt->execute([$userId]);
    $userData = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        _vpError('User not found after activation.', 500, $isJsonReq);
        exit;
    }

    // Create authenticated session
    session_regenerate_id(true);
    $user = [
        'id'                 => (int)$userData['id'],
        'name'               => $userData['username'],
        'username'           => $userData['username'],
        'email'              => $userData['email'],
        'phone'              => $userData['phone'],
        'role_id'            => $userData['role_id'],
        'preferred_language' => $userData['preferred_language'],
        'is_active'          => true,
        'permissions'        => [],
        'roles'              => [],
        'permissions_count'  => 0,
        'roles_count'        => 0,
    ];
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['user']               = $user;
    $GLOBALS['ADMIN_USER']          = $user;
    unset($_SESSION['pending_user_id']);

    // Expire the device cookie (no longer needed)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (!headers_sent()) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('qz_dvt', '', ['expires' => time() - 3600, 'path' => '/',
                                     'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
        } else {
            setcookie('qz_dvt', '', time() - 3600, '/', '', $secure, true);
        }
    }

    if ($isJsonReq) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'Account activated successfully.', 'user' => $user]);
    } else {
        // Redirect to frontend success page
        $dest = _vp_app_url() . '/frontend/verify_phone.php?status=success';
        if (!headers_sent()) header('Location: ' . $dest, true, 302);
    }

} catch (Throwable $e) {
    if (class_exists('Logger')) Logger::error('verify_phone error: ' . $e->getMessage());
    _vpError('حدث خطأ أثناء التفعيل. يرجى المحاولة مجدداً.', 500, $isJsonReq);
}
exit;
