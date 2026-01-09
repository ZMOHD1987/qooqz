<?php
// htdocs/frontend/wallet.php
// Frontend page for user wallet: view balance, transaction history, add funds, withdraw, and transfer to another user.
// Interacts with API endpoints: /api/wallet (GET), /api/wallet/transactions, /api/wallet/deposit, /api/wallet/withdraw, /api/wallet/transfer, /api/payments
// Minimal Arabic UI and lightweight JS to call API.

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

// Fetch wallet summary and recent transactions server-side for initial render
$walletRes = api_request('GET', '/wallet');
$transactionsRes = api_request('GET', '/wallet/transactions?per_page=20');

$wallet = $walletRes['wallet'] ?? $walletRes['data'] ?? $walletRes;
$transactions = $transactionsRes['data'] ?? $transactionsRes['transactions'] ?? (is_array($transactionsRes) && isset($transactionsRes[0]) ? $transactionsRes : []);
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>المحفظة الإلكترونية</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:980px;margin:auto}
        .card{border:1px solid #eee;padding:16px;margin-bottom:12px;border-radius:6px;background:#fff}
        .balance{font-size:1.6em;font-weight:700;color:#2d8cf0}
        .muted{color:#666}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        table{width:100%;border-collapse:collapse;margin-top:8px}
        th,td{padding:8px;border:1px solid #f1f1f1;text-align:right}
        label{display:block;margin-top:8px}
        input[type="text"], input[type="number"], select, textarea{width:100%;padding:8px;box-sizing:border-box;margin-top:4px}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .btn.ghost{background:#95a5a6}
        .small{font-size:0.9em;color:#777}
    </style>
</head>
<body>
<div class="container">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1>المحفظة</h1>
        <div><a class="btn" href="/frontend/profile.php">الملف الشخصي</a></div>
    </header>

    <section class="card" aria-labelledby="summaryHeading">
        <h2 id="summaryHeading">ملخص المحفظة</h2>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <div>
                <div class="small">الرصيد المتاح</div>
                <div class="balance"><?php echo e($wallet['balance'] ?? '0.00'); ?> <?php echo e($wallet['currency'] ?? 'USD'); ?></div>
                <div class="muted">الرصيد المحجوز: <?php echo e($wallet['reserved'] ?? '0.00'); ?></div>
            </div>
            <div style="text-align:left">
                <button class="btn" id="showDeposit">إضافة رصيد</button>
                <button class="btn" id="showWithdraw" style="background:#e67e22">سحب رصيد</button>
                <button class="btn ghost" id="showTransfer">تحويل لعميل</button>
            </div>
        </div>
    </section>

    <section class="card" id="depositCard" style="display:none">
        <h3>إضافة رصيد</h3>
        <p class="muted">اختر طريقة الإضافة أو أنشئ عملية دفع لتعبئة المحفظة.</p>
        <form id="depositForm">
            <label>المبلغ
                <input type="number" name="amount" min="1" step="0.01" required>
            </label>
            <label>طريقة الدفع
                <select name="method" id="depositMethod">
                    <option value="card">بطاقة ائتمان (تجريبي)</option>
                    <option value="paypal">PayPal</option>
                    <option value="bank_transfer">تحويل بنكي</option>
                </select>
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">ابدأ الإضافة</button>
                <button class="btn ghost" type="button" id="cancelDeposit">إلغاء</button>
            </div>
            <div id="depositMsg" class="muted" style="margin-top:8px"></div>
        </form>
    </section>

    <section class="card" id="withdrawCard" style="display:none">
        <h3>سحب رصيد</h3>
        <p class="muted">أدخل بيانات السحب. قد يتم فرض رسوم أو حد أدنى للسحب.</p>
        <form id="withdrawForm">
            <label>المبلغ
                <input type="number" name="amount" min="1" step="0.01" required>
            </label>
            <label>وجهة السحب
                <select name="destination" required>
                    <option value="bank">حساب بنكي</option>
                    <option value="paypal">حساب PayPal</option>
                </select>
            </label>
            <label>تفاصيل الوجهة (مثال: رقم الحساب أو البريد الإلكتروني)
                <input type="text" name="destination_details" required>
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">طلب سحب</button>
                <button class="btn ghost" type="button" id="cancelWithdraw">إلغاء</button>
            </div>
            <div id="withdrawMsg" class="muted" style="margin-top:8px"></div>
        </form>
    </section>

    <section class="card" id="transferCard" style="display:none">
        <h3>تحويل إلى مستخدم آخر</h3>
        <form id="transferForm">
            <label>إلى (معرّف المستخدم أو البريد الإلكتروني)
                <input type="text" name="to" required>
            </label>
            <label>المبلغ
                <input type="number" name="amount" min="1" step="0.01" required>
            </label>
            <label>ملاحظة (اختياري)
                <input type="text" name="note">
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">تحويل</button>
                <button class="btn ghost" type="button" id="cancelTransfer">إلغاء</button>
            </div>
            <div id="transferMsg" class="muted" style="margin-top:8px"></div>
        </form>
    </section>

    <section class="card" aria-labelledby="txHeading">
        <h2 id="txHeading">آخر الحركات</h2>
        <?php if (empty($transactions)): ?>
            <p class="muted">لا توجد معاملات لعرضها.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>الوقت</th><th>النوع</th><th>المبلغ</th><th>الحالة</th><th>ملاحظات</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): 
                    $when = isset($t['created_at']) ? date('Y-m-d H:i', strtotime($t['created_at'])) : ($t['date'] ?? '');
                    $type = $t['type'] ?? $t['transaction_type'] ?? '—';
                    $amount = $t['amount'] ?? '0.00';
                    $status = $t['status'] ?? '—';
                    $note = $t['note'] ?? $t['meta'] ?? '';
                ?>
                    <tr>
                        <td><?php echo e($when); ?></td>
                        <td><?php echo e($type); ?></td>
                        <td><?php echo e($amount); ?> <?php echo e($wallet['currency'] ?? ''); ?></td>
                        <td><?php echo e($status); ?></td>
                        <td><?php echo e(is_array($note) ? json_encode($note) : $note); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="small muted">للاطلاع على المزيد من الحركات استخدم واجهة API أو صفحة السجلات الكاملة.</p>
    </section>
</div>

<script>
(function () {
    function api(path, method='GET', body=null) {
        const opts = { method, headers: { 'Accept': 'application/json' } };
        if (body !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch('/api' + path, opts).then(r => r.json().catch(()=>({ ok: r.ok, status: r.status })));
    }

    function $(id){ return document.getElementById(id); }

    // Show/Hide forms
    $('showDeposit')?.addEventListener('click', function () {
        $('depositCard').style.display = 'block';
        $('withdrawCard').style.display = 'none';
        $('transferCard').style.display = 'none';
    });
    $('showWithdraw')?.addEventListener('click', function () {
        $('depositCard').style.display = 'none';
        $('withdrawCard').style.display = 'block';
        $('transferCard').style.display = 'none';
    });
    $('showTransfer')?.addEventListener('click', function () {
        $('depositCard').style.display = 'none';
        $('withdrawCard').style.display = 'none';
        $('transferCard').style.display = 'block';
    });

    // Cancel buttons
    $('cancelDeposit')?.addEventListener('click', function () { $('depositCard').style.display = 'none'; });
    $('cancelWithdraw')?.addEventListener('click', function () { $('withdrawCard').style.display = 'none'; });
    $('cancelTransfer')?.addEventListener('click', function () { $('transferCard').style.display = 'none'; });

    // Deposit: create a payment to fund wallet (API may support /wallet/deposit which returns payment_url or payment object)
    document.getElementById('depositForm')?.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const msg = $('depositMsg');
        msg.style.color = '';
        msg.textContent = 'جاري إنشاء عملية إضافة رصيد...';
        const form = ev.target;
        const fd = new FormData(form);
        const amount = parseFloat(fd.get('amount')) || 0;
        const method = fd.get('method');

        if (amount <= 0) {
            msg.style.color = 'red'; msg.textContent = 'الرجاء إدخال مبلغ صحيح.';
            return;
        }

        // Preferred: call /api/wallet/deposit to get payment instructions
        try {
            const res = await api('/wallet/deposit', 'POST', { amount, method });
            if (res && (res.success || res.ok)) {
                // response may contain payment_url to redirect
                const payment = res.payment || res.data || res;
                if (payment && payment.payment_url) {
                    msg.style.color = 'green';
                    msg.textContent = 'جاري التحويل إلى صفحة الدفع...';
                    window.location.href = payment.payment_url;
                    return;
                }
                // fallback: if response indicates payment record, show success
                msg.style.color = 'green';
                msg.textContent = 'تم إنشاء طلب الإضافة. تحقق من حالة المحفظة بعد إتمام الدفع.';
                // Optionally reload page after short delay
                setTimeout(()=> location.reload(), 1200);
                return;
            }
            // If deposit endpoint not available, fallback to create payment record at /api/payments with gateway = method
            const fallback = await api('/payments', 'POST', { amount, currency: '<?php echo e($wallet['currency'] ?? 'USD'); ?>', gateway: method, client_reference: 'wallet_deposit_' + Date.now() });
            if (fallback && (fallback.success || fallback.ok)) {
                const p = fallback.payment || fallback.data || fallback;
                if (p && p.payment_url) {
                    msg.style.color = 'green';
                    msg.textContent = 'جاري التحويل إلى صفحة الدفع...';
                    window.location.href = p.payment_url;
                    return;
                }
                msg.style.color = 'green';
                msg.textContent = 'تم إنشاء عملية الدفع. تفضل بالمتابعة.';
                setTimeout(()=> location.reload(), 1200);
                return;
            }
            msg.style.color = 'red';
            msg.textContent = (res && res.message) ? res.message : 'فشل إنشاء الإيداع';
        } catch (err) {
            msg.style.color = 'red';
            msg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

    // Withdraw
    document.getElementById('withdrawForm')?.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const msg = $('withdrawMsg');
        msg.style.color = '';
        msg.textContent = 'جاري إرسال طلب السحب...';
        const fd = new FormData(ev.target);
        const amount = parseFloat(fd.get('amount')) || 0;
        const dest = fd.get('destination');
        const details = fd.get('destination_details');

        if (amount <= 0 || !dest || !details) {
            msg.style.color = 'red'; msg.textContent = 'الرجاء تعبئة الحقول المطلوبة.';
            return;
        }

        try {
            const res = await api('/wallet/withdraw', 'POST', { amount, destination: dest, destination_details: details });
            if (res && (res.success || res.ok)) {
                msg.style.color = 'green';
                msg.textContent = 'تم إرسال طلب السحب. ستتم المعالجة وفقاً لسياسة الموقع.';
                setTimeout(()=> location.reload(), 1200);
                return;
            }
            msg.style.color = 'red';
            msg.textContent = res && res.message ? res.message : 'فشل إرسال طلب السحب';
        } catch (err) {
            msg.style.color = 'red';
            msg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

    // Transfer
    document.getElementById('transferForm')?.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const msg = $('transferMsg');
        msg.style.color = '';
        msg.textContent = 'جاري إجراء التحويل...';
        const fd = new FormData(ev.target);
        const to = fd.get('to');
        const amount = parseFloat(fd.get('amount')) || 0;
        const note = fd.get('note');

        if (!to || amount <= 0) {
            msg.style.color = 'red'; msg.textContent = 'الرجاء تعبئة المستلم والمبلغ.';
            return;
        }

        try {
            const res = await api('/wallet/transfer', 'POST', { to, amount, note });
            if (res && (res.success || res.ok)) {
                msg.style.color = 'green';
                msg.textContent = 'تم التحويل بنجاح.';
                setTimeout(()=> location.reload(), 900);
                return;
            }
            msg.style.color = 'red';
            msg.textContent = res && res.message ? res.message : 'فشل عملية التحويل';
        } catch (err) {
            msg.style.color = 'red';
            msg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

})();
</script>
</body>
</html>