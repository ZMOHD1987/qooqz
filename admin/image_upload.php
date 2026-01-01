<?php
    ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/require_permission.php';
require_login();

// بسيط: تحقق CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
  http_response_code(400); echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400); echo json_encode(['success'=>false,'message'=>'No file or upload error']); exit;
}

$file = $_FILES['image'];
// تحديد مجلد الرفع (تأكد أنّ المسار صحيح وقابل للكتابة)
$uploadDir = __DIR__ . '/../uploads/images/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest = $uploadDir . $filename;

// حاول نقل الملف فقط (دون استخدام GD)
if (!move_uploaded_file($file['tmp_name'], $dest)) {
  http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to move uploaded file']); exit;
}

// بنجاح: أرجع URL (تأكد أن UPLOAD_URL يطابق إعدادك)
$fileUrl = '/uploads/images/' . $filename;
echo json_encode(['success'=>true,'url'=>$fileUrl], JSON_UNESCAPED_UNICODE);
exit;