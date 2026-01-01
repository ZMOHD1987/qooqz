<?php
// htdocs/api/upload_image.php
// Simple, secure image upload endpoint.
// - Accepts POST multipart/form-data with file field name: file
// - Returns JSON: { success:true, url:"/uploads/..." } or error

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// permission: تأكد أن المستخدم مسجّل أو مخول
// if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
$allowedExt = ['jpg','jpeg','png','webp','gif'];

// upload dir relative to htdocs
$uploadDir = __DIR__ . '/uploads/banners';
$publicBase = '/api/uploads/banners'; // العرض العام (عدل حسب مسارك)

// ensure upload dir exists
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Failed to create upload dir']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

if (empty($_FILES) || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Upload error','code'=>$file['error']]);
    exit;
}

if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'File too large']);
    exit;
}

// validate mime using finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMime)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid file type','mime'=>$mime]);
    exit;
}

// extension from original name (fallback from mime)
$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt)) {
    // try map by mime
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $ext = isset($map[$mime]) ? $map[$mime] : '';
    if (!$ext) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid file extension']);
        exit;
    }
}

// create unique filename
$basename = bin2hex(random_bytes(8)); // 16 hex chars
$filename = $basename . '.' . $ext;
$target = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Failed to move uploaded file']);
    exit;
}

// optional: set permissions
@chmod($target, 0644);

// returned public URL — adjust according to your site path
// If your uploads folder is directly accessible under /uploads/... change publicBase accordingly.
$publicUrl = dirname($_SERVER['SCRIPT_NAME']) . '/uploads/banners/' . $filename; // e.g. /api/uploads/banners/xyz.jpg
// If you want absolute URL:
// $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $publicUrl;

echo json_encode(['success'=>true,'url'=>$publicUrl]);
exit;