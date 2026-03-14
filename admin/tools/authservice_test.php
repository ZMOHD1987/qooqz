<?php
declare(strict_types=1);

/**
 * Simple AuthService diagnostic script.
 * Usage (CLI): php authservice_test.php identifier password
 * Usage (web): /admin/tools/authservice_test.php?id=identifier&pw=password
 *
 * WARNING: Temporary debug tool. REMOVE after use.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Adjust base paths if your layout differs
$baseApi = __DIR__ . '/../../api';

$pathsToTry = [
    $baseApi . '/shared/config/db.php',
    $baseApi . '/shared/config/config.php',
    $baseApi . '/shared/core/DatabaseConnection.php',
    $baseApi . '/shared/core/Logger.php',
    $baseApi . '/v1/auth/services/AuthService.php',
];

echo "AuthService Diagnostic\n";
echo "======================\n";

// Input
if (php_sapi_name() === 'cli') {
    $identifier = $argv[1] ?? '';
    $password   = $argv[2] ?? '';
} else {
    $identifier = $_GET['id'] ?? $_POST['id'] ?? '';
    $password   = $_GET['pw'] ?? $_POST['pw'] ?? '';
}

// Show which files exist
foreach ($pathsToTry as $p) {
    echo ($p . ' => ' . (file_exists($p) ? "exists" : "MISSING")) . PHP_EOL;
}

// Require config and core pieces safely
$maybeFiles = [
    $baseApi . '/shared/config/db.php',
    $baseApi . '/shared/config/config.php',
    $baseApi . '/shared/core/DatabaseConnection.php',
    $baseApi . '/shared/core/Logger.php',
];

foreach ($maybeFiles as $f) {
    if (file_exists($f)) {
        require_once $f;
    }
}

// Now require AuthService
$svcPath = $baseApi . '/v1/auth/services/AuthService.php';
if (!file_exists($svcPath)) {
    echo "ERROR: AuthService file not found at {$svcPath}\n";
    exit(1);
}
require_once $svcPath;

// Try get PDO (DatabaseConnection may throw)
$pdo = null;
try {
    if (class_exists('DatabaseConnection')) {
        $pdo = DatabaseConnection::getConnection();
        echo "✅ DB connected successfully (via DatabaseConnection)." . PHP_EOL;
        // Show current DB name/time
        try {
            $row = $pdo->query("SELECT DATABASE() AS db, NOW() AS now")->fetch(PDO::FETCH_ASSOC);
            echo "DB: " . ($row['db'] ?? '(unknown)') . "  | now: " . ($row['now'] ?? '') . PHP_EOL;
        } catch (Throwable $e) {
            echo "Note: failed to run sample query: " . $e->getMessage() . PHP_EOL;
        }
    } else {
        echo "WARNING: DatabaseConnection class not found. AuthService will attempt internal connection." . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "DB connect error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

// Instantiate AuthService (inject PDO if available)
try {
    $auth = new AuthService($pdo ?? null);
    echo "✅ AuthService loaded!" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: failed to instantiate AuthService: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

// If identifier/password not provided, use defaults from DB for testing (first 3 users)
if ($identifier === '' || $password === '') {
    echo "No identifier/password provided. Will run a batch of sample tests (first few users) if available.\n";

    try {
        $stmt = $pdo->prepare("SELECT id, username, email, phone, password_hash, is_active FROM users LIMIT 5");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "No user rows found to test. Provide id and pw parameters.\n";
            exit(0);
        }
        echo "Found up to " . count($rows) . " users. For each user we'll attempt password verification using password you supply via ?pw=\n";
        foreach ($rows as $r) {
            echo "User id={$r['id']} username={$r['username']} email={$r['email']} phone={$r['phone']} is_active={$r['is_active']}\n";
            $hp = $r['password_hash'] ?? '';
            echo "Hash preview: " . ($hp !== '' ? substr($hp,0,30) . (strlen($hp)>30 ? '...' : '') : '(empty)') . PHP_EOL;
        }
        echo "To test, re-run with ?id=username&pw=yourpassword or via CLI.\n";
        exit(0);
    } catch (Throwable $e) {
        echo "Failed to query users: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

// Run login test for given identifier/password
$tests = is_array($identifier) ? $identifier : [$identifier];

echo "Testing login for identifier(s): " . implode(', ', $tests) . PHP_EOL;
echo "Password provided (length): " . strlen($password) . PHP_EOL;

foreach ($tests as $id) {
    $id = trim((string)$id);
    if ($id === '') continue;

    echo "----\n";
    echo "Testing: {$id}\n";
    try {
        $user = $auth->login($id, $password);
        if (is_array($user)) {
            echo "✅ Login succeeded for identifier '{$id}'\n";
            echo "User: " . print_r($user, true) . PHP_EOL;
        } else {
            echo "❌ Login failed! Will attempt direct DB inspection for '{$id}'.\n";
            // Inspect DB for row
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT id, username, email, phone, password_hash, is_active FROM users WHERE username = :i OR email = :i OR phone = :i LIMIT 1");
                $stmt->bindValue(':i', $id, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    echo " -> No user row found for identifier '{$id}'\n";
                } else {
                    echo " -> Found user id={$row['id']} username={$row['username']} email={$row['email']} phone={$row['phone']} is_active={$row['is_active']}\n";
                    $hp = $row['password_hash'] ?? '';
                    echo " -> Hash preview: " . ($hp !== '' ? substr($hp,0,30) . (strlen($hp)>30 ? '...' : '') : '(empty)') . PHP_EOL;
                    // direct password_verify check
                    try {
                        $pv = password_verify($password, $hp) ? 'YES' : 'NO';
                        echo " -> password_verify result: {$pv}\n";
                        if ($pv === 'NO' && preg_match('/^[a-f0-9]{32}$/i', $hp)) {
                            echo " -> Stored hash looks like MD5. md5(password) === stored ? " . (md5($password) === $hp ? 'YES' : 'NO') . PHP_EOL;
                        }
                    } catch (Throwable $e) {
                        echo " -> password_verify threw: " . $e->getMessage() . PHP_EOL;
                    }
                }
            } else {
                echo " -> No PDO available to inspect DB further.\n";
            }
        }
    } catch (Throwable $e) {
        echo "ERROR while testing login for '{$id}': " . $e->getMessage() . PHP_EOL;
        echo $e->getTraceAsString() . PHP_EOL;
    }
}

echo "Test completed.\n";