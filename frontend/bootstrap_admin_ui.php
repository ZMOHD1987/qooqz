<?php
/**
 * api/bootstrap_public_ui.php
 * Builds PUBLIC_UI payload for frontend & mobile
 * Safe for shared hosting + router-based access
 */

/* ===============================
 * 1. Logging
 * =============================== */
$API_LOG = __DIR__ . '/error_log.txt';

if (!file_exists($API_LOG)) {
    @touch($API_LOG);
}
@chmod($API_LOG, 0664);

function public_ui_log(string $msg): void {
    global $API_LOG;
    $line = '[' . date('c') . '] ' . $msg . PHP_EOL;
    @file_put_contents($API_LOG, $line, FILE_APPEND | LOCK_EX);
}

/* ===============================
 * 2. Session (safe)
 * =============================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===============================
 * 3. Database (best effort)
 * =============================== */
$db = null;

if (function_exists('connectDB')) {
    try {
        $conn = connectDB();
        if ($conn instanceof mysqli) {
            $db = $conn;
            @$db->set_charset('utf8mb4');
        }
    } catch (Throwable $e) {
        public_ui_log('connectDB error: ' . $e->getMessage());
    }
}

/* ===============================
 * 4. User resolution
 * =============================== */
$sessionUser = $_SESSION['user'] ?? null;

$user = [
    'id' => null,
    'username' => 'guest',
    'email' => null,
    'preferred_language' => 'en',
    'avatar' => '/assets/img/default-avatar.png',
    'is_active' => false,
];

if (is_array($sessionUser)) {
    $user['id'] = $sessionUser['id'] ?? null;
    $user['username'] = $sessionUser['username'] ?? 'guest';
    $user['email'] = $sessionUser['email'] ?? null;
    $user['avatar'] = $sessionUser['avatar'] ?? $user['avatar'];
    $user['preferred_language'] = $sessionUser['preferred_language'] ?? 'en';
    $user['is_active'] = !empty($sessionUser['is_active']);
}

/* ===============================
 * 5. Language & direction
 * =============================== */
$lang = strtolower($user['preferred_language'] ?? 'en');

$rtlLangs = ['ar','fa','he','ur','ps','sd','ku'];
$direction = in_array($lang, $rtlLangs, true) ? 'rtl' : 'ltr';

/* ===============================
 * 6. Translations
 * =============================== */
$strings = [];
$langFile = dirname(__DIR__) . "/languages/frontend/{$lang}.json";

if (is_readable($langFile)) {
    $json = @file_get_contents($langFile);
    $data = @json_decode($json, true);
    if (is_array($data) && isset($data['strings'])) {
        $strings = $data['strings'];
    }
}

/* ===============================
 * 7. Theme (default for now)
 * =============================== */
$theme = [
    'id' => null,
    'name' => 'Default',
    'slug' => 'default',
    'colors' => [],
    'fonts' => [],
    'buttons' => [],
    'cards' => [],
];

/* ===============================
 * 8. Final payload
 * =============================== */
$PUBLIC_UI = [
    'user' => $user,
    'lang' => $lang,
    'direction' => $direction,
    'strings' => $strings,
    'theme' => $theme,
];

$GLOBALS['PUBLIC_UI'] = $PUBLIC_UI;

/* ===============================
 * 9. Debug output
 * =============================== */
if (!empty($_GET['__public_ui_debug']) && $_GET['__public_ui_debug'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($PUBLIC_UI, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

return;
