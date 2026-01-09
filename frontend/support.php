<?php
// htdocs/frontend/support.php
// Frontend page for user support / tickets / contact.
// - Lists user's tickets (via /api/support/tickets)
// - Shows ticket details when ?id={ticket_id} provided (via /api/support/tickets/{id})
// - Allows creating a new ticket (POST /api/support/tickets)
// - Allows replying to a ticket (POST /api/support/tickets/{id}/reply)
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

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

// read ticket id from query
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// server-side fetch
$tickets = [];
$ticket = null;
$ticketErr = null;

if ($ticketId) {
    $res = api_request('GET', '/support/tickets/' . urlencode($ticketId));
    if (!empty($res['success'])) {
        $ticket = $res['ticket'] ?? $res['data'] ?? $res;
    } else {
        $ticketErr = $res['message'] ?? 'فشل جلب تذكرة الدعم';
    }
} else {
    $res = api_request('GET', '/support/tickets?page=' . $page . '&per_page=' . $perPage);
    if (!empty($res['success'])) {
        $tickets = $res['data'] ?? $res['tickets'] ?? (is_array($res) ? $res : []);
        $pagination = $res['meta'] ?? $res['pagination'] ?? ['page' => $page, 'per_page' => $perPage, 'total' => count($tickets)];
    } else {
        $tickets = [];
        $pagination = ['page' => $page, 'per_page' => $perPage, 'total' => 0];
    }
}

