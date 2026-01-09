<?php
// htdocs/frontend/vendors.php
// Frontend page for vendors: list vendors, view vendor profile and their products, contact or follow a vendor.
// Uses API endpoints: /api/vendors, /api/vendors/{id}, /api/products?vendor={id}, /api/vendors/{id}/contact
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

$vendorId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($vendorId) {
    // fetch vendor details and their products
    $vendorRes = api_request('GET', '/vendors/' . $vendorId);
    $productsRes = api_request('GET', '/products?vendor=' . $vendorId);
} else {
    // list vendors with optional search/page
    $qs = [];
    if (!empty($_GET['page'])) $qs[] = 'page=' . urlencode((int)$_GET['page']);
    if (!empty($_GET['q'])) $qs[] = 'q=' . urlencode($_GET['q']);
    $path = '/vendors' . ($qs ? '?' . implode('&', $qs) : '');
    $listRes = api_request('GET', $path);
}
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title><?php echo $vendorId ? 'بائع' : 'البائعون'; ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:1000px;margin:auto}
        .vendor, .product{border:1px solid #eee;padding:12px;margin-bottom:12px;border-radius:6px;background:#fff;display:flex;gap:12px;align-items:center}
        .logo{width:100px;height:100px;background:#f7f7f7;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .logo img{max-width:100%;max-height:100%;object-fit:cover}
        .meta{flex:1}
        .btn{display:inline-block;padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none}
        .muted{color:#666}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        form.contact { margin-top:12px; }
        label { display:block;margin-bottom:6px; }
        input[type="text"], input[type="email"], textarea { width:100%; padding:8px; box-sizing:border-box; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1><?php echo $vendorId ? 'تفاصيل البائع' : 'البائعون'; ?></h1>
        <div><a class="btn" href="/frontend/products.php">المنتجات</a></div>
    </header>

<?php if ($vendorId): ?>
    <?php if (empty($vendorRes['success'])): ?>
        <p class="muted">فشل جلب بيانات البائع. الرجاء المحاولة لاحقاً.</p>
    <?php else:
        $vendor = $vendorRes['vendor'] ?? $vendorRes['data'] ?? $vendorRes;
        if (!$vendor) { echo '<p class="muted">لم يتم العثور على البائع.</p>'; }
        else {
            $logo = $vendor['logo'] ?? $vendor['avatar'] ?? null;
    ?>
        <section class="vendor">
            <div class="logo">
                <?php if ($logo): ?><img src="<?php echo e($logo); ?>" alt="<?php echo e($vendor['name'] ?? ''); ?>"><?php else: ?><span>لا توجد صورة</span><?php endif; ?>
            </div>
            <div class="meta">
                <h2><?php echo e($vendor['name'] ?? $vendor['title'] ?? 'بائع'); ?></h2>
                <p class="muted"><?php echo e($vendor['short_description'] ?? $vendor['description'] ?? ''); ?></p>
                <p class="muted">الموقع: <?php echo e($vendor['location'] ?? '—'); ?> — المنتجات: <?php echo e($vendor['product_count'] ?? '—'); ?></p>
                <div style="margin-top:8px">
                    <button id="followBtn" class="btn"><?php echo (!empty($vendor['is_followed']) ? 'متابعة' : 'متابعة'); ?></button>
                    <a class="btn" href="/frontend/products.php?vendor=<?php echo e($vendor['id']); ?>">عرض منتجات البائع</a>
                </div>
            </div>
        </section>

        <section>
            <h3>منتجات البائع</h3>
            <div id="vendorProducts">
                <?php
                $products = [];
                if (!empty($productsRes['success'])) {
                    $products = $productsRes['data'] ?? $productsRes['products'] ?? (is_array($productsRes) && isset($productsRes[0]) ? $productsRes : []);
                }
                if (empty($products)) {
                    echo '<p class="muted">لا توجد منتجات للعرض.</p>';
                } else {
                    foreach ($products as $p) {
                        $img = $p['images'][0] ?? $p['image'] ?? null;
                        ?>
                        <article class="product">
                            <div class="logo" style="width:80px;height:80px">
                                <?php if ($img): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($p['title'] ?? ''); ?>"><?php else: ?><span>لا توجد صورة</span><?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <h4><?php echo e($p['title'] ?? $p['name'] ?? ''); ?></h4>
                                <div class="muted"><?php echo e(mb_substr($p['short_description'] ?? $p['description'] ?? '', 0, 120)); ?></div>
                            </div>
                            <div style="text-align:center">
                                <div style="font-weight:700;color:#c0392b"><?php echo e($p['price'] ?? '0.00'); ?> <?php echo e($p['currency'] ?? ''); ?></div>
                                <div style="margin-top:8px"><a class="btn" href="/frontend/products.php?id=<?php echo e($p['id']); ?>">عرض</a></div>
                            </div>
                        </article>
                        <?php
                    }
                }
                ?>
            </div>
        </section>

        <section style="margin-top:16px">
            <h3>اتصل بالبائع</h3>
            <form id="contactForm" class="contact">
                <label>الاسم
                    <input type="text" name="name" required>
                </label>
                <label>البريد الإلكتروني
                    <input type="email" name="email" required>
                </label>
                <label>الرسالة
                    <textarea name="message" rows="5" required></textarea>
                </label>
                <div style="margin-top:8px">
                    <button class="btn" type="submit">إرسال الرسالة</button>
                </div>
                <div id="contactMsg" style="margin-top:8px" class="muted"></div>
            </form>
        </section>

    <?php } // end vendor found ?>
    <?php endif; ?>

<?php else: // vendors list ?>
    <?php
    if (empty($listRes['success'])) {
        echo '<p class="muted">فشل جلب قائمة البائعين. الرجاء المحاولة لاحقاً.</p>';
    } else {
        $vendors = $listRes['data'] ?? $listRes['vendors'] ?? (is_array($listRes) && isset($listRes[0]) ? $listRes : []);
        if (empty($vendors)) {
            echo '<p class="muted">لا يوجد بائعون مسجلون.</p>';
        } else {
            foreach ($vendors as $v) {
                $logo = $v['logo'] ?? $v['avatar'] ?? null;
                ?>
                <article class="vendor">
                    <div class="logo">
                        <?php if ($logo): ?><img src="<?php echo e($logo); ?>" alt="<?php echo e($v['name'] ?? ''); ?>"><?php else: ?><span>لا توجد صورة</span><?php endif; ?>
                    </div>
                    <div class="meta">
                        <h3><?php echo e($v['name'] ?? $v['title'] ?? 'بائع'); ?></h3>
                        <p class="muted"><?php echo e(mb_substr($v['short_description'] ?? $v['description'] ?? '', 0, 140)); ?></p>
                        <p class="muted">المنتجات: <?php echo e($v['product_count'] ?? '—'); ?> — الموقع: <?php echo e($v['location'] ?? '—'); ?></p>
                        <div style="margin-top:8px">
                            <a class="btn" href="/frontend/vendors.php?id=<?php echo e($v['id']); ?>">عرض البائع</a>
                            <button class="btn followVendor" data-id="<?php echo e($v['id']); ?>">متابعة</button>
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
// Simple client-side actions: follow vendor, contact vendor
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
    // follow buttons on list
    document.querySelectorAll('.followVendor').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.getAttribute('data-id');
            this.disabled = true;
            const res = await api('/vendors/' + encodeURIComponent(id) + '/follow', 'POST', {});
            if (res.ok) {
                alert('تمت المتابعة');
            } else {
                alert('فشل المتابعة: ' + (res.json && res.json.message ? res.json.message : 'خطأ'));
            }
            this.disabled = false;
        });
    });

    // follow button on profile
    const followBtn = document.getElementById('followBtn');
    if (followBtn) {
        followBtn.addEventListener('click', async function () {
            const vendorId = <?php echo json_encode($vendorId ?: 'null'); ?>;
            if (!vendorId) return;
            this.disabled = true;
            const res = await api('/vendors/' + encodeURIComponent(vendorId) + '/follow', 'POST', {});
            if (res.ok) {
                alert('تمت المتابعة');
            } else {
                alert('فشل المتابعة');
            }
            this.disabled = false;
        });
    }

    // contact form
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const vendorId = <?php echo json_encode($vendorId ?: 'null'); ?>;
            if (!vendorId) return;
            const msgEl = document.getElementById('contactMsg');
            msgEl.textContent = 'جاري إرسال الرسالة...';
            const fd = new FormData(contactForm);
            const payload = {
                name: fd.get('name'),
                email: fd.get('email'),
                message: fd.get('message')
            };
            const res = await api('/vendors/' + encodeURIComponent(vendorId) + '/contact', 'POST', payload);
            if (res.ok) {
                msgEl.style.color = 'green';
                msgEl.textContent = 'تم إرسال الرسالة. سيتواصل معك البائع قريباً.';
                contactForm.reset();
            } else {
                msgEl.style.color = 'red';
                msgEl.textContent = 'فشل إرسال الرسالة: ' + (res.json && res.json.message ? res.json.message : 'خطأ');
            }
        });
    }
});
</script>
</body>
</html>