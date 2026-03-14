<?php
// htdocs/frontend/admin.php
// Frontend admin dashboard (minimal). Shows quick stats, recent orders, low-stock products, recent users,
// and provides quick actions like clearing caches or triggering reindex.
// Uses API endpoints: /api/users/me, /api/admin/stats, /api/admin/recent-orders, /api/products?low_stock=1, /api/admin/cache/clear
// Note: this page expects the requester to be authenticated and authorized (role: admin/superadmin).
// It's a lightweight scaffold — adapt endpoints and shapes to your API.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// Security headers
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

// Fetch current user to ensure admin access
$me = api_request('GET', '/users/me');
$isAdmin = false;
$user = null;
if (!empty($me['success']) && !empty($me['user'])) {
    $user = $me['user'];
    $role = strtolower($user['role'] ?? '');
    $isAdmin = in_array($role, ['admin', 'superadmin', 'manager']);
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>لوحة التحكم - الإدارة</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px;background:#f6f8fb}
        .container{max-width:1100px;margin:auto}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
        .card{background:#fff;padding:14px;border:1px solid #eee;border-radius:8px;min-height:80px}
        .card h3{margin:0 0 8px 0}
        .muted{color:#666;font-size:0.95em}
        .list{margin-top:8px}
        .list-item{padding:8px;border-bottom:1px solid #f1f1f1;display:flex;justify-content:space-between;align-items:center}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .btn{display:inline-block;padding:8px 10px;background:#2d8cf0;color:#fff;border-radius:6px;text-decoration:none;cursor:pointer}
        .btn.warn{background:#e67e22}
        .btn.ghost{background:#95a5a6}
        .sec{margin-top:12px}
        @media (max-width:900px){ .grid{grid-template-columns:repeat(2,1fr)} }
        @media (max-width:520px){ .grid{grid-template-columns:1fr} }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>لوحة الإدارة</h1>
            <div class="muted">مرحباً، <?php echo e($user['name'] ?? $user['email'] ?? 'مستخدم'); ?> — <?php echo e($user['role'] ?? ''); ?></div>
        </div>
        <div class="actions">
            <a class="btn" href="/frontend/products.php">الواجهة الأمامية</a>
            <a class="btn ghost" href="/frontend/admin/settings.php">الإعدادات</a>
            <a class="btn warn" href="/api/auth/logout">تسجيل الخروج</a>
        </div>
    </header>

<?php if (!$isAdmin): ?>
    <div class="card">
        <h3>ليس لديك صلاحية</h3>
        <p class="muted">يجب أن تكون مسؤولاً للوصول إلى هذه الصفحة.</p>
    </div>
    <?php exit; ?>
<?php endif; ?>

    <section class="grid" id="statsGrid">
        <div class="card">
            <h3>الطلبات</h3>
            <div id="ordersCount" style="font-size:1.4em;font-weight:700">...</div>
            <div class="muted">الطلبات المفتوحة / الكلية</div>
        </div>
        <div class="card">
            <h3>المبيعات</h3>
            <div id="salesTotal" style="font-size:1.4em;font-weight:700">...</div>
            <div class="muted">إجمالي المبيعات (آخر 30 يوم)</div>
        </div>
        <div class="card">
            <h3>المنتجات منخفضة المخزون</h3>
            <div id="lowStockCount" style="font-size:1.4em;font-weight:700">...</div>
            <div class="muted">منتجات بحاجة لإعادة تخزين</div>
        </div>
        <div class="card">
            <h3>المستخدمون</h3>
            <div id="usersCount" style="font-size:1.4em;font-weight:700">...</div>
            <div class="muted">المستخدمون المسجلون</div>
        </div>
    </section>

    <section class="card sec">
        <h3>أحدث الطلبات</h3>
        <div id="recentOrders" class="list">
            <div class="muted">جاري جلب الطلبات...</div>
        </div>
        <div style="margin-top:10px">
            <a class="btn" href="/frontend/orders.php">عرض جميع الطلبات</a>
            <button class="btn ghost" id="refreshOrders">تحديث</button>
        </div>
    </section>

    <section class="card sec">
        <h3>منتجات منخفضة المخزون</h3>
        <div id="lowStockList" class="list">
            <div class="muted">جاري جلب المنتجات...</div>
        </div>
        <div style="margin-top:10px">
            <a class="btn" href="/frontend/products.php">إدارة المنتجات</a>
            <button class="btn ghost" id="refreshStock">تحديث</button>
        </div>
    </section>

    <section class="card sec">
        <h3>آخر المستخدمين المسجلين</h3>
        <div id="recentUsers" class="list">
            <div class="muted">جاري جلب المستخدمين...</div>
        </div>
        <div style="margin-top:10px">
            <a class="btn" href="/frontend/users.php">قائمة المستخدمين</a>
            <button class="btn ghost" id="refreshUsers">تحديث</button>
        </div>
    </section>

    <section class="card sec">
        <h3>أوامر سريعة</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn" id="clearCache">مسح الكاش</button>
            <button class="btn" id="reindexSearch">إعادة فهرسة البحث</button>
            <button class="btn warn" id="runJobs">تشغيل مهام الخلفية (مثال)</button>
            <a class="btn ghost" href="/api/admin/health">قِراءة الحالة</a>
        </div>
        <div id="adminMsg" class="muted" style="margin-top:10px"></div>
    </section>

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

async function loadStats() {
    // try /api/admin/stats then fallback to /api/stats
    let res = await api('/admin/stats');
    if (!res.ok) res = await api('/stats');
    if (!res.ok) {
        el('ordersCount').textContent = 'N/A';
        el('salesTotal').textContent = 'N/A';
        el('lowStockCount').textContent = 'N/A';
        el('usersCount').textContent = 'N/A';
        return;
    }
    const d = res.json || res;
    el('ordersCount').textContent = d.open_orders ? (d.open_orders + ' / ' + (d.total_orders||'—')) : (d.orders_count || (d.orders && d.orders.total ? d.orders.total : '—'));
    el('salesTotal').textContent = d.sales_last_30_days ? (d.sales_last_30_days + ' ' + (d.currency || '')) : (d.sales_total || '—');
    el('lowStockCount').textContent = d.low_stock_count ?? (d.low_stock ? d.low_stock.length : '0');
    el('usersCount').textContent = d.users_count ?? (d.users && d.users.total ? d.users.total : '—');
}

async function loadRecentOrders() {
    const container = el('recentOrders');
    container.innerHTML = '<div class="muted">جاري جلب الطلبات...</div>';
    const res = await api('/admin/recent-orders?limit=8');
    if (!res.ok) {
        // fallback to /orders?recent=1&per_page=8
        const r2 = await api('/orders?recent=1&per_page=8');
        if (!r2.ok) {
            container.innerHTML = '<div class="muted">فشل جلب الطلبات.</div>';
            return;
        }
        renderOrders(r2.json.data || r2.json.orders || r2.json);
        return;
    }
    renderOrders(res.json.data || res.json.orders || res.json);
}

function renderOrders(list) {
    const container = el('recentOrders');
    if (!list || list.length === 0) {
        container.innerHTML = '<div class="muted">لا توجد طلبات حديثة.</div>';
        return;
    }
    let html = '';
    (list || []).forEach(o => {
        const id = o.order_number || o.id || '—';
        const total = o.grand_total || o.total || '0.00';
        const status = o.order_status || o.status || '—';
        const when = o.created_at ? (new Date(o.created_at)).toLocaleString() : (o.created || '');
        html += `<div class="list-item"><div>${escapeHtml(id)} <div class="muted" style="font-size:0.9em">${escapeHtml(when)}</div></div><div style="text-align:left">${escapeHtml(total)} <div class="muted">${escapeHtml(status)}</div><a class="btn ghost" href="/frontend/order.php?id=${encodeURIComponent(o.id||id)}" style="margin-top:6px">عرض</a></div></div>`;
    });
    container.innerHTML = html;
}

async function loadLowStock() {
    const container = el('lowStockList');
    container.innerHTML = '<div class="muted">جاري جلب المنتجات...</div>';
    const res = await api('/products?low_stock=1&per_page=8');
    if (!res.ok) {
        container.innerHTML = '<div class="muted">فشل جلب المنتجات.</div>';
        return;
    }
    const list = res.json.data || res.json.products || res.json;
    if (!list || list.length === 0) {
        container.innerHTML = '<div class="muted">لا توجد منتجات منخفضة المخزون.</div>';
        return;
    }
    let html = '';
    list.forEach(p => {
        const title = p.title || p.name || ('#' + (p.id||''));
        const stock = p.stock_quantity ?? p.stock ?? 0;
        html += `<div class="list-item"><div>${escapeHtml(title)}<div class="muted" style="font-size:0.9em">المخزون: ${escapeHtml(String(stock))}</div></div><div style="text-align:left"><a class="btn ghost" href="/frontend/product_edit.php?id=${encodeURIComponent(p.id||'')}">تحرير</a></div></div>`;
    });
    container.innerHTML = html;
}

async function loadRecentUsers() {
    const container = el('recentUsers');
    container.innerHTML = '<div class="muted">جاري جلب المستخدمين...</div>';
    const res = await api('/admin/recent-users?limit=8');
    if (!res.ok) {
        const r2 = await api('/users?sort=created_at&order=desc&per_page=8');
        if (!r2.ok) { container.innerHTML = '<div class="muted">فشل جلب المستخدمين.</div>'; return; }
        renderUsers(r2.json.data || r2.json.users || r2.json);
        return;
    }
    renderUsers(res.json.data || res.json.users || res.json);
}

function renderUsers(list) {
    const container = el('recentUsers');
    if (!list || list.length === 0) { container.innerHTML = '<div class="muted">لا يوجد مستخدمون حديثون.</div>'; return; }
    let html = '';
    list.forEach(u => {
        const name = u.name || u.email || ('#' + (u.id||''));
        const created = u.created_at ? (new Date(u.created_at)).toLocaleDateString() : (u.created||'');
        html += `<div class="list-item"><div>${escapeHtml(name)}<div class="muted" style="font-size:0.9em">${escapeHtml(created)}</div></div><div style="text-align:left"><a class="btn ghost" href="/frontend/admin/user_edit.php?id=${encodeURIComponent(u.id||'')}">تحرير</a></div></div>`;
    });
    container.innerHTML = html;
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
}

document.addEventListener('DOMContentLoaded', function () {
    loadStats();
    loadRecentOrders();
    loadLowStock();
    loadRecentUsers();

    el('refreshOrders')?.addEventListener('click', () => loadRecentOrders());
    el('refreshStock')?.addEventListener('click', () => loadLowStock());
    el('refreshUsers')?.addEventListener('click', () => loadRecentUsers());

    el('clearCache')?.addEventListener('click', async function () {
        if (!confirm('هل تريد مسح الكاش؟')) return;
        el('adminMsg').textContent = 'جاري مسح الكاش...';
        const res = await api('/admin/cache/clear', 'POST', {});
        if (res.ok) el('adminMsg').textContent = 'تم مسح الكاش.';
        else el('adminMsg').textContent = 'فشل مسح الكاش.';
    });

    el('reindexSearch')?.addEventListener('click', async function () {
        if (!confirm('إعادة فهرسة البحث قد تستغرق وقتاً. متابعة؟')) return;
        el('adminMsg').textContent = 'جاري إرسال عملية إعادة الفهرسة...';
        const res = await api('/admin/search/reindex', 'POST', {});
        if (res.ok) el('adminMsg').textContent = 'تم بدء عملية إعادة الفهرسة.';
        else el('adminMsg').textContent = 'فشل بدء إعادة الفهرسة.';
    });

    el('runJobs')?.addEventListener('click', async function () {
        el('adminMsg').textContent = 'تشغيل مهام الخلفية... (اختباري)';
        const res = await api('/admin/run-jobs', 'POST', {});
        if (res.ok) el('adminMsg').textContent = 'تم تنفيذ المهام (أو تم إرسالها إلى طابور التنفيذ).';
        else el('adminMsg').textContent = 'فشل تشغيل المهام.';
    });
});
</script>
</body>
</html>