<?php
// test_db_connection.php — مؤقت لفحص سبب عدم اتصال صفحات admin بقاعدة البيانات
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>DEBUG DB CONNECTION TEST\n\n";

// 1) عرض مسار الملف المتوقع
$cfg = __DIR__ . '/../api/config/db.php';
echo "Expected db.php path: $cfg\n";
if (!file_exists($cfg)) {
    echo "ERROR: db.php NOT FOUND at expected path.\n";
    echo "Check that file exists and permissions allow reading.\n";
    exit;
}

echo "db.php exists. Including...\n";
try {
    require_once $cfg;
    echo "Included db.php OK\n";
} catch (Throwable $e) {
    echo "INCLUDE ERROR: " . $e->getMessage() . "\n";
    exit;
}

// 2) Is connectDB() defined?
if (function_exists('connectDB')) {
    echo "Function connectDB() is defined.\n";
    try {
        $conn = connectDB();
        if ($conn instanceof mysqli) {
            echo "connectDB() returned mysqli — connection OK\n";
            echo "MySQL server_info: " . $conn->server_info . "\n";
            echo "MySQL host_info: " . $conn->host_info . "\n";
        } else {
            echo "connectDB() returned something but it's not mysqli (type: " . gettype($conn) . ")\n";
        }
    } catch (Throwable $e) {
        echo "connectDB() threw exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "connectDB() is NOT defined.\n";
    // maybe db.php defines $conn variable directly
    if (isset($conn) && ($conn instanceof mysqli)) {
        echo "\$conn variable exists and is mysqli (connection OK)\n";
    } else {
        echo "\$conn variable not defined or not mysqli.\n";
    }
}

// 3) Show user-defined functions (helpful)
echo "\nUser-defined functions (sample):\n";
$uf = get_defined_functions()['user'];
sort($uf);
foreach ($uf as $f) {
    if (stripos($f, 'connect') !== false || stripos($f, 'db') !== false) echo " - $f\n";
}

echo "\nSESSION (partial):\n";
if (session_status() === PHP_SESSION_NONE) @session_start();
print_r(array_intersect_key($_SESSION, array_flip(['user_id','user_role','preferred_language'])));

echo "\nEnd of test.\n</pre>";