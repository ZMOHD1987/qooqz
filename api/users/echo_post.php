<?php
// htdocs/api/users/echo_post.php - يعيد تفاصيل الطلب كـ JSON لتشخيص المشكلة
header('Content-Type: application/json; charset=utf-8');

$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $k => $v) {
        if (substr($k, 0, 5) === 'HTTP_') {
            $h = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$h] = $v;
        }
    }
}
$raw = @file_get_contents('php://input');
$response = [
    'time' => date('c'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'get' => $_GET,
    'post' => $_POST,
    'raw_body' => $raw,
    'files' => $_FILES,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
];
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>