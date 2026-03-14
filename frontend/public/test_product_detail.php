<?php
/**
 * test_product_api.php
 * 
 * أداة اختبار API المنتجات - تتحقق مما إذا كان الـ public API يعيد بيانات المنتج المطلوب.
 * استخدم ?id=رقم_المنتج  أو  ?slug=الرابط_المختصر
 * 
 * يعرض النتيجة بشكل مفصل (JSON خام + معلومات إضافية).
 */

// إظهار الأخطاء لتسهيل التصحيح
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. تحديد المعاملات
$productId   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$productSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!$productId && !$productSlug) {
    die("<p style='color:red;'>يرجى تحديد المنتج عبر ?id=رقم  أو  ?slug=الرابط</p>");
}

// 2. بناء عنوان API الصحيح (نفترض أن الموقع يعمل تحت https://hcsfcs.top)
$baseApi = 'https://hcsfcs.top/api/public/products';
$params = [];
if ($productId) {
    $params['id'] = $productId;
} else {
    $params['slug'] = $productSlug;
}
$params['lang'] = 'ar'; // يمكن تغيير اللغة
// أضف tenant_id إذا كان مطلوباً (عادة 1)
$params['tenant_id'] = 1;

$url = $baseApi . '?' . http_build_query($params);

echo "<h2>اختبار API المنتجات</h2>";
echo "<p><strong>الرابط المطلوب:</strong> <code>" . htmlspecialchars($url) . "</code></p>";

// 3. تنفيذ الطلب باستخدام cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false, // في بيئة الإنتاج يجب تفعيلها
]);

$response = curl_exec($ch);
$info     = curl_getinfo($ch);
$error    = curl_error($ch);
curl_close($ch);

// 4. عرض النتائج
if ($error) {
    echo "<p style='color:red;'><strong>خطأ cURL:</strong> $error</p>";
} else {
    echo "<p><strong>رمز الحالة:</strong> " . $info['http_code'] . "</p>";
    echo "<p><strong>الوقت المستغرق:</strong> " . $info['total_time'] . " ثانية</p>";
    
    // محاولة فك JSON
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p><strong style='color:green;'>✅ JSON صحيح.</strong></p>";
        
        // طباعة منسقة
        echo "<h3>البيانات المستلمة:</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        
        // التحقق من وجود المنتج
        if (isset($data['data']['product']) && !empty($data['data']['product'])) {
            $prod = $data['data']['product'];
            echo "<p style='color:green;'><strong>✅ تم العثور على المنتج:</strong> " . htmlspecialchars($prod['name'] ?? '') . "</p>";
        } elseif (isset($data['product']) && !empty($data['product'])) {
            // بعض الأحيان التنسيق يكون مختلفاً (حسب الـ API)
            $prod = $data['product'];
            echo "<p style='color:green;'><strong>✅ تم العثور على المنتج:</strong> " . htmlspecialchars($prod['name'] ?? '') . "</p>";
        } else {
            echo "<p style='color:red;'><strong>❌ المنتج غير موجود في الرد.</strong></p>";
        }
    } else {
        echo "<p style='color:red;'><strong>❌ الرد ليس JSON صحيح.</strong></p>";
        echo "<h3>الرد الخام:</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

// 5. معلومات إضافية للمساعدة
echo "<hr><h3>معلومات مساعدة</h3>";
echo "<ul>";
echo "<li>تأكد من أن قاعدة البيانات تحتوي على المنتج وأنه فعال (is_active = 1).</li>";
echo "<li>تأكد من أن tenant_id صحيح (افتراضياً 1).</li>";
echo "<li>إذا كان الـ API لا يستجيب، راجع سجلات الأخطاء في الخادم (error_log).</li>";
echo "<li>يمكنك أيضاً اختبار الاتصال بقاعدة البيانات عبر ملف آخر.</li>";
echo "</ul>";