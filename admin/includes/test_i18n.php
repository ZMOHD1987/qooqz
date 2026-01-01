<?php
// htdocs/admin/test_i18n.php
// Diagnostic page for i18n loading issues.
// Upload this file to htdocs/admin/test_i18n.php and open in browser: /admin/test_i18n.php?lang=en

// Temporary: show errors for debugging — احذر تعطيل هذا في الإنتاج
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>I18n Diagnostic</h2>";

// Print basic environment info
echo "<h3>Environment</h3><pre>";
echo "PHP SAPI: " . PHP_SAPI . PHP_EOL;
echo "PHP version: " . PHP_VERSION . PHP_EOL;
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . PHP_EOL;
echo "Script filename: " . (__FILE__) . PHP_EOL;
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL;
echo "Current working dir: " . getcwd() . PHP_EOL;
echo "</pre>";

// Check file existence for locales folder and example files
$localeDir = __DIR__ . '/assets/locales';
$enFile = $localeDir . '/en.json';
$arFile = $localeDir . '/ar.json';

echo "<h3>Files & Permissions</h3><pre>";
echo "Locale dir path: " . $localeDir . PHP_EOL;
echo "Locale dir exists? " . (is_dir($localeDir) ? 'YES' : 'NO') . PHP_EOL;
if (is_dir($localeDir)) {
    echo "Locale dir perms: " . substr(sprintf('%o', fileperms($localeDir)), -4) . PHP_EOL;
} else {
    echo "Check that folder htdocs/admin/assets/locales exists and contains en.json/ar.json\n";
}
echo "en.json exists? " . (is_file($enFile) ? 'YES' : 'NO') . PHP_EOL;
if (is_file($enFile)) {
    echo "en.json readable? " . (is_readable($enFile) ? 'YES' : 'NO') . PHP_EOL;
    echo "en.json perms: " . substr(sprintf('%o', fileperms($enFile)), -4) . PHP_EOL;
    echo "en.json size: " . filesize($enFile) . " bytes" . PHP_EOL;
}
echo "ar.json exists? " . (is_file($arFile) ? 'YES' : 'NO') . PHP_EOL;
if (is_file($arFile)) {
    echo "ar.json readable? " . (is_readable($arFile) ? 'YES' : 'NO') . PHP_EOL;
    echo "ar.json perms: " . substr(sprintf('%o', fileperms($arFile)), -4) . PHP_EOL;
    echo "ar.json size: " . filesize($arFile) . " bytes" . PHP_EOL;
}
echo "</pre>";

// Print open_basedir if set
echo "<h3>PHP Configuration</h3><pre>";
$ob = ini_get('open_basedir');
echo "open_basedir: " . ($ob ?: '(not set)') . PHP_EOL;
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . PHP_EOL;
echo "display_errors: " . ini_get('display_errors') . PHP_EOL;
echo "</pre>";

// Try to read en.json and json_decode
echo "<h3>Read JSON Test</h3><pre>";
if (is_file($enFile) && is_readable($enFile)) {
    $txt = @file_get_contents($enFile);
    if ($txt === false) {
        echo "Cannot file_get_contents(en.json)\n";
    } else {
        echo "First 300 chars of en.json:\n";
        echo htmlspecialchars(substr($txt,0,300)) . "\n\n";
        $decoded = json_decode($txt, true);
        $err = json_last_error();
        echo "json_decode result is array? " . (is_array($decoded) ? 'YES' : 'NO') . "\n";
        echo "json_last_error: " . ($err === JSON_ERROR_NONE ? 'NONE' : json_last_error_msg()) . "\n";
        if (is_array($decoded)) {
            echo "Top-level keys: " . implode(', ', array_keys($decoded)) . "\n";
        }
    }
} else {
    echo "en.json not accessible for reading\n";
}
echo "</pre>";

// Session and i18n instantiation test (try to include your i18n helper if present)
echo "<h3>Session & I18n instantiation</h3><pre>";
session_start();
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE') . PHP_EOL;
if (isset($_GET['lang'])) {
    $_SESSION['preferred_language'] = $_GET['lang'];
    echo "Set \$_SESSION['preferred_language'] = " . htmlspecialchars($_GET['lang']) . PHP_EOL;
} else {
    echo "No lang param provided. Use ?lang=en or ?lang=ar to test." . PHP_EOL;
}
$helperPath = __DIR__ . '/../api/helpers/i18n.php';
echo "Expected i18n helper path: " . $helperPath . PHP_EOL;
if (is_file($helperPath) && is_readable($helperPath)) {
    echo "i18n helper exists and is readable. Attempting to include and instantiate...\n";
    require_once $helperPath;
    if (class_exists('I18n')) {
        try {
            // Try DB connection if your db.php provides connectDB; otherwise pass null
            $dbPath = __DIR__ . '/../api/config/db.php';
            $mysqli = null;
            if (is_file($dbPath)) {
                require_once $dbPath;
                if (function_exists('connectDB')) {
                    $maybe = @connectDB();
                    if ($maybe instanceof mysqli) $mysqli = $maybe;
                }
            }
            $i18n = new I18n(null, $mysqli);
            echo "I18n instantiated. Locale: " . $i18n->getLocale() . " Direction: " . $i18n->getDirection() . PHP_EOL;
            echo "Top keys: " . implode(', ', array_keys($i18n->all())) . PHP_EOL;
            echo "strings.title => " . $i18n->t('strings.title', 'MISSING') . PHP_EOL;
        } catch (Throwable $e) {
            echo "I18n exception: " . $e->getMessage() . PHP_EOL;
        }
    } else {
        echo "I18n class not found after include." . PHP_EOL;
    }
} else {
    echo "i18n helper not found at expected path." . PHP_EOL;
}
echo "</pre>";

// End
echo "<hr><p>Done. If this shows nothing or errors, check webserver error log (e.g. /var/log/apache2/error.log or control panel logs).</p>";