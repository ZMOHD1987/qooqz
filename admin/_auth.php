<?php
// htdocs/admin/_auth.php
// Include this at the top of admin pages.
// Responsibilities:
// - session configuration (secure cookie settings)
// - DB connection
// - RBAC instance
// - i18n and CSRF initialization (optional)

if (session_status() === PHP_SESSION_NONE) {
    // session_config.php should call session_set_cookie_params(...) and session_start()
    $sessionConfig = __DIR__ . '/../api/session_config.php';
    if (file_exists($sessionConfig)) {
        require_once $sessionConfig;
    } else {
        // Fallback: minimal safe session start for dev
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '', // host-only in dev
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Load config and DB
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/db.php';

// Connect DB
if (function_exists('connectDB')) {
    $conn = connectDB();
} else {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        http_response_code(500);
        die('Database connection not configured.');
    }
}

// Load helpers
$rbacFile = __DIR__ . '/../api/helpers/RBAC.php';
$csrfFile = __DIR__ . '/../api/helpers/CSRF.php';
$i18nFile = __DIR__ . '/../api/helpers/i18n.php';

if (file_exists($rbacFile)) require_once $rbacFile;
if (file_exists($csrfFile)) require_once $csrfFile;
if (file_exists($i18nFile)) require_once $i18nFile;

// Init i18n (if available)
$lang = $_SESSION['preferred_language'] ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'ar');
if (class_exists('I18n')) {
    $i18n = new I18n($lang);
} else {
    // simple fallback translator
    $i18n = new class {
        public function t($k, $d='') { return $d ?: $k; }
    };
}

// Check logged-in user
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die($i18n->t('messages.forbidden', 'غير مصرح: يرجى تسجيل الدخول.'));
}

// Init RBAC
if (class_exists('RBAC')) {
    $rbac = new RBAC($conn, (int)$_SESSION['user_id']);
} else {
    // minimal fallback
    $rbac = new class {
        public function hasRole($r){ return false; }
        public function hasPermission($p){ return false; }
        public function requireRole($a){ http_response_code(403); die('Not allowed'); }
        public function requirePermission($a){ http_response_code(403); die('Not allowed'); }
    };
}

// Ensure CSRF token exists
if (class_exists('CSRF')) {
    CSRF::token();
}