<?php
// htdocs/api/config/db.php

$servername = "sql311.infinityfree.com";
$username   = "if0_39652926";
$password   = "Mohd28332";
$dbname     = "if0_39652926_qooqz";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->query("SET time_zone = '+00:00'");

if ($conn->connect_error) {
    $error_msg = $conn->connect_errno . ': ' . $conn->connect_error;
    error_log("فشل الاتصال بقاعدة البيانات: " . $error_msg);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        die(json_encode([
            'status' => 'error',
            'message' => 'فشل الاتصال بقاعدة البيانات / Database connection failed',
            'error_details' => $error_msg
        ]));
    } else {
        die("فشل الاتصال بقاعدة البيانات: " . $error_msg);
    }
}

$conn->set_charset("utf8mb4");

// حماية الدالة من إعادة التعريف
if (!function_exists('connectDB')) {
    function connectDB() {
        global $conn;
        return $conn;
    }
}
?>
