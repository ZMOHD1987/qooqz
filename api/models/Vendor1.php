<?php
// htdocs/api/models/Vendor.php
// Model للتجار (Vendors Model)
// يحتوي على جميع العمليات المتعلقة بالتجار والمتاجر

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';

// ===========================================
// Vendor Model Class
// ===========================================

class Vendor {
    
    private $mysqli;
    
    // خصائص التاجر
    public $id;
    public $user_id;
    public $store_name;
    public $store_slug;
    public $store_logo;
    public $store_banner;
    public $store_description;
    public $business_type;
    public $business_name;
    public $commercial_register;
    public $tax_number;
    public $license_number;
    public $vendor_type;
    public $status;
    public $commission_rate;
    public $bank_name;
    public $bank_account_number;
    public $bank_account_name;
    public $iban;
    public $phone;
    public $email;
    public $website;
    public $address;
    public $city;
    public $country;
    public $postal_code;
    public $rating_average;
    public $rating_count;
    public $total_sales;
    public $total_products;
    public $approved_at;
    public $approved_by;
    public $rejection_reason;
    public $created_at;
    public $updated_at;
    
    // ===========================================
    // 1️⃣ Constructor
    // ===========================================
    
    public function __construct() {
        $this->mysqli = connectDB();
    }
    
    // ===========================================
    // 2️⃣ إنشاء تاجر جديد (Create)
    // ===========================================
    
