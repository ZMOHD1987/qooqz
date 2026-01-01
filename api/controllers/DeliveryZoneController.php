<?php
// api/controllers/DeliveryZoneController.php
// Controller for Delivery Zones. Uses DeliveryZoneModel and DeliveryZoneValidator.
// Methods: listAction, getAction, createAction, updateAction, deleteAction

declare(strict_types=1);

require_once __DIR__ . '/../models/DeliveryZone.php';
require_once __DIR__ . '/../validators/DeliveryZone.php';

// Fallback response helpers if project doesn't provide them
if (!function_exists('json_ok')) {
    function json_ok($d = [], $c = 200) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $c);
        $out = is_array($d) ? array_merge(['success' => true], $d) : ['success' => true, 'data' => $d];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_error')) {
    function json_error($m = 'Error', $c = 400, $e = []) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $c);
        $out = array_merge(['success' => false, 'message' => $m], $e);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('log_debug')) {
    function log_debug($m){ @file_put_contents(__DIR__ . '/../error_debug.log', "[".date('c')."] ".trim($m).PHP_EOL, FILE_APPEND); }
}

class DeliveryZoneController
{
    private DeliveryZoneModel $model;
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->model = new DeliveryZoneModel($db);
    }

    private function currentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
        if (!empty($_SESSION['user_id'])) return ['id' => (int)$_SESSION['user_id'], 'role_id' => $_SESSION['role_id'] ?? null, 'permissions' => $_SESSION['permissions'] ?? []];
        return null;
    }

    private function isAdminOrManage(): bool
    {
        $u = $this->currentUser();
        if (!$u) return false;
        if ((int)($u['role_id'] ?? 0) === 1) return true;
        $perms = $u['permissions'] ?? [];
        return is_array($perms) && in_array('manage_vendors', $perms, true);
    }

    // GET ?action=list
    public function listAction()
    {
        $u = $this->currentUser();
        $filters = [
            'q' => $_GET['q'] ?? null,
            'status' => $_GET['status'] ?? null
        ];
        if (!$this->isAdminOrManage()) {
            $filters['user_id'] = $u['id'] ?? 0;
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = min(200, max(5, (int)($_GET['per_page'] ?? 20)));
        try {
            $res = $this->model->search($filters, $page, $per);
            // parse zone_value for clients (do not mutate DB)
            foreach ($res['data'] as &$r) {
                $r['zone_value_json'] = null;
                if (!empty($r['zone_value'])) {
                    $dec = @json_decode($r['zone_value'], true);
                    if (json_last_error() === JSON_ERROR_NONE) $r['zone_value_json'] = $dec;
                }
            }
            json_ok(['data' => $res['data'], 'total' => $res['total'], 'page' => $page, 'per_page' => $per]);
        } catch (Throwable $e) {
            log_debug("listAction error: " . $e->getMessage());
            json_error('Server error', 500);
        }
    }

    // GET ?action=get&id=...
    public function getAction()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) json_error('Invalid id', 400);

        $row = $this->model->findById($id);
        if (!$row) json_error('Not found', 404);

        $u = $this->currentUser();
        if (!$this->isAdminOrManage()) {
            if ((int)($row['user_id'] ?? 0) !== (int)($u['id'] ?? 0)) json_error('Forbidden', 403);
        }

        $row['zone_value_json'] = null;
        if (!empty($row['zone_value'])) {
            $dec = @json_decode($row['zone_value'], true);
            if (json_last_error() === JSON_ERROR_NONE) $row['zone_value_json'] = $dec;
        }

        json_ok(['data' => $row]);
    }

    // POST ?action=create_zone
    public function createAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) json_error('Invalid CSRF', 403);

        $u = $this->currentUser();
        if (!$u) json_error('Unauthorized', 401);

        $payload = [
            'user_id' => isset($_POST['user_id']) ? (int)$_POST['user_id'] : ($u['id'] ?? 0),
            'zone_name' => $_POST['zone_name'] ?? '',
            'zone_type' => $_POST['zone_type'] ?? '',
            'zone_value' => $_POST['zone_value'] ?? '',
            'shipping_rate' => $_POST['shipping_rate'] ?? 0,
            'free_shipping_threshold' => $_POST['free_shipping_threshold'] ?? null,
            'estimated_delivery_days' => $_POST['estimated_delivery_days'] ?? 3,
            'status' => $_POST['status'] ?? 'active'
        ];

        // non-admin cannot create on behalf of others
        if (!((int)($u['role_id'] ?? 0) === 1) && ((int)$payload['user_id'] !== (int)$u['id'])) {
            $payload['user_id'] = (int)$u['id'];
        }

        $errors = DeliveryZoneValidator::validate($payload);
        if (!empty($errors)) json_error('Validation failed', 400, ['errors' => $errors]);

        // ensure zone_value stored as JSON string
        if (isset($payload['zone_value']) && !is_string($payload['zone_value'])) {
            $payload['zone_value'] = json_encode($payload['zone_value'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $newId = $this->model->create($payload);
            if (!$newId) { log_debug("createAction: model->create failed. payload: " . json_encode($payload)); json_error('Create failed', 500); }
            json_ok(['id' => $newId, 'message' => 'Created']);
        } catch (Throwable $e) {
            log_debug("createAction exception: " . $e->getMessage());
            json_error('Server error', 500);
        }
    }

    // POST ?action=update_zone
    public function updateAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) json_error('Invalid CSRF', 403);

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) json_error('Invalid id', 400);

        $row = $this->model->findById($id);
        if (!$row) json_error('Not found', 404);

        $u = $this->currentUser();
        if (!$this->isAdminOrManage()) {
            if ((int)$row['user_id'] !== (int)$u['id']) json_error('Forbidden', 403);
        }

        $payload = [];
        foreach (['zone_name','zone_type','zone_value','shipping_rate','free_shipping_threshold','estimated_delivery_days','status','user_id'] as $f) {
            if (isset($_POST[$f])) $payload[$f] = $_POST[$f];
        }
        // only admin may change user_id
        if (isset($payload['user_id']) && (int)$u['role_id'] !== 1) unset($payload['user_id']);

        $errors = DeliveryZoneValidator::validate(array_merge($row, $payload));
        if (!empty($errors)) json_error('Validation failed', 400, ['errors' => $errors]);

        if (isset($payload['zone_value']) && !is_string($payload['zone_value'])) $payload['zone_value'] = json_encode($payload['zone_value'], JSON_UNESCAPED_UNICODE);

        try {
            $ok = $this->model->update($id, $payload);
            if (!$ok) { log_debug("updateAction: model->update failed for id {$id}. payload: " . json_encode($payload)); json_error('Update failed', 500); }
            json_ok(['message' => 'Updated']);
        } catch (Throwable $e) {
            log_debug("updateAction exception: " . $e->getMessage());
            json_error('Server error', 500);
        }
    }

    // POST ?action=delete_zone
    public function deleteAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

        if (function_exists('verify_csrf') && !verify_csrf($_POST['csrf_token'] ?? '')) json_error('Invalid CSRF', 403);

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) json_error('Invalid id', 400);

        $row = $this->model->findById($id);
        if (!$row) json_error('Not found', 404);

        $u = $this->currentUser();
        if (!$this->isAdminOrManage()) {
            if ((int)$row['user_id'] !== (int)$u['id']) json_error('Forbidden', 403);
        }

        try {
            $ok = $this->model->delete($id);
            if (!$ok) json_error('Delete failed', 500);
            json_ok(['deleted' => true]);
        } catch (Throwable $e) {
            log_debug("deleteAction exception: " . $e->getMessage());
            json_error('Server error', 500);
        }
    }
}