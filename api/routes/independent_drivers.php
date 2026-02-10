<?php
declare(strict_types=1);

/**
 * htdocs/api/routes/independent_drivers.php
 * Complete, robust API for independent_drivers (list, get, create, update, delete, uploads)
 *
 * Place this file at: htdocs/api/routes/independent_drivers.php
 *
 * Requirements:
 * - config/db.php must provide connectDB() or $conn/$mysqli
 * - Session-based auth expected (session user_id, role_id, permissions)
 *
 * Notes:
 * - Uses prepared statements for all DB writes
 * - Uploaded files are moved to /uploads/independent_drivers/{id}/
 * - For creates we temporarily save uploads to /uploads/independent_drivers/temp and move after insert
 */

header('Content-Type: application/json; charset=utf-8');

// Basic CORS (adjust origin as needed)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    // Allow same origin; if you need to allow multiple origins, adapt as necessary
    $allowed_origin = 'https://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);
    if (stripos((string)$_SERVER['HTTP_ORIGIN'], $allowed_origin) === 0) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Respond to OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ensure session
if (session_status() === PHP_SESSION_NONE) @session_start();

// Helper: JSON response
function json_ok($data = [], int $code = 200) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = is_array($data) && array_key_exists('success', $data) ? $data : array_merge(['success' => true], (array)$data);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error(string $msg = 'Error', int $code = 400, array $extra = []) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = array_merge(['success' => false, 'message' => $msg], $extra);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// DB connect (config/db.php should provide connectDB())
$db = null;
$dbCfg = __DIR__ . '/../config/db.php';
if (is_readable($dbCfg)) {
    try {
        require_once $dbCfg;
    } catch (Throwable $e) {
        // ignore; connectDB may be defined by that file
    }
}
if (function_exists('connectDB')) {
    try { $db = connectDB(); } catch (Throwable $e) { $db = null; }
}
if (!($db instanceof mysqli)) {
    // try common globals
    if (!empty($conn) && $conn instanceof mysqli) $db = $conn;
    if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) $db = $GLOBALS['conn'];
}
if (!($db instanceof mysqli)) {
    json_error('Database connection not available', 500);
}

/* Upload paths */
define('UPLOAD_DIR', realpath(__DIR__ . '/../../uploads') ? realpath(__DIR__ . '/../../uploads') . '/independent_drivers' : __DIR__ . '/../../uploads/independent_drivers');
define('UPLOAD_URL', '/uploads/independent_drivers'); // web-relative prefix

if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);

/* Helpers for dynamic bind (mysqli) */
function stmt_bind_params(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') return;
    // mysqli::bind_param requires references
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

/* Basic auth/permission helpers (adapt to your app) */
function current_user_id(): int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}
function current_is_admin(): bool {
    return isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;
}
function has_perm(string $perm): bool {
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($perm, $_SESSION['permissions'], true);
    }
    return false;
}

