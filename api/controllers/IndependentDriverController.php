<?php
// api/controllers/IndependentDriverController.php
// Complete, robust controller for Independent Drivers (JSON API).
// - Actions supported via ?action=list|get|create|update|delete
// - RBAC-aware: uses user_has(), require_permission(), can_modify_entity() if available
// - Works with IndependentDriverModel if present; otherwise falls back to simple mysqli queries
// - Safe file uploads (uses upload_image_file() when available), stores under /uploads/independent_drivers/{id}/
// - Normalizes model->search outputs of various shapes: ['data'=>...], ['rows'=>...], or plain rows array
// - Returns JSON with consistent structure: { success: true|false, data:..., message:... }

declare(strict_types=1);

// Bootstrap (loads DB, RBAC adapter, helpers). Adjust path if your bootstrap is elsewhere.
$bootstrap = __DIR__ . '/../bootstrap.php';
if (is_readable($bootstrap)) require_once $bootstrap;

// Acquire DB connection (try helpers or common globals)
$db = null;
if (function_exists('get_db')) $db = get_db();
if (!($db instanceof mysqli)) {
    if (function_exists('acquire_db')) $db = @acquire_db();
    if (!($db instanceof mysqli) && !empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) $db = $GLOBALS['conn'];
    if (!($db instanceof mysqli) && !empty($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) $db = $GLOBALS['mysqli'];
}
if (!($db instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// Helpers: json responses
if (!function_exists('json_ok')) {
    function json_ok($d = [], int $code = 200) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        $out = is_array($d) && isset($d['success']) ? $d : array_merge(['success' => true], (array)$d);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_error')) {
    function json_error(string $msg = 'Error', int $code = 400, $extra = []) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        $out = array_merge(['success' => false, 'message' => $msg], is_array($extra) ? $extra : []);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Load optional model, validator, upload helper if available
$modelClassFile = __DIR__ . '/../models/IndependentDriver.php';
$validatorFile = __DIR__ . '/../validators/IndependentDriver.php';
$uploadHelperFile = __DIR__ . '/../helpers/upload.php';
if (is_readable($modelClassFile)) require_once $modelClassFile;
if (is_readable($validatorFile)) require_once $validatorFile;
if (is_readable($uploadHelperFile)) require_once $uploadHelperFile;

// Utility functions
function current_user(): ?array {
    if (function_exists('get_current_user')) return get_current_user();
    if (session_status() === PHP_SESSION_NONE) @session_start();
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) return ['id' => (int)$_SESSION['user_id']];
    return null;
}
function has_perm(string $p): bool {
    if (function_exists('user_has')) return user_has($p);
    if (function_exists('require_permission')) {
        // require_permission cannot be used for check; fallback to session
    }
    if (session_status() === PHP_SESSION_NONE) @session_start();
    return !empty($_SESSION['permissions']) && in_array($p, (array)$_SESSION['permissions'], true);
}
function can_modify(int $ownerId, string $perm = 'edit_drivers'): bool {
    if (function_exists('can_modify_entity')) return can_modify_entity($perm, $ownerId);
    if (has_perm($perm)) return true;
    $u = current_user();
    return ($u && isset($u['id']) && (int)$u['id'] === (int)$ownerId);
}

// Simple fallback model implementation using mysqli if IndependentDriverModel not present
class SimpleIndependentDriverModel {
    private mysqli $db;
    private string $table = 'independent_drivers';
    public function __construct(mysqli $db) { $this->db = $db; }

    // Search with simple filtering: supports q, status, vehicle_type, user_id
    public function search(array $filters = [], int $page = 1, int $per = 50): array {
        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR license_number LIKE ? OR vehicle_number LIKE ?)";
            $types .= 'sssss';
            array_push($params, $q, $q, $q, $q, $q);
        }
        if (!empty($filters['status'])) { $where[] = "status = ?"; $types .= 's'; $params[] = $filters['status']; }
        if (!empty($filters['vehicle_type'])) { $where[] = "vehicle_type = ?"; $types .= 's'; $params[] = $filters['vehicle_type']; }
        if (!empty($filters['user_id'])) { $where[] = "user_id = ?"; $types .= 'i'; $params[] = (int)$filters['user_id']; }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $offset = max(0, ($page - 1) * $per);

        // total
        $total = 0;
        $countSql = "SELECT COUNT(*) AS c FROM `{$this->table}` {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        if ($stmt && $types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) $total = (int)$row['c'];
        $stmt->close();

        // rows
        $sql = "SELECT * FROM `{$this->table}` {$whereSql} ORDER BY id DESC LIMIT ?, ?";
        $stmt2 = $this->db->prepare($sql);
        if ($stmt2) {
            // bind params plus offset & limit
            if ($types !== '') {
                // build types for offset and limit
                $allTypes = $types . 'ii';
                $allParams = array_merge($params, [$offset, $per]);
                $stmt2->bind_param($allTypes, ...$allParams);
            } else {
                $stmt2->bind_param('ii', $offset, $per);
            }
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            $rows = $r2->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        } else {
            $rows = [];
        }

        return ['data' => $rows, 'total' => $total];
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function create(array $payload) {
        $fields = [];
        $placeholders = [];
        $types = '';
        $values = [];
        $allowed = ['user_id','full_name','phone','email','vehicle_type','vehicle_number','license_number','status','rating_average','rating_count'];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $payload)) {
                $fields[] = "`$k`";
                $placeholders[] = '?';
                $v = $payload[$k];
                if (is_int($v)) { $types .= 'i'; }
                elseif (is_float($v) || is_double($v)) { $types .= 'd'; }
                else { $types .= 's'; }
                $values[] = $v;
            }
        }
        $fields[] = '`created_at`';
        $placeholders[] = 'NOW()';
        $sql = "INSERT INTO `{$this->table}` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        if ($stmt && count($values)) {
            $stmt->bind_param($types, ...$values);
            $ok = $stmt->execute();
            if (!$ok) { $stmt->close(); return 0; }
            $id = $this->db->insert_id;
            $stmt->close();
            return $id;
        } elseif ($stmt) {
            // no bind params (unlikely) - execute directly
            $ok = $stmt->execute();
            $id = $this->db->insert_id;
            $stmt->close();
            return $id ?: 0;
        }
        return 0;
    }

    public function update(int $id, array $payload) {
        $sets = [];
        $types = '';
        $values = [];
        $allowed = ['full_name','phone','email','vehicle_type','vehicle_number','license_number','license_photo_url','id_photo_url','status','rating_average','rating_count','user_id'];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $payload)) {
                $sets[] = "`$k` = ?";
                $v = $payload[$k];
                if (is_int($v)) $types .= 'i';
                elseif (is_float($v) || is_double($v)) $types .= 'd';
                else $types .= 's';
                $values[] = $v;
            }
        }
        if (empty($sets)) return true;
        $sql = "UPDATE `{$this->table}` SET " . implode(',', $sets) . ", updated_at = NOW() WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $types .= 'i';
        $values[] = $id;
        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function delete(int $id) {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

// Instantiate model (prefer explicit model class)
if (class_exists('IndependentDriverModel')) {
    try {
        $model = new IndependentDriverModel($db);
    } catch (Throwable $e) {
        // fallback
        $model = new SimpleIndependentDriverModel($db);
    }
} else {
    $model = new SimpleIndependentDriverModel($db);
}

// Allowed vehicle types (match DB enum)
const ALLOWED_VEHICLE_TYPES = ['motorcycle','car','van','truck'];

/**
 * Normalize search result to ['data'=>[], 'total'=>int]
 */
function normalize_search_result($res): array {
    $data = [];
    $total = 0;
    if (is_array($res)) {
        if (isset($res['data']) && is_array($res['data'])) {
            $data = $res['data'];
            $total = isset($res['total']) ? (int)$res['total'] : count($data);
        } elseif (isset($res['rows']) && is_array($res['rows'])) {
            $data = $res['rows'];
            $total = isset($res['total']) ? (int)$res['total'] : count($data);
        } else {
            // numeric indexed array
            $keys = array_keys($res);
            if ($keys !== [] && is_int($keys[0])) {
                $data = $res;
                $total = count($data);
            } else {
                // maybe single record
                $data = [$res];
                $total = 1;
            }
        }
    }
    return ['data' => $data, 'total' => $total];
}

// Route dispatcher: read action param
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list')));

// Ensure JSON responses for errors
try {
    $controller = new class($model) {
        private $model;
        public function __construct($model) { $this->model = $model; }

        private function currentUser() { return current_user(); }

        public function listAction() {
            $filters = [];
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q !== '') $filters['q'] = $q;
            if (!empty($_GET['status'])) $filters['status'] = trim((string)$_GET['status']);
            if (!empty($_GET['vehicle_type'])) $filters['vehicle_type'] = trim((string)$_GET['vehicle_type']);

            $page = max(1, (int)($_GET['page'] ?? 1));
            $per = min(200, max(5, (int)($_GET['per_page'] ?? 50)));

            $user = $this->currentUser();

            if (!has_perm('view_drivers')) {
                if ($user && !empty($user['id'])) {
                    $filters['user_id'] = (int)$user['id'];
                } else {
                    json_error('Forbidden', 403);
                }
            } else {
                if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
            }

            $res = $this->model->search($filters, $page, $per);
            $norm = normalize_search_result($res);
            json_ok(['data' => $norm['data'], 'total' => $norm['total'], 'page' => $page, 'per_page' => $per]);
        }

        public function getAction() {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) json_error('Invalid id', 400);
            $row = $this->model->findById($id);
            if (!$row) json_error('Not found', 404);

            $user = $this->currentUser();
            if (!has_perm('view_drivers')) {
                if (!$user || empty($user['id']) || (int)$user['id'] !== (int)$row['user_id']) json_error('Forbidden', 403);
            }
            json_ok(['data' => $row]);
        }

        private function _handle_file_upload($fileInputName, int $ownerId, string $typePrefix='file') {
            // returns relative URL or null
            if (empty($_FILES[$fileInputName]) || empty($_FILES[$fileInputName]['tmp_name'])) return null;
            // prefer upload_image_file helper if available (maintains quality and resizing)
            $uploadBase = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/independent_drivers/' . $ownerId;
            if (!is_dir($uploadBase)) @mkdir($uploadBase, 0755, true);
            if (function_exists('upload_image_file')) {
                // signature: upload_image_file($_FILES[field], $destDir, $namePrefix, $maxW, $maxH, $quality, $maxBytes)
                try {
                    $path = upload_image_file($_FILES[$fileInputName], $uploadBase, $typePrefix, 1600, 1600, 90, 5 * 1024 * 1024);
                    // convert to web relative path
                    $rel = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'), '', $path);
                    return $rel;
                } catch (Throwable $e) {
                    throw new RuntimeException('Upload failed: ' . $e->getMessage());
                }
            } else {
                // basic move_uploaded_file fallback
                $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
                $fname = $typePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'bin');
                $dest = $uploadBase . '/' . $fname;
                if (!move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $dest)) {
                    throw new RuntimeException('Failed to move uploaded file');
                }
                $rel = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'), '', $dest);
                return $rel;
            }
        }

        public function createAction() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
            $user = $this->currentUser();
            if (!$user) json_error('Authentication required', 401);

            $postedUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $targetUserId = $postedUserId ?: (int)($user['id'] ?? 0);

            // allow create if has create_drivers OR creating own
            if (!has_perm('create_drivers') && ($targetUserId !== (int)$user['id'])) json_error('Forbidden', 403);

            $payload = [
                'user_id' => $targetUserId,
                'full_name' => trim((string)($_POST['name'] ?? $_POST['full_name'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'email' => trim((string)($_POST['email'] ?? '')),
                'vehicle_type' => trim((string)($_POST['vehicle_type'] ?? '')),
                'vehicle_number' => trim((string)($_POST['vehicle_number'] ?? '')),
                'license_number' => trim((string)($_POST['license_number'] ?? '')),
                'status' => trim((string)($_POST['status'] ?? 'active')),
            ];

            if ($payload['vehicle_type'] === '' || !in_array($payload['vehicle_type'], ALLOWED_VEHICLE_TYPES, true)) {
                json_error('Validation failed', 400, ['errors' => ['vehicle_type' => ['Invalid vehicle type']]]);
            }

            // optional validator
            if (class_exists('IndependentDriverValidator')) {
                $errs = IndependentDriverValidator::validate($payload);
                if (!empty($errs)) json_error('Validation failed', 400, ['errors' => $errs]);
            }

            try {
                $id = $this->model->create($payload);
                if (!$id) json_error('Create failed', 500);

                // handle uploads
                $updated = [];
                if (!empty($_FILES['license_photo']['tmp_name'])) {
                    $p = $this->_handle_file_upload('license_photo', $id, 'license');
                    $updated['license_photo_url'] = $p;
                }
                if (!empty($_FILES['id_photo']['tmp_name'])) {
                    $p2 = $this->_handle_file_upload('id_photo', $id, 'id');
                    $updated['id_photo_url'] = $p2;
                }
                if (!empty($updated)) $this->model->update($id, $updated);

                json_ok(['id' => $id, 'message' => 'Created']);
            } catch (Throwable $e) {
                error_log('IndependentDriver createAction error: ' . $e->getMessage());
                json_error('Server error', 500);
            }
        }

        public function updateAction() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) json_error('Invalid id', 400);

            $row = $this->model->findById($id);
            if (!$row) json_error('Not found', 404);

            $user = $this->currentUser();
            if (!$user) json_error('Authentication required', 401);

            if (!has_perm('edit_drivers') && ((int)$row['user_id'] !== (int)$user['id'])) json_error('Forbidden', 403);

            $mapping = [
                'name' => 'full_name', 'full_name' => 'full_name',
                'phone' => 'phone', 'email' => 'email',
                'vehicle_type' => 'vehicle_type', 'vehicle_number' => 'vehicle_number',
                'license_number' => 'license_number', 'status' => 'status'
            ];
            $payload = [];
            foreach ($mapping as $k => $field) {
                if (isset($_POST[$k])) $payload[$field] = trim((string)$_POST[$k]);
            }

            if (isset($payload['status']) && !has_perm('approve_drivers')) unset($payload['status']);

            if (isset($payload['vehicle_type']) && !in_array($payload['vehicle_type'], ALLOWED_VEHICLE_TYPES, true)) {
                json_error('Validation failed', 400, ['errors' => ['vehicle_type' => ['Invalid vehicle type']]]);
            }

            // optional validator merge
            $toValidate = array_merge($row, $payload);
            if (class_exists('IndependentDriverValidator')) {
                $errs = IndependentDriverValidator::validate($toValidate);
                if (!empty($errs)) json_error('Validation failed', 400, ['errors' => $errs]);
            }

            try {
                if (!empty($payload)) {
                    $ok = $this->model->update($id, $payload);
                    if (!$ok) json_error('Update failed', 500);
                }

                // deletion flags for photos
                $updates = [];
                if (!empty($_POST['delete_license']) && $_POST['delete_license'] === '1') {
                    if (!empty($row['license_photo_url'])) {
                        $abs = $_SERVER['DOCUMENT_ROOT'] . $row['license_photo_url'];
                        if (file_exists($abs) && strpos(realpath($abs), realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/independent_drivers')) === 0) {
                            @unlink($abs);
                        }
                    }
                    $updates['license_photo_url'] = null;
                }
                if (!empty($_POST['delete_id']) && $_POST['delete_id'] === '1') {
                    if (!empty($row['id_photo_url'])) {
                        $abs2 = $_SERVER['DOCUMENT_ROOT'] . $row['id_photo_url'];
                        if (file_exists($abs2) && strpos(realpath($abs2), realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/independent_drivers')) === 0) {
                            @unlink($abs2);
                        }
                    }
                    $updates['id_photo_url'] = null;
                }

                // new uploads
                if (!empty($_FILES['license_photo']['tmp_name'])) {
                    $p = $this->_handle_file_upload('license_photo', $id, 'license');
                    $updates['license_photo_url'] = $p;
                }
                if (!empty($_FILES['id_photo']['tmp_name'])) {
                    $p2 = $this->_handle_file_upload('id_photo', $id, 'id');
                    $updates['id_photo_url'] = $p2;
                }

                if (!empty($updates)) $this->model->update($id, $updates);

                json_ok(['message' => 'Updated']);
            } catch (Throwable $e) {
                error_log('IndependentDriver updateAction error: ' . $e->getMessage());
                json_error('Server error', 500);
            }
        }

        public function deleteAction() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) json_error('Invalid id', 400);

            $row = $this->model->findById($id);
            if (!$row) json_error('Not found', 404);

            $user = $this->currentUser();
            if (!$user) json_error('Authentication required', 401);

            if (!has_perm('delete_drivers') && ((int)$row['user_id'] !== (int)$user['id'])) json_error('Forbidden', 403);

            try {
                // delete associated files
                if (!empty($row['license_photo_url'])) {
                    $abs = $_SERVER['DOCUMENT_ROOT'] . $row['license_photo_url'];
                    if (file_exists($abs) && strpos(realpath($abs), realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/independent_drivers')) === 0) {
                        @unlink($abs);
                    }
                }
                if (!empty($row['id_photo_url'])) {
                    $abs2 = $_SERVER['DOCUMENT_ROOT'] . $row['id_photo_url'];
                    if (file_exists($abs2) && strpos(realpath($abs2), realpath(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/independent_drivers')) === 0) {
                        @unlink($abs2);
                    }
                }

                $ok = $this->model->delete($id);
                if (!$ok) json_error('Delete failed', 500);
                json_ok(['deleted' => true, 'message' => 'Deleted']);
            } catch (Throwable $e) {
                error_log('IndependentDriver deleteAction error: ' . $e->getMessage());
                json_error('Server error', 500);
            }
        }
    };

    // dispatch
    switch ($action) {
        case 'list': $controller->listAction(); break;
        case 'get': $controller->getAction(); break;
        case 'create': $controller->createAction(); break;
        case 'update': $controller->updateAction(); break;
        case 'delete': $controller->deleteAction(); break;
        default: json_error('Unknown action', 400);
    }
} catch (Throwable $e) {
    error_log('IndependentDriverController fatal: ' . $e->getMessage());
    json_error('Server error', 500);
}