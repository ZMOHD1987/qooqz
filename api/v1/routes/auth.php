<?php
declare(strict_types=1);
/**
 * routes/auth.php — Production Final
 *
 * GET  : me | csrf | check | logout | google_callback
 * POST : login | register | verify_otp | resend_verification
 *        google_login | facebook_login | apple_login
 *        register_device | update_fcm
 */

// ══════════════════════════════════════════════════════════════════════════════
//  SESSION BOOTSTRAP
// ══════════════════════════════════════════════════════════════════════════════
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
    $cp = ['lifetime'=>0,'path'=>'/','domain'=>$_SERVER['HTTP_HOST']??'',
           'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'];
    PHP_VERSION_ID >= 70300
        ? session_set_cookie_params($cp)
        : session_set_cookie_params($cp['lifetime'],$cp['path'],$cp['domain'],$cp['secure'],$cp['httponly']);
    @session_start();
}

// ══════════════════════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function _no_cache(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

function _app_url(): string
{
    $v = getenv('APP_URL') ?: (defined('APP_URL') ? APP_URL : '');
    if ($v !== '') return rtrim($v, '/');
    $s = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($s ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function _read_payload(): array
{
    $raw = @file_get_contents('php://input');
    if ($raw) { $d = @json_decode($raw, true); if (is_array($d)) return $d; }
    return $_POST ?: [];
}

function _current_user(): ?array
{
    $u = $GLOBALS['ADMIN_USER'] ?? null;
    if (!$u && !empty($_SESSION['user'])) $u = $_SESSION['user'];
    return is_array($u) ? $u : null;
}

function _ua(): string   { return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512); }
function _ip(): string   { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
function _secure(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }

function _curl_get(string $url, array $headers = [], int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout,
                            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_HTTPHEADER=>$headers]);
    $b = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    return [$b ?: '', $e];
}

function _curl_post(string $url, string $fields, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout,
                            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_POST=>true,
                            CURLOPT_POSTFIELDS=>$fields]);
    $b = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    return [$b ?: '', $e];
}

function _set_device_cookie(string $value): void
{
    if (headers_sent()) return;
    $s = _secure();
    PHP_VERSION_ID >= 70300
        ? setcookie('qz_dvt', $value, ['expires'=>time()+86400,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$s])
        : setcookie('qz_dvt', $value, time()+86400, '/', '', $s, true);
}

function _flush_response(): void
{
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    while (ob_get_level() > 0) ob_end_flush();
    flush();
}

// ══════════════════════════════════════════════════════════════════════════════
//  RBAC
// ══════════════════════════════════════════════════════════════════════════════
function _load_rbac(PDO $pdo, int $userId, ?int $roleId = null): array
{
    $perms = []; $roles = [];
    try {
        $st = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        if ($st && $st->rowCount()) {
            $q = $pdo->prepare("SELECT r.key_name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?");
            $q->execute([$userId]);
            $roles = array_merge($roles, $q->fetchAll(PDO::FETCH_COLUMN, 0));
        } elseif ($roleId) {
            $q = $pdo->prepare("SELECT key_name FROM roles WHERE id=? LIMIT 1");
            $q->execute([$roleId]); $r = $q->fetchColumn(); if ($r) $roles[] = $r;
        }
        $st2 = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
        if ($st2 && $st2->rowCount()) {
            $q2 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN user_permissions up ON up.permission_id=p.id WHERE up.user_id=?");
            $q2->execute([$userId]);
            $perms = array_merge($perms, $q2->fetchAll(PDO::FETCH_COLUMN, 0));
        }
        if ($roleId) {
            $q3 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=?");
            $q3->execute([$roleId]); $perms = array_merge($perms, $q3->fetchAll(PDO::FETCH_COLUMN, 0));
        } elseif (!empty($roles)) {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $q4 = $pdo->prepare("SELECT DISTINCT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN roles r ON r.id=rp.role_id WHERE r.key_name IN ($in)");
            $q4->execute($roles); $perms = array_merge($perms, $q4->fetchAll(PDO::FETCH_COLUMN, 0));
        }
    } catch (Throwable $e) { if (class_exists('Logger')) Logger::error('RBAC: '.$e->getMessage()); }
    return ['permissions'=>array_values(array_unique($perms)), 'roles'=>array_values(array_unique($roles))];
}

// ══════════════════════════════════════════════════════════════════════════════
//  DEVICE HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function _detect_device(string $ua): array
{
    static $loaded = false;
    if (!$loaded) {
        $f = dirname(__DIR__, 2) . '/shared/helpers/device_detector.php';
        if (file_exists($f)) { require_once $f; } $loaded = true;
    }
    $type = class_exists('DeviceDetector') ? DeviceDetector::detectType($ua) : 'web';
    $name = class_exists('DeviceDetector') ? DeviceDetector::detectName($ua) : 'Browser';
    return [$type, substr($name, 0, 100)];
}

