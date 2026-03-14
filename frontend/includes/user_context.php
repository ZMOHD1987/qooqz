<?php
declare(strict_types=1);
/**
 * frontend/includes/user_context.php
 *
 * User context bootstrap for QOOQZ frontend pages.
 * - Loads user info from session
 * - Resolves language and direction
 * - Loads theme (optional)
 * - No API fetch for user data (local session only)
 */

if (!defined('FRONTEND_USER_CONTEXT')) {
    define('FRONTEND_USER_CONTEXT', true);
}

/* -------------------------------------------------------
 * 1. Base path & environment
 * ----------------------------------------------------- */
defined('FRONTEND_BASE') || define('FRONTEND_BASE', dirname(__DIR__));

$appConfigFile = FRONTEND_BASE . '/config/app.php';
$appConfig = is_readable($appConfigFile) ? (require $appConfigFile) : [];

/* -------------------------------------------------------
 * 2. Session (safe)
 * ----------------------------------------------------- */
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    if (session_name() === 'PHPSESSID' || session_name() === '') {
        session_name('APP_SESSID');
    }
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

/* -------------------------------------------------------
 * 3. Language resolution (from session or default)
 * ----------------------------------------------------- */
$lang = $_SESSION['pub_lang'] ?? $appConfig['default_lang'] ?? 'en';
$langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
if (!is_readable($langFile)) {
    $lang     = $appConfig['default_lang'] ?? 'en';
    $langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
}
$translations = is_readable($langFile) ? json_decode(file_get_contents($langFile), true) : [];
$dir = $translations['dir'] ?? (in_array($lang, ['ar','fa','ur','he']) ? 'rtl' : 'ltr');
$GLOBALS['PUB_STRINGS'] = $translations;

/* -------------------------------------------------------
 * 4. Theme (optional)
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    require_once FRONTEND_BASE . '/includes/public_context.php'; // reuse theme loader
}
$tenantId = (int)($_SESSION['pub_tenant_id'] ?? 1);
$theme = pub_load_theme($tenantId);

/* -------------------------------------------------------
 * 5. User data from session
 * ----------------------------------------------------- */
$user = $_SESSION['user'] ?? $_SESSION['current_user'] ?? null;

/* -------------------------------------------------------
 * 6. Compose the shared user context globals
 * ----------------------------------------------------- */
$GLOBALS['USER_CONTEXT'] = [
    'lang'      => $lang,
    'dir'       => $dir,
    'tenant_id' => $tenantId,
    'theme'     => $theme,
    'user'      => $user,
    'app'       => $appConfig,
];

/* -------------------------------------------------------
 * 7. Helper function: get current user safely
 * ----------------------------------------------------- */
if (!function_exists('current_user')) {
    function current_user(): ?array {
        return $GLOBALS['USER_CONTEXT']['user'] ?? null;
    }
}