?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>الدعم والمساعدة</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:980px;margin:auto}
        .card{border:1px solid #eee;padding:14px;margin-bottom:12px;border-radius:6px;background:#fff}
        .ticket{border-bottom:1px solid #f1f1f1;padding:10px 0;display:flex;justify-content:space-between;align-items:center}
        .ticket.unread{background:#f4fbff;border-left:4px solid #2d8cf0}
        .muted{color:#666}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .btn.ghost{background:#95a5a6}
        label{display:block;margin-top:8px}
        input[type="text"], input[type="email"], textarea, select{width:100%;padding:8px;box-sizing:border-box;margin-top:4px}
        .meta{flex:1;text-align:right;margin-right:12px}
        .small{font-size:0.9em;color:#777}
        .reply{margin-top:12px;border-top:1px dashed #eee;padding-top:12px}
    </style>
</head>
<body>
<div class="container">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1>الدعم</h1>
        <div><a class="btn" href="/frontend/products.php">العودة للمتجر</a></div>
    </header>

<?php if ($ticketId): ?>
    <div class="card">
        <h2>تفاصيل التذكرة</h2>
        <?php if ($ticketErr): ?>
            <p class="muted"><?php echo e($ticketErr); ?></p>
        <?php elseif (!$ticket): ?>
            <p class="muted">لا توجد تذكرة بهذا المعرّف.</p>
        <?php else: ?>
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h3><?php echo e($ticket['subject'] ?? ('تذكرة #' . ($ticket['id'] ?? ''))); ?></h3>
                        <div class="small muted">رقم: <?php echo e($ticket['ticket_number'] ?? $ticket['id'] ?? ''); ?> — الحالة: <strong><?php echo e($ticket['status'] ?? ''); ?></strong></div>
                    </div>
                    <div>
                        <a class="btn ghost" href="/frontend/support.php">قائمة التذاكر</a>
                        <?php if (!in_array(strtolower($ticket['status'] ?? ''), ['closed','resolved'])): ?>
                            <button id="closeTicket" class="btn" data-id="<?php echo e($ticket['id']); ?>">إغلاق التذكرة</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:12px;border:1px solid #f5f5f5;padding:12px;border-radius:6px;background:#fafafa">
                    <strong>الوصف</strong>
                    <div style="margin-top:8px"><?php echo nl2br(e($ticket['message'] ?? $ticket['body'] ?? '')); ?></div>
                </div>

                <section style="margin-top:12px">
                    <h4>المحادثة</h4>
                    <?php
                    $threads = $ticket['messages'] ?? $ticket['threads'] ?? $ticket['conversation'] ?? [];
                    if (!empty($threads)):
                        foreach ($threads as $th):
                            $author = $th['author_name'] ?? $th['from'] ?? ($th['author'] ?? ''); 
                            $time = isset($th['created_at']) ? date('Y-m-d H:i', strtotime($th['created_at'])) : ($th['created'] ?? '');
                            $body = $th['body'] ?? $th['message'] ?? '';
                            $isAgent = !empty($th['is_agent']) || (!empty($th['role']) && in_array($th['role'], ['agent','admin','support']));
                            ?>
                            <div style="margin-bottom:10px;padding:10px;border-radius:6px;<?php echo $isAgent ? 'background:#f1f8ff;border:1px solid #e1f0ff;' : 'background:#fff;border:1px solid #f1f1f1;'; ?>">
                                <div class="small muted"><?php echo e($author ?: ($isAgent ? 'فريق الدعم' : 'أنت')); ?> — <?php echo e($time); ?></div>
                                <div style="margin-top:6px"><?php echo nl2br(e($body)); ?></div>
                                <?php if (!empty($th['attachments'])): ?>
                                    <div class="muted small" style="margin-top:8px">
                                        مرفقات:
                                        <?php foreach ($th['attachments'] as $att): ?>
                                            <div><a href="<?php echo e($att['url'] ?? '#'); ?>" target="_blank"><?php echo e($att['name'] ?? basename($att['url'] ?? 'file')); ?></a></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php
                        endforeach;
                    else:
                        echo '<p class="muted">لا توجد رسائل في هذه التذكرة بعد.</p>';
                    endif;
                    ?>
                </section>

                <section class="reply">
                    <h4>أرسل رداً</h4>
                    <form id="replyForm">
                        <input type="hidden" name="ticket_id" value="<?php echo e($ticket['id']); ?>">
                        <label>رسالتك
                            <textarea name="message" rows="5" required></textarea>
                        </label>
                        <label>بريد إلكتروني للمتابعة (اختياري)
                            <input type="email" name="email" value="<?php echo e($ticket['email'] ?? ''); ?>">
                        </label>
                        <div style="margin-top:8px">
                            <button class="btn" type="submit">إرسال الرد</button>
                            <button class="btn ghost" type="button" id="cancelReply">إلغاء</button>
                        </div>
                        <div id="replyMsg" class="muted" style="margin-top:8px"></div>
                    </form>
                </section>
            </div>
        <?php endif; ?>
    </div>

<?php else: // list view ?>
    <div class="card">
        <h2>إنشاء تذكرة جديدة</h2>
        <form id="createForm">
            <label>الموضوع
                <input type="text" name="subject" required>
            </label>
            <label>البريد الإلكتروني (للمتابعة)
                <input type="email" name="email">
            </label>
            <label>الرسالة
                <textarea name="message" rows="5" required></textarea>
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">أنشئ التذكرة</button>
                <button class="btn ghost" type="reset">إلغاء</button>
            </div>
            <div id="createMsg" class="muted" style="margin-top:8px"></div>
        </form>
    </div>

    <div class="card">
        <h2>تذاكري</h2>
        <?php if (empty($tickets)): ?>
            <p class="muted">لا توجد تذاكر لعرضها.</p>
        <?php else: ?>
            <?php foreach ($tickets as $t): 
                $tid = $t['id'] ?? $t['ticket_number'] ?? '';
                $title = $t['subject'] ?? ('تذكرة #' . ($t['id'] ?? ''));
                $status = $t['status'] ?? '—';
                $created = isset($t['created_at']) ? date('Y-m-d', strtotime($t['created_at'])) : ($t['created'] ?? '');
                $unread = empty($t['is_read']) && empty($t['read']);
                ?>
                <div class="ticket <?php echo $unread ? 'unread' : ''; ?>">
                    <div class="meta">
                        <strong><?php echo e($title); ?></strong>
                        <div class="small muted">حالة: <?php echo e($status); ?> — تاريخ: <?php echo e($created); ?></div>
                    </div>
                    <div>
                        <a class="btn" href="/frontend/support.php?id=<?php echo urlencode($t['id'] ?? $tid); ?>">عرض</a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
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
        <?php endif; ?>
    </div>
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

document.addEventListener('DOMContentLoaded', function () {
    // Create ticket
    const createForm = document.getElementById('createForm');
    if (createForm) {
        createForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const msg = document.getElementById('createMsg');
            msg.style.color = '';
            msg.textContent = 'جاري إنشاء التذكرة...';
            const fd = new FormData(createForm);
            const payload = {};
            for (const [k, v] of fd.entries()) if (v !== '') payload[k] = v;
            try {
                const res = await api('/support/tickets', 'POST', payload);
                if (res.ok) {
                    msg.style.color = 'green';
                    msg.textContent = 'تم إنشاء التذكرة. سيتم إعلامك عبر البريد الإلكتروني.';
                    setTimeout(()=> window.location.href = '/frontend/support.php', 900);
                } else {
                    msg.style.color = 'red';
                    msg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إنشاء التذكرة';
                }
            } catch (err) {
                msg.style.color = 'red';
                msg.textContent = 'خطأ في الاتصال: ' + err.message;
            }
        });
    }

    // Reply to ticket
    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const msg = document.getElementById('replyMsg');
            msg.style.color = '';
            msg.textContent = 'جاري إرسال الرد...';
            const fd = new FormData(replyForm);
            const ticketId = fd.get('ticket_id');
            const payload = { message: fd.get('message') || '', email: fd.get('email') || '' };
            try {
                const res = await api('/support/tickets/' + encodeURIComponent(ticketId) + '/reply', 'POST', payload);
                if (res.ok) {
                    msg.style.color = 'green';
                    msg.textContent = 'تم إرسال ردك.';
                    setTimeout(()=> location.reload(), 800);
                } else {
                    msg.style.color = 'red';
                    msg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إرسال الرد';
                }
            } catch (err) {
                msg.style.color = 'red';
                msg.textContent = 'خطأ في الاتصال: ' + err.message;
            }
        });
    }

    // Close ticket
    const closeBtn = document.getElementById('closeTicket');
    if (closeBtn) {
        closeBtn.addEventListener('click', async function () {
            const id = this.getAttribute('data-id');
            if (!confirm('هل تريد إغلاق هذه التذكرة؟')) return;
            this.disabled = true;
            try {
                const res = await api('/support/tickets/' + encodeURIComponent(id) + '/close', 'POST', {});
                if (res.ok) {
                    alert('تم إغلاق التذكرة');
                    location.reload();
                } else {
                    alert((res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إغلاق التذكرة');
                }
            } catch (err) {
                alert('خطأ في الاتصال: ' + err.message);
            } finally {
                this.disabled = false;
            }
        });
    }

    // Cancel reply button simply resets the form
    document.getElementById('cancelReply')?.addEventListener('click', function () {
        const tf = document.querySelector('#replyForm textarea[name="message"]');
        if (tf) tf.value = '';
    });

});
</script>
</body>
</html>