    /**
     * إنشاء تاجر جديد
     * 
     * @param array $data
     * @return array|false
     */
    public function create($data) {
        $sql = "INSERT INTO vendors (
                    user_id,
                    store_name,
                    store_slug,
                    store_description,
                    business_type,
                    business_name,
                    commercial_register,
                    tax_number,
                    license_number,
                    vendor_type,
                    status,
                    commission_rate,
                    phone,
                    email,
                    address,
                    city,
                    country,
                    postal_code,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            Utils::log("Vendor create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }
        
        // القيم الافتراضية
        $businessType = $data['business_type'] ??  BUSINESS_TYPE_INDIVIDUAL;
        $vendorType = $data['vendor_type'] ?? VENDOR_TYPE_PRODUCT_SELLER;
        $status = $data['status'] ??  VENDOR_STATUS_PENDING;
        $commissionRate = $data['commission_rate'] ??  DEFAULT_COMMISSION_RATE;
        $storeDescription = $data['store_description'] ?? null;
        $businessName = $data['business_name'] ?? null;
        $commercialRegister = $data['commercial_register'] ?? null;
        $taxNumber = $data['tax_number'] ?? null;
        $licenseNumber = $data['license_number'] ?? null;
        $address = $data['address'] ?? null;
        $city = $data['city'] ?? null;
        $country = $data['country'] ?? 'SA';
        $postalCode = $data['postal_code'] ?? null;
        
        $stmt->bind_param(
            'issssssssssssssss',
            $data['user_id'],
            $data['store_name'],
            $data['store_slug'],
            $storeDescription,
            $businessType,
            $businessName,
            $commercialRegister,
            $taxNumber,
            $licenseNumber,
            $vendorType,
            $status,
            $commissionRate,
            $data['phone'],
            $data['email'],
            $address,
            $city,
            $country,
            $postalCode
        );
        
        if ($stmt->execute()) {
            $vendorId = $stmt->insert_id;
            $stmt->close();
            
            Utils::log("Vendor created:  ID {$vendorId}, Store: {$data['store_name']}", 'INFO');
            
            return $this->findById($vendorId);
        }
        
        $error = $stmt->error;
        $stmt->close();
        
        Utils::log("Vendor create failed:  " . $error, 'ERROR');
        return false;
    }
    
    // ===========================================
    // 3️⃣ البحث والاستعلام (Read/Find)
    // ===========================================
    
    /**
     * البحث عن تاجر بالـ ID
     * 
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $sql = "SELECT 
                    v.*,
                    u.username,
                    u.email as user_email,
                    u.phone as user_phone,
                    u.avatar as user_avatar,
                    u.first_name,
                    u.last_name
                FROM vendors v
                INNER JOIN users u ON v.user_id = u.id
                WHERE v.id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $vendor = $result->fetch_assoc();
        $stmt->close();
        
        return $vendor;
    }
    
    /**
     * البحث عن تاجر بالـ User ID
     * 
     * @param int $userId
     * @return array|null
     */
    public function findByUserId($userId) {
        $sql = "SELECT * FROM vendors WHERE user_id = ? ";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $vendor = $result->fetch_assoc();
        $stmt->close();
        
        return $this->findById($vendor['id']);
    }
    
    /**
     * البحث عن تاجر بالـ Slug
     * 
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $sql = "SELECT * FROM vendors WHERE store_slug = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $vendor = $result->fetch_assoc();
        $stmt->close();
        
        return $this->findById($vendor['id']);
    }
    
    /**
     * الحصول على جميع التجار مع فلترة
     * 
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        $where = [];
        $params = [];
        $types = '';
        
        // فلترة حسب الحالة
        if (isset($filters['status'])) {
            $where[] = "v.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        } else {
            // افتراضياً عرض النشطين فقط
            $where[] = "v.status = ? ";
            $params[] = VENDOR_STATUS_ACTIVE;
            $types .= 's';
        }
        
        // فلترة حسب نوع التاجر
        if (isset($filters['vendor_type'])) {
            $where[] = "v.vendor_type = ?";
            $params[] = $filters['vendor_type'];
            $types .= 's';
        }
        
        // فلترة حسب نوع العمل
        if (isset($filters['business_type'])) {
            $where[] = "v.business_type = ?";
            $params[] = $filters['business_type'];
            $types .= 's';
        }
        
        // فلترة حسب المدينة
        if (isset($filters['city'])) {
            $where[] = "v. city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }
        
        // فلترة حسب الدولة
        if (isset($filters['country'])) {
            $where[] = "v.country = ?";
            $params[] = $filters['country'];
            $types .= 's';
        }
        
        // بحث نصي
        if (isset($filters['search'])) {
            $where[] = "(v.store_name LIKE ?  OR v.business_name LIKE ?  OR v.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] .  '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types . = 'sss';
        }
        
        // بناء الـ WHERE clause
        $whereClause = ! empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // حساب الإجمالي
        $countSql = "SELECT COUNT(*) as total FROM vendors v {$whereClause}";
        $stmt = $this->mysqli->prepare($countSql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();
        
        // الترتيب
        $orderBy = "v.created_at DESC";
        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'name_asc':
                    $orderBy = "v.store_name ASC";
                    break;
                case 'name_desc':
                    $orderBy = "v.store_name DESC";
                    break;
                case 'rating': 
                    $orderBy = "v.rating_average DESC, v.rating_count DESC";
                    break;
                case 'sales':
                    $orderBy = "v.total_sales DESC";
                    break;
                case 'products':
                    $orderBy = "v.total_products DESC";
                    break;
                case 'newest':
                    $orderBy = "v.created_at DESC";
                    break;
            }
        }
        
        // جلب البيانات
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT 
                    v. id,
                    v.user_id,
                    v.store_name,
                    v. store_slug,
                    v.store_logo,
                    v.store_description,
                    v.vendor_type,
                    v. status,
                    v.rating_average,
                    v. rating_count,
                    v.total_sales,
                    v.total_products,
                    v.city,
                    v.country,
                    v.created_at,
                    u.username
                FROM vendors v
                INNER JOIN users u ON v.user_id = u.id
                {$whereClause}
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ? ";
        
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, .. .$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }
        
        $stmt->close();
        
        return [
            'data' => $vendors,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * التجار المميزين (أعلى تقييم)
     * 
     * @param int $limit
     * @return array
     */
    public function getFeatured($limit = 10) {
        $result = $this->getAll(['sort' => 'rating'], 1, $limit);
        return $result['data'];
    }
    
    /**
     * التجار الأكثر مبيعاً
     * 
     * @param int $limit
     * @return array
     */
    public function getTopSellers($limit = 10) {
        $result = $this->getAll(['sort' => 'sales'], 1, $limit);
        return $result['data'];
    }
    
    /**
     * التجار الجدد
     * 
     * @param int $limit
     * @return array
     */
    public function getNewVendors($limit = 10) {
        $result = $this->getAll(['sort' => 'newest'], 1, $limit);
        return $result['data'];
    }
    
    /**
     * التجار في انتظار الموافقة
     * 
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPending($page = 1, $perPage = 20) {
        return $this->getAll(['status' => VENDOR_STATUS_PENDING], $page, $perPage);
    }
    
    // ===========================================
    // 4️⃣ تحديث تاجر (Update)
    // ===========================================
    
    /**
     * تحديث بيانات التاجر
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        $types = '';
        
        // الحقول المسموح بتحديثها
        $allowedFields = [
            'store_name' => 's',
            'store_slug' => 's',
            'store_logo' => 's',
            'store_banner' => 's',
            'store_description' => 's',
            'business_type' => 's',
            'business_name' => 's',
            'commercial_register' => 's',
            'tax_number' => 's',
            'license_number' => 's',
            'vendor_type' => 's',
            'status' => 's',
            'commission_rate' => 'd',
            'bank_name' => 's',
            'bank_account_number' => 's',
            'bank_account_name' => 's',
            'iban' => 's',
            'phone' => 's',
            'email' => 's',
            'website' => 's',
            'address' => 's',
            'city' => 's',
            'country' => 's',
            'postal_code' => 's'
        ];
        
        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        
        $sql = "UPDATE vendors SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            Utils::log("Vendor update prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }
        
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            Utils::log("Vendor updated:  ID {$id}", 'INFO');
        }
        
        return $success;
    }
    
    /**
     * تحديث شعار المتجر
     * 
     * @param int $id
     * @param string $logoUrl
     * @return bool
     */
    public function updateLogo($id, $logoUrl) {
        return $this->update($id, ['store_logo' => $logoUrl]);
    }
    
    /**
     * تحديث بنر المتجر
     * 
     * @param int $id
     * @param string $bannerUrl
     * @return bool
     */
    public function updateBanner($id, $bannerUrl) {
        return $this->update($id, ['store_banner' => $bannerUrl]);
    }
    
    /**
     * تحديث نسبة العمولة
     * 
     * @param int $id
     * @param float $commissionRate
     * @return bool
     */
    public function updateCommissionRate($id, $commissionRate) {
        $success = $this->update($id, ['commission_rate' => $commissionRate]);
        
        if ($success) {
            Utils:: log("Commission rate updated for vendor ID {$id}:  {$commissionRate}%", 'INFO');
        }
        
        return $success;
    }
    
    /**
     * تحديث إحصائيات التاجر
     * 
     * @param int $id
     * @return bool
     */
    public function updateStatistics($id) {
        // حساب عدد المنتجات
        $productsSql = "SELECT COUNT(*) as count FROM products WHERE vendor_id = ?  AND is_active = 1";
        $stmt = $this->mysqli->prepare($productsSql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $productsCount = $result->fetch_assoc()['count'];
        $stmt->close();
        
        // حساب إجمالي المبيعات
        $salesSql = "SELECT SUM(p.total_sales) as total FROM products p WHERE p.vendor_id = ? ";
        $stmt = $this->mysqli->prepare($salesSql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalSales = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        // تحديث
        $sql = "UPDATE vendors SET total_products = ?, total_sales = ? WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('iii', $productsCount, $totalSales, $id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    // ===========================================
    // 5️⃣ الموافقة والرفض (Approval)
    // ===========================================
    
    /**
     * الموافقة على تاجر
     * 
     * @param int $id
     * @param int $approvedBy معرف المدير
     * @return bool
     */
    public function approve($id, $approvedBy) {
        $sql = "UPDATE vendors 
                SET status = ?,
                    approved_at = NOW(),
                    approved_by = ?,
                    rejection_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (! $stmt) {
            return false;
        }
        
        $status = VENDOR_STATUS_ACTIVE;
        $stmt->bind_param('sii', $status, $approvedBy, $id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            Utils::log("Vendor approved:  ID {$id} by Admin ID {$approvedBy}", 'INFO');
            Security::logSecurityEvent('vendor_approved', "Vendor ID:  {$id}, Approved by:  {$approvedBy}");
        }
        
        return $success;
    }
    
    /**
     * رفض تاجر
     * 
     * @param int $id
     * @param string $reason
     * @return bool
     */
    public function reject($id, $reason) {
        $sql = "UPDATE vendors 
                SET status = ?,
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $status = VENDOR_STATUS_REJECTED;
        $stmt->bind_param('ssi', $status, $reason, $id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            Utils::log("Vendor rejected: ID {$id}, Reason: {$reason}", 'WARNING');
            Security::logSecurityEvent('vendor_rejected', "Vendor ID: {$id}, Reason: {$reason}");
        }
        
        return $success;
    }
    
    /**
     * تعليق تاجر
     * 
     * @param int $id
     * @param string $reason
     * @return bool
     */
    public function suspend($id, $reason = null) {
        $success = $this->update($id, ['status' => VENDOR_STATUS_SUSPENDED]);
        
        if ($success) {
            Utils::log("Vendor suspended: ID {$id}, Reason: {$reason}", 'WARNING');
            Security::logSecurityEvent('vendor_suspended', "Vendor ID: {$id}, Reason: {$reason}");
        }
        
        return $success;
    }
    
    /**
     * إلغاء تعليق تاجر
     * 
     * @param int $id
     * @return bool
     */
    public function unsuspend($id) {
        return $this->update($id, ['status' => VENDOR_STATUS_ACTIVE]);
    }
    
    /**
     * إلغاء تفعيل تاجر
     * 
     * @param int $id
     * @return bool
     */
    public function deactivate($id) {
        return $this->update($id, ['status' => VENDOR_STATUS_INACTIVE]);
    }
    
    /**
     * إعادة تفعيل تاجر
     * 
     * @param int $id
     * @return bool
     */
    public function activate($id) {
        return $this->update($id, ['status' => VENDOR_STATUS_ACTIVE]);
    }
    
    // ===========================================
    // 6️⃣ المستندات (Documents)
    // ===========================================
    
    /**
     * الحصول على مستندات التاجر
     * 
     * @param int $vendorId
     * @return array
     */
    public function getDocuments($vendorId) {
        $sql = "SELECT * FROM vendor_documents 
                WHERE vendor_id = ?  
                ORDER BY document_type, uploaded_at DESC";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        $stmt->close();
        
        return $documents;
    }
    
    /**
     * إضافة مستند
     * 
     * @param int $vendorId
     * @param string $documentType
     * @param string $fileUrl
     * @param string|null $documentNumber
     * @param string|null $expiryDate
     * @return bool
     */
    public function addDocument($vendorId, $documentType, $fileUrl, $documentNumber = null, $expiryDate = null) {
        $sql = "INSERT INTO vendor_documents 
                (vendor_id, document_type, file_url, document_number, expiry_date, status, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (! $stmt) {
            return false;
        }
        
        $status = DOCUMENT_STATUS_PENDING;
        $stmt->bind_param('isssss', $vendorId, $documentType, $fileUrl, $documentNumber, $expiryDate, $status);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            Utils:: log("Document uploaded for vendor ID {$vendorId}: {$documentType}", 'INFO');
        }
        
        return $success;
    }
    
    /**
     * التحقق من مستند
     * 
     * @param int $documentId
     * @param int $verifiedBy
     * @return bool
     */
    public function verifyDocument($documentId, $verifiedBy) {
        $sql = "UPDATE vendor_documents 
                SET status = ?,
                    verified_at = NOW(),
                    verified_by = ? 
                WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $status = DOCUMENT_STATUS_APPROVED;
        $stmt->bind_param('sii', $status, $verifiedBy, $documentId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * رفض مستند
     * 
     * @param int $documentId
     * @param string $reason
     * @return bool
     */
    public function rejectDocument($documentId, $reason) {
        $sql = "UPDATE vendor_documents 
                SET status = ?,
                    rejection_reason = ? 
                WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $status = DOCUMENT_STATUS_REJECTED;
        $stmt->bind_param('ssi', $status, $reason, $documentId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    // ===========================================
    // 7️⃣ التقييمات (Reviews)
    // ===========================================
    
    /**
     * الحصول على تقييمات التاجر
     * 
     * @param int $vendorId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getReviews($vendorId, $page = 1, $perPage = 20) {
        // حساب الإجمالي
        $countSql = "SELECT COUNT(*) as total FROM vendor_reviews WHERE vendor_id = ? ";
        $stmt = $this->mysqli->prepare($countSql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();
        
        // جلب التقييمات
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT 
                    vr.*,
                    u.username,
                    u.avatar
                FROM vendor_reviews vr
                INNER JOIN users u ON vr. user_id = u.id
                WHERE vr.vendor_id = ? 
                ORDER BY vr.created_at DESC
                LIMIT ?  OFFSET ?";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('iii', $vendorId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        $stmt->close();
        
        return [
            'data' => $reviews,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * تحديث متوسط التقييم
     * 
     * @param int $vendorId
     * @return bool
     */
    public function updateRating($vendorId) {
        $sql = "UPDATE vendors 
                SET rating_average = (
                    SELECT AVG(rating) FROM vendor_reviews WHERE vendor_id = ? 
                ),
                rating_count = (
                    SELECT COUNT(*) FROM vendor_reviews WHERE vendor_id = ?
                )
                WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('iii', $vendorId, $vendorId, $vendorId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    // ===========================================
    // 8️⃣ الدفعات (Payouts)
    // ===========================================
    
    /**
     * الحصول على دفعات التاجر
     * 
     * @param int $vendorId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPayouts($vendorId, $page = 1, $perPage = 20) {
        $countSql = "SELECT COUNT(*) as total FROM vendor_payouts WHERE vendor_id = ?";
        $stmt = $this->mysqli->prepare($countSql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM vendor_payouts 
                WHERE vendor_id = ? 
                ORDER BY created_at DESC
                LIMIT ? OFFSET ? ";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('iii', $vendorId, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payouts = [];
        while ($row = $result->fetch_assoc()) {
            $payouts[] = $row;
        }
        
        $stmt->close();
        
        return [
            'data' => $payouts,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * حساب الرصيد المستحق
     * 
     * @param int $vendorId
     * @return array
     */
    public function calculateBalance($vendorId) {
        $sql = "SELECT 
                    COALESCE(SUM(oi.total), 0) as total_revenue,
                    COALESCE(SUM(vo.commission_amount), 0) as total_commission,
                    COALESCE(SUM(vo.payout_amount), 0) as gross_amount
                FROM vendor_orders vo
                INNER JOIN order_items oi ON vo.order_id = oi.order_id
                WHERE vo.vendor_id = ?  
                AND vo.payout_status = 'pending'";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $balance = $result->fetch_assoc();
        $stmt->close();
        
        // المبالغ المدفوعة
        $paidSql = "SELECT COALESCE(SUM(payout_amount), 0) as total_paid 
                    FROM vendor_payouts 
                    WHERE vendor_id = ? AND status = 'completed'";
        
        $stmt = $this->mysqli->prepare($paidSql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $paid = $result->fetch_assoc();
        $stmt->close();
        
        return [
            'pending_amount' => $balance['gross_amount'],
            'total_revenue' => $balance['total_revenue'],
            'total_commission' => $balance['total_commission'],
            'total_paid' => $paid['total_paid'],
            'available_for_payout' => $balance['gross_amount']
        ];
    }
    
    // ===========================================
    // 9️⃣ الاشتراكات (Subscriptions)
    // ===========================================
    
    /**
     * الحصول على اشتراك التاجر الحالي
     * 
     * @param int $vendorId
     * @return array|null
     */
    public function getCurrentSubscription($vendorId) {
        $sql = "SELECT * FROM vendor_subscriptions 
                WHERE vendor_id = ? 
                AND status = 'active'
                AND (end_date IS NULL OR end_date > NOW())
                ORDER BY start_date DESC
                LIMIT 1";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $subscription = $result->fetch_assoc();
        $stmt->close();
        
        return $subscription;
    }
    
    // ===========================================
    // 🔟 إحصائيات (Statistics)
    // ===========================================
    
    /**
     * عدد التجار حسب الحالة
     * 
     * @param string $status
     * @return int
     */
    public function countByStatus($status) {
        $sql = "SELECT COUNT(*) as count FROM vendors WHERE status = ?";
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'];
    }
    
    /**
     * إحصائيات عامة للتجار
     * 
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_vendors,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vendors,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_vendors,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_vendors,
                    SUM(total_sales) as total_sales,
                    SUM(total_products) as total_products,
                    AVG(rating_average) as avg_rating
                FROM vendors";
        
        $result = $this->mysqli->query($sql);
        $stats = $result->fetch_assoc();
        
        return $stats;
    }
    
    /**
     * إحصائيات التاجر
     * 
     * @param int $vendorId
     * @return array
     */
    public function getVendorStatistics($vendorId) {
        $vendor = $this->findById($vendorId);
        
        if (!$vendor) {
            return null;
        }
        
        // عدد الطلبات
        $ordersSql = "SELECT COUNT(*) as count FROM vendor_orders WHERE vendor_id = ? ";
        $stmt = $this->mysqli->prepare($ordersSql);
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ordersCount = $result->fetch_assoc()['count'];
        $stmt->close();
        
        // الرصيد
        $balance = $this->calculateBalance($vendorId);
        
        return [
            'total_products' => $vendor['total_products'],
            'total_sales' => $vendor['total_sales'],
            'total_orders' => $ordersCount,
            'rating_average' => $vendor['rating_average'],
            'rating_count' => $vendor['rating_count'],
            'pending_amount' => $balance['pending_amount'],
            'total_paid' => $balance['total_paid']
        ];
    }
    
    // ===========================================
    // 1️⃣1️⃣ التحقق من التفرد (Uniqueness)
    // ===========================================
    
    /**
     * التحقق من أن Slug فريد
     * 
     * @param string $slug
     * @param int|null $exceptId
     * @return bool
     */
    public function isSlugUnique($slug, $exceptId = null) {
        $sql = "SELECT COUNT(*) as count FROM vendors WHERE store_slug = ? ";
        
        if ($exceptId) {
            $sql .= " AND id != ? ";
        }
        
        $stmt = $this->mysqli->prepare($sql);
        
        if ($exceptId) {
            $stmt->bind_param('si', $slug, $exceptId);
        } else {
            $stmt->bind_param('s', $slug);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] == 0;
    }
    
    /**
     * التحقق من أن البريد فريد
     * 
     * @param string $email
     * @param int|null $exceptId
     * @return bool
     */
    public function isEmailUnique($email, $exceptId = null) {
        $sql = "SELECT COUNT(*) as count FROM vendors WHERE email = ?";
        
        if ($exceptId) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $this->mysqli->prepare($sql);
        
        if ($exceptId) {
            $stmt->bind_param('si', $email, $exceptId);
        } else {
            $stmt->bind_param('s', $email);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] == 0;
    }
}

// ===========================================
// ✅ تم تحميل Vendor Model بنجاح
// ===========================================

?>