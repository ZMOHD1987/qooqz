<?php
// htdocs/frontend/services.php
// Frontend page for services: list services, view service details, check availability and book a service.
// Uses API endpoints: /api/services, /api/services/{id}, /api/services/{id}/book
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

$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? null;

if ($serviceId) {
    // fetch single service details
    $svcRes = api_request('GET', '/services/' . $serviceId);
    // optional availability endpoint
    $availRes = api_request('GET', '/services/' . $serviceId . '/availability');
} else {
    // list services (optional query params: page, q, category)
    $qs = [];
    if (!empty($_GET['page'])) $qs[] = 'page=' . urlencode((int)$_GET['page']);
    if (!empty($_GET['q'])) $qs[] = 'q=' . urlencode($_GET['q']);
    if (!empty($_GET['category'])) $qs[] = 'category=' . urlencode($_GET['category']);
    $path = '/services' . ($qs ? '?' . implode('&', $qs) : '');
    $listRes = api_request('GET', $path);
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title><?php echo $serviceId ? 'تفاصيل الخدمة' : 'الخدمات'; ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:980px;margin:auto}
        .service, .item {border:1px solid #eee;padding:12px;margin-bottom:12px;border-radius:6px;background:#fff;display:flex;gap:12px;align-items:flex-start}
        .media{width:140px;height:100px;background:#f7f7f7;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .media img{max-width:100%;max-height:100%;object-fit:cover}
        .meta{flex:1}
        .price{font-weight:700;color:#c0392b}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        label{display:block;margin-top:8px}
        input, textarea, select{width:100%;padding:8px;box-sizing:border-box;margin-top:4px}
        .muted{color:#666}
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><?php echo $serviceId ? 'تفاصيل الخدمة' : 'الخدمات المتاحة'; ?></h1>
        <div><a class="btn" href="/frontend/products.php">الذهاب إلى المتجر</a></div>
    </header>

<?php if ($serviceId): ?>
    <?php if (empty($svcRes['success'])): ?>
        <p class="muted">فشل جلب بيانات الخدمة. الرجاء المحاولة لاحقاً.</p>
    <?php else:
        $svc = $svcRes['service'] ?? $svcRes['data'] ?? $svcRes;
        if (!$svc) { echo '<p class="muted">لم يتم العثور على الخدمة.</p>'; }
        else {
            $img = $svc['image'] ?? ($svc['images'][0] ?? null);
            $price = $svc['price'] ?? ($svc['starting_price'] ?? '0.00');
            $duration = $svc['duration'] ?? $svc['estimated_time'] ?? '';
    ?>
        <section class="service">
            <div class="media">
                <?php if ($img): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($svc['title'] ?? ''); ?>"><?php else: ?><span>لا توجد صورة</span><?php endif; ?>
            </div>
            <div class="meta">
                <h2><?php echo e($svc['title'] ?? $svc['name'] ?? 'خدمة'); ?></h2>
                <p class="muted"><?php echo nl2br(e($svc['short_description'] ?? $svc['description'] ?? '')); ?></p>
                <p class="price"><?php echo e($price); ?> <?php echo e($svc['currency'] ?? ''); ?> <?php if($duration): ?><span class="muted"> — المدة: <?php echo e($duration); ?></span><?php endif; ?></p>

                <div style="margin-top:12px">
                    <button id="bookBtn" class="btn">احجز الآن</button>
                    <a class="btn" href="/frontend/services.php">رجوع إلى الخدمات</a>
                </div>

                <div id="availability" style="margin-top:12px">
                    <h4>التوافر</h4>
                    <?php
                    if (!empty($availRes['success']) && !empty($availRes['availability'])) {
                        $slots = $availRes['availability'];
                        if (empty($slots)) {
                            echo '<p class="muted">لا توجد مواعيد متاحة حالياً.</p>';
                        } else {
                            echo '<ul>';
                            foreach ($slots as $s) {
                                $when = e($s['when'] ?? $s['start'] ?? $s['slot'] ?? '');
                                echo '<li>' . $when . '</li>';
                            }
                            echo '</ul>';
                        }
                    } else {
                        echo '<p class="muted">لم تتوفر معلومات التوافر.</p>';
                    }
                    ?>
                </div>

                <div id="bookingFormContainer" style="display:none;margin-top:12px;border-top:1px dashed #eee;padding-top:12px">
                    <h4>نموذج الحجز</h4>
                    <form id="bookingForm">
                        <label>الاسم
                            <input type="text" name="name" required>
                        </label>
                        <label>البريد الإلكتروني
                            <input type="email" name="email" required>
                        </label>
                        <label>التاريخ / الوقت المرغوب (إن وجد)
                            <input type="text" name="requested_at" placeholder="YYYY-MM-DD HH:MM">
                        </label>
                        <label>ملاحظات
                            <textarea name="notes" rows="4"></textarea>
                        </label>
                        <div style="margin-top:8px">
                            <button class="btn" type="submit">إرسال طلب الحجز</button>
                            <button class="btn" type="button" id="cancelBooking" style="background:#95a5a6">إلغاء</button>
                        </div>
                        <div id="bookingMsg" style="margin-top:8px" class="muted"></div>
                    </form>
                </div>
            </div>
        </section>

    <?php } // end service found ?>
    <?php endif; ?>

<?php else: // list view ?>
    <?php
    if (empty($listRes['success'])) {
        echo '<p class="muted">فشل جلب قائمة الخدمات. الرجاء المحاولة لاحقاً.</p>';
    } else {
        $services = $listRes['data'] ?? $listRes['services'] ?? (is_array($listRes) && isset($listRes[0]) ? $listRes : []);
        if (empty($services)) {
            echo '<p class="muted">لا توجد خدمات متاحة.</p>';
        } else {
            foreach ($services as $s) {
                $img = $s['image'] ?? ($s['images'][0] ?? null);
                $price = $s['price'] ?? ($s['starting_price'] ?? '0.00');
                ?>
                <article class="item">
                    <div class="media">
                        <?php if ($img): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($s['title'] ?? ''); ?>"><?php else: ?><span>لا توجد صورة</span><?php endif; ?>
                    </div>
                    <div class="meta">
                        <h3><?php echo e($s['title'] ?? $s['name'] ?? 'خدمة'); ?></h3>
                        <p class="muted"><?php echo e(mb_substr($s['short_description'] ?? $s['description'] ?? '', 0, 160)); ?></p>
                    </div>
                    <div style="text-align:center">
                        <div class="price"><?php echo e($price); ?> <?php echo e($s['currency'] ?? ''); ?></div>
                        <div style="margin-top:8px">
                            <a class="btn" href="/frontend/services.php?id=<?php echo e($s['id']); ?>">عرض</a>
                            <button class="btn" onclick="quickBook(<?php echo (int)$s['id']; ?>)">حجز سريع</button>
                        </div>
                    </div>
                </article>
                <?php
            }
        }
    }
    ?>
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
    const bookBtn = document.getElementById('bookBtn');
    if (bookBtn) {
        bookBtn.addEventListener('click', function () {
            document.getElementById('bookingFormContainer').style.display = 'block';
            window.scrollTo({ top: document.getElementById('bookingFormContainer').offsetTop - 20, behavior: 'smooth' });
        });
    }

    const cancelBooking = document.getElementById('cancelBooking');
    if (cancelBooking) {
        cancelBooking.addEventListener('click', function () {
            document.getElementById('bookingFormContainer').style.display = 'none';
        });
    }

    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const msgEl = document.getElementById('bookingMsg');
            msgEl.style.color = '';
            msgEl.textContent = 'جارٍ إرسال طلب الحجز...';
            const fd = new FormData(bookingForm);
            const payload = {
                name: fd.get('name'),
                email: fd.get('email'),
                requested_at: fd.get('requested_at'),
                notes: fd.get('notes')
            };
            const serviceId = <?php echo json_encode($serviceId ?: 'null'); ?>;
            if (!serviceId) {
                msgEl.style.color = 'red';
                msgEl.textContent = 'معرّف الخدمة غير متوفر.';
                return;
            }
            const res = await api('/services/' + encodeURIComponent(serviceId) + '/book', 'POST', payload);
            if (res.ok) {
                msgEl.style.color = 'green';
                msgEl.textContent = 'تم إرسال طلب الحجز. سنتواصل معك قريباً.';
                bookingForm.reset();
                setTimeout(()=> { document.getElementById('bookingFormContainer').style.display = 'none'; }, 1200);
            } else {
                msgEl.style.color = 'red';
                msgEl.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إرسال الحجز';
            }
        });
    }
});

async function quickBook(id) {
    const name = prompt('الاسم للاتصال');
    if (!name) return;
    const email = prompt('البريد الإلكتروني');
    if (!email) return;
    const res = await api('/services/' + encodeURIComponent(id) + '/book', 'POST', { name, email });
    if (res.ok) alert('طلب الحجز المصغر أُرسل');
    else alert('فشل الحجز: ' + (res.json && res.json.message ? res.json.message : 'خطأ'));
}
</script>
</body>
</html>