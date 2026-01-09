<?php
// htdocs/frontend/shipping.php
// Frontend page to calculate shipping options, manage shipping addresses, and track shipments.
// Interacts with API endpoints: /api/shipping/calculate, /api/shipping/methods, /api/shipments, /api/shipments/{tracking}
// Minimal Arabic UI and lightweight JS to call API and present results.

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
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>الشحن والتتبع</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:1000px;margin:auto}
        .card{border:1px solid #eee;padding:16px;margin-bottom:12px;border-radius:6px;background:#fff}
        label{display:block;margin-top:8px}
        input, select, textarea{width:100%;padding:8px;box-sizing:border-box;margin-top:4px}
        .methods{display:flex;flex-direction:column;gap:8px;margin-top:8px}
        .method{border:1px solid #f0f0f0;padding:10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        .muted{color:#666}
        .result { margin-top:12px }
        table{width:100%;border-collapse:collapse;margin-top:8px}
        th,td{padding:8px;border:1px solid #f1f1f1;text-align:right}
    </style>
</head>
<body>
<div class="container">
    <h1>حساب الشحن وتتبع الشحنات</h1>

    <section class="card" aria-labelledby="calcHeading">
        <h2 id="calcHeading">حساب تكلفة الشحن</h2>
        <form id="calcForm">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label>الوزن الإجمالي (كجم)
                        <input type="text" name="total_weight" placeholder="مثال: 2.5" required>
                    </label>
                    <label>قيمة الطلب (عملة محلية)
                        <input type="text" name="cart_total" placeholder="مثال: 150.00">
                    </label>
                    <label>العملة (اختياري)
                        <input type="text" name="currency" placeholder="USD">
                    </label>
                </div>
                <div>
                    <label>البلد
                        <input type="text" name="country" placeholder="مثال: US" required>
                    </label>
                    <label>المنطقة/الولاية
                        <input type="text" name="region" placeholder="مثال: CA">
                    </label>
                    <label>الرمز البريدي
                        <input type="text" name="postal_code" placeholder="مثال: 90001">
                    </label>
                </div>
            </div>

            <div style="margin-top:12px">
                <button class="btn" type="submit">احسب خيارات الشحن</button>
                <button class="btn" type="button" id="clearCalc" style="background:#95a5a6">مسح</button>
            </div>
        </form>

        <div id="calcResult" class="result"></div>
    </section>

    <section class="card" aria-labelledby="methodsHeading">
        <h2 id="methodsHeading">طرق الشحن المتوفرة</h2>
        <p class="muted">قائمة طرق الشحن المسجلة في النظام.</p>
        <div id="methodsList" class="methods">
            <!-- populated by JS -->
        </div>
        <div style="margin-top:8px">
            <button class="btn" id="refreshMethods">تحديث الطرق</button>
        </div>
    </section>

    <section class="card" aria-labelledby="trackHeading">
        <h2 id="trackHeading">تتبع شحنة</h2>
        <form id="trackForm" style="display:flex;gap:8px;align-items:center">
            <input type="text" name="tracking_number" placeholder="أدخل رقم التتبع" required>
            <button class="btn" type="submit">تتبع</button>
        </form>
        <div id="trackResult" class="result"></div>
    </section>

    <section class="card" aria-labelledby="createShipHeading">
        <h2 id="createShipHeading">إنشاء سجل شحنة (اختياري)</h2>
        <p class="muted">إذا أردت يمكنك إنشاء سجل شحنة بسيط لربطه بطلب.</p>
        <form id="createShipForm">
            <label>رقم الطلب (اختياري)
                <input type="text" name="order_number" placeholder="معرف الطلب">
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <label>شركة الناقل
                    <input type="text" name="carrier" placeholder="مثال: DHL">
                </label>
                <label>رقم التتبع
                    <input type="text" name="tracking_number" placeholder="مثال: 123456789">
                </label>
            </div>
            <label>ملاحظات
                <textarea name="notes" rows="3"></textarea>
            </label>
            <div style="margin-top:8px">
                <button class="btn" type="submit">إنشاء الشحنة</button>
            </div>
        </form>
        <div id="createShipMsg" class="result"></div>
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

document.addEventListener('DOMContentLoaded', function () {
    const calcForm = el('calcForm');
    const calcResult = el('calcResult');
    const methodsList = el('methodsList');
    const refreshBtn = el('refreshMethods');
    const trackForm = el('trackForm');
    const trackResult = el('trackResult');
    const createShipForm = el('createShipForm');
    const createShipMsg = el('createShipMsg');
    const clearCalc = el('clearCalc');

    async function loadMethods() {
        methodsList.innerHTML = '<div class="muted">جاري جلب الطرق...</div>';
        const res = await api('/shipping/methods');
        if (!res.ok) {
            methodsList.innerHTML = '<div class="muted">فشل جلب طرق الشحن.</div>';
            return;
        }
        const methods = res.json && (res.json.data || res.json.methods || res.json) ? (res.json.data || res.json.methods || res.json) : [];
        if (!methods || methods.length === 0) {
            methodsList.innerHTML = '<div class="muted">لا توجد طرق مسجلة.</div>';
            return;
        }
        methodsList.innerHTML = '';
        methods.forEach(m => {
            const div = document.createElement('div');
            div.className = 'method';
            const title = m.title || m.name || m.provider || m.id;
            const desc = m.description || (m.settings && m.settings.estimate) || '';
            div.innerHTML = '<div><strong>' + escapeHtml(title) + '</strong><div class="muted" style="margin-top:6px">' + escapeHtml(desc) + '</div></div>' +
                            '<div><button class="btn viewDetails" data-id="' + escapeHtml(m.id || m.code || '') + '">عرض</button></div>';
            methodsList.appendChild(div);
        });
    }

    loadMethods();

    refreshBtn.addEventListener('click', function () { loadMethods(); });

    calcForm.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        calcResult.innerHTML = '<div class="muted">جاري حساب خيارات الشحن...</div>';
        const fd = new FormData(calcForm);
        const payload = {
            total_weight: parseFloat(fd.get('total_weight') || 0) || 0,
            cart_total: parseFloat(fd.get('cart_total') || 0) || 0,
            country: fd.get('country') || '',
            region: fd.get('region') || '',
            postal_code: fd.get('postal_code') || '',
            currency: fd.get('currency') || ''
        };
        try {
            const res = await api('/shipping/calculate', 'POST', payload);
            if (!res.ok) {
                calcResult.innerHTML = '<div class="muted">فشل الحساب: ' + escapeHtml((res.json && (res.json.message || JSON.stringify(res.json))) || 'خطأ') + '</div>';
                return;
            }
            const options = res.json && (res.json.data || res.json.options || res.json) ? (res.json.data || res.json.options || res.json) : [];
            if (!options || options.length === 0) {
                calcResult.innerHTML = '<div class="muted">لم تُعثر أي خيارات شحن.</div>';
                return;
            }
            // render table
            let html = '<table><thead><tr><th>طريقة الشحن</th><th>السعر</th><th>التقدير</th><th>مقدم</th></tr></thead><tbody>';
            options.forEach(o => {
                const method = o.method || (o.rate && o.rate.method) || (o.method_name) || '';
                const price = (o.price !== undefined ? o.price : (o.rate && o.rate.calculated_price) || o.rate && o.rate.amount) || 0;
                const estimate = o.estimate || (o.method && o.method.settings && (o.method.settings.estimate || o.method.settings.delivery_time)) || (o.rate && o.rate.estimate) || '';
                const provider = (o.method && o.method.provider) || (o.rate && o.rate.provider) || '';
                html += '<tr><td>' + escapeHtml(method) + '</td><td>' + escapeHtml(Number(price).toFixed(2)) + '</td><td>' + escapeHtml(estimate) + '</td><td>' + escapeHtml(provider) + '</td></tr>';
            });
            html += '</tbody></table>';
            calcResult.innerHTML = html;
        } catch (err) {
            calcResult.innerHTML = '<div class="muted">خطأ في الاتصال: ' + escapeHtml(err.message) + '</div>';
        }
    });

    clearCalc.addEventListener('click', function () {
        calcForm.reset();
        calcResult.innerHTML = '';
    });

    methodsList.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.viewDetails');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        if (!id) return;
        btn.disabled = true;
        api('/shipping/methods/' + encodeURIComponent(id)).then(res => {
            btn.disabled = false;
            if (!res.ok) {
                alert('فشل جلب تفاصيل الطريقة');
                return;
            }
            const m = res.json && (res.json.data || res.json.method || res.json) ? (res.json.data || res.json.method || res.json) : {};
            let html = '<h4>تفاصيل الطريقة</h4>';
            html += '<p><strong>' + escapeHtml(m.title || m.name || m.id) + '</strong></p>';
            if (m.settings) html += '<pre class="muted">' + escapeHtml(JSON.stringify(m.settings, null, 2)) + '</pre>';
            alert(stripTags(html) || 'لا توجد تفاصيل');
        }).catch(()=>{ btn.disabled = false; alert('خطأ'); });
    });

    trackForm.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        trackResult.innerHTML = '<div class="muted">جاري التتبع...</div>';
        const fd = new FormData(trackForm);
        const tracking = (fd.get('tracking_number') || '').trim();
        if (!tracking) {
            trackResult.innerHTML = '<div class="muted">الرجاء إدخال رقم التتبع.</div>';
            return;
        }
        try {
            const res = await api('/shipments/' + encodeURIComponent(tracking));
            if (!res.ok) {
                trackResult.innerHTML = '<div class="muted">لم يتم العثور على الشحنة أو فشل الاتصال.</div>';
                return;
            }
            const s = res.json && (res.json.data || res.json.shipment || res.json) ? (res.json.data || res.json.shipment || res.json) : {};
            let html = '<h4>حالة الشحنة</h4>';
            html += '<p>ناقل: ' + escapeHtml(s.carrier || s.provider || '') + ' — رقم تتبع: ' + escapeHtml(s.tracking_number || tracking) + '</p>';
            html += '<p>الحالة: <strong>' + escapeHtml(s.status || '') + '</strong></p>';
            if (s.events && s.events.length) {
                html += '<table><thead><tr><th>التاريخ</th><th>الحدث</th></tr></thead><tbody>';
                s.events.forEach(ev => {
                    html += '<tr><td>' + escapeHtml(ev.at || ev.time || '') + '</td><td>' + escapeHtml(ev.description || ev.note || ev.status || '') + '</td></tr>';
                });
                html += '</tbody></table>';
            } else if (s.meta) {
                html += '<pre class="muted">' + escapeHtml(JSON.stringify(s.meta, null, 2)) + '</pre>';
            } else {
                html += '<p class="muted">لا توجد تفاصيل إضافية.</p>';
            }
            trackResult.innerHTML = html;
        } catch (err) {
            trackResult.innerHTML = '<div class="muted">خطأ في الاتصال: ' + escapeHtml(err.message) + '</div>';
        }
    });

    createShipForm.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        createShipMsg.innerHTML = '<div class="muted">جاري إنشاء سجل الشحنة...</div>';
        const fd = new FormData(createShipForm);
        const payload = {
            order_number: fd.get('order_number') || null,
            carrier: fd.get('carrier') || null,
            tracking_number: fd.get('tracking_number') || null,
            notes: fd.get('notes') || null
        };
        try {
            const res = await api('/shipments', 'POST', payload);
            if (!res.ok) {
                createShipMsg.innerHTML = '<div class="muted">فشل الإنشاء: ' + escapeHtml((res.json && (res.json.message || JSON.stringify(res.json))) || 'خطأ') + '</div>';
                return;
            }
            const s = res.json && (res.json.data || res.json.shipment || res.json) ? (res.json.data || res.json.shipment || res.json) : {};
            createShipMsg.innerHTML = '<div class="muted">تم إنشاء الشحنة. معرف: ' + escapeHtml(s.id || s.tracking_number || '') + '</div>';
            createShipForm.reset();
        } catch (err) {
            createShipMsg.innerHTML = '<div class="muted">خطأ في الاتصال: ' + escapeHtml(err.message) + '</div>';
        }
    });

    // helpers
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }
    function stripTags(html) {
        return (html || '').replace(/<\/?[^>]+(>|$)/g, "");
    }
});
</script>
</body>
</html>