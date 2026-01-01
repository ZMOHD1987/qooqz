<?php
// htdocs/api/controllers/VendorController.php
// Controller لإدارة التجار (إنشاء طلب التاجر، موافقة/رفض، إدارة المستندات، دفعات، إحصاءات، واجهة المتجر)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/upload.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';
require_once __DIR__ . '/../helpers/notification.php';
require_once __DIR__ . '/../helpers/mail.php';

class VendorController
{
    /**
     * تقديم طلب فتح متجر (من قبل مستخدم مسجل)
     * POST /api/vendor/apply
     */
    public static function apply()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $input = $_POST;

        $rules = [
            'store_name' => 'required|string|min:3|max:150',
            'store_slug' => "optional|string|min:3|max:150|unique:vendors,store_slug",
            'phone' => 'required|saudi_phone',
            'email' => "required|email|unique:vendors,email",
            'business_type' => 'optional|string',
            'business_name' => 'optional|string',
            'commercial_register' => 'optional|string',
            'tax_number' => 'optional|string',
            'license_number' => 'optional|string',
            'address' => 'optional|string',
            'city' => 'optional|string',
            'country' => 'optional|string'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $vendorModel = new Vendor();

        // Ensure slug uniqueness or generate
        $slug = $validated['store_slug'] ?? Utils::createSlug($validated['store_name']);
        if (!$vendorModel->isSlugUnique($slug)) {
            // try append random suffix
            $slug .= '-' . substr(Utils::generateUUID(), 0, 6);
        }
        $validated['store_slug'] = $slug;
        $validated['user_id'] = $user['id'];

        $newVendor = $vendorModel->create($validated);

        if (!$newVendor) {
            Response::error('Failed to create vendor application', 500);
        }

        // Notify admin(s)
        Notification::specialOffer(1, 'New vendor application', "New vendor: {$newVendor['store_name']}"); // example; adapt as needed

        // Optional: send email to vendor
        if (!empty($newVendor['email']) && MAIL_ENABLED) {
            Mail::sendVendorApproval($newVendor['email'], $newVendor['store_name']); // Note: this sends approval template; could be adapted
        }

        Response::created(['vendor' => $newVendor], 'Vendor application submitted successfully. It will be reviewed by admin.');
    }

