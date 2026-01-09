<?php
// htdocs/frontend/payments.php
// Frontend page for payments/checkout:
// - If ?order_id={id} provided, shows order summary and available payment methods and lets user initiate payment.
// - Otherwise shows recent payments or available payment methods.
// - Interacts with API endpoints: /api/orders/{id}, /api/payments, /api/payment-methods (or /api/payments/methods)
// - Minimal Arabic UI and simple JS to create payment record and follow gateway redirect when provided.

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

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

// Get order_id from query if present
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Fetch payment methods (try /payment-methods then fallback)
$methodsRes = api_request('GET', '/payment-methods');
if (empty($methodsRes['success'])) {
    $methodsRes = api_request('GET', '/payments/methods');
}

// If order id provided fetch order details
$order = null;
$orderFetchMsg = null;
if ($orderId) {
    $ordRes = api_request('GET', '/orders/' . urlencode($orderId));
    if (!empty($ordRes['success'])) {
        // normalize
        $order = $ordRes['order'] ?? $ordRes['data'] ?? $ordRes;
    } else {
        $orderFetchMsg = $ordRes['message'] ?? 'فشل جلب بيانات الطلب';
    }
}

// Optionally fetch user recent payments
$recentPayments = [];
$paymentsRes = api_request('GET', '/payments?recent=1');
if (!empty($paymentsRes['success'])) {
    $recentPayments = $paymentsRes['data'] ?? $paymentsRes['payments'] ?? [];
}

