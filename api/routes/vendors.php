<?php
declare(strict_types=1);

/**
 * /api/routes/vendors.php
 * نقطة دخول موحدة لكل عمليات Vendors
 * يعمل 100% على استضافات مجانية مثل rf.gd
 */

header('Content-Type: application/json; charset=utf-8');

// CORS بسيط
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ابدأ الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل الاتصال بقاعدة البيانات
require_once __DIR__ . '/../config/db.php';
$conn = connectDB();
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// CSRF بسيط
function validate_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// المستخدم الحالي
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1;

// مسار رفع الصور
define('UPLOAD_DIR', __DIR__ . '/../../uploads/vendors');
define('UPLOAD_URL', '/uploads/vendors');

// تأكد من وجود مجلد الرفع
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

$method = $_SERVER['REQUEST_METHOD'];

// === GET: جلب البيانات ===
if ($method === 'GET') {
    // جلب صف واحد
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vendor = $result->fetch_assoc();
        $stmt->close();

        if (!$vendor) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Not found']);
            exit;
        }

        // جلب الترجمات
        $tstmt = $conn->prepare("SELECT language_code, description, return_policy, shipping_policy, meta_title, meta_description FROM vendor_translations WHERE vendor_id = ?");
        $tstmt->bind_param('i', $id);
        $tstmt->execute();
        $tres = $tstmt->get_result();
        $translations = [];
        while ($tr = $tres->fetch_assoc()) {
            $translations[$tr['language_code']] = $tr;
        }
        $tstmt->close();

        $vendor['translations'] = $translations;

        echo json_encode(['success' => true, 'data' => $vendor]);
        exit;
    }

    // جلب المتاجر الأب
    if (isset($_GET['parents'])) {
        $sql = "SELECT id, store_name, slug FROM vendors WHERE is_branch = 0 ORDER BY store_name";
        $result = $conn->query($sql);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // القائمة مع فلاتر
    $sql = "SELECT * FROM vendors WHERE 1=1";
    $params = [];
    $types = '';

    if (!$is_admin) {
        $sql .= " AND user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }

    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $sql .= " AND (store_name LIKE ? OR email LIKE ? OR slug LIKE ?)";
        $params[] = $search; $params[] = $search; $params[] = $search;
        $types .= 'sss';
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (isset($_GET['is_verified'])) {
        $sql .= " AND is_verified = ?";
        $params[] = (int)$_GET['is_verified'];
        $types .= 'i';
    }

    if (!empty($_GET['country_id'])) {
        $sql .= " AND country_id = ?";
        $params[] = (int)$_GET['country_id'];
        $types .= 'i';
    }

    if (!empty($_GET['city_id'])) {
        $sql .= " AND city_id = ?";
        $params[] = (int)$_GET['city_id'];
        $types .= 'i';
    }

    if (!empty($_GET['phone'])) {
        $sql .= " AND phone LIKE ?";
        $params[] = '%' . $_GET['phone'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['email'])) {
        $sql .= " AND email LIKE ?";
        $params[] = '%' . $_GET['email'] . '%';
        $types .= 's';
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'total' => count($rows)
    ]);
    exit;
}

