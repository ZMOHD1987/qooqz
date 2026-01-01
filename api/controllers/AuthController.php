<?php
// htdocs/api/controllers/AuthController.php
// Controller للمصادقة (Registration, Login, Logout, Refresh Token, Password Reset, OTP, Email Verify)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/mail.php';
require_once __DIR__ . '/../helpers/sms.php';
require_once __DIR__ . '/../middleware/rate_limit.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController
{
    /**
     * تسجيل مستخدم جديد
     * POST /api/auth/register
     */
    public static function register()
    {
        RateLimitMiddleware::forRegister(Security::getRealIP());

        $input = $_POST;

        $rules = [
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'phone' => 'optional|saudi_phone|unique:users,phone',
            'password' => 'required|min:8|strong_password',
            'password_confirmation' => 'required|same:password',
            'user_type' => 'optional|in:customer,vendor'
        ];

        $validator = Validator::make($input, $rules);
        $validated = $validator->validated();

        $userModel = new User();

        $createData = [
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'user_type' => $validated['user_type'] ?? USER_TYPE_CUSTOMER,
            'first_name' => $input['first_name'] ?? null,
            'last_name' => $input['last_name'] ?? null,
            'language' => $input['language'] ?? DEFAULT_LANGUAGE,
            'currency' => $input['currency'] ?? DEFAULT_CURRENCY,
            'timezone' => $input['timezone'] ?? DEFAULT_TIMEZONE,
            'status' => USER_STATUS_PENDING,
            'is_verified' => 0
        ];

        $newUser = $userModel->create($createData);

        if (!$newUser) {
            Response::error('Failed to create user', 500);
        }

        // إرسال بريد التحقق (إن رغبت)
        if (MAIL_ENABLED && !empty($newUser['email'])) {
            // توليد رمز تحقق أو رابط
            $token = JWT::createVerificationToken($newUser['id'], $newUser['email'] ?? '');
            Mail::sendPasswordReset($newUser['email'], $newUser['username'], $token); // أو قالب مخصص للverify
        }

        Response::created([
            'user' => $newUser
        ], 'User registered successfully. Please verify your email/phone if required.');
    }

    /**
     * تسجيل الدخول (باستخدام email/username/phone + password)
     * POST /api/auth/login
     */
    public static function login()
    {
        RateLimitMiddleware::forLogin(Security::getRealIP());

        $input = $_POST;
        $identifier = trim($input['identifier'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            Response::validationError(['identifier' => ['Identifier and password are required']]);
        }

        $userModel = new User();
        $user = $userModel->findByIdentifier($identifier);

        if (!$user) {
            // تسجيل محاولة فاشلة
            Security::recordFailedLogin($identifier);
            Response::unauthorized('Invalid credentials');
        }

        // تحقق من القفل
        $lock = Security::checkLoginLock($identifier);
        if ($lock['locked']) {
            Response::tooManyRequests('Account temporarily locked. Try again in ' . $lock['lock_time'] . ' seconds');
        }

        if (!Security::verifyPassword($password, $user['password'])) {
            Security::recordFailedLogin($identifier);
            Response::unauthorized('Invalid credentials');
        }

        // تحقق من الحالة
        if ($user['status'] !== USER_STATUS_ACTIVE) {
            Response::forbidden('Account is not active');
        }

        // إنشاء access & refresh tokens
        $accessToken = JWT::createAccessToken($user['id'], $user['user_type']);
        $refreshToken = JWT::createRefreshToken($user['id'], $user['user_type']);

        // حفظ الجلسة إن رغبت (مثال)
        $tokenHash = hash('sha256', $refreshToken);
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, is_active, expires_at, created_at)
                                    VALUES (?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())");
        if ($stmt) {
            $expires = REFRESH_TOKEN_EXPIRES; // من config
            $ip = Security::getRealIP();
            $ua = Security::getUserAgent();
            $stmt->bind_param('isssi', $user['id'], $tokenHash, $ip, $ua, $expires);
            $stmt->execute();
            $stmt->close();
        }

        // تسجيل نجاح الدخول
        AuthMiddleware::logLogin($user['id'], Security::getRealIP(), Security::getUserAgent());
        Security::resetLoginAttempts($identifier);

        // أزل كلمة المرور من الاستجابة
        unset($user['password']);

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => JWT::getAccessTokenTTL(),
            'user' => $user
        ], 'Login successful');
    }

    /**
     * Refresh token
     * POST /api/auth/refresh
     */
    public static function refresh()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $refreshToken = $input['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::unauthorized('Refresh token required');
        }

        $payload = JWT::decode($refreshToken, 'refresh');
        if ($payload === false) {
            Response::unauthorized('Invalid or expired refresh token');
        }

        $userId = $payload['user_id'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        // تحقق من الجلسة النشطة
        if (!AuthMiddleware::isSessionActive($userId, $refreshToken)) {
            Response::unauthorized('Session not active');
        }

        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        $accessToken = JWT::createAccessToken($user['id'], $user['user_type']);
        $newRefresh = JWT::createRefreshToken($user['id'], $user['user_type']);

        // تحديث الsession token hash
        $tokenHash = hash('sha256', $newRefresh);
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("UPDATE user_sessions SET token = ?, token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW() WHERE user_id = ? AND is_active = 1");
        if ($stmt) {
            $expires = REFRESH_TOKEN_EXPIRES;
            // Some installations may store token or hash; adapt accordingly.
            $stmt->bind_param('ssii', $newRefresh, $tokenHash, $expires, $userId);
            $stmt->execute();
            $stmt->close();
        }

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $newRefresh,
            'expires_in' => JWT::getAccessTokenTTL()
        ], 'Token refreshed');
    }

    /**
     * تسجيل الخروج - إنهاء الجلسة
     * POST /api/auth/logout
     */
    public static function logout()
    {
        $token = JWT::getBearerToken();
        $payload = JWT::decode($token);
        $userId = $payload['user_id'] ?? null;

        // إنهاء الجلسة إن وُجدت
        $mysqli = connectDB();
        if ($userId) {
            $stmt = $mysqli->prepare("UPDATE user_sessions SET is_active = 0, updated_at = NOW() WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        Response::success(null, 'Logged out successfully');
    }

    /**
     * جلب بيانات المستخدم المصادق عليه
     * GET /api/auth/me
     */
    public static function me()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            Response::unauthorized();
        }

        // تحديث آخر نشاط
        AuthMiddleware::updateLastActivity($user['id']);

        Response::success($user);
    }

    /**
     * إرسال OTP إلى الجوال
     * POST /api/auth/send-otp
     */
    public static function sendOTP()
    {
        RateLimitMiddleware::forOTP(Security::getRealIP());

        $input = $_POST;
        $phone = $input['phone'] ?? null;
        $lang = $input['lang'] ?? DEFAULT_LANGUAGE;

        if (!$phone) Response::validationError(['phone' => ['Phone is required']]);

        $otp = Security::generateOTP(6);

        // حفظ OTP في DB أو cache (مثال: table user_otps)
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("INSERT INTO user_otps (phone, otp, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())");
        $expiryMinutes = OTP_EXPIRY / 60;
        if ($stmt) {
            $stmt->bind_param('ssi', $phone, $otp, $expiryMinutes);
            $stmt->execute();
            $stmt->close();
        }

        // إرسال عبر SMS
        $smsResult = SMS::sendOTP($phone, $otp, $lang);

        if ($smsResult['success']) {
            Response::success(['message' => 'OTP sent']);
        } else {
            Response::error('Failed to send OTP: ' . $smsResult['message'], 500);
        }
    }

    /**
     * تحقق OTP
     * POST /api/auth/verify-otp
     */
    public static function verifyOTP()
    {
        $input = $_POST;
        $phone = $input['phone'] ?? null;
        $otp = $input['otp'] ?? null;

        if (!$phone || !$otp) {
            Response::validationError(['phone' => ['Phone required'], 'otp' => ['OTP required']]);
        }

        $mysqli = connectDB();
        $stmt = $mysqli->prepare("SELECT id, otp, expires_at FROM user_otps WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
        if (!$stmt) {
            Response::error('DB error', 500);
        }
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            Response::error('OTP not found', 404);
        }
        $row = $res->fetch_assoc();
        $stmt->close();

        if (strtotime($row['expires_at']) < time()) {
            Response::error('OTP expired', 400);
        }

        if (hash_equals($row['otp'], $otp)) {
            // تمييز كموثق - في حالة وجود مستخدم مرتبط بالجوال، وسمّه كمحقق
            $userModel = new User();
            $user = $userModel->findByPhone($phone);
            if ($user) {
                $userModel->verifyPhone($user['id']);
            }

            Response::success(['message' => 'OTP verified']);
        } else {
            Response::error('Invalid OTP', 400);
        }
    }

    /**
     * طلب إعادة تعيين كلمة المرور (إرسال رابط أو رمز)
     * POST /api/auth/forgot-password
     */
    public static function forgotPassword()
    {
        RateLimitMiddleware::forPasswordReset(Security::getRealIP());

        $input = $_POST;
        $email = $input['email'] ?? null;

        if (!$email) Response::validationError(['email' => ['Email required']]);

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user) {
            // لا تكشف إن لم يوجد المستخدم
            Response::success(null, 'If the email exists, a reset link has been sent');
        }

        // إنشاء token (JWT أو رمز عشوائي محفوظ في table)
        $token = Security::generateToken(32);

        $mysqli = connectDB();
        $stmt = $mysqli->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())");
        $expiry = PASSWORD_RESET_EXPIRY / 60;
        if ($stmt) {
            $stmt->bind_param('isi', $user['id'], $token, $expiry);
            $stmt->execute();
            $stmt->close();
        }

        // إرسال البريد مع رابط إعادة التعيين
        $resetLink = APP_URL . '/reset-password?token=' . $token;
        Mail::sendPasswordReset($user['email'], $user['username'], $token);

        Response::success(null, 'If the email exists, a reset link has been sent');
    }

    /**
     * إعادة تعيين كلمة المرور
     * POST /api/auth/reset-password
     */
    public static function resetPassword()
    {
        $input = $_POST;
        $token = $input['token'] ?? null;
        $password = $input['password'] ?? null;
        $passwordConfirm = $input['password_confirmation'] ?? null;

        if (!$token || !$password || !$passwordConfirm) {
            Response::validationError(['token' => ['Token required'], 'password' => ['Password required']]);
        }

        if ($password !== $passwordConfirm) {
            Response::validationError(['password_confirmation' => ['Passwords do not match']]);
        }

        // تحقق من token
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND used = 0 LIMIT 1");
        if (!$stmt) Response::error('DB error', 500);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); Response::error('Invalid or used token', 400); }
        $row = $res->fetch_assoc();
        $stmt->close();

        if (strtotime($row['expires_at']) < time()) {
            Response::error('Token expired', 400);
        }

        $userId = (int)$row['user_id'];
        $userModel = new User();
        $ok = $userModel->updatePassword($userId, $password);

        if ($ok) {
            // علم الـ token كمستخدم
            $stmt = $mysqli->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE token = ?");
            if ($stmt) {
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $stmt->close();
            }

            // إنهاء جميع جلسات المستخدم
            AuthMiddleware::terminateAllSessions($userId);

            Response::success(null, 'Password has been reset successfully');
        }

        Response::error('Failed to reset password', 500);
    }

    /**
     * إرسال رابط/رمز تحقق البريد
     * POST /api/auth/send-verify-email
     */
    public static function sendVerifyEmail()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $token = JWT::createVerificationToken($user['id'], $user['email'] ?? '');
        Mail::sendPasswordReset($user['email'], $user['username'], $token); // reuse or create verify template

        Response::success(null, 'Verification email sent');
    }

    /**
     * تحقق رابط البريد (قد يكون GET endpoint)
     * GET /api/auth/verify-email?token=...
     */
    public static function verifyEmail()
    {
        $token = $_GET['token'] ?? null;
        if (!$token) Response::error('Token required', 400);

        $payload = JWT::decode($token, 'verify');
        if ($payload === false) Response::error('Invalid or expired token', 400);

        $userId = $payload['user_id'] ?? null;
        if (!$userId) Response::error('Invalid token', 400);

        $userModel = new User();
        $ok = $userModel->verifyEmail($userId);

        if ($ok) {
            Response::success(null, 'Email verified successfully');
        } else {
            Response::error('Failed to verify email', 500);
        }
    }
}

// Router usage example (if simple routing)
$path = $_SERVER['REQUEST_URI'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// The actual routing is assumed to be done elsewhere; this controller exposes static methods.
// Example:
// if ($path === '/api/auth/login' && $method === 'POST') AuthController::login();

?>