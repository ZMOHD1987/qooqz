<?php
// htdocs/frontend/reviews.php
// Frontend page for reviews: list reviews and submit a new review for product/vendor/service.
// Uses API endpoints: /api/reviews (GET, POST), /api/products/{id}, /api/vendors/{id}, /api/services/{id}
// Minimal Arabic UI and lightweight JS to fetch and post reviews.

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

// Determine context: product_id, vendor_id, service_id or generic listing
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$vendorId  = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;

// Fetch reviews for the given context
$query = [];
if ($productId) $query[] = 'product_id=' . urlencode($productId);
if ($vendorId)  $query[] = 'vendor_id=' . urlencode($vendorId);
if ($serviceId) $query[] = 'service_id=' . urlencode($serviceId);
$query[] = 'page=' . $page;
$query[] = 'per_page=' . $perPage;
$path = '/reviews' . ($query ? '?' . implode('&', $query) : '');
$reviewsRes = api_request('GET', $path);

// Fetch target item meta for header/title if applicable
$target = null;
if ($productId) {
    $pRes = api_request('GET', '/products/' . $productId);
    if (!empty($pRes['success'])) $target = $pRes['product'] ?? $pRes['data'] ?? $pRes;
}
if (!$target && $vendorId) {
    $vRes = api_request('GET', '/vendors/' . $vendorId);
    if (!empty($vRes['success'])) $target = $vRes['vendor'] ?? $vRes['data'] ?? $vRes;
}
if (!$target && $serviceId) {
    $sRes = api_request('GET', '/services/' . $serviceId);
    if (!empty($sRes['success'])) $target = $sRes['service'] ?? $sRes['data'] ?? $sRes;
}