// === POST: حفظ، حذف، تبديل التحقق ===
if ($method === 'POST') {
    if (!validate_csrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? 'save';

    // حذف
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $check = $conn->query("SELECT user_id FROM vendors WHERE id = $id")->fetch_assoc();
        if (!$check || (!$is_admin && $check['user_id'] != $user_id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        $conn->query("DELETE FROM vendors WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }

    // تبديل التحقق
    if ($action === 'toggle_verify') {
        if (!$is_admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $value = (int)($_POST['value'] ?? 0);
        $conn->query("UPDATE vendors SET is_verified = $value WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }

    // حفظ (إنشاء أو تعديل)
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $is_edit = $id > 0;

        // التحقق من الصلاحيات
        if ($is_edit) {
            $check = $conn->query("SELECT user_id FROM vendors WHERE id = $id")->fetch_assoc();
            if (!$check || (!$is_admin && $check['user_id'] != $user_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }
        } else {
            if ($user_id === 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }

        // الحقول المسموحة
        $fields = ['store_name','slug','vendor_type','store_type','is_branch','parent_vendor_id','branch_code',
            'inherit_settings','inherit_products','inherit_commission','phone','mobile','email','website_url',
            'registration_number','tax_number','country_id','city_id','address','postal_code','latitude','longitude',
            'commission_rate','service_radius','accepts_online_booking','average_response_time'];

        $data = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $data[$f] = $_POST[$f] === '' ? null : $_POST[$f];
            }
        }

        if (!$is_admin) {
            unset($data['status'], $data['is_verified'], $data['is_featured']);
        }

        // رفع الصور
        $image_fields = ['logo' => 'logo_url', 'cover' => 'cover_image_url', 'banner' => 'banner_url'];
        foreach ($image_fields as $input => $column) {
            if (!empty($_FILES["vendor_$input"]['name'])) {
                $vendor_dir = UPLOAD_DIR . '/' . ($is_edit ? $id : 'temp');
                if (!is_dir($vendor_dir)) mkdir($vendor_dir, 0755, true);

                $ext = pathinfo($_FILES["vendor_$input"]['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $target = $vendor_dir . '/' . $filename;

                if (move_uploaded_file($_FILES["vendor_$input"]['tmp_name'], $target)) {
                    $data[$column] = UPLOAD_URL . '/' . ($is_edit ? $id : 'temp') . '/' . $filename;
                }
            }
        }

        // حفظ في جدول vendors
        if ($is_edit) {
            $sets = [];
            foreach ($data as $k => $v) $sets[] = "$k = " . ($v === null ? 'NULL' : "'".$conn->real_escape_string($v)."'");
            $sql = "UPDATE vendors SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = $id";
        } else {
            $data['user_id'] = $user_id;
            $cols = array_keys($data);
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'".$conn->real_escape_string($v)."'", array_values($data));
            $sql = "INSERT INTO vendors (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        }

        if (!$conn->query($sql)) {
            echo json_encode(['success' => false, 'message' => 'DB error']);
            exit;
        }

        $vendor_id = $is_edit ? $id : $conn->insert_id;

        // بعد الإدراج، انقل الصور إلى مجلد المتجر الجديد
        if (!$is_edit && isset($data['logo_url'])) {
            $temp_dir = UPLOAD_DIR . '/temp';
            $new_dir = UPLOAD_DIR . '/' . $vendor_id;
            if (is_dir($temp_dir)) {
                if (!is_dir($new_dir)) mkdir($new_dir, 0755, true);
                foreach (glob($temp_dir . '/*') as $file) {
                    rename($file, $new_dir . '/' . basename($file));
                    $data[str_replace('vendor_', '', basename($file, pathinfo($file, PATHINFO_EXTENSION)) . '_url')] = str_replace('/temp', '/' . $vendor_id, $data[str_replace('vendor_', '', basename($file, pathinfo($file, PATHINFO_EXTENSION)) . '_url')]);
                }
                rmdir($temp_dir);
            }
        }

        // حفظ الترجمات
        if (!empty($_POST['translations'])) {
            $trans = json_decode($_POST['translations'], true);
            if (is_array($trans)) {
                $conn->query("DELETE FROM vendor_translations WHERE vendor_id = $vendor_id");
                $stmt = $conn->prepare("INSERT INTO vendor_translations (vendor_id, language_code, description, return_policy, shipping_policy, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($trans as $lang => $t) {
                    $stmt->bind_param('issssss', $vendor_id, $lang,
                        $t['description'] ?? null,
                        $t['return_policy'] ?? null,
                        $t['shipping_policy'] ?? null,
                        $t['meta_title'] ?? null,
                        $t['meta_description'] ?? null
                    );
                    $stmt->execute();
                }
                $stmt->close();
            }
        }

        echo json_encode(['success' => true, 'data' => ['id' => $vendor_id]]);
        exit;
    }
}

// خطأ عام
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
