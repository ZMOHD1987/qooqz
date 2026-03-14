<?php
/**
 * frontend/public/debug_product.php
 * QOOQZ Diagnostic Tool — Product Detail Debug
 *
 * Tests every step of the product loading chain to identify exactly where it fails.
 * ⚠️ REMOVE THIS FILE after debugging is complete (exposes server paths).
 *
 * Access: /frontend/public/debug_product.php?id=1
 */

// Guard: require a simple token to prevent accidental exposure
$token = $_GET['token'] ?? '';
if ($token !== 'qooqz_debug_2026') {
    http_response_code(403);
    echo '<pre>403 Forbidden — add ?token=qooqz_debug_2026 to the URL</pre>';
    exit;
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

$productId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 1;

function dbg_ok(string $msg): void  { echo "<div style='color:#22c55e'>✅ $msg</div>"; }
function dbg_err(string $msg): void { echo "<div style='color:#ef4444'>❌ $msg</div>"; }
function dbg_warn(string $msg): void{ echo "<div style='color:#f59e0b'>⚠️ $msg</div>"; }
function dbg_info(string $msg): void{ echo "<div style='color:#60a5fa'>ℹ️ $msg</div>"; }
function dbg_pre($val, string $label = ''): void {
    if ($label) echo "<b>$label:</b> ";
    echo "<pre style='background:#1e293b;color:#e2e8f0;padding:10px;border-radius:4px;overflow-x:auto'>";
    echo htmlspecialchars(is_string($val) ? $val : print_r($val, true));
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>QOOQZ Product Debug — ID <?= $productId ?></title>
<style>
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 20px; line-height: 1.6; }
  h2   { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 6px; }
  hr   { border-color: #334155; }
</style>
</head>
<body>
<h1 style="color:#f59e0b">🔍 QOOQZ Product Debug</h1>
<p>Testing product ID: <b><?= $productId ?></b> — <a href="?token=qooqz_debug_2026&id=<?= $productId ?>">refresh</a></p>

<?php

/* -------------------------------------------------------
 * STEP 1: PHP Environment
 * ----------------------------------------------------- */
echo '<h2>Step 1: PHP Environment</h2>';
dbg_info('PHP version: ' . PHP_VERSION);
dbg_info('SAPI: ' . php_sapi_name());
dbg_info('DOCUMENT_ROOT: ' . ($_SERVER['DOCUMENT_ROOT'] ?? '(not set)'));
dbg_info('__DIR__: ' . __DIR__);
dbg_info('SERVER_NAME: ' . ($_SERVER['SERVER_NAME'] ?? '(not set)'));
dbg_info('HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? '(not set)'));
dbg_info('session.auto_start: ' . ini_get('session.auto_start'));
dbg_info('session.save_path: ' . ini_get('session.save_path'));
dbg_info('session.name: ' . ini_get('session.name'));
dbg_info('Current session name: ' . session_name());
dbg_info('Session status: ' . session_status() . ' (0=disabled,1=none,2=active)');
$urlReachable = function_exists('curl_init') ? 'curl available' : 'curl NOT available (will use file_get_contents)';
dbg_info('HTTP client: ' . $urlReachable);

/* -------------------------------------------------------
 * STEP 2: DB config file paths
 * ----------------------------------------------------- */
echo '<h2>Step 2: DB Config File Detection</h2>';

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$frontendBase = dirname(__DIR__); // /path/to/frontend
$candidates = [
    $docRoot . '/api/shared/config/db.php',
    $frontendBase . '/../api/shared/config/db.php',
    realpath($frontendBase . '/../api/shared/config/db.php') ?: '(realpath failed)',
    dirname($frontendBase) . '/api/shared/config/db.php',
];

$foundDb = null;
$dbConfig = null;
foreach ($candidates as $i => $path) {
    if ($path && file_exists($path)) {
        dbg_ok("Candidate $i FOUND: $path");
        if ($foundDb === null) {
            $foundDb = $path;
            try {
                $loaded = require $path;
                if (is_array($loaded)) {
                    $dbConfig = $loaded;
                    dbg_ok("db.php loaded successfully — keys: " . implode(', ', array_keys($dbConfig)));
                } else {
                    dbg_warn("db.php loaded but returned " . gettype($loaded) . " instead of array");
                    // Check if constants are defined instead
                }
            } catch (Throwable $ex) {
                dbg_err("db.php threw exception: " . $ex->getMessage());
            }
        }
    } else {
        dbg_err("Candidate $i NOT found: $path");
    }
}

if (!$dbConfig) {
    dbg_warn("No db.php file found via candidates. Checking if DB_HOST constants are defined...");
    if (defined('DB_HOST')) {
        dbg_ok("DB_HOST constant is defined: " . DB_HOST);
        $dbConfig = [
            'host'    => DB_HOST,
            'user'    => defined('DB_USER') ? DB_USER : '',
            'pass'    => defined('DB_PASS') ? '(set)' : '(NOT SET)',
            'name'    => defined('DB_NAME') ? DB_NAME : '',
            'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
            'port'    => defined('DB_PORT') ? DB_PORT : 3306,
        ];
    } else {
        dbg_err("DB_HOST constant NOT defined — DB config is completely unavailable");
    }
}

if ($dbConfig) {
    $safeConfig = $dbConfig;
    $safeConfig['pass'] = !empty($safeConfig['pass']) ? '(set, ' . strlen((string)$safeConfig['pass']) . ' chars)' : '(empty!)';
    dbg_pre($safeConfig, 'DB config (password hidden)');
}

/* -------------------------------------------------------
 * STEP 3: Direct PDO connection
 * ----------------------------------------------------- */
echo '<h2>Step 3: Direct PDO Connection</h2>';

$pdo = null;
if ($dbConfig && !empty($dbConfig['name'])) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'] ?? 'localhost',
            (int)($dbConfig['port'] ?? 3306),
            $dbConfig['name'],
            $dbConfig['charset'] ?? 'utf8mb4'
        );
        dbg_info("DSN: " . preg_replace('/dbname=[^;]+/', 'dbname=(hidden)', $dsn));
        $pdo = new PDO($dsn, $dbConfig['user'] ?? '', $dbConfig['pass'] ?? '', [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        dbg_ok("PDO connection established");

        // Test query
        $testRow = $pdo->query('SELECT 1 AS ok')->fetch();
        dbg_ok("Test query SELECT 1 returned: " . json_encode($testRow));

        // Check products table
        $cnt = $pdo->query('SELECT COUNT(*) AS n FROM products')->fetch();
        dbg_ok("Total products in DB: " . ($cnt['n'] ?? '?'));

        // Query the specific product
        $st = $pdo->prepare('SELECT id, sku, slug, is_active, tenant_id FROM products WHERE id = ? LIMIT 1');
        $st->execute([$productId]);
        $row = $st->fetch();
        if ($row) {
            dbg_ok("Product ID $productId found in DB:");
            dbg_pre($row);
        } else {
            dbg_err("Product ID $productId NOT found in products table (WHERE id = $productId)");
            // Try broader search
            $all = $pdo->query('SELECT id, sku, slug, is_active, tenant_id FROM products ORDER BY id LIMIT 10')->fetchAll();
            dbg_warn("First 10 products in table:");
            dbg_pre($all);
        }

        // Check product_translations
        $st2 = $pdo->prepare('SELECT product_id, language_code, name FROM product_translations WHERE product_id = ?');
        $st2->execute([$productId]);
        $translations = $st2->fetchAll();
        if ($translations) {
            dbg_ok("Translations for product $productId: " . count($translations) . " rows");
            dbg_pre($translations);
        } else {
            dbg_warn("No product_translations rows for product_id=$productId — name will fall back to slug");
        }

        // Check product_pricing
        $st3 = $pdo->prepare('SELECT id, product_id, price, currency_code FROM product_pricing WHERE product_id = ? LIMIT 3');
        $st3->execute([$productId]);
        $pricing = $st3->fetchAll();
        if ($pricing) {
            dbg_ok("Pricing for product $productId: " . count($pricing) . " rows");
            dbg_pre($pricing);
        } else {
            dbg_warn("No product_pricing rows for product_id=$productId — price will be NULL");
        }

        // Check images
        $st4 = $pdo->prepare('SELECT id, owner_id, url, is_main FROM images WHERE owner_id = ? LIMIT 5');
        $st4->execute([$productId]);
        $imgs = $st4->fetchAll();
        if ($imgs) {
            dbg_ok("Images for product $productId: " . count($imgs) . " rows");
        } else {
            dbg_warn("No images found for owner_id=$productId");
        }

    } catch (Throwable $ex) {
        dbg_err("PDO connection/query failed: " . $ex->getMessage());
        dbg_err("File: " . $ex->getFile() . " Line: " . $ex->getLine());
    }
} else {
    dbg_err("Skipping PDO test — no DB config available");
}

/* -------------------------------------------------------
 * STEP 4: pub_api_url() output
 * ----------------------------------------------------- */
echo '<h2>Step 4: API URL Detection</h2>';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl = rtrim($scheme . '://' . $host . '/api', '/') . '/public/products';
$fullUrl = $apiUrl . '?id=' . $productId . '&lang=en';
dbg_info("Computed API URL: $fullUrl");

/* -------------------------------------------------------
 * STEP 5: HTTP fetch test
 * ----------------------------------------------------- */
echo '<h2>Step 5: HTTP API Fetch Test</h2>';
dbg_info("Fetching: $fullUrl");

$body = false;
$curlError = '';
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($fullUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
} else {
    $ctx  = stream_context_create(['http' => ['timeout' => 6]]);
    $body = @file_get_contents($fullUrl, false, $ctx);
}

if ($body === false || $body === '') {
    dbg_err("HTTP fetch returned empty/false. HTTP code: $httpCode. cURL error: $curlError");
    dbg_warn("This means HTTP loopback is BLOCKED on this server.");
    dbg_warn("Product page CANNOT use HTTP API — must use direct PDO only.");
} else {
    dbg_ok("HTTP fetch succeeded (HTTP $httpCode). Response length: " . strlen($body) . " bytes");
    $decoded = json_decode($body, true);
    if ($decoded === null) {
        dbg_err("Response is NOT valid JSON. First 500 chars:");
        dbg_pre(substr($body, 0, 500));
    } else {
        dbg_ok("Response is valid JSON. success=" . ($decoded['success'] ? 'true' : 'false'));
        if (!empty($decoded['data']['product'])) {
            dbg_ok("API returned product data:");
            dbg_pre($decoded['data']['product']);
        } elseif (!empty($decoded['data'])) {
            dbg_warn("API returned data but no 'product' key. data keys: " . implode(', ', array_keys($decoded['data'])));
            dbg_pre($decoded['data']);
        } else {
            dbg_err("API returned success=false or no data. Full response:");
            dbg_pre($decoded);
        }
    }
}

/* -------------------------------------------------------
 * STEP 6: public_context.php bootstrap test
 * ----------------------------------------------------- */
echo '<h2>Step 6: public_context.php Bootstrap Test</h2>';

try {
    require_once dirname(__DIR__) . '/includes/public_context.php';
    dbg_ok("public_context.php loaded without exceptions");

    if (isset($GLOBALS['PUB_CONTEXT'])) {
        dbg_ok("PUB_CONTEXT is set. Lang: " . ($GLOBALS['PUB_CONTEXT']['lang'] ?? '?') . ", tenant_id: " . ($GLOBALS['PUB_CONTEXT']['tenant_id'] ?? '?'));
    } else {
        dbg_err("PUB_CONTEXT is NOT set after loading public_context.php");
    }

    $pdoTest = pub_get_pdo();
    if ($pdoTest instanceof PDO) {
        dbg_ok("pub_get_pdo() returned a valid PDO connection");
        $t = $pdoTest->query('SELECT 1 AS ok')->fetch();
        dbg_ok("pub_get_pdo() test query: " . json_encode($t));
    } else {
        dbg_err("pub_get_pdo() returned NULL — all product.php PDO fallback will fail");
        dbg_warn("This is the #1 reason product details are blank.");
    }

    $apiRespTest = pub_fetch(pub_api_url('public/products') . '?id=' . $productId . '&lang=en');
    if (!empty($apiRespTest['data']['product'])) {
        dbg_ok("pub_fetch() returned product data via HTTP API:");
        dbg_pre($apiRespTest['data']['product']);
    } elseif (!empty($apiRespTest)) {
        dbg_warn("pub_fetch() returned data but no product key. Keys: " . implode(', ', array_keys($apiRespTest['data'] ?? $apiRespTest)));
    } else {
        dbg_err("pub_fetch() returned [] — HTTP API not reachable from frontend PHP context");
        dbg_warn("This means product.php Step 1 (HTTP) fails → falls to PDO → if PDO also null → 'not found'");
    }

} catch (Throwable $ex) {
    dbg_err("public_context.php threw exception: " . $ex->getMessage());
    dbg_err("File: " . $ex->getFile() . " Line: " . $ex->getLine());
    dbg_pre($ex->getTraceAsString(), 'Stack trace');
}

/* -------------------------------------------------------
 * STEP 7: Summary and recommendation
 * ----------------------------------------------------- */
echo '<h2>Step 7: Diagnosis Summary</h2>';
echo '<div style="background:#1e293b;padding:16px;border-radius:8px;border-left:4px solid #f59e0b;">';
echo '<p>Based on the above results:</p>';
echo '<ul>';
echo '<li>If Step 3 PDO works → direct DB queries work → problem is in product.php query itself</li>';
echo '<li>If Step 5 HTTP returns [] → loopback is blocked → must use PDO-only approach</li>';
echo '<li>If Step 6 pub_get_pdo() is null → db.php path not found → must add DB constants fallback</li>';
echo '<li>If Step 3 shows product exists but Step 5/6 says "not found" → API has DB connection issue</li>';
echo '</ul>';
echo '<p style="color:#f59e0b">⚠️ <b>DELETE this file after debugging: /frontend/public/debug_product.php</b></p>';
echo '</div>';
?>
</body>
</html>
