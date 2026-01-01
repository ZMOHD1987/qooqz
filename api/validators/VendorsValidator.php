<?php
// api/controllers/VendorController.php
// Controller wrapper that uses VendorModel and session-based auth.
// Expects api/models/Vendor.php present.

require_once __DIR__ . '/../models/Vendor.php';
if (is_readable(__DIR__ . '/../helpers/validators/VendorsValidator.php')) require_once __DIR__ . '/../helpers/validators/VendorsValidator.php';
if (is_readable(__DIR__ . '/../helpers/utils.php')) require_once __DIR__ . '/../helpers/utils.php';
if (is_readable(__DIR__ . '/../helpers/response.php')) require_once __DIR__ . '/../helpers/response.php';

class VendorController
{
    private $model;
    private $currentUser;
    private $isAdmin;

    public function __construct(mysqli $conn = null)
    {
        $this->model = new VendorModel($conn);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->currentUser = $_SESSION['user'] ?? null;
        $this->isAdmin = $this->currentUser && isset($this->currentUser['role_id']) && (int)$this->currentUser['role_id'] === 1;
    }

    private function json($data, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
    private function unauthorized() { $this->json(['success'=>false,'message'=>'Unauthorized'],403); }
    private function forbidden() { $this->json(['success'=>false,'message'=>'Forbidden'],403); }

    public function index($params = [])
    {
        if (!$this->isAdmin && $this->currentUser) {
            $v = $this->model->findByUserId((int)$this->currentUser['id']);
            return $this->json(['success'=>true,'data'=>['data'=>$v ? [$v] : [], 'total'=> $v ? 1 : 0]]);
        }
        $page = max(1, (int)($params['page'] ?? 1));
        $per = min(200, max(10, (int)($params['per_page'] ?? 20)));
        $filters = [];
        if (!empty($params['status'])) $filters['status'] = $params['status'];
        if (!empty($params['vendor_type'])) $filters['vendor_type'] = $params['vendor_type'];
        if (!empty($params['search'])) $filters['search'] = $params['search'];
        $res = $this->model->getAll($filters, $page, $per);
        $this->json(['success'=>true,'data'=>$res]);
    }

    public function show($idOrSlug)
    {
        if (is_numeric($idOrSlug)) $v = $this->model->findById((int)$idOrSlug);
        else $v = $this->model->findBySlug((string)$idOrSlug);
        if (!$v) $this->json(['success'=>false,'message'=>'Not found'],404);
        if (!$this->isAdmin && $this->currentUser && $v['user_id'] != $this->currentUser['id']) $this->forbidden();
        $this->json(['success'=>true,'data'=>$v]);
    }

    public function apply($input = [])
    {
        if (!$this->currentUser) $this->unauthorized();
        $input['user_id'] = (int)$this->currentUser['id'];
        if (class_exists('VendorsValidator')) {
            $v = VendorsValidator::validate($input, $this->model->conn ?? null);
            if (!$v['valid']) $this->json(['success'=>false,'errors'=>$v['errors']],422);
            $clean = $v['data'];
        } else { $clean = $input; }
        $translations = $input['translations'] ?? [];
        $files = $_FILES ?? [];
        $id = $this->model->save($clean, $translations, $files);
        if (!$id) $this->json(['success'=>false,'message'=>'Create failed'],500);
        $vendor = $this->model->findById($id);
        if (class_exists('Notification') && method_exists('Notification','send')) {
            try { Notification::send(1, 'vendor', 'New vendor application', "Vendor {$vendor['store_name']} submitted"); } catch(Throwable $e){}
        }
        $this->json(['success'=>true,'data'=>$vendor,'message'=>'Vendor application submitted']);
    }

    public function update($id, $input = [])
    {
        if (!$this->currentUser) $this->unauthorized();
        $id = (int)$id;
        $existing = $this->model->findById($id);
        if (!$existing) $this->json(['success'=>false,'message'=>'Not found'],404);
        if (!$this->isAdmin && $existing['user_id'] != $this->currentUser['id']) $this->forbidden();

        if (class_exists('VendorsValidator')) {
            $v = VendorsValidator::validate($input, $this->model->conn ?? null, $id);
            if (!$v['valid']) $this->json(['success'=>false,'errors'=>$v['errors']],422);
            $clean = $v['data'];
        } else $clean = $input;

        $translations = $input['translations'] ?? [];
        $files = $_FILES ?? [];
        $savedId = $this->model->save(array_merge($clean, ['id'=>$id]), $translations, $files);
        if (!$savedId) $this->json(['success'=>false,'message'=>'Update failed'],500);
        $vendor = $this->model->findById($savedId);
        $this->json(['success'=>true,'data'=>$vendor,'message'=>'Updated']);
    }

    public function uploadDocument($id, $post = [], $file = null)
    {
        if (!$this->currentUser) $this->unauthorized();
        $id = (int)$id;
        $vendor = $this->model->findById($id);
        if (!$vendor) $this->json(['success'=>false,'message'=>'Not found'],404);
        if (!$this->isAdmin && $vendor['user_id'] != $this->currentUser['id']) $this->forbidden();

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) $this->json(['success'=>false,'message'=>'No file'],400);

        $docType = $post['document_type'] ?? 'national_id';
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fn = 'v' . $id . '_doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/vendor_documents/';
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $dest = $destDir . $fn;
        if (!move_uploaded_file($file['tmp_name'], $dest)) $this->json(['success'=>false,'message'=>'Upload failed'],500);
        $url = '/uploads/vendor_documents/' . $fn;

        $stmt = $this->model->conn->prepare("INSERT INTO vendor_documents (vendor_id, document_type, document_url, document_number, status, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) $this->json(['success'=>false,'message'=>'DB error'],500);
        $status = 'pending';
        $docNumber = $post['document_number'] ?? null;
        $stmt->bind_param('issss', $id, $docType, $url, $docNumber, $status);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) $this->json(['success'=>false,'message'=>'DB insert failed'],500);
        $this->json(['success'=>true,'file_url'=>$url]);
    }

    public function requestPayout($id = null, $input = [])
    {
        if (!$this->currentUser) $this->unauthorized();
        if ($id === null) {
            $vendor = $this->model->findByUserId((int)$this->currentUser['id']);
            if (!$vendor) $this->json(['success'=>false,'message'=>'Vendor not found'],404);
            $id = (int)$vendor['id'];
        } else $id = (int)$id;

        $balance = $this->model->calculateBalance($id);
        $available = (float)($balance['available_for_payout'] ?? 0);

        $amount = isset($input['amount']) ? (float)$input['amount'] : $available;
        if ($amount <= 0 || $amount > $available) $this->json(['success'=>false,'message'=>'Invalid amount'],422);

        $mysqli = $this->model->conn;
        $sql = "INSERT INTO vendor_payouts (vendor_id, payout_amount, payout_method, total_sales, total_commission, status, created_at) VALUES (?, ?, ?, 0, 0, 'pending', NOW())";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) $this->json(['success'=>false,'message'=>'DB error'],500);
        $method = $input['payout_method'] ?? 'bank_transfer';
        $stmt->bind_param('ids', $id, $amount, $method);
        $ok = $stmt->execute();
        $payoutId = $mysqli->insert_id;
        $stmt->close();
        if (!$ok) $this->json(['success'=>false,'message'=>'Create payout failed'],500);
        if (class_exists('Notification') && method_exists('Notification','send')) {
            try { Notification::send(1, 'payout', 'Payout Request', "Vendor {$id} requested payout {$amount}"); } catch(Throwable $e) {}
        }
        $this->json(['success'=>true,'payout_id'=>$payoutId]);
    }
}