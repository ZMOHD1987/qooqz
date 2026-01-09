<?php
// htdocs/frontend/cart.php
// Simple frontend page to view/update the cart and proceed to checkout.
// Interacts with /api/cart endpoints.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// Basic security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function api_get($path)
{
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return ['success' => false, 'error' => $err, 'status' => $status];
    $decoded = json_decode($resp, true);
    if ($decoded === null) return ['success' => $status >= 200 && $status < 300, 'body' => $resp, 'status' => $status];
    return array_merge(['success' => $status >= 200 && $status < 300, 'status' => $status], $decoded);
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$res = api_get('/cart');
?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>سلة التسوق</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;direction:rtl;margin:20px}
        .container{max-width:900px;margin:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border:1px solid #eee;text-align:right}
        .actions{display:flex;gap:8px;justify-content:flex-start}
        .btn{padding:8px 12px;background:#2d8cf0;color:#fff;border-radius:4px;text-decoration:none;display:inline-block}
        .muted{color:#666}
    </style>
</head>
<body>
<div class="container">
    <h1>سلة التسوق</h1>
    <p><a class="btn" href="/frontend/products.php">متابعة التسوق</a></p>

<?php
if (!$res['success']) {
    echo '<p>فشل جلب محتوى السلة.</p>';
    if (!empty($res['message'])) echo '<pre>' . e($res['message']) . '</pre>';
    exit;
}

// cart structure may vary; typical: { items: [...], subtotal, total, coupons, meta }
$cart = $res['cart'] ?? $res['data'] ?? $res;
$items = $cart['items'] ?? [];

if (empty($items)) {
    echo '<p>السلة فارغة.</p>';
    exit;
}
?>

<table>
    <thead>
        <tr><th>المنتج</th><th>السعر</th><th>الكمية</th><th>المجموع</th><th>إجراءات</th></tr>
    </thead>
    <tbody id="cartBody">
    <?php foreach ($items as $it): ?>
        <tr data-item-id="<?php echo e($it['id'] ?? $it['product_id'] ?? ''); ?>">
            <td><?php echo e($it['name'] ?? $it['title'] ?? ''); ?></td>
            <td><?php echo e($it['price'] ?? '0.00'); ?></td>
            <td>
                <input type="number" class="qty" value="<?php echo e($it['quantity'] ?? 1); ?>" min="1" style="width:80px;padding:6px">
            </td>
            <td class="line-total"><?php echo e($it['total'] ?? (($it['price'] ?? 0) * ($it['quantity'] ?? 1))); ?></td>
            <td class="actions">
                <button class="btn update">تحديث</button>
                <button class="btn" onclick="removeItem('<?php echo e($it['id'] ?? $it['product_id']); ?>')">حذف</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="muted">المجموع: <strong id="cartSubtotal"><?php echo e($cart['subtotal'] ?? $cart['total'] ?? '0.00'); ?></strong></p>

<div style="margin-top:16px">
    <button class="btn" id="checkoutBtn">الدفع والمتابعة</button>
</div>

<script>
async function api(path, method = 'GET', body = null) {
    const opts = { method: method, headers: { 'Accept': 'application/json' } };
    if (body !== null) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch('/api' + path, opts);
    const json = await res.json().catch(() => null);
    return { ok: res.ok, status: res.status, json };
}

document.querySelectorAll('#cartBody .update').forEach(btn => {
    btn.addEventListener('click', async function () {
        const tr = this.closest('tr');
        const itemId = tr.getAttribute('data-item-id');
        const qty = parseInt(tr.querySelector('.qty').value, 10) || 1;
        const resp = await api('/cart/items/' + encodeURIComponent(itemId), 'POST', { quantity: qty });
        if (resp.ok) {
            alert('تم التحديث');
            location.reload();
        } else {
            alert('فشل التحديث: ' + (resp.json && resp.json.message ? resp.json.message : 'خطأ'));
        }
    });
});

async function removeItem(itemId) {
    if (!confirm('هل تريد حذف هذا المنتج من السلة؟')) return;
    const resp = await api('/cart/items/' + encodeURIComponent(itemId), 'DELETE');
    if (resp.ok) {
        alert('تم الحذف');
        location.reload();
    } else {
        alert('فشل الحذف');
    }
}

document.getElementById('checkoutBtn').addEventListener('click', function () {
    // Simple redirect to a checkout page (not implemented here).
    // In a real app you would call /api/orders to create an order or obtain checkout info.
    window.location.href = '/frontend/checkout.php';
});
</script>

</div>
</body>
</html>