// Compute aggregate (if provided by API) or derive from returned reviews
$reviews = [];
$pagination = [];
$average = null;
if (!empty($reviewsRes['success'])) {
    // API may return { data: [...], meta: { total, page, per_page }, average_rating }
    if (!empty($reviewsRes['data']) && is_array($reviewsRes['data'])) $reviews = $reviewsRes['data'];
    elseif (!empty($reviewsRes['reviews']) && is_array($reviewsRes['reviews'])) $reviews = $reviewsRes['reviews'];
    elseif (is_array($reviewsRes) && isset($reviewsRes[0])) $reviews = $reviewsRes;

    $pagination = $reviewsRes['meta'] ?? $reviewsRes['pagination'] ?? ['page' => $page, 'per_page' => $perPage, 'total' => count($reviews)];
    $average = $reviewsRes['average_rating'] ?? $reviewsRes['avg_rating'] ?? null;

    // fallback calculate average from fetched page only
    if ($average === null && !empty($reviews)) {
        $sum = 0; $cnt = 0;
        foreach ($reviews as $r) { if (isset($r['rating'])) { $sum += (float)$r['rating']; $cnt++; } }
        if ($cnt > 0) $average = round($sum / $cnt, 2);
    }
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title><?php
        if ($target) echo e($target['title'] ?? $target['name'] ?? $target['title'] ?? 'التعليقات');
        else echo 'المراجعات والتقييمات';
    ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:980px;margin:auto}
        .card{border:1px solid #eee;padding:12px;margin-bottom:12px;border-radius:6px;background:#fff}
        .review{border-bottom:1px solid #f1f1f1;padding:10px 0}
        .review:last-child{border-bottom:none}
        .meta{color:#666;font-size:0.9em;margin-bottom:6px}
        .stars{color:#f1c40f;margin-left:6px}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;cursor:pointer}
        form label{display:block;margin-top:8px}
        input[type="text"], input[type="email"], textarea, select{width:100%;padding:8px;box-sizing:border-box;margin-top:4px}
        .muted{color:#666}
        .avg { font-size:1.1em; font-weight:700; color:#2d8cf0; }
        .pager { display:flex; gap:8px; justify-content:flex-start; margin-top:12px; }
        .small { font-size:0.9em; color:#777; }
    </style>
</head>
<body>
<div class="container">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
            <h1><?php
                if ($target) echo e($target['title'] ?? $target['name'] ?? 'التعليقات والمراجعات');
                else echo 'المراجعات والتقييمات';
            ?></h1>
            <?php if ($average !== null): ?>
                <div class="small">متوسط التقييم: <span class="avg"><?php echo e(number_format($average, 2)); ?></span>
                    <span class="stars"><?php echo str_repeat('★', round($average)); echo str_repeat('☆', 5 - round($average)); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <a class="btn" href="#writeReview" onclick="document.getElementById('reviewForm').scrollIntoView({behavior:'smooth'})">أضف مراجعتك</a>
        </div>
    </header>

    <section class="card" aria-labelledby="listHeading">
        <h2 id="listHeading">التقييمات</h2>

        <?php if (empty($reviews)): ?>
            <p class="muted">لا توجد مراجعات لعرضها.</p>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <article class="review" role="article">
                    <div class="meta">
                        <strong><?php echo e($r['author_name'] ?? $r['name'] ?? 'مستخدم'); ?></strong>
                        &middot;
                        <span class="small"><?php echo e(date('Y-m-d', strtotime($r['created_at'] ?? $r['created'] ?? ''))); ?></span>
                        <span class="stars"><?php echo str_repeat('★', min(5, (int)($r['rating'] ?? 0))) . str_repeat('☆', 5 - min(5, (int)($r['rating'] ?? 0))); ?></span>
                    </div>
                    <?php if (!empty($r['title'])): ?><h3><?php echo e($r['title']); ?></h3><?php endif; ?>
                    <div><?php echo nl2br(e($r['body'] ?? $r['message'] ?? '')); ?></div>
                    <?php if (!empty($r['response'])): ?>
                        <div style="margin-top:8px;padding:8px;background:#f7f9fc;border-left:3px solid #2d8cf0">
                            <strong>رد البائع/الموقع:</strong>
                            <div><?php echo nl2br(e($r['response'])); ?></div>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php
            // simple pager
            $total = $pagination['total'] ?? null;
            $cur = $pagination['page'] ?? $page;
            $pp = $pagination['per_page'] ?? $perPage;
            if ($total !== null && $total > $pp):
                $last = (int)ceil($total / $pp);
            ?>
                <nav class="pager" aria-label="pagination">
                    <?php if ($cur > 1): ?>
                        <a class="btn" href="?<?php
                            $qs = $_GET; $qs['page'] = $cur - 1;
                            echo http_build_query($qs);
                        ?>">السابق</a>
                    <?php endif; ?>
                    <span class="small">صفحة <?php echo e($cur); ?> من <?php echo e($last); ?></span>
                    <?php if ($cur < $last): ?>
                        <a class="btn" href="?<?php
                            $qs = $_GET; $qs['page'] = $cur + 1;
                            echo http_build_query($qs);
                        ?>">التالي</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </section>

    <section class="card" id="writeReview" aria-labelledby="writeHeading">
        <h2 id="writeHeading">أضف مراجعتك</h2>
        <form id="reviewForm">
            <input type="hidden" name="product_id" value="<?php echo e($productId ?: ''); ?>">
            <input type="hidden" name="vendor_id" value="<?php echo e($vendorId ?: ''); ?>">
            <input type="hidden" name="service_id" value="<?php echo e($serviceId ?: ''); ?>">

            <label>الاسم (ظاهر)
                <input type="text" name="author_name" required>
            </label>
            <label>البريد الإلكتروني (اختياري)
                <input type="email" name="author_email">
            </label>
            <label>العنوان (اختياري)
                <input type="text" name="title">
            </label>
            <label>التقييم
                <select name="rating" required>
                    <option value="">اختر التقييم</option>
                    <option value="5">5 — ممتاز</option>
                    <option value="4">4 — جيد جداً</option>
                    <option value="3">3 — جيد</option>
                    <option value="2">2 — مقبول</option>
                    <option value="1">1 — ضعيف</option>
                </select>
            </label>
            <label>المراجعة
                <textarea name="body" rows="6" required></textarea>
            </label>

            <div style="margin-top:8px">
                <button class="btn" type="submit">أرسل المراجعة</button>
                <span id="reviewMsg" class="muted" style="margin-left:8px"></span>
            </div>
        </form>
    </section>
</div>

<script>
(async function () {
    function qs(obj) { return Object.keys(obj).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k])).join('&'); }
    async function post(path, body) {
        const res = await fetch('/api' + path, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const json = await res.json().catch(()=>null);
        return { ok: res.ok, status: res.status, json };
    }

    const form = document.getElementById('reviewForm');
    const msg = document.getElementById('reviewMsg');
    form.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        msg.style.color = '';
        msg.textContent = 'جارٍ إرسال المراجعة...';
        const fd = new FormData(form);
        const payload = {};
        for (const [k, v] of fd.entries()) {
            if (v === '') continue;
            payload[k] = v;
        }
        // Basic client-side validation
        if (!payload.rating || !payload.body || !payload.author_name) {
            msg.style.color = 'red';
            msg.textContent = 'يرجى ملء الاسم، و التقييم، و نص المراجعة.';
            return;
        }
        try {
            const res = await post('/reviews', payload);
            if (res.ok) {
                msg.style.color = 'green';
                msg.textContent = 'تم إرسال المراجعة. ستظهر بعد المراجعة إذا لزم الأمر.';
                form.reset();
                // Optionally reload the page to show new review (or append)
                setTimeout(()=> location.reload(), 900);
            } else {
                msg.style.color = 'red';
                msg.textContent = (res.json && (res.json.message || JSON.stringify(res.json))) || 'فشل إرسال المراجعة';
            }
        } catch (err) {
            msg.style.color = 'red';
            msg.textContent = 'خطأ في الاتصال: ' + err.message;
        }
    });
})();
</script>
</body>
</html>