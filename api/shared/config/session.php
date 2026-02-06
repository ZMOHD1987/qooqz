<?php
declare(strict_types=1);

/**
 * /api/shared/config/session.php
 * Unified & secure session kernel
 * Shared-hosting safe
 * Fixes empty session / cookie mismatch issues
 */

// منع التنفيذ من CLI
if (php_sapi_name() === 'cli') {
    return;
}

/**
 * Ensure session directory exists and is writable
 */
$sessionPath = __DIR__ . '/../../storage/sessions';

if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0700, true);
}

if (!is_writable($sessionPath)) {
    error_log('Session path is not writable: ' . $sessionPath);
}

// بدء الجلسة مرة واحدة فقط
if (session_status() === PHP_SESSION_NONE) {

    // ===== SESSION STORAGE =====
    ini_set('session.save_path', $sessionPath);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // ===== COOKIE SETTINGS (HTTPS ONLY) =====
    session_set_cookie_params([
        'lifetime' => 604800, // 7 days
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,   // ثابت – لا تعتمد على $_SERVER['HTTPS']
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // ===== SESSION NAME =====
    session_name('APP_SESSID');

    // ===== START SESSION =====
    session_start();

    // ===== BASIC HARDENING =====
    if (empty($_SESSION['__initiated'])) {
        session_regenerate_id(true);
        $_SESSION['__initiated'] = time();
    }

    // ===== DEBUG (REMOVE IN PROD IF NEEDED) =====
    error_log('[SESSION] started: ' . session_id());
}

/**
 * =========================
 * Helper functions
 * =========================
 */

/**
 * Regenerate session securely (on login)
 */
function regenerateSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['__regenerated_at'] = time();
    }
}

/**
 * Close session safely
 */
function secureSessionClose(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Check if session is valid (basic)
 */
function isSessionValid(): bool
{
    return !empty($_SESSION)
        && !empty($_SESSION['__initiated'])
        && !empty($_SESSION['user_id']);
}

/**
 * Destroy session completely (logout)
 */
function destroySession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
