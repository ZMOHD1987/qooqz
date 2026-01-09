<?php
// htdocs/frontend/users.php
// Frontend page for user actions: view/edit profile, login, register, and simple admin users list (if authorized).
// Uses API endpoints under /api (e.g., /api/users/me, /api/auth/login, /api/auth/register)
// Minimal Arabic UI, server-side fetch for initial state, client-side JS for actions.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// Basic security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function api_request(string $method, string $path, $payload = null, $headersExtra = [])
{
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json'];
    // propagate Authorization header from client (if server received it)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($headersExtra) && is_array($headersExtra)) {
        $headers = array_merge($headers, $headersExtra);
    }

    if ($payload !== null) {
        $json = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return ['success' => false, 'status' => $status, 'error' => $err];
    $decoded = json_decode($resp, true);
    if ($decoded === null) return ['success' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $resp];
    return array_merge(['success' => $status >= 200 && $status < 300, 'status' => $status], $decoded);
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Try fetch current user profile using server-side call (useful for SSR rendering if Authorization cookie/header passed)
$meRes = api_request('GET', '/users/me');
// also try fetch a short users list if user is admin (non-fatal)
$usersList = [];
if (!empty($meRes['success']) && !empty($meRes['user']) && !empty($meRes['user']['role']) && in_array($meRes['user']['role'], ['admin', 'superadmin'])) {
    $ul = api_request('GET', '/admin/users');
    if (!empty($ul['success'])) {
        $usersList = $ul['data'] ?? $ul['users'] ?? [];
    }
}

?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>المستخدمون</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; direction: rtl; margin:20px; }
        .container { max-width: 900px; margin: auto; }
        .card { border:1px solid #eee; padding:12px; margin-bottom:12px; border-radius:6px; background:#fff; }
        label { display:block; margin-bottom:6px; }
        input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:8px; box-sizing:border-box; margin-bottom:8px; }
        .btn { display:inline-block; padding:8px 12px; background:#2d8cf0; color:#fff; border-radius:4px; text-decoration:none; cursor:pointer; }
        .muted { color:#666; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
        .admin-list table { width:100%; border-collapse:collapse; }
        .admin-list th, .admin-list td { padding:8px; border:1px solid #f1f1f1; text-align:right; }
    </style>
</head>
<body>
<div class="container">
    <h1>المستخدمون</h1>

    <?php if (!empty($meRes['success']) && !empty($meRes['user'])): 
        $user = $meRes['user'];
    ?>
        <section class="card" aria-labelledby="profileHeading">
            <h2 id="profileHeading">ملفي الشخصي</h2>
            <p class="muted">مرحباً، <?php echo e($user['name'] ?? $user['email'] ?? ''); ?></p>

            <form id="profileForm">
                <div class="grid">
                    <div>
                        <label>الاسم الكامل
                            <input type="text" name="name" value="<?php echo e($user['name'] ?? ''); ?>">
                        </label>
                        <label>الهاتف
                            <input type="text" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>">
                        </label>
                    </div>
                    <div>
                        <label>البريد الإلكتروني
                            <input type="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>">
                        </label>
                        <label>كلمة المرور (اتركه فارغاً للحفاظ على الحالي)
                            <input type="password" name="password" placeholder="••••••••">
                        </label>
                    </div>
                </div>
                <div style="margin-top:8px">
                    <button class="btn" type="submit">حفظ التغييرات</button>
                    <button class="btn" type="button" id="logoutBtn" style="background:#e74c3c">تسجيل الخروج</button>
                </div>
                <div id="profileMsg" style="margin-top:8px"></div>
            </form>
        </section>
    <?php else: ?>
        <section class="card">
            <h2>تسجيل الدخول</h2>
            <form id="loginForm">
                <label>البريد الإلكتروني أو اسم المستخدم
                    <input type="text" name="identifier" required>
                </label>
                <label>كلمة المرور
                    <input type="password" name="password" required>
                </label>
                <div>
                    <button class="btn" type="submit">تسجيل الدخول</button>
                    <a class="btn" href="#register" id="showRegister" style="background:#27ae60">تسجيل حساب جديد</a>
                </div>
                <div id="loginMsg" style="margin-top:8px"></div>
            </form>
        </section>

        <section class="card" id="registerCard" style="display:none">
            <h2>إنشاء حساب</h2>
            <form id="registerForm">
                <label>الاسم الكامل
                    <input type="text" name="name" required>
                </label>
                <label>البريد الإلكتروني
                    <input type="email" name="email" required>
                </label>
                <label>كلمة المرور
                    <input type="password" name="password" required>
                </label>
                <label>تأكيد كلمة المرور
                    <input type="password" name="password_confirmation" required>
                </label>
                <div>
                    <button class="btn" type="submit">إنشاء الحساب</button>
                    <button class="btn" type="button" id="cancelRegister" style="background:#95a5a6">إلغاء</button>
                </div>
                <div id="registerMsg" style="margin-top:8px"></div>
            </form>
        </section>
    <?php endif; ?>

    <?php if (!empty($usersList)): ?>
        <section class="card admin-list" aria-labelledby="adminUsersHeading">
            <h2 id="adminUsersHeading">قائمة المستخدمين (إداري)</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الاسم</th><th>البريد</th><th>الدور</th><th>إجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($usersList as $u): ?>
                    <tr data-user-id="<?php echo e($u['id'] ?? ''); ?>">
                        <td><?php echo e($u['id'] ?? ''); ?></td>
                        <td><?php echo e($u['name'] ?? ''); ?></td>
                        <td><?php echo e($u['email'] ?? ''); ?></td>
                        <td><?php echo e($u['role'] ?? ''); ?></td>
                        <td>
                            <button class="btn admin-edit" data-id="<?php echo e($u['id'] ?? ''); ?>">تحرير</button>
                            <button class="btn" style="background:#e74c3c" onclick="adminDelete(<?php echo e($u['id'] ?? 0); ?>)">حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

</div>

<script>
// Lightweight client-side interactions: login, register, profile update, logout, admin delete
function notify(el, msg, ok = true) {
    el.textContent = msg;
    el.style.color = ok ? 'green' : 'red';
}

async function api(path, method = 'GET', body = null) {
    const opts = { method, headers: { 'Accept': 'application/json' } };
    if (body !== null) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch('/api' + path, opts);
    const json = await res.json().catch(()=>null);
    return { ok: res.ok, status: res.status, json };
}

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const msg = document.getElementById('loginMsg');
            notify(msg, '...جاري تسجيل الدخول', true);
            const formData = new FormData(loginForm);
            const payload = {
                identifier: formData.get('identifier'),
                password: formData.get('password')
            };
            const res = await api('/auth/login', 'POST', payload);
            if (res.ok) {
                notify(msg, 'تم تسجيل الدخول. يرجى إعادة تحميل الصفحة...', true);
                setTimeout(()=> location.reload(), 800);
            } else {
                notify(msg, (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل تسجيل الدخول', false);
            }
        });

        document.getElementById('showRegister')?.addEventListener('click', function (ev) {
            ev.preventDefault();
            document.getElementById('registerCard').style.display = 'block';
            loginForm.style.display = 'none';
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const msg = document.getElementById('registerMsg');
            notify(msg, '...جاري إنشاء الحساب', true);
            const fd = new FormData(registerForm);
            const payload = {
                name: fd.get('name'),
                email: fd.get('email'),
                password: fd.get('password'),
                password_confirmation: fd.get('password_confirmation')
            };
            const res = await api('/auth/register', 'POST', payload);
            if (res.ok) {
                notify(msg, 'تم إنشاء الحساب. ستتم إعادة التحويل بعد تسجيل الدخول...', true);
                setTimeout(()=> location.reload(), 900);
            } else {
                notify(msg, (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إنشاء الحساب', false);
            }
        });

        document.getElementById('cancelRegister')?.addEventListener('click', function () {
            document.getElementById('registerCard').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
        });
    }

    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const msg = document.getElementById('profileMsg');
            notify(msg, '...جارٍ حفظ البيانات', true);
            const fd = new FormData(profileForm);
            const payload = {
                name: fd.get('name'),
                phone: fd.get('phone'),
                email: fd.get('email')
            };
            const pwd = fd.get('password');
            if (pwd) payload.password = pwd;
            // API may expect PUT /users/{id} or POST /users/me
            const res = await api('/users/me', 'POST', payload);
            if (res.ok) {
                notify(msg, 'تم حفظ الملف الشخصي', true);
                setTimeout(()=> location.reload(), 800);
            } else {
                notify(msg, (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل الحفظ', false);
            }
        });

        document.getElementById('logoutBtn')?.addEventListener('click', async function () {
            // call logout endpoint if exists, else clear client cookie and reload
            await api('/auth/logout', 'POST', {});
            location.reload();
        });
    }

    // admin actions
    document.querySelectorAll('.admin-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            window.location.href = '/frontend/admin/user_edit.php?id=' + encodeURIComponent(id);
        });
    });
});

async function adminDelete(id) {
    if (!confirm('هل أنت متأكد من حذف هذا المستخدم؟ هذه العملية لا يمكن التراجع عنها.')) return;
    const res = await api('/admin/users/' + encodeURIComponent(id), 'DELETE');
    if (res.ok) {
        alert('تم الحذف');
        location.reload();
    } else {
        alert('فشل الحذف: ' + ((res.json && (res.json.message || JSON.stringify(res.json))) || 'خطأ'));
    }
}
</script>
</body>
</html>