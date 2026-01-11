<?php
// api/controllers/UserController.php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../validators/UserValidator.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/response.php';

if (is_readable(__DIR__ . '/../helpers/RBAC.php')) {
    require_once __DIR__ . '/../helpers/RBAC.php';
}

class UserController
{
    private static function checkPermission(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) {
            return true;
        }

        if (function_exists('has_permission') && has_permission('manage_users')) {
            return true;
        }

        return false;
    }

    private static function validateCSRF(array $input): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $input['csrf_token'] 
              ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
              ?? $_SERVER['HTTP_X_CSRF-TOKEN'] 
              ?? '';

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
    }

    private static function logDebug(string $message): void
    {
        $logFile = __DIR__ . '/../logs/user_controller_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] UserController: $message\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * جلب القائمة مع دعم الفلاتر الكاملة
     */
    public static function list(array $input = []): void
    {
        if (!self::checkPermission()) {
            respond_error('غير مصرح لك بالوصول لهذه الصفحة', 401);
            return;
        }

        try {
            // تجميع خيارات التصفية من المدخلات
            $opts = [
                'q'                  => trim((string)($input['q'] ?? '')),
                'role_id'            => isset($input['role_id']) && $input['role_id'] !== '' ? (int)$input['role_id'] : null,
                'is_active'          => isset($input['is_active']) && $input['is_active'] !== '' ? (int)$input['is_active'] : null,
                'country_id'         => isset($input['country_id']) && $input['country_id'] !== '' ? (int)$input['country_id'] : null,
                'city_id'            => isset($input['city_id']) && $input['city_id'] !== '' ? (int)$input['city_id'] : null,
                'preferred_language' => trim((string)($input['lang'] ?? $input['preferred_language'] ?? '')),
                'timezone'           => trim((string)($input['timezone'] ?? '')),
                'limit'              => max(10, min(500, (int)($input['limit'] ?? 100))),
            ];

            // استدعاء الموديل مع الفلاتر
            $rows = User::all($opts);

            respond([
                'success' => true,
                'data'    => $rows ?: [],
                'total'   => count($rows),
                'filters' => $opts // نرسل الفلاتر المطبقة للتأكيد في الواجهة
            ]);

        } catch (Throwable $e) {
            self::logDebug("list() error: " . $e->getMessage());
            respond_error('حدث خطأ أثناء جلب قائمة المستخدمين', 500);
        }
    }

    public static function get(array $input): void
    {
        if (!self::checkPermission()) {
            respond_error('غير مصرح', 401);
            return;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            respond_error('معرف المستخدم غير صالح', 400);
            return;
        }

        $row = User::find($id);
        if (!$row) {
            respond_error('لم يتم العثور على المستخدم', 404);
            return;
        }

        respond(['success' => true, 'data' => $row]);
    }

    /**
     * الحفظ والتحديث الذكي
     */
    public static function save(array $input): void
    {
        if (!self::checkPermission()) {
            respond_error('غير مصرح', 401);
            return;
        }

        if (!self::validateCSRF($input)) {
            respond_error('توكن CSRF غير صالح', 403);
            return;
        }

        try {
            $id = (int)($input['id'] ?? 0);
            $mode = $id > 0 ? 'update' : 'create';

            $errors = UserValidator::validate($input, $mode);
            if (!empty($errors)) {
                respond(['success' => false, 'errors' => $errors, 'message' => 'فشل التحقق من البيانات'], 422);
                return;
            }

            $data = [];
            // الحقول المسموح بتحديثها
            $fields = ['username', 'email', 'phone', 'role_id', 'country_id', 'city_id', 'preferred_language', 'timezone'];
            
            foreach ($fields as $field) {
                if (isset($input[$field]) && trim((string)$input[$field]) !== '') {
                    $data[$field] = in_array($field, ['role_id', 'country_id', 'city_id']) 
                                    ? (int)$input[$field] 
                                    : trim((string)$input[$field]);
                }
            }

            // التعامل الخاص مع الحالات المنطقية التي قد تكون 0
            if (isset($input['is_active'])) {
                $data['is_active'] = (int)$input['is_active'];
            }

            // كلمة المرور
            if (!empty($input['password'])) {
                $data['password'] = $input['password'];
            } elseif ($mode === 'create') {
                respond_error('كلمة المرور مطلوبة للمستخدم الجديد', 422);
                return;
            }

            if ($mode === 'update') {
                if (empty($data)) {
                    respond(['success' => true, 'message' => 'لا توجد بيانات جديدة للتحديث']);
                    return;
                }
                $success = User::update($id, $data);
                $message = $success ? 'تم تحديث البيانات بنجاح' : 'لم يتم تغيير أي بيانات';
            } else {
                $newId = User::create($data);
                $success = $newId !== null;
                $id = $newId;
                $message = $success ? 'تم إنشاء المستخدم بنجاح' : 'فشل إنشاء المستخدم';
            }

            respond([
                'success' => $success,
                'message' => $message,
                'data'    => ['id' => $id]
            ]);

        } catch (Throwable $e) {
            self::logDebug("save() error: " . $e->getMessage());
            respond_error('خطأ أثناء حفظ البيانات: ' . $e->getMessage(), 500);
        }
    }

    public static function delete(array $input): void
    {
        if (!self::checkPermission()) {
            respond_error('غير مصرح', 401);
            return;
        }

        if (!self::validateCSRF($input)) {
            respond_error('توكن CSRF غير صالح', 403);
            return;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            respond_error('معرف المستخدم غير صالح', 400);
            return;
        }

        try {
            $success = User::delete($id);
            respond([
                'success' => $success,
                'message' => $success ? 'تم حذف المستخدم بنجاح' : 'فشل حذف المستخدم'
            ]);
        } catch (Throwable $e) {
            self::logDebug("delete() error: " . $e->getMessage());
            respond_error('خطأ أثناء عملية الحذف', 500);
        }
    }
}
