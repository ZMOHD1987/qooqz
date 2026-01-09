<?php
// htdocs/frontend/coupons.php
// Frontend page for coupons/promotions: list available coupons, apply a coupon to the cart, and (for admins) create new coupons.
// Uses API endpoints: /api/coupons, /api/coupons/{id}, /api/cart/apply-coupon, /api/cart/remove-coupon, /api/coupons (POST for create)
// Minimal Arabic UI and lightweight JS to interact with API.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// Basic security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function api_request(string $method, string $path, $payload = null, $extraHeaders = [])
{
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json'];
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($extraHeaders) && is_array($extraHeaders)) {
        $headers = array_merge($headers, $extraHeaders);
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

// Fetch coupons for listing
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 30;
$couponsRes = api_request('GET', '/coupons?page=' . $page . '&per_page=' . $perPage);

// Try fetch current user to show admin create form when appropriate
$meRes = api_request('GET', '/users/me');
$isAdmin = !empty($meRes['success']) && !empty($meRes['user']) && in_array($meRes['user']['role'] ?? '', ['admin', 'superadmin']);

$coupons = [];
$pagination = [];
if (!empty($couponsRes['success'])) {
    $coupons = $couponsRes['data'] ?? $couponsRes['coupons'] ?? (is_array($couponsRes) && isset($couponsRes[0]) ? $couponsRes : []);
    $pagination = $couponsRes['meta'] ?? $couponsRes['pagination'] ?? ['page' => $page, 'per_page' => $perPage, 'total' => count($coupons)];
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>قسائم وخصومات</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:900px;margin:auto}
        .card{border:1px solid #eee;padding:14px;margin-bottom:12px;border-radius:6px;background:#fff}
        .coupon{border:1px dashed #e6e6e6;padding:10px;margin-bottom:8px;border-radius:6px;display:flex;justify-content:space-between;align-items:center}
        .coupon.unavailable{opacity:0.6}
        .meta{flex:1;text-align:right;margin-right:12px}
        .code{font-weight:700;color:#c0392b;font-family:monospace}
        .muted{color:#666}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .btn.ghost{background:#95a5a6}
        label{display:block;margin-top:8px}
        input[type="text"]{width:260px;padding:8px;box-sizing:border-box;margin-left:8px}
        .actions{display:flex;gap:8px}
    </style>
</head>
<body>
<div class="container">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1>القسائم والعروض</h1>
        <div><a class="btn" href="/frontend/cart.php">عرض السلة</a></div>
    </header>

    <section class="card" aria-labelledby="applyHeading">
        <h2 id="applyHeading">تطبيق قسيمة إلى السلة</h2>
        <form id="applyForm" style="display:flex;align-items:center;gap:8px">
            <label for="couponCode">رمز القسيمة:</label>
            <input type="text" id="couponCode" name="code" placeholder="أدخل رمز القسيمة" required>
            <button class="btn" type="submit">تطبيق</button>
            <button class="btn ghost" type="button" id="removeCoupon">إزالة القسيمة</button>
        </form>
        <div id="applyMsg" style="margin-top:10px" class="muted"></div>
    </section>

    <section class="card" aria-labelledby="listHeading">
        <h2 id="listHeading">القسائم المتاحة</h2>
        <?php if (empty($coupons)): ?>
            <p class="muted">لا توجد قسائم متاحة حالياً.</p>
        <?php else: ?>
            <?php foreach ($coupons as $c): 
                $code = $c['code'] ?? $c['coupon_code'] ?? ($c['id'] ?? '');
                $title = $c['title'] ?? $c['name'] ?? '';
                $desc = $c['description'] ?? $c['note'] ?? '';
                $amount = isset($c['amount']) ? $c['amount'] : ($c['discount'] ?? null);
                $type = $c['type'] ?? ($c['discount_type'] ?? 'percentage'); // 'percentage' or 'fixed'
                $min = $c['min_total'] ?? $c['minimum_order_amount'] ?? null;
                $expires = $c['expires_at'] ?? $c['expiry'] ?? null;
                $active = empty($c['is_active']) ? false : true;
                $available = $active && (empty($c['uses_left']) || $c['uses_left'] > 0);
            ?>
                <div class="coupon <?php echo $available ? '' : 'unavailable'; ?>">
                    <div class="meta">
                        <div><span class="code"><?php echo e($code); ?></span> — <strong><?php echo e($title); ?></strong></div>
                        <?php if ($desc): ?><div class="muted" style="margin-top:6px"><?php echo e(mb_substr($desc,0,200)); ?></div><?php endif; ?>
                        <div class="muted" style="margin-top:6px">
                            <?php if ($amount !== null): ?>
                                خصم: <?php echo e($type === 'percentage' ? (float)$amount . '%' : number_format((float)$amount,2)); ?>
                                &nbsp;|&nbsp;
                            <?php endif; ?>
                            <?php if ($min): ?>حد أدنى للطلب: <?php echo e((float)$min); ?> &nbsp;|&nbsp;<?php endif; ?>
                            <?php if ($expires): ?>تنتهي: <?php echo e(date('Y-m-d', strtotime($expires))); ?><?php endif; ?>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn applyBtn" data-code="<?php echo e($code); ?>" <?php echo $available ? '' : 'disabled'; ?>>تطبيق</button>
                        <?php if ($isAdmin): ?>
                            <a class="btn ghost" href="/frontend/admin/coupon_edit.php?id=<?php echo urlencode($c['id'] ?? ''); ?>">تحرير</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            // simple pager display if pagination provided
            $total = $pagination['total'] ?? null;
            $cur = $pagination['page'] ?? $page;
            $pp = $pagination['per_page'] ?? $perPage;
            if ($total !== null && $total > $pp):
                $last = (int)ceil($total / $pp);
            ?>
                <div style="margin-top:12px; display:flex;gap:8px;align-items:center">
                    <?php if ($cur > 1): ?>
                        <a class="btn ghost" href="?page=<?php echo $cur - 1; ?>">السابق</a>
                    <?php endif; ?>
                    <span class="muted">صفحة <?php echo e($cur); ?> من <?php echo e($last); ?></span>
                    <?php if ($cur < $last): ?>
                        <a class="btn" href="?page=<?php echo $cur + 1; ?>">التالي</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($isAdmin): ?>
    <section class="card" aria-labelledby="adminHeading">
        <h2 id="adminHeading">إنشاء قسيمة جديدة (إداري)</h2>
        <form id="createForm">
            <label>رمز القسيمة
                <input type="text" name="code" required placeholder="مثال: SAVE10">
            </label>
            <label>الاسم
                <input type="text" name="title" placeholder="عنوان العرض">
            </label>
            <label>الوصف
                <input type="text" name="description" placeholder="وصف مختصر">
            </label>
            <label>نوع الخصم
                <select name="type">
                    <option value="percentage">نسبة مئوية</option>
                    <option value="fixed">قيمة ثابتة</option>
                </select>
            </label>
            <label>قيمة الخصم
                <input type="text" name="amount" required placeholder="مثال: 10 أو 15.5">
            </label>
            <label>حد أدنى للطلب (اختياري)
                <input type="text" name="min_total" placeholder="مثال: 100.00">
            </label>
            <label>تاريخ الانتهاء (YYYY-MM-DD)
                <input type="text" name="expires_at" placeholder="مثال: 2025-12-31">
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">إنشاء القسيمة</button>
            </div>
            <div id="createMsg" style="margin-top:8px" class="muted"></div>
        </form>
    </section>
    <?php endif; ?>

</div>

<script>
async function api(path, method='GET', body=null) {
    const opts = { method, headers: { 'Accept': 'application/json' } };
    if (body !== null) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch('/api' + path, opts);
    const json = await res.json().catch(()=>null);
    return { ok: res.ok, status: res.status, json };
}

function el(id){ return document.getElementById(id); }
function qsa(sel, ctx=document){ return Array.from((ctx||document).querySelectorAll(sel)); }

document.addEventListener('DOMContentLoaded', function () {
    const applyForm = el('applyForm');
    const applyMsg = el('applyMsg');
    const couponInput = el('couponCode');
    const removeBtn = el('removeCoupon');

    // apply coupon using /api/cart/apply-coupon or /api/cart/coupon
    applyForm.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const code = couponInput.value.trim();
        if (!code) {
            applyMsg.style.color = 'red'; applyMsg.textContent = 'الرجاء إدخال رمز القسيمة.';
            return;
        }
        applyMsg.style.color = ''; applyMsg.textContent = 'جاري تطبيق القسيمة...';
        try {
            // attempt recommended endpoint
            let res = await api('/cart/apply-coupon', 'POST', { code });
            if (!res.ok) {
                // fallback names
                res = await api('/cart/coupon', 'POST', { code });
            }
            if (res.ok) {
                applyMsg.style.color = 'green';
                applyMsg.textContent = (res.json && (res.json.message || 'تم تطبيق القسيمة')) || 'تم تطبيق القسيمة';
                // optionally reload cart after short delay
                setTimeout(()=> window.location.href = '/frontend/cart.php', 900);
            } else {
                applyMsg.style.color = 'red';
                applyMsg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل تطبيق القسيمة';
            }
        } catch (err) {
            applyMsg.style.color = 'red';
            applyMsg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

    // remove coupon
    removeBtn?.addEventListener('click', async function () {
        if (!confirm('هل تريد إزالة القسيمة المطبقة من السلة؟')) return;
        applyMsg.style.color = ''; applyMsg.textContent = 'جاري إزالة القسيمة...';
        try {
            let res = await api('/cart/remove-coupon', 'POST', {});
            if (!res.ok) {
                res = await api('/cart/coupon', 'DELETE', {});
            }
            if (res.ok) {
                applyMsg.style.color = 'green';
                applyMsg.textContent = (res.json && (res.json.message || 'تمت إزالة القسيمة')) || 'تمت إزالة القسيمة';
                setTimeout(()=> window.location.reload(), 800);
            } else {
                applyMsg.style.color = 'red';
                applyMsg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إزالة القسيمة';
            }
        } catch (err) {
            applyMsg.style.color = 'red';
            applyMsg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

    // coupon apply buttons in list
    qsa('.applyBtn').forEach(b => {
        b.addEventListener('click', async function () {
            const code = this.getAttribute('data-code') || '';
            if (!code) return;
            couponInput.value = code;
            applyForm.dispatchEvent(new Event('submit', { cancelable: true }));
        });
    });

    // Admin create coupon
    const createForm = el('createForm');
    const createMsg = el('createMsg');
    if (createForm) {
        createForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            createMsg.style.color = '';
            createMsg.textContent = 'جاري إنشاء القسيمة...';
            const fd = new FormData(createForm);
            const payload = {};
            for (const [k,v] of fd.entries()) { if (v !== '') payload[k] = v; }
            // normalize numeric fields
            if (payload.amount) payload.amount = parseFloat(payload.amount);
            if (payload.min_total) payload.min_total = parseFloat(payload.min_total);
            try {
                const res = await api('/coupons', 'POST', payload);
                if (res.ok) {
                    createMsg.style.color = 'green';
                    createMsg.textContent = (res.json && (res.json.message || 'تم إنشاء القسيمة')) || 'تم إنشاء القسيمة';
                    setTimeout(()=> location.reload(), 900);
                } else {
                    createMsg.style.color = 'red';
                    createMsg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إنشاء القسيمة';
                }
            } catch (err) {
                createMsg.style.color = 'red';
                createMsg.textContent = 'خطأ في الاتصال: ' + err.message;
            }
        });
    }
});
</script>
</body>
</html>