<?php
// htdocs/api/controllers/UserController.php
// Controller لإدارة المستخدمين (ملفات التعريف، تحديث، كلمات المرور، إدارة بواسطة المدير)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/upload.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

class UserController
{
    /**
     * جلب الملف الشخصي للمستخدم المصادق عليه
     * GET /api/user/me
     */
    public static function me()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            Response::unauthorized();
        }

        // تحديث آخر نشاط (اختياري)
        AuthMiddleware::updateLastActivity($user['id']);

        // أزل الحقول الحساسة
        unset($user['password']);

        Response::success($user);
    }

    /**
     * تحديث الملف الشخصي للمستخدم المصادق عليه
     * PUT /api/user/profile
     */
    public static function updateProfile()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $input = $_POST;

        $rules = [
            'username' => "optional|string|min:3|max:50|alpha_dash|unique:users,username,{$user['id']}",
            'email' => "optional|email|unique:users,email,{$user['id']}",
            'phone' => "optional|saudi_phone|unique:users,phone,{$user['id']}",
            'first_name' => 'optional|string|max:60',
            'last_name' => 'optional|string|max:60',
            'language' => 'optional|string',
            'currency' => 'optional|string',
            'timezone' => 'optional|string',
            'bio' => 'optional|string|max:1000'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $userModel = new User();
        $ok = $userModel->update($user['id'], $validated);

        if ($ok) {
            $updated = $userModel->findById($user['id']);
            unset($updated['password']);
            Response::success($updated, 'Profile updated successfully');
        }

        Response::error('Failed to update profile', 500);
    }

    /**
     * تغيير كلمة المرور
     * POST /api/user/change-password
     */
    public static function changePassword()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $input = $_POST;
        $rules = [
            'current_password' => 'required|string',
            'new_password' => 'required|min:8|strong_password',
            'new_password_confirmation' => 'required|same:new_password'
        ];

        Validator::make($input, $rules)->validated();

        $userModel = new User();
        $dbUser = $userModel->findById($user['id']);

        if (!Security::verifyPassword($input['current_password'], $dbUser['password'])) {
            Response::validationError(['current_password' => ['Current password is incorrect']]);
        }

        $ok = $userModel->updatePassword($user['id'], $input['new_password']);
        if ($ok) {
            // إنهاء جميع الجلسات بعد تغيير كلمة المرور
            AuthMiddleware::terminateAllSessions($user['id']);
            Response::success(null, 'Password changed successfully');
        }

        Response::error('Failed to change password', 500);
    }

    /**
     * رفع / تحديث الصورة الشخصية
     * POST /api/user/avatar
     * يتوقع ملف باسم "avatar"
     */
    public static function uploadAvatar()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!isset($_FILES['avatar'])) {
            Response::validationError(['avatar' => ['Avatar file is required']]);
        }

        // استخدم Upload helper
        $result = Upload::uploadImage($_FILES['avatar'], 'users', 800, 800, true);

        if (!$result['success']) {
            Response::error($result['message'] ?? 'Upload failed', 400);
        }

        $avatarUrl = $result['file_url'];

        $userModel = new User();
        $ok = $userModel->updateAvatar($user['id'], $avatarUrl);

        if ($ok) {
            $updated = $userModel->findById($user['id']);
            unset($updated['password']);
            Response::success($updated, 'Avatar uploaded successfully');
        }

        Response::error('Failed to update avatar', 500);
    }

    /**
     * الحصول على قائمة المستخدمين (للـ Admin)
     * GET /api/admin/users
     */
    public static function listUsers()
    {
        $admin = RoleMiddleware::canRead('users'); // throws if not allowed

        $q = $_GET;
        $page = isset($q['page']) ? (int)$q['page'] : 1;
        $perPage = isset($q['per_page']) ? (int)$q['per_page'] : 20;

        $filters = [];
        if (!empty($q['user_type'])) $filters['user_type'] = $q['user_type'];
        if (!empty($q['status'])) $filters['status'] = $q['status'];
        if (!empty($q['search'])) $filters['search'] = $q['search'];
        if (isset($q['is_verified'])) $filters['is_verified'] = (int)$q['is_verified'];

        $userModel = new User();
        $result = $userModel->getAll($filters, $page, $perPage);

        Response::success($result);
    }

    /**
     * جلب مستخدم حسب ID (Admin)
     * GET /api/admin/users/{id}
     */
    public static function getUser($id = null)
    {
        RoleMiddleware::canRead('users');

        if (!$id) {
            Response::validationError(['id' => ['User id is required']]);
        }

        $userModel = new User();
        $u = $userModel->findById((int)$id);
        if (!$u) Response::error('User not found', 404);
        unset($u['password']);
        Response::success($u);
    }

    /**
     * تحديث مستخدم بواسطة Admin
     * PUT /api/admin/users/{id}
     */
    public static function updateUser($id = null)
    {
        RoleMiddleware::canUpdate('users');

        if (!$id) Response::validationError(['id' => ['User id is required']]);
        $input = $_POST;

        $userModel = new User();
        $existing = $userModel->findById((int)$id);
        if (!$existing) Response::error('User not found', 404);

        $rules = [
            'username' => "optional|string|min:3|max:50|alpha_dash|unique:users,username,{$id}",
            'email' => "optional|email|unique:users,email,{$id}",
            'phone' => "optional|saudi_phone|unique:users,phone,{$id}",
            'first_name' => 'optional|string|max:60',
            'last_name' => 'optional|string|max:60',
            'user_type' => 'optional|in:customer,vendor,admin,super_admin,support,moderator',
            'status' => 'optional|string',
            'is_verified' => 'optional|boolean'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $ok = $userModel->update((int)$id, $validated);
        if ($ok) {
            $updated = $userModel->findById((int)$id);
            unset($updated['password']);
            Response::success($updated, 'User updated successfully');
        }

        Response::error('Failed to update user', 500);
    }

    /**
     * حذف مستخدم (soft/hard) بواسطة Admin
     * DELETE /api/admin/users/{id}
     */
    public static function deleteUser($id = null)
    {
        RoleMiddleware::canDelete('users');

        if (!$id) Response::validationError(['id' => ['User id is required']]);

        $hard = isset($_GET['hard']) && $_GET['hard'] == '1';

        $userModel = new User();
        $exists = $userModel->findById((int)$id);
        if (!$exists) Response::error('User not found', 404);

        $success = $hard ? $userModel->delete((int)$id) : $userModel->softDelete((int)$id);

        if ($success) {
            Response::success(null, $hard ? 'User permanently deleted' : 'User soft deleted');
        }

        Response::error('Failed to delete user', 500);
    }

    /**
     * تعليق / رفع تعليق عن مستخدم (Admin)
     * POST /api/admin/users/{id}/suspend
     */
    public static function suspendUser($id = null)
    {
        RoleMiddleware::canUpdate('users');

        if (!$id) Response::validationError(['id' => ['User id is required']]);

        $input = $_POST;
        $reason = $input['reason'] ?? null;

        $userModel = new User();
        $exists = $userModel->findById((int)$id);
        if (!$exists) Response::error('User not found', 404);

        $ok = $userModel->suspend((int)$id, $reason);
        if ($ok) {
            Response::success(null, 'User suspended');
        }

        Response::error('Failed to suspend user', 500);
    }

    /**
     * إلغاء تعليق مستخدم (Admin)
     * POST /api/admin/users/{id}/unsuspend
     */
    public static function unsuspendUser($id = null)
    {
        RoleMiddleware::canUpdate('users');

        if (!$id) Response::validationError(['id' => ['User id is required']]);

        $userModel = new User();
        $exists = $userModel->findById((int)$id);
        if (!$exists) Response::error('User not found', 404);

        $ok = $userModel->unsuspend((int)$id);
        if ($ok) {
            Response::success(null, 'User unsuspended');
        }

        Response::error('Failed to unsuspend user', 500);
    }

    /**
     * إحصائيات المستخدمين (Admin)
     * GET /api/admin/users/stats
     */
    public static function stats()
    {
        RoleMiddleware::canRead('users');

        $userModel = new User();
        $stats = $userModel->getStatistics();

        Response::success($stats);
    }
}

// الملاحظة: الطرق هنا مهيأة للاستخدام مع الراوتر العام.
// مثال: if ($path === '/api/user/me') UserController::me();

?>