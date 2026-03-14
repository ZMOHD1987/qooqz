<?php
// =====================================================
// diagnostic.php — ضعه في api/routes/ مؤقتاً
// ثم افتح: https://hcsfcs.top/api/routes/diagnostic.php
// احذفه بعد حل المشكلة
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre style='direction:ltr;font-size:13px;'>\n";
echo "=== PHP VERSION ===\n";
echo PHP_VERSION . "\n\n";

echo "=== CONSTANTS ===\n";
echo "API_VERSION_PATH defined: " . (defined('API_VERSION_PATH') ? 'YES → ' . API_VERSION_PATH : 'NO ← مشكلة') . "\n\n";

// إذا لم يكن محدداً نحاول نبني المسار يدوياً
$base = defined('API_VERSION_PATH') ? API_VERSION_PATH : dirname(__DIR__, 2) . '/v1';

echo "=== CHECKING FILES ===\n";
$files = [
    $base . '/models/homepage_sections/Contracts/HomepageSectionsRepositoryInterface.php',
    $base . '/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php',
    $base . '/models/homepage_sections/validators/HomepageSectionsValidator.php',
    $base . '/models/homepage_sections/services/HomepageSectionsService.php',
    $base . '/models/homepage_sections/controllers/HomepageSectionsController.php',
];

foreach ($files as $file) {
    $short = str_replace($base, '[API_VERSION_PATH]', $file);
    if (file_exists($file)) {
        echo "✅ EXISTS  : $short\n";
    } else {
        echo "❌ MISSING : $short\n";
    }
}

echo "\n=== SESSION ===\n";
if (session_status() === PHP_SESSION_NONE) session_start();
echo "tenant_id : " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "\n";
echo "user_id   : " . ($_SESSION['user_id']   ?? 'NOT SET') . "\n";

echo "\n=== DATABASE ===\n";
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
echo "ADMIN_DB  : " . ($pdo instanceof PDO ? "✅ Connected" : "❌ NOT initialized") . "\n";

echo "\n=== SYNTAX CHECK ===\n";
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $short  = str_replace($base, '[v1]', $file);
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    $ok     = str_contains($output ?? '', 'No syntax errors');
    echo ($ok ? "✅" : "❌") . " $short\n";
    if (!$ok) echo "   → $output\n";
}

echo "\n=== TRY LOADING FILES ===\n";
foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "⏭️  SKIP (missing): $file\n";
        continue;
    }
    try {
        require_once $file;
        echo "✅ Loaded: " . basename($file) . "\n";
    } catch (Throwable $e) {
        echo "❌ Error in " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n=== DONE ===\n";
echo "</pre>\n";
