<?php
// htdocs/frontend/notifications.php
// Frontend page for user notifications: lists notifications, mark read/unread, delete, and subscription settings.
// Uses API endpoints: /api/notifications (GET), /api/notifications/{id}/read (POST), /api/notifications/{id} (DELETE),
// /api/notifications/mark-all-read (POST), /api/notifications/subscribe (POST/DELETE)
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

// Server-side fetch initial notifications for SSR
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$path = '/notifications?page=' . $page . '&per_page=' . $perPage;
$notifRes = api_request('GET', $path);

// subscription state (email/push) - try endpoint /notifications/subscription or user meta
$subscribed = null;
$subRes = api_request('GET', '/notifications/subscription');
if (!empty($subRes['success'])) {
    $subscribed = $subRes['subscribed'] ?? $subRes['data']['subscribed'] ?? null;
}

$notifications = [];
$pagination = [];
if (!empty($notifRes['success'])) {
    $notifications = $notifRes['data'] ?? $notifRes['notifications'] ?? (is_array($notifRes) && isset($notifRes[0]) ? $notifRes : []);
    $pagination = $notifRes['meta'] ?? $notifRes['pagination'] ?? ['page' => $page, 'per_page' => $perPage, 'total' => count($notifications)];
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>الإشعارات</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:900px;margin:auto}
        .card{border:1px solid #eee;padding:12px;margin-bottom:12px;border-radius:6px;background:#fff}
        .notif{padding:10px;border-bottom:1px solid #f1f1f1;display:flex;gap:12px;align-items:flex-start}
        .notif.unread{background:#f4fbff;border-left:4px solid #2d8cf0}
        .notif .meta{flex:1}
        .notif .actions{display:flex;gap:8px;flex-shrink:0}
        .btn{display:inline-block;padding:6px 10px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .btn.ghost{background:#95a5a6}
        .muted{color:#666}
        .small{font-size:0.9em;color:#777}
        .empty{padding:20px;text-align:center;color:#666}
        .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px}
        .filters{display:flex;gap:8px;align-items:center}
        .search input{padding:8px;width:240px;box-sizing:border-box}
    </style>
</head>
<body>
<div class="container">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1>الإشعارات</h1>
        <div>
            <a class="btn" href="/frontend/profile.php">العودة للملف</a>
        </div>
    </header>

    <section class="card">
        <div class="toolbar">
            <div class="filters">
                <button class="btn" id="markAllRead">تمييز الكل كمقروء</button>
                <button class="btn ghost" id="clearRead">حذف المقروء</button>
                <div style="margin-right:12px;">
                    حالة الاشتراك بالبريد:
                    <label style="margin-left:6px;">
                        <input type="checkbox" id="subscribeEmail" <?php echo ($subscribed ? 'checked' : ''); ?>> اشترك
                    </label>
                </div>
            </div>

            <div class="search">
                <input type="text" id="q" placeholder="ابحث في الإشعارات...">
                <button class="btn ghost" id="searchBtn">بحث</button>
            </div>
        </div>

        <div id="notificationsList">
            <?php if (empty($notifications)): ?>
                <div class="empty">لا توجد إشعارات حالياً.</div>
            <?php else: ?>
                <?php foreach ($notifications as $n): 
                    $nid = $n['id'] ?? $n['notification_id'] ?? '';
                    $title = $n['title'] ?? $n['subject'] ?? '';
                    $body = $n['body'] ?? $n['message'] ?? '';
                    $created = isset($n['created_at']) ? date('Y-m-d H:i', strtotime($n['created_at'])) : ($n['created'] ?? '');
                    $read = !empty($n['read']) || !empty($n['is_read']);
                ?>
                    <article class="notif <?php echo $read ? '' : 'unread'; ?>" data-id="<?php echo e($nid); ?>">
                        <div class="meta">
                            <strong><?php echo e($title ?: 'إشعار'); ?></strong>
                            <div class="small muted"><?php echo e($created); ?></div>
                            <div style="margin-top:8px"><?php echo nl2br(e($body)); ?></div>
                        </div>
                        <div class="actions">
                            <button class="btn markRead" data-id="<?php echo e($nid); ?>"><?php echo $read ? 'تمييز كغير مقروء' : 'تمييز كمقروء'; ?></button>
                            <button class="btn ghost deleteBtn" data-id="<?php echo e($nid); ?>">حذف</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php
        // simple pagination display
        $total = $pagination['total'] ?? null;
        $cur = $pagination['page'] ?? $page;
        $pp = $pagination['per_page'] ?? $perPage;
        if ($total !== null && $total > $pp):
            $last = (int)ceil($total / $pp);
        ?>
            <nav style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                <?php if ($cur > 1): ?>
                    <a class="btn ghost" href="?page=<?php echo $cur - 1; ?>">السابق</a>
                <?php endif; ?>
                <span class="small">صفحة <?php echo e($cur); ?> من <?php echo e($last); ?></span>
                <?php if ($cur < $last): ?>
                    <a class="btn" href="?page=<?php echo $cur + 1; ?>">التالي</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<script>
// Lightweight client-side interactions for notifications
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
function q(selector, ctx=document){ return ctx.querySelector(selector); }
function qa(selector, ctx=document){ return Array.from(ctx.querySelectorAll(selector)); }

document.addEventListener('DOMContentLoaded', function () {
    const list = el('notificationsList');

    // mark single read/unread
    list.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('.markRead');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        if (!id) return;
        btn.disabled = true;

        // Determine current state from DOM
        const article = btn.closest('.notif');
        const isUnread = article.classList.contains('unread');

        try {
            if (isUnread) {
                // mark read
                const res = await api('/notifications/' + encodeURIComponent(id) + '/read', 'POST', {});
                if (res.ok) {
                    article.classList.remove('unread');
                    btn.textContent = 'تمييز كغير مقروء';
                } else {
                    alert('فشل التحديث');
                }
            } else {
                // mark unread (if API supports toggle)
                const res = await api('/notifications/' + encodeURIComponent(id) + '/read', 'DELETE', {});
                if (res.ok) {
                    article.classList.add('unread');
                    btn.textContent = 'تمييز كمقروء';
                } else {
                    alert('فشل التحديث');
                }
            }
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
        } finally {
            btn.disabled = false;
        }
    });

    // delete single notification
    list.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('.deleteBtn');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        if (!id) return;
        if (!confirm('هل تريد حذف هذا الإشعار؟')) return;
        btn.disabled = true;
        try {
            const res = await api('/notifications/' + encodeURIComponent(id), 'DELETE');
            if (res.ok) {
                const art = btn.closest('.notif');
                art.parentNode.removeChild(art);
            } else {
                alert('فشل الحذف');
            }
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
        } finally {
            btn.disabled = false;
        }
    });

    // mark all read
    el('markAllRead')?.addEventListener('click', async function () {
        if (!confirm('هل تريد تمييز كل الإشعارات كمقروءة؟')) return;
        this.disabled = true;
        try {
            const res = await api('/notifications/mark-all-read', 'POST', {});
            if (res.ok) {
                qa('.notif.unread').forEach(n => n.classList.remove('unread'));
                qa('.markRead').forEach(b => b.textContent = 'تمييز كغير مقروء');
            } else {
                alert('فشل العملية');
            }
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
        } finally {
            this.disabled = false;
        }
    });

    // clear read notifications
    el('clearRead')?.addEventListener('click', async function () {
        if (!confirm('حذف جميع الإشعارات المقروءة؟')) return;
        this.disabled = true;
        try {
            const res = await api('/notifications/clean-read', 'POST', {});
            if (res.ok) {
                qa('.notif').forEach(n => {
                    if (!n.classList.contains('unread')) n.remove();
                });
            } else {
                alert('فشل العملية');
            }
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
        } finally {
            this.disabled = false;
        }
    });

    // search
    el('searchBtn')?.addEventListener('click', async function () {
        const qv = document.getElementById('q').value.trim();
        if (qv === '') {
            location.href = '/frontend/notifications.php';
            return;
        }
        this.disabled = true;
        try {
            const res = await api('/notifications?search=' + encodeURIComponent(qv));
            if (!res.ok) {
                alert('فشل البحث');
                return;
            }
            const items = res.json && (res.json.data || res.json.notifications || res.json) ? (res.json.data || res.json.notifications || res.json) : [];
            renderList(items);
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
        } finally {
            this.disabled = false;
        }
    });

    // subscription toggle
    el('subscribeEmail')?.addEventListener('change', async function () {
        this.disabled = true;
        try {
            if (this.checked) {
                const res = await api('/notifications/subscribe', 'POST', {});
                if (!res.ok) {
                    alert('فشل الاشتراك');
                    this.checked = false;
                }
            } else {
                const res = await api('/notifications/subscribe', 'DELETE', {});
                if (!res.ok) {
                    alert('فشل إلغاء الاشتراك');
                    this.checked = true;
                }
            }
        } catch (err) {
            alert('خطأ في الاتصال: ' + err.message);
            this.checked = !this.checked;
        } finally {
            this.disabled = false;
        }
    });

    // helper to render list when search used
    function renderList(items) {
        const container = el('notificationsList');
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="empty">لا توجد إشعارات مطابقة.</div>';
            return;
        }
        let html = '';
        items.forEach(n => {
            const id = n.id || n.notification_id || '';
            const title = escapeHtml(n.title || n.subject || 'إشعار');
            const body = escapeHtml(n.body || n.message || '');
            const created = n.created_at ? (new Date(n.created_at)).toLocaleString() : (n.created || '');
            const read = n.read || n.is_read ? '' : 'unread';
            const markText = read ? 'تمييز كغير مقروء' : 'تمييز كمقروء';
            html += `<article class="notif ${read}" data-id="${escapeHtml(id)}">
                <div class="meta"><strong>${title}</strong><div class="small muted">${escapeHtml(created)}</div><div style="margin-top:8px">${body.replace(/\n/g,'<br>')}</div></div>
                <div class="actions"><button class="btn markRead" data-id="${escapeHtml(id)}">${markText}</button><button class="btn ghost deleteBtn" data-id="${escapeHtml(id)}">حذف</button></div>
            </article>`;
        });
        container.innerHTML = html;
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }
});
</script>
</body>
</html>