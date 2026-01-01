<?php
// htdocs/api/document_types.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';
$mysqli = connectDB();
if (!$mysqli) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB not available']);
    exit;
}

try {
    $res = $mysqli->query('SELECT id, key_name, display_name, required_for, allow_multiple FROM document_types ORDER BY id ASC');
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'document_types'=>$rows]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
    exit;
}
?>