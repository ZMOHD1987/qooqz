<?php
declare(strict_types=1);

/**
 * frontend/login.php
 * QOOQZ — Public Login / Register Page
 *
 * Session handling: include the SAME shared session config the API uses so
 * that session.save_path, session_name (APP_SESSID), and cookie params all
 * match. This ensures only ONE APP_SESSID cookie exists in the browser and
 * the API can find the session when the user submits the login form.
 */

if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        session_name('APP_SESSID');
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        session_start();
    }
    unset($__sharedSess, $__sp);
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Current logged-in user (if any) — used for the "already logged in" notice
$loginPageUser = !empty($_SESSION['user']) ? $_SESSION['user'] : null;

// Detect page language from session (set by public pages)
$loginLang = $_SESSION['pub_lang'] ?? $_SESSION['user']['preferred_language'] ?? 'en';
$loginDir  = in_array($loginLang, ['ar','fa','ur','he'], true) ? 'rtl' : 'ltr';
$isRtl     = $loginDir === 'rtl';

// Load available languages from DB for the preferred_language dropdown
$availLangs = [];
try {
    $__dbFile = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php';
    if (!is_readable($__dbFile)) $__dbFile = dirname(__DIR__) . '/api/shared/config/db.php';
    if (is_readable($__dbFile)) {
        $__dbc = require $__dbFile;
        $__dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $__dbc['host'] ?? 'localhost', (int)($__dbc['port'] ?? 3306), $__dbc['name']);
        $__pdoL = new PDO($__dsn, $__dbc['user'], $__dbc['pass'], [PDO::ATTR_TIMEOUT => 3]);
        $__stL  = $__pdoL->query('SELECT code, name FROM languages ORDER BY name');
        $availLangs = $__stL ? $__stL->fetchAll(PDO::FETCH_ASSOC) : [];
        unset($__pdoL, $__stL, $__dbc, $__dsn);
    }
    unset($__dbFile);
} catch (Throwable $_) {}