/* Request handling */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ----------------- GET: single or list ----------------- */
if ($method === 'GET') {
    // single
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) json_error('Prepare failed: ' . $db->error, 500);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) json_ok(['data' => $row]);
        else json_error('Not found', 404);
    }

    // list with filters & simple RBAC
    $params = [];
    $types = '';
    $where = [];

    $isAdmin = current_is_admin();
    $uid = current_user_id();

    // if not admin and no 'view_drivers' permission, restrict to current user
    if (!$isAdmin && !has_perm('view_drivers')) {
        $where[] = 'user_id = ?';
        $params[] = $uid;
        $types .= 'i';
    } else {
        // optional filter by user_id (admins)
        if (!empty($_GET['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int)$_GET['user_id'];
            $types .= 'i';
        }
    }

    if (!empty($_GET['q'])) {
        $q = '%' . $_GET['q'] . '%';
        $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR license_number LIKE ? OR vehicle_number LIKE ?)";
        $params = array_merge($params, [$q, $q, $q, $q, $q]);
        $types .= 'sssss';
    }
    if (!empty($_GET['status'])) {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    if (!empty($_GET['vehicle_type'])) {
        $where[] = "vehicle_type = ?";
        $params[] = $_GET['vehicle_type'];
        $types .= 's';
    }

    $sql = "SELECT * FROM independent_drivers";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY id DESC";

    $stmt = $db->prepare($sql);
    if (!$stmt) json_error('Prepare failed: ' . $db->error, 500);
    if ($types) stmt_bind_params($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    json_ok(['data' => $rows, 'total' => count($rows)]);
}

/* ----------------- POST: create / update / delete ----------------- */
if ($method === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'save'));
    $isAdmin = current_is_admin();
    $uid = current_user_id();

    // Delete (admin only)
    if ($action === 'delete') {
        if (!$isAdmin) json_error('Forbidden', 403);
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_error('Invalid id', 400);

        // fetch files to remove
        $stmt = $db->prepare("SELECT license_photo_url, id_photo_url FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) json_error('Prepare failed: ' . $db->error, 500);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            foreach (['license_photo_url','id_photo_url'] as $col) {
                if (!empty($row[$col])) {
                    $file = $_SERVER['DOCUMENT_ROOT'] . $row[$col];
                    if (file_exists($file)) @unlink($file);
                }
            }
        }

        $del = $db->prepare("DELETE FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$del) json_error('Prepare failed: ' . $db->error, 500);
        $del->bind_param('i', $id);
        $ok = $del->execute();
        $del->close();
        if ($ok) json_ok(['message' => 'Deleted']);
        else json_error('Delete failed');
    }

    // Save (create or update) - accepts action=create, update, save (legacy)
    if (in_array($action, ['save','create','update',''], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $is_edit = $id > 0;
        $is_create = !$is_edit;

        // permission checks
        if ($is_edit) {
            // ensure owner or admin or edit permission
            $check = $db->prepare("SELECT user_id FROM independent_drivers WHERE id = ? LIMIT 1");
            if (!$check) json_error('Prepare failed: ' . $db->error, 500);
            $check->bind_param('i', $id);
            $check->execute();
            $res = $check->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $check->close();
            if (!$row) json_error('Not found', 404);
            if (!$isAdmin && (int)$row['user_id'] !== $uid && !has_perm('edit_drivers')) json_error('Forbidden', 403);
        } else {
            if ($uid <= 0) json_error('Unauthorized', 401);
            if (!has_perm('create_drivers') && !$isAdmin) {
                // allow regular users to create their own entries depending on your policy; adjust as needed.
                // Here we allow creation if user_id present (they are logged in).
            }
        }

        // Gather posted fields
        $allowedFields = ['full_name','phone','email','vehicle_type','vehicle_number','license_number','status'];
        $data = [];
        foreach ($allowedFields as $f) {
            if (isset($_POST[$f])) $data[$f] = trim((string)$_POST[$f]);
        }

        // required for create
        $required = ['full_name','phone','vehicle_type','license_number'];
        foreach ($required as $r) {
            if ($is_create && empty($data[$r])) json_error(ucfirst(str_replace('_',' ',$r)) . ' is required', 400);
        }

        // Handle file uploads to temp or final dir
        $uploadMap = ['license_photo' => 'license_photo_url', 'id_photo' => 'id_photo_url'];
        $uploaded = []; // col => url (points to temp or final)
        foreach ($uploadMap as $input => $col) {
            if (!empty($_FILES[$input]['name']) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
                $destDir = $is_edit ? (UPLOAD_DIR . '/' . $id) : (UPLOAD_DIR . '/temp');
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
                $filename = bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
                $destPath = $destDir . '/' . $filename;
                if (!move_uploaded_file($_FILES[$input]['tmp_name'], $destPath)) {
                    json_error('Failed to move uploaded file', 500);
                }
                $uploaded[$col] = UPLOAD_URL . '/' . ($is_edit ? $id : 'temp') . '/' . $filename;
            }
        }

        // Merge uploaded urls into data for insert/update
        foreach ($uploaded as $col => $url) $data[$col] = $url;

        // INSERT (create)
        if ($is_create) {
            // add user_id
            $data['user_id'] = $uid;

            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));

            $types = '';
            $values = [];
            foreach ($cols as $c) {
                $v = $data[$c];
                if (is_int($v)) $types .= 'i';
                else $types .= 's';
                $values[] = $v;
            }

            $sql = "INSERT INTO `independent_drivers` ({$colList}) VALUES ({$placeholders})";
            $stmt = $db->prepare($sql);
            if (!$stmt) json_error('Prepare failed: ' . $db->error, 500);
            if ($values) {
                $bindParams = array_merge([$types], $values);
                // convert to references
                $refs = [];
                foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }
            $ok = $stmt->execute();
            if (!$ok) {
                $err = $stmt->error ?: $db->error;
                $stmt->close();
                json_error('Insert failed: ' . $err, 500);
            }
            $driver_id = (int)$db->insert_id;
            $stmt->close();
        } else {
            // UPDATE
            $sets = [];
            $values = [];
            $types = '';
            foreach ($data as $k => $v) {
                $sets[] = "`$k` = ?";
                $values[] = $v;
                $types .= 's';
            }
            if (!empty($sets)) {
                $sql = "UPDATE `independent_drivers` SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
                $types .= 'i';
                $values[] = $id;
                $stmt = $db->prepare($sql);
                if (!$stmt) json_error('Prepare failed: ' . $db->error, 500);
                $bindParams = array_merge([$types], $values);
                $refs = [];
                foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
                call_user_func_array([$stmt, 'bind_param'], $refs);
                $ok = $stmt->execute();
                if (!$ok) {
                    $err = $stmt->error ?: $db->error;
                    $stmt->close();
                    json_error('Update failed: ' . $err, 500);
                }
                $driver_id = $id;
                $stmt->close();
            } else {
                // nothing to update
                $driver_id = $id;
            }
        }

        // If created and we had temp uploads, move them to final folder and update DB columns individually
        if ($is_create && !empty($uploaded)) {
            $tempDir = UPLOAD_DIR . '/temp';
            $finalDir = UPLOAD_DIR . '/' . $driver_id;
            if (!is_dir($finalDir)) @mkdir($finalDir, 0755, true);

            foreach ($uploaded as $col => $oldUrl) {
                $filename = basename($oldUrl);
                $oldPath = $tempDir . '/' . $filename;
                $newPath = $finalDir . '/' . $filename;
                if (file_exists($oldPath)) {
                    rename($oldPath, $newPath);
                    $newUrl = UPLOAD_URL . '/' . $driver_id . '/' . $filename;
                    $upd = $db->prepare("UPDATE `independent_drivers` SET `$col` = ? WHERE id = ? LIMIT 1");
                    if ($upd) {
                        $upd->bind_param('si', $newUrl, $driver_id);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
            // attempt to remove temp dir if empty
            @rmdir($tempDir);
        }

        json_ok(['data' => ['id' => $driver_id], 'message' => 'Saved successfully']);
    }

    // unknown action
    json_error('Invalid action', 400);
}

// fallback
json_error('Invalid request', 400);