?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>الدفع</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:900px;margin:auto}
        .card{border:1px solid #eee;padding:16px;margin-bottom:12px;border-radius:6px;background:#fff}
        .methods{display:flex;flex-wrap:wrap;gap:10px}
        .method{border:1px solid #f0f0f0;padding:10px;border-radius:6px;min-width:160px;flex:1;cursor:pointer}
        .method.selected{border-color:#2d8cf0;background:#f0f8ff}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .muted{color:#666}
        table{width:100%;border-collapse:collapse;margin-top:8px}
        th,td{padding:8px;border:1px solid #f1f1f1;text-align:right}
        input[type="text"], input[type="email"]{width:100%;padding:8px;box-sizing:border-box}
    </style>
</head>
<body>
<div class="container">
    <h1>صفحة الدفع</h1>

    <?php if ($orderId): ?>
        <div class="card">
            <h2>ملخص الطلب</h2>
            <?php if ($order): ?>
                <p>رقم الطلب: <strong><?php echo e($order['order_number'] ?? $order['id']); ?></strong></p>
                <p>تاريخ: <span class="muted"><?php echo e($order['created_at'] ?? $order['created'] ?? ''); ?></span></p>
                <p>الحالة: <strong><?php echo e($order['order_status'] ?? ''); ?></strong></p>

                <table>
                    <thead><tr><th>المنتج</th><th>سعر الوحدة</th><th>كمية</th><th>المجموع</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] ?? [] as $it): ?>
                        <tr>
                            <td><?php echo e($it['name'] ?? $it['title'] ?? ''); ?></td>
                            <td><?php echo e($it['price'] ?? '0.00'); ?></td>
                            <td><?php echo e($it['quantity'] ?? 1); ?></td>
                            <td><?php echo e($it['total'] ?? (($it['price'] ?? 0) * ($it['quantity'] ?? 1))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="muted">الشحن: <?php echo e($order['shipping_fee'] ?? '0.00'); ?> — الخصم: <?php echo e($order['discount_amount'] ?? '0.00'); ?></p>
                <p>الإجمالي: <strong><?php echo e($order['grand_total'] ?? $order['total'] ?? '0.00'); ?> <?php echo e($order['currency'] ?? ''); ?></strong></p>
            <?php else: ?>
                <p class="muted"><?php echo e($orderFetchMsg ?? 'لم يتم العثور على تفاصيل الطلب'); ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>اختَر طريقة الدفع</h2>
            <?php
            $methods = [];
            if (!empty($methodsRes['success'])) {
                $methods = $methodsRes['data'] ?? $methodsRes['methods'] ?? $methodsRes;
            }
            if (empty($methods)) {
                echo '<p class="muted">لم تتوفر طرق دفع. تواصل مع الدعم.</p>';
            } else {
                echo '<div class="methods" id="methods">';
                foreach ($methods as $m) {
                    $mid = $m['id'] ?? $m['code'] ?? ($m['gateway'] ?? '');
                    $title = $m['title'] ?? $m['name'] ?? ucfirst($m['gateway'] ?? $mid);
                    $desc = $m['description'] ?? $m['desc'] ?? '';
                    echo '<div class="method" data-id="'.e($mid).'" data-gateway="'.e($m['gateway'] ?? $mid).'">';
                    echo '<strong>' . e($title) . '</strong>';
                    if ($desc) echo '<div class="muted" style="margin-top:6px">' . e($desc) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
            <div style="margin-top:12px">
                <button id="payBtn" class="btn">ادفع الآن</button>
                <a class="btn" href="/frontend/orders.php" style="background:#95a5a6">العودة للطلبات</a>
            </div>
            <div id="payMsg" style="margin-top:12px"></div>
        </div>

        <div class="card" id="cardFormWrap" style="display:none">
            <h2>تفاصيل البطاقة (تجريبي)</h2>
            <p class="muted">هذه مجرد نموذج توضيحي؛ في الإنتاج استخدم بوابة دفع آمنة (Stripe/PayPal) عبر صفحة الدفع الخاصة بالبوابة.</p>
            <form id="cardForm">
                <label>اسم حامل البطاقة
                    <input type="text" name="card_name" required>
                </label>
                <label>رقم البطاقة
                    <input type="text" name="card_number" placeholder="4242424242424242" required>
                </label>
                <label>انتهاء الصلاحية (MM/YY)
                    <input type="text" name="card_exp" placeholder="12/34" required>
                </label>
                <label>CVC
                    <input type="text" name="card_cvc" placeholder="123" required>
                </label>
                <div style="margin-top:8px">
                    <button class="btn" type="submit">إرسال بيانات البطاقة</button>
                    <button class="btn" type="button" id="cancelCard" style="background:#95a5a6">إلغاء</button>
                </div>
                <div id="cardMsg" style="margin-top:8px" class="muted"></div>
            </form>
        </div>

    <?php else: // no orderId - show methods and recent payments ?>
        <div class="card">
            <h2>طرق الدفع المتاحة</h2>
            <?php
            $methods = [];
            if (!empty($methodsRes['success'])) {
                $methods = $methodsRes['data'] ?? $methodsRes['methods'] ?? $methodsRes;
            }
            if (empty($methods)) {
                echo '<p class="muted">لا توجد طرق دفع متاحة حالياً.</p>';
            } else {
                echo '<div class="methods">';
                foreach ($methods as $m) {
                    $mid = $m['id'] ?? $m['code'] ?? ($m['gateway'] ?? '');
                    $title = $m['title'] ?? $m['name'] ?? ucfirst($m['gateway'] ?? $mid);
                    $desc = $m['description'] ?? '';
                    echo '<div class="method" style="min-width:220px"><strong>' . e($title) . '</strong>';
                    if ($desc) echo '<div class="muted" style="margin-top:6px">' . e($desc) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>

        <div class="card">
            <h2>الدفعات الأخيرة</h2>
            <?php if (empty($recentPayments)): ?>
                <p class="muted">لا توجد دفعات سابقة لعرضها.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>معرّف</th><th>الطلب</th><th>المبلغ</th><th>البوابة</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><?php echo e($p['id'] ?? ''); ?></td>
                            <td><?php echo e($p['order_id'] ?? $p['order_number'] ?? ''); ?></td>
                            <td><?php echo e($p['amount'] ?? '0.00'); ?> <?php echo e($p['currency'] ?? ''); ?></td>
                            <td><?php echo e($p['gateway'] ?? $p['method'] ?? ''); ?></td>
                            <td><?php echo e($p['status'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// client-side behaviour: select method, create payment via API, handle card form fallback or redirect
(function () {
    const methodsEl = document.getElementById('methods');
    let selectedGateway = null;
    if (methodsEl) {
        methodsEl.addEventListener('click', function (ev) {
            const card = ev.target.closest('.method');
            if (!card) return;
            // toggle selection
            Array.from(methodsEl.querySelectorAll('.method')).forEach(m => m.classList.remove('selected'));
            card.classList.add('selected');
            selectedGateway = card.getAttribute('data-gateway') || card.getAttribute('data-id');
            // show card form for specific gateways (for demo, show when gateway == 'card' or contains 'card')
            const cardWrap = document.getElementById('cardFormWrap');
            if (selectedGateway && selectedGateway.toLowerCase().indexOf('card') !== -1) {
                cardWrap.style.display = 'block';
            } else {
                if (cardWrap) cardWrap.style.display = 'none';
            }
            const payMsg = document.getElementById('payMsg');
            if (payMsg) { payMsg.textContent = ''; }
        });
    }

    document.getElementById('payBtn')?.addEventListener('click', async function () {
        const payMsg = document.getElementById('payMsg');
        if (!selectedGateway) {
            payMsg.style.color = 'red';
            payMsg.textContent = 'الرجاء اختيار وسيلة دفع.';
            return;
        }
        payMsg.style.color = '';
        payMsg.textContent = 'جاري إنشاء عملية الدفع...';

        // create payment record via API
        const orderId = <?php echo json_encode($orderId ?: null); ?>;
        // amount and currency: prefer order totals when available
        const amount = <?php echo json_encode($order ? ($order['grand_total'] ?? $order['total'] ?? 0) : null); ?>;
        const currency = <?php echo json_encode($order ? ($order['currency'] ?? '') : ''); ?>;
        const payload = {
            order_id: orderId,
            amount: amount,
            currency: currency || undefined,
            gateway: selectedGateway,
            client_reference: 'frontend_' + Date.now()
        };

        try {
            const res = await fetch('/api/payments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json().catch(()=>null);
            if (!res.ok) {
                payMsg.style.color = 'red';
                payMsg.textContent = (json && (json.message || JSON.stringify(json))) || 'فشل إنشاء عملية الدفع';
                return;
            }

            // On success, API may return payment object with 'payment_url' for redirect or 'requires_card' flag
            const payment = json.payment ?? json.data ?? json;
            // if gateway returns a redirect url, go there
            if (payment && payment.payment_url) {
                payMsg.style.color = 'green';
                payMsg.textContent = 'جاري التحويل إلى بوابة الدفع...';
                // redirect to gateway (could be popup)
                window.location.href = payment.payment_url;
                return;
            }

            // If selected gateway is 'card' and no redirect, show card form (handled separately)
            if (selectedGateway && selectedGateway.toLowerCase().indexOf('card') !== -1) {
                document.getElementById('cardFormWrap').style.display = 'block';
                payMsg.style.color = 'green';
                payMsg.textContent = 'أدخل بيانات البطاقة لإتمام الدفع (تجريبي).';
                // store created payment id to attach when submitting card
                document.getElementById('cardForm').dataset.paymentId = payment.id || payment.payment_id || '';
                return;
            }

            // Otherwise show success or next step
            payMsg.style.color = 'green';
            payMsg.textContent = 'تم إنشاء عملية الدفع. إذا لزم الأمر سيتم توجيهك للمزيد من الخطوات.';
            // optionally redirect to order page
            setTimeout(function () {
                window.location.href = '/frontend/orders.php';
            }, 1200);

        } catch (err) {
            payMsg.style.color = 'red';
            payMsg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });

    // Card form submit (demo)
    const cardForm = document.getElementById('cardForm');
    if (cardForm) {
        cardForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const msg = document.getElementById('cardMsg');
            msg.textContent = 'جارٍ إرسال بيانات البطاقة (تجريبي)...';

            const fd = new FormData(cardForm);
            const paymentId = cardForm.dataset.paymentId || null;
            const payload = {
                payment_id: paymentId,
                card_name: fd.get('card_name'),
                card_number: fd.get('card_number'),
                card_exp: fd.get('card_exp'),
                card_cvc: fd.get('card_cvc')
            };

            try {
                const res = await fetch('/api/payments/capture', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json().catch(()=>null);
                if (res.ok) {
                    msg.style.color = 'green';
                    msg.textContent = 'تمت معالجة الدفع بنجاح (تجريبي). ستتلقى تأكيداً عبر البريد الإلكتروني.';
                    setTimeout(()=> window.location.href = '/frontend/orders.php', 1200);
                } else {
                    msg.style.color = 'red';
                    msg.textContent = (json && (json.message || JSON.stringify(json))) || 'فشل معالجة البطاقة';
                }
            } catch (err) {
                msg.style.color = 'red';
                msg.textContent = 'خطأ في الاتصال: ' + err.message;
            }
        });

        document.getElementById('cancelCard')?.addEventListener('click', function () {
            document.getElementById('cardFormWrap').style.display = 'none';
        });
    }
})();
</script>
</body>
</html>