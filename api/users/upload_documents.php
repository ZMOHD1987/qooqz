<?php
// upload_documents.php
// Usage: POST /api/users/upload_documents.php
// multipart/form-data: files in 'documents[]' (or 'profile_image'), documents_type_id[] parallel array, owner=user|store, owner_id=<id>
// Requires insert_document_with_logging.php and ensure_document_type_id.php to be present

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php';
$mysqli = connectDB();
if (!$mysqli) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB connection error']); exit; }

require_once __DIR__ . '/doc_insert_helper.php'; // insert_document_with_logging(...)
require_once __DIR__ . '/ensure_document_type.php'; // ensure_document_type_id(...)

$uploader_id = $_SESSION['user_id'] ?? null; // or accept admin auth or API key
if (!$uploader_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Authentication required']);
    exit;
}

$owner_type = $_POST['owner_type'] ?? 'user'; // 'user' or 'vendor' (or role key)
$owner_id = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
if ($owner_id <= 0) { echo json_encode(['success'=>false,'message'=>'owner_id required']); exit; }

// Prepare upload dir
$uploadBase = realpath(__DIR__ . '/../../uploads') ?: __DIR__ . '/../../uploads';
$userDir = rtrim($uploadBase, '/') . '/users/' . $owner_id;
if (!is_dir($userDir)) @mkdir($userDir, 0755, true);

$results = ['uploaded'=>[], 'errors'=>[]];

// handle profile_image single file if present
if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['profile_image'];
    $orig = basename($f['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $new = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
    $dest = $userDir . '/' . $new;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        $storage_key = 'uploads/users/' . $owner_id . '/' . $new;
        $content_type = mime_content_type($dest);
        $filesize = filesize($dest);
        $checksum = file_exists($dest) ? hash_file('sha256', $dest) : null;
        // try find profile_image type
        $dtid = 0;
        $r = $mysqli->prepare("SELECT id FROM document_types WHERE key_name = 'profile_image' LIMIT 1");
        if ($r) { $r->execute(); $r->bind_result($tmp); if ($r->fetch()) $dtid = (int)$tmp; $r->close(); }
        $dtid = ensure_document_type_id($mysqli, $dtid);
        $res = insert_document_with_logging($mysqli, $owner_type, $owner_id, $dtid, $orig, $storage_key, $content_type, $filesize, $checksum, 'pending', $uploader_id);
        if ($res['ok']) $results['uploaded'][] = $res['id']; else $results['errors'][] = $res;
    } else {
        $results['errors'][] = ['error'=>'move_failed','file'=>$orig];
    }
}

// handle generic documents[] array
if (!empty($_FILES['documents'])) {
    $files = [];
    foreach ($_FILES['documents'] as $k => $list) foreach ($list as $i => $v) $files[$i][$k] = $v;
    $documents_type_ids = isset($_POST['documents_type_id']) ? (is_array($_POST['documents_type_id'])?$_POST['documents_type_id']:[$_POST['documents_type_id']]) : [];
    foreach ($files as $idx => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) { $results['errors'][] = ['index'=>$idx,'error'=>'upload_error','code'=>$file['error']]; continue; }
        $orig = basename($file['name']); $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $new = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
        $dest = $userDir . '/' . $new;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { $results['errors'][] = ['file'=>$orig,'error'=>'move_failed']; continue; }
        $storage_key = 'uploads/users/' . $owner_id . '/' . $new;
        $content_type = mime_content_type($dest);
        $filesize = filesize($dest);
        $checksum = file_exists($dest) ? hash_file('sha256', $dest) : null;
        $provided_doc_type = isset($documents_type_ids[$idx]) ? intval($documents_type_ids[$idx]) : 0;
        $doc_type_id = ensure_document_type_id($mysqli, $provided_doc_type);
        if ($doc_type_id === 0) { $results['errors'][] = ['file'=>$orig,'error'=>'invalid_document_type']; continue; }
        $res = insert_document_with_logging($mysqli, $owner_type, $owner_id, $doc_type_id, $orig, $storage_key, $content_type, $filesize, $checksum, 'pending', $uploader_id);
        if ($res['ok']) $results['uploaded'][] = $res['id']; else $results['errors'][] = $res;
    }
}

echo json_encode(array_merge(['success'=>true], $results));
exit;
?>