    /**
     * تحديث بيانات التاجر (Vendor self or Admin)
     * PUT /api/vendor/{id}
     */
    public static function update($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!$id) {
            // user updating their own vendor (find by user_id)
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) Response::error('Vendor account not found for user', 404);
            $id = $vendor['id'];
        } else {
            $id = (int)$id;
        }

        // If not admin, ensure ownership
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findById($id);
            if (!$vendor) Response::error('Vendor not found', 404);
            if ($vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to update this vendor');
            }
        }

        $input = $_POST;

        $rules = [
            'store_name' => 'optional|string|min:3|max:150',
            'store_slug' => "optional|string|min:3|max:150|unique:vendors,store_slug,{$id}",
            'store_description' => 'optional|string|max:2000',
            'business_type' => 'optional|string',
            'business_name' => 'optional|string',
            'commercial_register' => 'optional|string',
            'tax_number' => 'optional|string',
            'license_number' => 'optional|string',
            'phone' => 'optional|saudi_phone',
            'email' => "optional|email|unique:vendors,email,{$id}",
            'address' => 'optional|string',
            'city' => 'optional|string',
            'country' => 'optional|string',
            'postal_code' => 'optional|string'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $vendorModel = new Vendor();
        $ok = $vendorModel->update($id, $validated);

        if ($ok) {
            $updated = $vendorModel->findById($id);
            Response::success(['vendor' => $updated], 'Vendor updated successfully');
        }

        Response::error('Failed to update vendor', 500);
    }

    /**
     * رفع مستند للتاجر (مثل السجل التجاري)
     * POST /api/vendor/{id}/documents
     * يتوقع ملف باسم "document"
     */
    public static function uploadDocument($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!$id) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) Response::error('Vendor account not found for user', 404);
            $id = $vendor['id'];
        } else {
            $id = (int)$id;
        }

        // Ownership check for non-admins
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findById($id);
            if ($vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to upload documents for this vendor');
            }
        }

        if (!isset($_FILES['document'])) {
            Response::validationError(['document' => ['Document file is required']]);
        }

        $file = $_FILES['document'];
        $result = Upload::uploadFile($file, 'vendor_documents');

        if (!$result['success']) {
            Response::error($result['message'] ?? 'Upload failed', 400);
        }

        $fileUrl = $result['file_url'];
        $documentType = $_POST['document_type'] ?? 'identity';
        $documentNumber = $_POST['document_number'] ?? null;
        $expiryDate = $_POST['expiry_date'] ?? null;

        $vendorModel = new Vendor();
        $ok = $vendorModel->addDocument($id, $documentType, $fileUrl, $documentNumber, $expiryDate);

        if ($ok) {
            Response::success(['file_url' => $fileUrl], 'Document uploaded successfully');
        }

        Response::error('Failed to save document', 500);
    }

    /**
     * الموافقة على تاجر (Admin)
     * POST /api/admin/vendors/{id}/approve
     */
    public static function approve($id = null)
    {
        RoleMiddleware::canUpdate('vendors');

        if (!$id) Response::validationError(['id' => ['Vendor id is required']]);
        $admin = AuthMiddleware::authenticate();
        $vendorModel = new Vendor();
        $vendor = $vendorModel->findById((int)$id);
        if (!$vendor) Response::error('Vendor not found', 404);

        $ok = $vendorModel->approve((int)$id, $admin['id']);
        if ($ok) {
            // Notify vendor
            if (!empty($vendor['email']) && MAIL_ENABLED) {
                Mail::sendVendorApproval($vendor['email'], $vendor['store_name']);
            }

            // Save notification in DB
            Notification::send((int)$vendor['user_id'], 'vendor', 'Vendor Approved', "Your store {$vendor['store_name']} has been approved", [], ['database', 'email']);

            Response::success(null, 'Vendor approved successfully');
        }

        Response::error('Failed to approve vendor', 500);
    }

    /**
     * رفض تاجر (Admin)
     * POST /api/admin/vendors/{id}/reject
     */
    public static function reject($id = null)
    {
        RoleMiddleware::canUpdate('vendors');

        if (!$id) Response::validationError(['id' => ['Vendor id is required']]);
        $input = $_POST;
        $reason = $input['reason'] ?? 'Application not meeting requirements';

        $vendorModel = new Vendor();
        $vendor = $vendorModel->findById((int)$id);
        if (!$vendor) Response::error('Vendor not found', 404);

        $ok = $vendorModel->reject((int)$id, $reason);
        if ($ok) {
            // Notify vendor
            if (!empty($vendor['email']) && MAIL_ENABLED) {
                Mail::sendVendorRejection($vendor['email'], $vendor['store_name'], $reason);
            }

            Notification::send((int)$vendor['user_id'], 'vendor', 'Vendor Rejected', "Your store {$vendor['store_name']} was rejected. Reason: {$reason}", [], ['database', 'email']);

            Response::success(null, 'Vendor rejected');
        }

        Response::error('Failed to reject vendor', 500);
    }

    /**
     * جلب بيانات تاجر (عام / متجر)
     * GET /api/vendors/{id_or_slug}
     */
    public static function show($idOrSlug = null)
    {
        if (!$idOrSlug) Response::validationError(['id' => ['Vendor id or slug is required']]);

        $vendorModel = new Vendor();
        $vendor = is_numeric($idOrSlug) ? $vendorModel->findById((int)$idOrSlug) : $vendorModel->findBySlug($idOrSlug);

        if (!$vendor) Response::error('Vendor not found', 404);

        // إزالة حقول حساسة قبل العرض العام
        unset($vendor['email']); // optional: keep if needed
        unset($vendor['phone']);

        // جلب منتجات المتجر (مختصر)
        $productModel = new Product();
        $products = $productModel->getByVendor($vendor['id'], ['is_active' => 1], 1, 12);

        $vendor['products'] = $products['data'];

        Response::success($vendor);
    }

    /**
     * قائمة التجار (عام أو Admin)
     * GET /api/vendors
     */
    public static function index()
    {
        $q = $_GET;
        $page = isset($q['page']) ? (int)$q['page'] : 1;
        $perPage = isset($q['per_page']) ? (int)$q['per_page'] : 20;

        $filters = [];
        if (!empty($q['status'])) $filters['status'] = $q['status'];
        if (!empty($q['vendor_type'])) $filters['vendor_type'] = $q['vendor_type'];
        if (!empty($q['city'])) $filters['city'] = $q['city'];
        if (!empty($q['search'])) $filters['search'] = $q['search'];

        $vendorModel = new Vendor();
        $result = $vendorModel->getAll($filters, $page, $perPage);

        Response::success($result);
    }

    /**
     * إحصائيات التاجر (Owner or Admin)
     * GET /api/vendor/{id}/stats
     */
    public static function stats($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!$id) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) Response::error('Vendor account not found for user', 404);
            $id = $vendor['id'];
        } else {
            $id = (int)$id;
            // If not admin ensure ownership
            if (!AuthMiddleware::isAdmin()) {
                $vendorModel = new Vendor();
                $vendor = $vendorModel->findById($id);
                if ($vendor['user_id'] != $user['id']) {
                    Response::forbidden('You do not have permission to view these stats');
                }
            }
        }

        $vendorModel = new Vendor();
        $stats = $vendorModel->getVendorStatistics($id);

        Response::success($stats);
    }

    /**
     * طلب سحب دفعة (Vendor) - بسيط للغاية (تنظيف وحساب الرصيد مطلوب)
     * POST /api/vendor/{id}/payouts/request
     */
    public static function requestPayout($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $vendorModel = new Vendor();
        if (!$id) {
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) Response::error('Vendor account not found', 404);
            $id = $vendor['id'];
        } else {
            $id = (int)$id;
        }

        // Ownership
        if (!AuthMiddleware::isAdmin()) {
            $vendor = $vendorModel->findById($id);
            if ($vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to request payout for this vendor');
            }
        }

        // حساب الرصيد المتاح
        $balance = $vendorModel->calculateBalance($id);
        $available = (float)$balance['available_for_payout'] ?? 0.0;

        $input = $_POST;
        $amount = isset($input['amount']) ? (float)$input['amount'] : $available;

        if ($amount <= 0 || $amount > $available) {
            Response::validationError(['amount' => ['Invalid payout amount']]);
        }

        // إنشاء سجل دفعة بانتظار المعالجة (vendor_payouts)
        $mysqli = connectDB();
        $sql = "INSERT INTO vendor_payouts (vendor_id, amount, status, requested_at, created_at)
                VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) Response::error('DB error', 500);
        $status = 'pending';
        $stmt->bind_param('ids', $id, $amount, $status);
        $ok = $stmt->execute();
        if ($ok) {
            $payoutId = $stmt->insert_id;
            $stmt->close();

            // Notify admin
            Notification::specialOffer(1, 'Payout Request', "Vendor {$id} requested payout of {$amount}");

            Response::created(['payout_id' => $payoutId], 'Payout request submitted');
        } else {
            $err = $stmt->error;
            $stmt->close();
            Utils::log("Payout request failed: " . $err, 'ERROR');
            Response::error('Failed to create payout request', 500);
        }
    }
}

// Router examples (actual routing done elsewhere)
// if ($path === '/api/vendor/apply' && $method === 'POST') VendorController::apply();

?>