// Translations (inline — login page doesn't include public_context.php)
$tr = $isRtl ? [
    'login_title'    => 'تسجيل الدخول',
    'register_title' => 'إنشاء حساب',
    'login_btn'      => 'تسجيل الدخول',
    'register_btn'   => 'إنشاء الحساب',
    'username'       => 'اسم المستخدم',
    'email'          => 'البريد الإلكتروني',
    'password'       => 'كلمة المرور',
    'phone'          => 'رقم الهاتف',
    'lang_pref'      => 'اللغة المفضلة',
    'or_email'       => 'اسم المستخدم / البريد',
    'tagline'        => 'منصة QOOQZ العالمية',
    'already'        => 'لديك حساب؟',
    'no_account'     => 'ليس لديك حساب؟',
    'logged_in_as'   => 'أنت مسجّل الدخول بوصفك',
    'go_home'        => 'الذهاب إلى الرئيسية',
    'logout'         => 'تسجيل الخروج',
] : [
    'login_title'    => 'Sign In',
    'register_title' => 'Create Account',
    'login_btn'      => 'Sign In',
    'register_btn'   => 'Create Account',
    'username'       => 'Username',
    'email'          => 'Email',
    'password'       => 'Password',
    'phone'          => 'Phone Number',
    'lang_pref'      => 'Preferred Language',
    'or_email'       => 'Username / Email',
    'tagline'        => 'QOOQZ Global Platform',
    'already'        => 'Already have an account?',
    'no_account'     => "Don't have an account?",
    'logged_in_as'   => 'You are signed in as',
    'go_home'        => 'Go to Homepage',
    'logout'         => 'Sign Out',
];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($loginLang) ?>" dir="<?= $loginDir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($tr['tagline']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ($isRtl): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="<?= $loginDir ?>">

<?php if ($loginPageUser): ?>
<!-- Already-logged-in notice — shown at top of page instead of forcing a redirect -->
<div class="lq-already-notice" role="alert">
    <span>
        <?= htmlspecialchars($tr['logged_in_as']) ?>
        <strong><?= htmlspecialchars($loginPageUser['username'] ?? $loginPageUser['email'] ?? 'User') ?></strong>
    </span>
    <a href="/frontend/public/index.php" class="lq-notice-btn"><?= htmlspecialchars($tr['go_home']) ?></a>
    <a href="/frontend/logout.php" class="lq-notice-btn lq-notice-btn-out"><?= htmlspecialchars($tr['logout']) ?></a>
</div>
<?php endif; ?>

<div class="lq-wrapper">

    <!-- Brand panel -->
    <div class="lq-brand" aria-hidden="true">
        <div class="lq-brand-inner">
            <div class="lq-logo">🌐</div>
            <h1 class="lq-brand-name">QOOQZ</h1>
            <p class="lq-brand-tagline"><?= htmlspecialchars($tr['tagline']) ?></p>
        </div>
    </div>

    <!-- Form panel -->
    <div class="lq-panel">
        <div class="lq-box">

            <!-- Tabs -->
            <div class="lq-tabs" role="tablist">
                <button id="tab-login" class="lq-tab active" role="tab"
                        aria-selected="true" aria-controls="loginForm"
                        onclick="showForm('login')">
                    <?= htmlspecialchars($tr['login_title']) ?>
                </button>
                <button id="tab-register" class="lq-tab" role="tab"
                        aria-selected="false" aria-controls="registerForm"
                        onclick="showForm('register')">
                    <?= htmlspecialchars($tr['register_title']) ?>
                </button>
            </div>

            <!-- Login form -->
            <form id="loginForm" class="lq-form" action="javascript:void(0);" autocomplete="off" aria-labelledby="tab-login">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="login">

                <div class="lq-field">
                    <label for="login_username"><?= htmlspecialchars($tr['or_email']) ?></label>
                    <input id="login_username" name="username" type="text"
                           autocomplete="username" required
                           placeholder="<?= htmlspecialchars($tr['or_email']) ?>">
                </div>

                <div class="lq-field">
                    <label for="login_password"><?= htmlspecialchars($tr['password']) ?></label>
                    <div class="lq-password-wrap">
                        <input id="login_password" name="password" type="password"
                               autocomplete="current-password" required
                               placeholder="••••••••">
                        <button type="button" class="lq-toggle-pw" tabindex="-1"
                                onclick="togglePw('login_password',this)">👁</button>
                    </div>
                </div>

                <button type="submit" class="lq-btn"><?= htmlspecialchars($tr['login_btn']) ?></button>

                <p class="lq-switch">
                    <?= htmlspecialchars($tr['no_account']) ?>
                    <a href="#" onclick="showForm('register');return false;"><?= htmlspecialchars($tr['register_title']) ?></a>
                </p>
            </form>

            <!-- Register form -->
            <form id="registerForm" class="lq-form lq-hidden" action="javascript:void(0);" autocomplete="off" aria-labelledby="tab-register">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="register">

                <div class="lq-field">
                    <label for="reg_username"><?= htmlspecialchars($tr['username']) ?></label>
                    <input id="reg_username" name="username" type="text"
                           autocomplete="username" required
                           placeholder="<?= htmlspecialchars($tr['username']) ?>">
                </div>

                <div class="lq-field">
                    <label for="reg_email"><?= htmlspecialchars($tr['email']) ?></label>
                    <input id="reg_email" name="email" type="email"
                           autocomplete="email" required
                           placeholder="<?= htmlspecialchars($tr['email']) ?>">
                </div>

                <div class="lq-field">
                    <label for="reg_phone"><?= htmlspecialchars($tr['phone']) ?></label>
                    <input id="reg_phone" name="phone" type="tel"
                           autocomplete="tel"
                           placeholder="+971 50 000 0000">
                </div>

                <div class="lq-field">
                    <label for="reg_lang"><?= htmlspecialchars($tr['lang_pref']) ?></label>
                    <select id="reg_lang" name="preferred_language">
                        <?php if ($availLangs): ?>
                            <?php foreach ($availLangs as $lng): ?>
                            <option value="<?= htmlspecialchars($lng['code']) ?>"
                                <?= $lng['code'] === $loginLang ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lng['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="en" <?= $loginLang === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="ar" <?= $loginLang === 'ar' ? 'selected' : '' ?>>العربية</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="lq-field">
                    <label for="reg_password"><?= htmlspecialchars($tr['password']) ?></label>
                    <div class="lq-password-wrap">
                        <input id="reg_password" name="password" type="password"
                               autocomplete="new-password" required minlength="6"
                               placeholder="••••••••">
                        <button type="button" class="lq-toggle-pw" tabindex="-1"
                                onclick="togglePw('reg_password',this)">👁</button>
                    </div>
                </div>

                <button type="submit" class="lq-btn"><?= htmlspecialchars($tr['register_btn']) ?></button>

                <p class="lq-switch">
                    <?= htmlspecialchars($tr['already']) ?>
                    <a href="#" onclick="showForm('login');return false;"><?= htmlspecialchars($tr['login_title']) ?></a>
                </p>
            </form>

            <!-- Status message -->
            <div id="result" class="lq-result" role="status" aria-live="polite"></div>
        </div>
    </div>
</div>

<script src="assets/js/login.js"></script>
<script>
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>