/**
 * After login: link an anonymous device row to the real user_id.
 * Priority: cookie token → UA match → create new row.
 */
function _link_device_on_login(PDO $pdo, int $userId): void
{
    try {
        $ua        = _ua();
        $ip        = _ip();
        $anonToken = $_COOKIE['qz_dvt'] ?? null;

        if ($anonToken && strlen($anonToken) === 64) {
            $upd = $pdo->prepare(
                'UPDATE user_devices SET user_id=?,ip=?,last_seen_at=NOW(),updated_at=CURRENT_TIMESTAMP
                  WHERE anonymous_token=? AND (user_id IS NULL OR user_id=?) AND is_active=1'
            );
            $upd->execute([$userId, $ip, $anonToken, $userId]);
            if ($upd->rowCount() > 0) return;
        }

        $sel = $pdo->prepare(
            'SELECT id FROM user_devices WHERE user_agent=? AND is_active=1
              AND (user_id IS NULL OR user_id=?) ORDER BY last_seen_at DESC LIMIT 1'
        );
        $sel->execute([$ua, $userId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare(
                'UPDATE user_devices SET user_id=?,ip=?,last_seen_at=NOW(),updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([$userId, $ip, $row['id']]);
            return;
        }

        [$type, $name] = _detect_device($ua);
        $newAnon = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO user_devices (user_id,anonymous_token,device_type,device_name,user_agent,ip,last_seen_at,is_active,created_at)
             VALUES (?,?,?,?,?,?,NOW(),1,CURRENT_TIMESTAMP)'
        )->execute([$userId, $newAnon, $type, $name, $ua, $ip]);
        _set_device_cookie($newAnon);
    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('Device link: '.$e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  OAUTH PROVIDER UPSERT  (Google / Facebook / Apple share this)
// ══════════════════════════════════════════════════════════════════════════════
function _provider_login(PDO $pdo, string $provider, string $sub, string $email, string $name, array $extra): array
{
    // 1. Already linked?
    $st = $pdo->prepare('SELECT user_id FROM user_auth_providers WHERE provider=? AND provider_user_id=? LIMIT 1');
    $st->execute([$provider, $sub]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $userId = (int)$row['user_id'];
    } else {
        // Existing user with same email?
        $st2 = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $st2->execute([$email]);
        $ex = $st2->fetch(PDO::FETCH_ASSOC);

        if ($ex) {
            $userId = (int)$ex['id'];
        } else {
            // Create new user
            $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: 'user');
            if (strlen($base) < 3) $base = 'user';
            $username = substr($base, 0, 45); $c = 1;
            while (true) {
                $chk = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
                $chk->execute([$username]);
                if (!$chk->fetch()) break;
                $username = substr($base, 0, 40) . $c++;
            }
            $pdo->prepare('INSERT INTO users (username,email,is_active,preferred_language,created_at) VALUES (?,?,1,?,NOW())')
                ->execute([$username, $email, 'en']);
            $userId = (int)$pdo->lastInsertId();
        }

        // Link provider — INSERT IGNORE handles race conditions
        $pdo->prepare('INSERT IGNORE INTO user_auth_providers (user_id,provider,provider_user_id,provider_extra) VALUES (?,?,?,?)')
            ->execute([$userId, $provider, $sub, json_encode($extra)]);
    }

    // Load full record
    $ul = $pdo->prepare(
        'SELECT u.id,u.username,u.email,u.phone,u.preferred_language,u.is_active,tu.role_id,tu.tenant_id
         FROM users u LEFT JOIN tenant_users tu ON tu.user_id=u.id AND tu.is_active=1
         WHERE u.id=? ORDER BY tu.joined_at DESC LIMIT 1'
    );
    $ul->execute([$userId]);
    $uRow = $ul->fetch(PDO::FETCH_ASSOC);
    if (!$uRow) throw new RuntimeException("User not found after {$provider} upsert (id={$userId})");

    // Re-activate if needed
    if (!(bool)$uRow['is_active']) {
        $pdo->prepare('UPDATE users SET is_active=1,updated_at=NOW() WHERE id=?')->execute([$userId]);
    }

    $rbac = _load_rbac($pdo, $userId, isset($uRow['role_id']) ? (int)$uRow['role_id'] : null);
    session_regenerate_id(true);

    $user = [
        'id'                 => (int)$uRow['id'],
        'name'               => $uRow['username'],
        'username'           => $uRow['username'],
        'email'              => $uRow['email'],
        'phone'              => $uRow['phone'] ?? null,
        'role_id'            => isset($uRow['role_id'])   ? (int)$uRow['role_id']   : null,
        'tenant_id'          => isset($uRow['tenant_id']) ? (int)$uRow['tenant_id'] : 1,
        'preferred_language' => $uRow['preferred_language'] ?? 'en',
        'is_active'          => true,
        'permissions'        => $rbac['permissions'],
        'roles'              => $rbac['roles'],
        'permissions_count'  => count($rbac['permissions']),
        'roles_count'        => count($rbac['roles']),
    ];

    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user']        = $user;
    $_SESSION['permissions'] = $user['permissions'];
    $_SESSION['roles']       = $user['roles'];
    unset($_SESSION['pending_user_id'], $_SESSION['pending_otp'],
          $_SESSION['pending_otp_expires'], $_SESSION['pending_otp_attempts'],
          $_SESSION['pending_verify_link']);
    $GLOBALS['ADMIN_USER'] = $user;
    _link_device_on_login($pdo, $userId);
    return $user;
}

// ══════════════════════════════════════════════════════════════════════════════
//  DB CHECK
// ══════════════════════════════════════════════════════════════════════════════
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) { _no_cache(); ResponseFormatter::serverError('Database unavailable'); exit; }

// ══════════════════════════════════════════════════════════════════════════════
//  ROUTING
// ══════════════════════════════════════════════════════════════════════════════
$segments = $_GET['segments'] ?? [];
$firstSeg = strtolower((string)($segments[0] ?? ''));
$action   = $firstSeg !== '' ? $firstSeg : strtolower((string)($_GET['__action'] ?? ''));


// ╔══════════════════════════════════════════════════════════════════════════╗
// ║  GET                                                                    ║
// ╚══════════════════════════════════════════════════════════════════════════╝
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    _no_cache();

    if ($action === 'logout') {
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['roles']);
        $GLOBALS['ADMIN_USER'] = null;
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_regenerate_id(true);
        ResponseFormatter::success(['ok'=>true,'message'=>'Logged out']); exit;
    }

    if ($action === 'me') {
        $u = _current_user();
        $u ? ResponseFormatter::success(['ok'=>true,'user'=>$u]) : ResponseFormatter::notFound('Not authenticated');
        exit;
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        ResponseFormatter::success(['ok'=>true,'csrf'=>$_SESSION['csrf_token']]); exit;
    }

    if ($action === 'check') {
        $u = _current_user();
        ResponseFormatter::success(['ok'=>true,'authenticated'=>(bool)$u,'user'=>$u]); exit;
    }

    // ── Google Authorization Code callback ───────────────────────────────
    if ($action === 'google_callback') {
        $code     = trim((string)($_GET['code']  ?? ''));
        $oauthErr = trim((string)($_GET['error'] ?? ''));
        $appUrl   = _app_url();
        $loginUrl = $appUrl . '/frontend/login.php';
        $redirUri = $appUrl . '/api/auth?__action=google_callback';
        $clientId     = getenv('GOOGLE_CLIENT_ID')     ?: (defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : '');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');

        if ($oauthErr !== '' || $code === '') {
            header('Location: '.$loginUrl.'?google_error='.urlencode($oauthErr ?: 'access_denied')); exit;
        }
        if ($clientId === '' || $clientSecret === '') {
            header('Location: '.$loginUrl.'?google_error=server_config'); exit;
        }

        [$tokenRaw, $err] = _curl_post('https://oauth2.googleapis.com/token',
            http_build_query(['code'=>$code,'client_id'=>$clientId,'client_secret'=>$clientSecret,
                              'redirect_uri'=>$redirUri,'grant_type'=>'authorization_code']));
        if ($err || !$tokenRaw) {
            if (class_exists('Logger')) Logger::error('Google token exchange curl: '.$err);
            header('Location: '.$loginUrl.'?google_error=token_exchange_failed'); exit;
        }
        $td = json_decode($tokenRaw, true);
        if (empty($td['access_token'])) {
            if (class_exists('Logger')) Logger::error('Google token response: '.$tokenRaw);
            header('Location: '.$loginUrl.'?google_error='.urlencode($td['error'] ?? 'no_access_token')); exit;
        }

        [$uiRaw, $err2] = _curl_get('https://www.googleapis.com/oauth2/v2/userinfo',
                                     ['Authorization: Bearer '.$td['access_token']]);
        if ($err2 || !$uiRaw) { header('Location: '.$loginUrl.'?google_error=userinfo_failed'); exit; }

        $ui    = json_decode($uiRaw, true);
        $sub   = (string)($ui['id'] ?? '');
        $email = filter_var($ui['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $ui['email'] : '';
        $name  = trim((string)($ui['name'] ?? $ui['given_name'] ?? ''));

        if ($sub === '' || $email === '') {
            if (class_exists('Logger')) Logger::error('Google userinfo missing: '.$uiRaw);
            header('Location: '.$loginUrl.'?google_error=invalid_user_info'); exit;
        }

        try {
            _provider_login($pdo, 'google', $sub, $email, $name, [
                'email_verified'=>(bool)($ui['verified_email'] ?? false),'name'=>$name,'picture'=>$ui['picture'] ?? null,
            ]);
            header('Location: '.$appUrl.'/frontend/public/index.php');
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Google callback DB: '.$e->getMessage());
            header('Location: '.$loginUrl.'?google_error=server_error');
        }
        exit;
    }

    ResponseFormatter::error('Invalid GET action. Use: me, csrf, check, logout, google_callback', 400); exit;
}


// ╔══════════════════════════════════════════════════════════════════════════╗
// ║  POST                                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    _no_cache();
    $payload = _read_payload();
    $postAct = strtolower(trim((string)($payload['action'] ?? '')));
    $ea      = ($action !== '' && !in_array($action, ['login','register'], true))
               ? $action : ($postAct ?: ($action ?: 'login'));

    $allowed = ['login','register','verify_otp','resend_verification',
                'google_login','facebook_login','apple_login',
                'register_device','update_fcm'];
    if (!in_array($ea, $allowed, true)) { ResponseFormatter::notFound('Auth POST route not found'); exit; }

    $csrfOk = static function() use ($payload): bool {
        $s = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) ?: trim((string)($payload['csrf_token'] ?? ''));
        $t = (string)($_SESSION['csrf_token'] ?? '');
        return $t !== '' && $s !== '' && hash_equals($t, $s);
    };


    // ════════════════════════════════════════════════════════════════════════
    //  REGISTER DEVICE  — anonymous, works before login
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'register_device') {
        $fcmToken  = trim((string)($payload['fcm_token']       ?? '')) ?: null;
        $anonToken = trim((string)($payload['anonymous_token'] ?? '')) ?: ($_COOKIE['qz_dvt'] ?? null);
        $dType     = trim((string)($payload['device_type']     ?? ''));
        $dName     = trim((string)($payload['device_name']     ?? '')) ?: null;
        if ($dName) $dName = substr($dName, 0, 100);
        $ua = _ua(); $ip = _ip();
        if (!in_array($dType, ['web','android','ios','other'], true)) {
            [$dType, $auto] = _detect_device($ua); if (!$dName) $dName = $auto;
        }
        $userId = _current_user()['id'] ?? null;

        try {
            $existingId = null;
            if ($anonToken && strlen($anonToken) === 64) {
                $r = $pdo->prepare('SELECT id FROM user_devices WHERE anonymous_token=? LIMIT 1');
                $r->execute([$anonToken]);
                $row = $r->fetch(PDO::FETCH_ASSOC); if ($row) $existingId = (int)$row['id'];
            }
            if (!$existingId && $userId) {
                $r2 = $pdo->prepare('SELECT id FROM user_devices WHERE user_id=? AND user_agent=? AND is_active=1 LIMIT 1');
                $r2->execute([$userId, $ua]);
                $row2 = $r2->fetch(PDO::FETCH_ASSOC); if ($row2) $existingId = (int)$row2['id'];
            }

            if ($existingId) {
                $pdo->prepare(
                    'UPDATE user_devices SET user_id=COALESCE(?,user_id),fcm_token=COALESCE(?,fcm_token),
                        device_type=?,device_name=COALESCE(?,device_name),ip=?,
                        last_seen_at=NOW(),updated_at=CURRENT_TIMESTAMP WHERE id=?'
                )->execute([$userId,$fcmToken,$dType,$dName,$ip,$existingId]);
                $deviceId = $existingId;
            } else {
                $anonToken = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO user_devices (user_id,anonymous_token,fcm_token,device_type,device_name,user_agent,ip,last_seen_at,is_active,created_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),1,CURRENT_TIMESTAMP)'
                )->execute([$userId,$anonToken,$fcmToken,$dType,$dName,$ua,$ip]);
                $deviceId = (int)$pdo->lastInsertId();
                _set_device_cookie($anonToken);
            }

            ResponseFormatter::success(['ok'=>true,'device_id'=>$deviceId,'anonymous_token'=>$anonToken,'fcm_saved'=>($fcmToken!==null)]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('register_device: '.$e->getMessage());
            ResponseFormatter::serverError('Could not register device.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  UPDATE FCM TOKEN  — after push permission granted (web + android)
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'update_fcm') {
        $fcmToken  = trim((string)($payload['fcm_token']       ?? ''));
        $anonToken = trim((string)($payload['anonymous_token'] ?? '')) ?: ($_COOKIE['qz_dvt'] ?? null);
        $deviceId  = isset($payload['device_id']) ? (int)$payload['device_id'] : null;
        if ($fcmToken === '') { ResponseFormatter::error('fcm_token is required', 422); exit; }

        $userId = _current_user()['id'] ?? null;
        $ua     = _ua();

        try {
            // Remove stale binding on other users
            if ($userId) {
                $pdo->prepare('UPDATE user_devices SET fcm_token=NULL,updated_at=CURRENT_TIMESTAMP WHERE fcm_token=? AND user_id!=?')
                    ->execute([$fcmToken,$userId]);
            }

            $targetId = null;
            if ($deviceId) { $targetId = $deviceId; }
            elseif ($anonToken && strlen($anonToken) === 64) {
                $r = $pdo->prepare('SELECT id FROM user_devices WHERE anonymous_token=? LIMIT 1');
                $r->execute([$anonToken]); $row = $r->fetch(PDO::FETCH_ASSOC); if ($row) $targetId = (int)$row['id'];
            } elseif ($userId) {
                $r = $pdo->prepare('SELECT id FROM user_devices WHERE user_id=? AND user_agent=? AND is_active=1 ORDER BY last_seen_at DESC LIMIT 1');
                $r->execute([$userId,$ua]); $row = $r->fetch(PDO::FETCH_ASSOC); if ($row) $targetId = (int)$row['id'];
            }

            if (!$targetId) {
                [$dType, $dName] = _detect_device($ua);
                $newAnon = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO user_devices (user_id,anonymous_token,fcm_token,device_type,device_name,user_agent,ip,last_seen_at,is_active,created_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),1,CURRENT_TIMESTAMP)'
                )->execute([$userId,$newAnon,$fcmToken,$dType,$dName,$ua,_ip()]);
                $targetId = (int)$pdo->lastInsertId(); $anonToken = $newAnon; _set_device_cookie($newAnon);
            } else {
                $pdo->prepare('UPDATE user_devices SET fcm_token=?,user_id=COALESCE(?,user_id),last_seen_at=NOW(),updated_at=CURRENT_TIMESTAMP WHERE id=?')
                    ->execute([$fcmToken,$userId,$targetId]);
            }

            ResponseFormatter::success(['ok'=>true,'device_id'=>$targetId,'anonymous_token'=>$anonToken]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('update_fcm: '.$e->getMessage());
            ResponseFormatter::serverError('Could not update FCM token.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  REGISTER
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'register') {
        if (!$csrfOk()) { ResponseFormatter::error('Invalid request. Please reload the page.', 403); exit; }

        $regUser  = trim((string)($payload['username'] ?? ''));
        $regEmail = trim((string)($payload['email']    ?? ''));
        $regPass  = (string)($payload['password']       ?? '');
        $regPhone = trim((string)($payload['phone']     ?? ''));
        $regLang  = preg_replace('/[^a-z\-]/', '', strtolower((string)($payload['preferred_language'] ?? 'en'))) ?: 'en';

        $errors = [];
        if ($regUser === '') $errors['username'] = 'Username is required';
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $regUser)) $errors['username'] = 'Username must be 3–50 alphanumeric or underscore characters';
        if ($regEmail === '') $errors['email'] = 'Email is required';
        elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address';
        if (strlen($regPass) < 6) $errors['password'] = 'Password must be at least 6 characters';
        if ($errors) { ResponseFormatter::error('Validation failed', 422, $errors); exit; }

        try {
            $clientIp = _ip();
            if ($clientIp !== '') {
                $rt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM user_phone_verifications WHERE ip=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');
                $rt->execute([$clientIp]);
                if ((int)$rt->fetchColumn() >= 5) { ResponseFormatter::error('Too many registration attempts. Please try again later.', 429); exit; }
            }

            $chk = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
            $chk->execute([$regUser,$regEmail]);
            if ($chk->fetch()) { ResponseFormatter::error('Username or email already exists', 409); exit; }

            $pdo->prepare('INSERT INTO users (username,email,password_hash,phone,preferred_language,is_active,created_at) VALUES (?,?,?,?,?,0,NOW())')
                ->execute([$regUser,$regEmail,password_hash($regPass,PASSWORD_DEFAULT),$regPhone ?: null,$regLang]);
            $newId = (int)$pdo->lastInsertId();

            $rawToken  = bin2hex(random_bytes(32)); $rawDevice = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', time()+86400);

            $insV = $pdo->prepare(
                'INSERT INTO user_phone_verifications (user_id,token_hash,device_hash,session_id,user_agent,ip,expires_at) VALUES (?,?,?,?,?,?,?)'
            );
            $insV->execute([$newId,hash('sha256',$rawToken),hash('sha256',$rawDevice),session_id(),_ua(),$clientIp,$expiresAt]);
            $vRowId = (int)$pdo->lastInsertId();
            _set_device_cookie($rawDevice);

            $activationLink = _app_url().'/frontend/verify_phone.php?t='.urlencode($rawToken);
            session_regenerate_id(true);
            if ($vRowId > 0) $pdo->prepare('UPDATE user_phone_verifications SET session_id=? WHERE id=?')->execute([session_id(),$vRowId]);
            $_SESSION['pending_user_id'] = $newId;
            unset($_SESSION['user_id'],$_SESSION['user'],$_SESSION['pending_otp'],$_SESSION['pending_verify_link']);

            if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); _no_cache(); }
            echo json_encode([
                'ok'              => true,
                'message'         => $regLang === 'ar' ? 'تم إنشاء الحساب. شارك رابط التفعيل يدوياً.' : 'Account created. Share the activation link manually.',
                'activation_link' => $activationLink,
                'user'            => ['id'=>$newId,'username'=>$regUser,'email'=>$regEmail,'phone'=>$regPhone ?: null,
                                      'preferred_language'=>$regLang,'is_active'=>false,'permissions'=>[],'roles'=>[]],
            ]);
            _flush_response();

            if ($regPhone) {
                try {
                    $sf = __DIR__.'/../../../shared/helpers/sms.php';
                    if (file_exists($sf)) require_once $sf;
                    if (class_exists('SMS')) { SMS::setPDO($pdo); SMS::sendVerificationLink($regPhone,$activationLink,$regLang ?: 'ar'); }
                } catch (Throwable $smsE) { if (class_exists('Logger')) Logger::error('SMS: '.$smsE->getMessage()); }
            }
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Register: '.$e->getMessage());
            ResponseFormatter::serverError('Registration failed.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  RESEND VERIFICATION
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'resend_verification') {
        if (!$csrfOk()) { ResponseFormatter::error('Invalid request. Please reload the page.', 403); exit; }

        $pendingId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingId) { ResponseFormatter::error('No pending registration found.', 400); exit; }

        try {
            $ur = $pdo->prepare('SELECT phone,preferred_language FROM users WHERE id=? AND is_active=0 LIMIT 1');
            $ur->execute([(int)$pendingId]); $uData = $ur->fetch(PDO::FETCH_ASSOC);
            if (!$uData || empty($uData['phone'])) { ResponseFormatter::error('User not found or already activated.', 400); exit; }

            $rc = $pdo->prepare('SELECT COUNT(*) FROM user_phone_verifications WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 60 SECOND)');
            $rc->execute([(int)$pendingId]);
            if ((int)$rc->fetchColumn() > 0) { ResponseFormatter::error('Please wait 60 seconds before requesting another SMS.', 429); exit; }

            $rawToken = bin2hex(random_bytes(32)); $rawDevice = bin2hex(random_bytes(16));
            $pdo->prepare('INSERT INTO user_phone_verifications (user_id,token_hash,device_hash,session_id,user_agent,ip,expires_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([(int)$pendingId,hash('sha256',$rawToken),hash('sha256',$rawDevice),session_id(),_ua(),_ip(),date('Y-m-d H:i:s',time()+86400)]);
            _set_device_cookie($rawDevice);

            $activationLink = _app_url().'/frontend/verify_phone.php?t='.urlencode($rawToken);
            $resendLang = preg_replace('/[^a-z\-]/', '', strtolower($uData['preferred_language'] ?: 'ar'));

            unset($_SESSION['pending_verify_link']);
            if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); _no_cache(); }
            echo json_encode(['ok'=>true,'message'=>'Verification SMS sent.','activation_link'=>$activationLink,'phone'=>$uData['phone']??'']);
            _flush_response();

            $sf = __DIR__.'/../../../shared/helpers/sms.php';
            if (file_exists($sf)) require_once $sf;
            if (class_exists('SMS')) { SMS::setPDO($pdo); SMS::sendVerificationLink($uData['phone'],$activationLink,$resendLang); }
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Resend verification: '.$e->getMessage());
            ResponseFormatter::serverError('Failed to resend verification SMS.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  VERIFY OTP
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'verify_otp') {
        $otp = trim((string)($payload['otp'] ?? ''));
        $sessOtp  = $_SESSION['pending_otp']         ?? null;
        $sessUid  = $_SESSION['pending_user_id']      ?? null;
        $expires  = (int)($_SESSION['pending_otp_expires']  ?? 0);
        $attempts = (int)($_SESSION['pending_otp_attempts'] ?? 0);

        if (!preg_match('/^\d{6}$/', $otp)) { ResponseFormatter::error('OTP must be a 6-digit number', 422); exit; }
        if (!$sessOtp || !$sessUid) { ResponseFormatter::error('No pending verification. Please register again.', 400); exit; }
        if (time() > $expires) {
            unset($_SESSION['pending_otp'],$_SESSION['pending_user_id'],$_SESSION['pending_otp_expires'],$_SESSION['pending_otp_attempts']);
            ResponseFormatter::error('OTP expired. Please register again.', 400); exit;
        }
        if ($attempts >= 5) {
            unset($_SESSION['pending_otp'],$_SESSION['pending_user_id'],$_SESSION['pending_otp_expires'],$_SESSION['pending_otp_attempts']);
            ResponseFormatter::error('Too many attempts. Please register again.', 429); exit;
        }
        if ($otp !== $sessOtp) {
            $_SESSION['pending_otp_attempts'] = $attempts + 1;
            ResponseFormatter::error('Invalid OTP. '.(5-$attempts-1).' attempt(s) remaining.', 401); exit;
        }

        try {
            $upd = $pdo->prepare('UPDATE users SET is_active=1,updated_at=NOW() WHERE id=? AND is_active=0');
            $upd->execute([$sessUid]);
            if ($upd->rowCount() === 0) { ResponseFormatter::error('Account already active or not found.', 409); exit; }

            $rs = $pdo->prepare('SELECT id,username,email,phone,preferred_language FROM users WHERE id=?');
            $rs->execute([$sessUid]); $ud = $rs->fetch(PDO::FETCH_ASSOC);

            unset($_SESSION['pending_otp'],$_SESSION['pending_user_id'],$_SESSION['pending_otp_expires'],$_SESSION['pending_otp_attempts']);
            session_regenerate_id(true);

            $user = ['id'=>(int)$ud['id'],'name'=>$ud['username'],'username'=>$ud['username'],'email'=>$ud['email'],
                     'phone'=>$ud['phone'],'role_id'=>null,'preferred_language'=>$ud['preferred_language'],
                     'is_active'=>true,'permissions'=>[],'roles'=>[],'permissions_count'=>0,'roles_count'=>0];
            $_SESSION['user_id'] = $user['id']; $_SESSION['user'] = $user; $GLOBALS['ADMIN_USER'] = $user;

            if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); _no_cache(); }
            echo json_encode(['ok'=>true,'message'=>'Account verified and activated','user'=>$user]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Verify OTP: '.$e->getMessage());
            ResponseFormatter::serverError('Verification failed.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  GOOGLE LOGIN  (id_token from Google SDK)
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'google_login') {
        $idToken = trim((string)($payload['id_token'] ?? ''));
        if ($idToken === '') { ResponseFormatter::error('Missing Google ID token', 400); exit; }

        [$tiRaw, $err] = _curl_get('https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($idToken));
        if ($err || !$tiRaw) { ResponseFormatter::serverError('Could not reach Google servers. Try again.'); exit; }
        $ti = json_decode($tiRaw, true);
        if (empty($ti['sub']) || empty($ti['email'])) {
            if (class_exists('Logger')) Logger::error('Google tokeninfo invalid: '.$tiRaw);
            ResponseFormatter::error('Invalid Google token', 401); exit;
        }

        $clientId = getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
        if ($clientId !== '' && ($ti['aud'] ?? '') !== $clientId) { ResponseFormatter::error('Token audience mismatch', 401); exit; }

        $sub   = (string)$ti['sub'];
        $email = filter_var($ti['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $ti['email'] : '';
        $name  = trim((string)($ti['name'] ?? $ti['given_name'] ?? ''));
        if ($email === '') { ResponseFormatter::error('Google account email missing or invalid', 422); exit; }

        try {
            $user = _provider_login($pdo, 'google', $sub, $email, $name,
                ['email_verified'=>(bool)($ti['email_verified'] ?? false),'name'=>$name,'picture'=>$ti['picture'] ?? null]);
            ResponseFormatter::success(['ok'=>true,'message'=>'Authenticated','user'=>$user]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Google login: '.$e->getMessage());
            ResponseFormatter::serverError('Google sign-in failed. Please try again.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  FACEBOOK LOGIN  (access_token from Facebook SDK)
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'facebook_login') {
        $accessToken = trim((string)($payload['access_token'] ?? ''));
        if ($accessToken === '') { ResponseFormatter::error('Missing Facebook access token', 400); exit; }

        [$meRaw, $err] = _curl_get(
            'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token='.urlencode($accessToken)
        );
        if ($err || !$meRaw) { ResponseFormatter::serverError('Could not reach Facebook servers. Try again.'); exit; }
        $me = json_decode($meRaw, true);
        if (empty($me['id'])) {
            if (class_exists('Logger')) Logger::error('Facebook /me invalid: '.$meRaw);
            ResponseFormatter::error('Invalid Facebook token', 401); exit;
        }

        $sub   = (string)$me['id'];
        $email = filter_var($me['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $me['email'] : '';
        $name  = trim((string)($me['name'] ?? ''));

        if ($email === '') {
            ResponseFormatter::error('Your Facebook account has no verified email. Please use another sign-in method.', 422); exit;
        }

        try {
            $user = _provider_login($pdo, 'facebook', $sub, $email, $name,
                ['email_verified'=>true,'name'=>$name,'picture'=>$me['picture']['data']['url'] ?? null]);
            ResponseFormatter::success(['ok'=>true,'message'=>'Authenticated','user'=>$user]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Facebook login: '.$e->getMessage());
            ResponseFormatter::serverError('Facebook sign-in failed. Please try again.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  APPLE LOGIN  (identity_token from Apple SDK)
    // ════════════════════════════════════════════════════════════════════════
    if ($ea === 'apple_login') {
        $identityToken = trim((string)($payload['identity_token'] ?? ''));
        $appleUserName = trim((string)($payload['user_name']      ?? ''));
        if ($identityToken === '') { ResponseFormatter::error('Missing Apple identity token', 400); exit; }

        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) { ResponseFormatter::error('Invalid Apple token format', 401); exit; }

        $jwtPayload = json_decode(
            base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true
        );
        if (empty($jwtPayload['sub'])) { ResponseFormatter::error('Invalid Apple token', 401); exit; }

        $appleAppId = getenv('APPLE_APP_ID') ?: (defined('APPLE_APP_ID') ? APPLE_APP_ID : '');
        if ($appleAppId !== '' && ($jwtPayload['aud'] ?? '') !== $appleAppId) {
            ResponseFormatter::error('Apple token audience mismatch', 401); exit;
        }
        if (isset($jwtPayload['exp']) && $jwtPayload['exp'] < time()) {
            ResponseFormatter::error('Apple token expired', 401); exit;
        }

        $sub   = (string)$jwtPayload['sub'];
        $email = filter_var($jwtPayload['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $jwtPayload['email'] : '';

        // Apple only sends email on FIRST sign-in — look up from previous login
        if ($email === '') {
            $prev = $pdo->prepare("SELECT provider_extra FROM user_auth_providers WHERE provider='apple' AND provider_user_id=? LIMIT 1");
            $prev->execute([$sub]); $prevRow = $prev->fetch(PDO::FETCH_ASSOC);
            if ($prevRow) { $pe = json_decode($prevRow['provider_extra'] ?? '{}', true); $email = $pe['email'] ?? ''; }
        }

        if ($email === '') {
            ResponseFormatter::error('Could not retrieve email from Apple. Please sign in with Apple again on your device.', 422); exit;
        }

        $name = $appleUserName ?: trim(explode('@', $email)[0]);

        try {
            $user = _provider_login($pdo, 'apple', $sub, $email, $name,
                ['email_verified'=>true,'name'=>$name,'email'=>$email]);
            ResponseFormatter::success(['ok'=>true,'message'=>'Authenticated','user'=>$user]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Apple login: '.$e->getMessage());
            ResponseFormatter::serverError('Apple sign-in failed. Please try again.');
        }
        exit;
    }


    // ════════════════════════════════════════════════════════════════════════
    //  LOGIN  (username/email + password)
    // ════════════════════════════════════════════════════════════════════════
    $username = trim((string)($payload['username'] ?? $payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    if ($username === '' || $password === '') { ResponseFormatter::error('Missing credentials', 400); exit; }

    try {
        $stmt = $pdo->prepare(
            'SELECT u.id,u.username,u.email,u.password_hash,u.phone,u.preferred_language,u.is_active,tu.role_id,tu.tenant_id
             FROM users u LEFT JOIN tenant_users tu ON tu.user_id=u.id AND tu.is_active=1
             WHERE (u.username=? OR u.email=?) ORDER BY tu.joined_at DESC LIMIT 1'
        );
        $stmt->execute([$username,$username]); $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !@password_verify($password, (string)($row['password_hash'] ?? ''))) {
            ResponseFormatter::error('Invalid credentials', 401); exit;
        }
        if (isset($row['is_active']) && !(bool)$row['is_active']) {
            ResponseFormatter::error('Account is not active. Please verify your phone number.', 403); exit;
        }

        session_regenerate_id(true);
        $rbac = _load_rbac($pdo, (int)$row['id'], isset($row['role_id']) ? (int)$row['role_id'] : null);

        $user = [
            'id'                 => (int)$row['id'],
            'name'               => $row['username'],
            'username'           => $row['username'],
            'email'              => $row['email'],
            'phone'              => $row['phone'] ?? null,
            'role_id'            => isset($row['role_id'])   ? (int)$row['role_id']   : null,
            'tenant_id'          => isset($row['tenant_id']) ? (int)$row['tenant_id'] : 1,
            'preferred_language' => $row['preferred_language'] ?? 'en',
            'is_active'          => true,
            'permissions'        => $rbac['permissions'],
            'roles'              => $rbac['roles'],
            'permissions_count'  => count($rbac['permissions']),
            'roles_count'        => count($rbac['roles']),
        ];

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user']        = $user;
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['roles']       = $user['roles'];
        $GLOBALS['ADMIN_USER']   = $user;
        _link_device_on_login($pdo, (int)$user['id']);

        ResponseFormatter::success(['ok'=>true,'message'=>'Authenticated','user'=>$user]);
    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('Login: '.$e->getMessage());
        ResponseFormatter::serverError('Authentication failed.');
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
ResponseFormatter::notFound('Auth route not supported');
exit;
