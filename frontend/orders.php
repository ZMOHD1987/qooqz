<?php
// htdocs/frontend/orders.php
// Frontend page: list current user's orders and view order details.
// Uses the API at /api/orders to fetch orders. If not authenticated, prompts to login.
// Minimal UI in Arabic, lightweight JS for viewing details.

date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// Basic security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function api_request(string $method, string $path, $payload = null)
{
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // propagate Authorization header from client if present
    $headers = ['Accept: application/json'];
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
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

    if ($resp === false) {
        return ['success' => false, 'status' => $status, 'error' => $err];
    }
    $decoded = json_decode($resp, true);
    if ($decoded === null) {
        return ['success' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $resp];
    }
    return array_merge(['success' => $status >= 200 && $status < 300, 'status' => $status], $decoded);
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Try to fetch user's orders (API should use Authorization header/cookies to determine user)
$res = api_request('GET', '/orders');

?>
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <title>طلباتي</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; direction: rtl; margin: 20px; }
        .container { max-width: 980px; margin: auto; }
        .order { border:1px solid #eee; padding:12px; margin-bottom:12px; }
        .order h3 { margin:0 0 8px 0; }
        .muted { color:#666; }
        .btn { padding:8px 12px; background:#2d8cf0; color:#fff; border-radius:4px; text-decoration:none; display:inline-block; margin-left:8px; }
        .details { margin-top:8px; border-top:1px dashed #ddd; padding-top:8px; display:none; }
        table { width:100%; border-collapse: collapse; margin-top:8px; }
        th,td { padding:6px; border:1px solid #f1f1f1; text-align:right; }
    </style>
</head>
<body>
<div class="container">
    <h1>طلباتي</h1>
    <p><a class="btn" href="/frontend/products.php">العودة إلى المتجر</a></p>

<?php
if (!$res['success']) {
    // If API returned 401 or similar, prompt to login
    if (isset($res['status']) && ($res['status'] === 401 || $res['status'] === 403)) {
        echo '<p>يرجى تسجيل الدخول لعرض طلباتك. <a href="/frontend/login.php">تسجيل الدخول</a></p>';
    } else {
        echo '<p>فشل جلب الطلبات من الخادم. الرجاء المحاولة لاحقاً.</p>';
        if (!empty($res['message'])) echo '<pre>' . e($res['message']) . '</pre>';
    }
    exit;
}

// expected shape: { data: [orders], orders: [...], orders: {data: [...]}, or direct array }
$orders = [];
if (isset($res['orders']) && is_array($res['orders'])) {
    $orders = $res['orders'];
} elseif (isset($res['data']) && is_array($res['data']) && isset($res['data'][0])) {
    $orders = $res['data'];
} elseif (is_array($res) && isset($res[0])) {
    $orders = $res;
} elseif (isset($res['order']) && is_array($res['order'])) {
    $orders = [$res['order']];
} else {
    $orders = $res['data'] ?? [];
}

if (empty($orders)) {
    echo '<p>لا توجد طلبات لعرضها.</p>';
    exit;
}

foreach ($orders as $order) {
    $orderNumber = $order['order_number'] ?? $order['id'] ?? '—';
    $created = isset($order['created_at']) ? date('Y-m-d', strtotime($order['created_at'])) : ($order['created'] ?? '');
    $status = $order['order_status'] ?? $order['status'] ?? '—';
    $payment = $order['payment_status'] ?? '—';
    $grand = $order['grand_total'] ?? $order['total'] ?? '0.00';
    ?>
    <div class="order" data-order-id="<?php echo e($order['id'] ?? ''); ?>">
        <h3>طلب <strong><?php echo e($orderNumber); ?></strong> — <span class="muted"><?php echo e($created); ?></span></h3>
        <div>
            الحالة: <strong><?php echo e($status); ?></strong>
            الدفع: <strong><?php echo e($payment); ?></strong>
            المجموع: <strong><?php echo e($grand); ?> <?php echo e($order['currency'] ?? ''); ?></strong>
            <a href="#" class="btn viewBtn">عرض التفاصيل</a>
            <a class="btn" href="/frontend/order.php?id=<?php echo urlencode($order['id'] ?? $orderNumber); ?>">صفحة الطلب</a>
        </div>

        <div class="details" aria-hidden="true">
            <?php
            $items = $order['items'] ?? [];
            if (!empty($items)) {
                echo '<table><thead><tr><th>المنتج</th><th>سعر الوحدة</th><th>الكمية</th><th>المجموع</th></tr></thead><tbody>';
                foreach ($items as $it) {
                    $name = $it['name'] ?? $it['title'] ?? 'منتج';
                    $price = $it['price'] ?? '0.00';
                    $qty = $it['quantity'] ?? 1;
                    $total = $it['total'] ?? ($price * $qty);
                    echo '<tr><td>' . e($name) . '</td><td>' . e($price) . '</td><td>' . e($qty) . '</td><td>' . e($total) . '</td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p class="muted">لا توجد عناصر مرئية لهذا الطلب.</p>';
            }

            if (!empty($order['shipping_address'])) {
                $sa = $order['shipping_address'];
                echo '<p class="muted">عنوان الشحن: ' . e(is_array($sa) ? (implode(' / ', array_filter([$sa['address'] ?? '', $sa['city'] ?? '', $sa['country'] ?? ''])) ) : e($sa)) . '</p>';
            }
            if (!empty($order['notes'])) {
                echo '<p>ملاحظات: ' . e($order['notes']) . '</p>';
            }
            ?>
        </div>
    </div>
    <?php
}
?>

</div>

<script>
document.querySelectorAll('.viewBtn').forEach(btn => {
    btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        const container = this.closest('.order');
        const details = container.querySelector('.details');
        const shown = details.style.display === 'block';
        details.style.display = shown ? 'none' : 'block';
        details.setAttribute('aria-hidden', shown ? 'true' : 'false');
        this.textContent = shown ? 'عرض التفاصيل' : 'إخفاء التفاصيل';
    });
});
</script>
</body>
</html>