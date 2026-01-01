<?php
// config/constants.php
// ملف الثوابت العامة للمشروع (مُصحّح)

// -----------------------------
// Helper: guard against re-definitions
// -----------------------------
function _def($name, $value) {
    if (!defined($name)) define($name, $value);
}

// ===========================================
// 1️⃣ حالات المستخدم (User Status)
// ===========================================
_def('USER_STATUS_PENDING', 'pending');           // في انتظار التحقق
_def('USER_STATUS_ACTIVE', 'active');             // نشط
_def('USER_STATUS_INACTIVE', 'inactive');         // غير نشط
_def('USER_STATUS_SUSPENDED', 'suspended');       // محظور مؤقتاً
_def('USER_STATUS_BANNED', 'banned');             // محظور نهائياً
_def('USER_STATUS_DELETED', 'deleted');           // محذوف

// ===========================================
// 2️⃣ أنواع المستخدمين (User Types)
// ===========================================
_def('USER_TYPE_CUSTOMER', 'customer');           // عميل
_def('USER_TYPE_VENDOR', 'vendor');               // تاجر
_def('USER_TYPE_ADMIN', 'admin');                 // مدير
_def('USER_TYPE_SUPER_ADMIN', 'super_admin');     // مدير أعلى
_def('USER_TYPE_SUPPORT', 'support');             // دعم فني
_def('USER_TYPE_MODERATOR', 'moderator');         // مشرف

// ===========================================
// 3️⃣ حالات التاجر (Vendor Status)
// ===========================================
_def('VENDOR_STATUS_PENDING', 'pending');         // في انتظار الموافقة
_def('VENDOR_STATUS_ACTIVE', 'active');           // نشط
_def('VENDOR_STATUS_SUSPENDED', 'suspended');     // معلق
_def('VENDOR_STATUS_REJECTED', 'rejected');       // مرفوض
_def('VENDOR_STATUS_INACTIVE', 'inactive');       // غير نشط

// ===========================================
// 4️⃣ أنواع التاجر (Vendor Types)
// ===========================================
_def('VENDOR_TYPE_PRODUCT_SELLER', 'product_seller');     // بائع منتجات
_def('VENDOR_TYPE_SERVICE_PROVIDER', 'service_provider'); // مقدم خدمات
_def('VENDOR_TYPE_BOTH', 'both');                         // كلاهما

// ===========================================
// 5️⃣ أنواع الأعمال (Business Types)
// ===========================================
_def('BUSINESS_TYPE_INDIVIDUAL', 'individual');   // فردي
_def('BUSINESS_TYPE_COMPANY', 'company');         // شركة

// ===========================================
// 6️⃣ حالات الطلب (Order Status)
// ===========================================
_def('ORDER_STATUS_PENDING', 'pending');               // قيد الانتظار
_def('ORDER_STATUS_CONFIRMED', 'confirmed');           // مؤكد
_def('ORDER_STATUS_PROCESSING', 'processing');         // قيد التجهيز
_def('ORDER_STATUS_PACKED', 'packed');                 // جاهز للشحن
_def('ORDER_STATUS_SHIPPED', 'shipped');               // تم الشحن
_def('ORDER_STATUS_OUT_FOR_DELIVERY', 'out_for_delivery'); // في طريق التوصيل
_def('ORDER_STATUS_DELIVERED', 'delivered');           // تم التسليم
_def('ORDER_STATUS_COMPLETED', 'completed');           // مكتمل
_def('ORDER_STATUS_CANCELLED', 'cancelled');           // ملغي
_def('ORDER_STATUS_REFUNDED', 'refunded');             // مسترد
_def('ORDER_STATUS_FAILED', 'failed');                 // فشل

// ===========================================
// 7️⃣ حالات الدفع (Payment Status)
// ===========================================
_def('PAYMENT_STATUS_PENDING', 'pending');         // في انتظار الدفع
_def('PAYMENT_STATUS_PROCESSING', 'processing');   // قيد المعالجة
_def('PAYMENT_STATUS_PAID', 'paid');               // مدفوع
_def('PAYMENT_STATUS_FAILED', 'failed');           // فشل
_def('PAYMENT_STATUS_REFUNDED', 'refunded');       // مسترد
_def('PAYMENT_STATUS_PARTIALLY_REFUNDED', 'partially_refunded'); // مسترد جزئياً
_def('PAYMENT_STATUS_CANCELLED', 'cancelled');     // ملغي

// ===========================================
// 8️⃣ طرق الدفع (Payment Methods)
// ===========================================
_def('PAYMENT_METHOD_CREDIT_CARD', 'credit_card');         // بطاقة ائتمان
_def('PAYMENT_METHOD_MADA', 'mada');                       // مدى
_def('PAYMENT_METHOD_APPLE_PAY', 'apple_pay');             // أبل باي
_def('PAYMENT_METHOD_STC_PAY', 'stcpay');                  // STC Pay
_def('PAYMENT_METHOD_CASH_ON_DELIVERY', 'cash_on_delivery'); // الدفع عند الاستلام
_def('PAYMENT_METHOD_BANK_TRANSFER', 'bank_transfer');     // تحويل بنكي
_def('PAYMENT_METHOD_WALLET', 'wallet');                   // المحفظة

// ===========================================
// 9️⃣ حالات الشحن (Shipment Status)
// ===========================================
_def('SHIPMENT_STATUS_PENDING', 'pending');               // في انتظار الشحن
_def('SHIPMENT_STATUS_PICKED_UP', 'picked_up');           // تم الاستلام
_def('SHIPMENT_STATUS_IN_TRANSIT', 'in_transit');         // في الطريق
_def('SHIPMENT_STATUS_OUT_FOR_DELIVERY', 'out_for_delivery'); // في طريق التوصيل
_def('SHIPMENT_STATUS_DELIVERED', 'delivered');           // تم التسليم
_def('SHIPMENT_STATUS_FAILED', 'failed');                 // فشل التوصيل
_def('SHIPMENT_STATUS_RETURNED', 'returned');             // مرتجع

// ===========================================
// 🔟 أنواع المنتجات (Product Types)
// ===========================================
_def('PRODUCT_TYPE_SIMPLE', 'simple');             // منتج بسيط
_def('PRODUCT_TYPE_VARIABLE', 'variable');         // منتج متغير (مقاسات، ألوان)
_def('PRODUCT_TYPE_DIGITAL', 'digital');           // منتج رقمي
_def('PRODUCT_TYPE_BUNDLE', 'bundle');             // حزمة منتجات

// ===========================================
// 1️⃣1️⃣ حالات المخزون (Stock Status)
// ===========================================
_def('STOCK_STATUS_IN_STOCK', 'in_stock');         // متوفر
_def('STOCK_STATUS_OUT_OF_STOCK', 'out_of_stock'); // غير متوفر
_def('STOCK_STATUS_ON_BACKORDER', 'on_backorder'); // طلب مسبق

// ===========================================
// 1️⃣2️⃣ أنواع الخصم (Discount Types)
// ===========================================
_def('DISCOUNT_TYPE_PERCENTAGE', 'percentage');    // نسبة مئوية
_def('DISCOUNT_TYPE_FIXED', 'fixed');              // مبلغ ثابت

// ===========================================
// 1️⃣3️⃣ حالات الكوبون (Coupon Status)
// ===========================================
_def('COUPON_STATUS_ACTIVE', 'active');            // نشط
_def('COUPON_STATUS_INACTIVE', 'inactive');        // غير نشط
_def('COUPON_STATUS_EXPIRED', 'expired');          // منتهي
_def('COUPON_STATUS_USED_UP', 'used_up');          // استُخدم كاملاً

// ===========================================
// 1️⃣4️⃣ حالات المرتجعات (Return Status)
// ===========================================
_def('RETURN_STATUS_PENDING', 'pending');          // في انتظار المراجعة
_def('RETURN_STATUS_APPROVED', 'approved');        // موافق عليه
_def('RETURN_STATUS_REJECTED', 'rejected');        // مرفوض
_def('RETURN_STATUS_RECEIVED', 'received');        // تم استلام المرتجع
_def('RETURN_STATUS_COMPLETED', 'completed');      // مكتمل
_def('RETURN_STATUS_CANCELLED', 'cancelled');      // ملغي

// ===========================================
// 1️⃣5️⃣ أسباب الإرجاع (Return Reasons)
// ===========================================
_def('RETURN_REASON_DEFECTIVE', 'defective');              // معيب
_def('RETURN_REASON_WRONG_ITEM', 'wrong_item');            // منتج خاطئ
_def('RETURN_REASON_NOT_AS_DESCRIBED', 'not_as_described'); // غير مطابق للوصف
_def('RETURN_REASON_DAMAGED', 'damaged');                  // تالف
_def('RETURN_REASON_CHANGED_MIND', 'changed_mind');        // غير رأيه
_def('RETURN_REASON_SIZE_ISSUE', 'size_issue');            // مشكلة في المقاس
_def('RETURN_REASON_QUALITY_ISSUE', 'quality_issue');      // مشكلة في الجودة
_def('RETURN_REASON_OTHER', 'other');                      // سبب آخر

// ===========================================
// 1️⃣6️⃣ طرق الاسترداد (Refund Methods)
// ===========================================
_def('REFUND_METHOD_ORIGINAL_PAYMENT', 'original_payment'); // نفس طريقة الدفع
_def('REFUND_METHOD_WALLET', 'wallet');                     // المحفظة
_def('REFUND_METHOD_BANK_TRANSFER', 'bank_transfer');       // تحويل بنكي

// ===========================================
// 1️⃣7️⃣ أنواع العناوين (Address Types)
// ===========================================
_def('ADDRESS_TYPE_SHIPPING', 'shipping');         // عنوان الشحن
_def('ADDRESS_TYPE_BILLING', 'billing');           // عنوان الفواتير
_def('ADDRESS_TYPE_BOTH', 'both');                 // كلاهما

// ===========================================
// 1️⃣8️⃣ أنواع الإشعارات (Notification Types)
// ===========================================
_def('NOTIFICATION_TYPE_ORDER', 'order');                  // طلب
_def('NOTIFICATION_TYPE_PAYMENT', 'payment');              // دفع
_def('NOTIFICATION_TYPE_SHIPMENT', 'shipment');            // شحن
_def('NOTIFICATION_TYPE_RETURN', 'return');                // إرجاع
_def('NOTIFICATION_TYPE_REVIEW', 'review');                // تقييم
_def('NOTIFICATION_TYPE_PROMOTION', 'promotion');          // عرض ترويجي
_def('NOTIFICATION_TYPE_SYSTEM', 'system');                // نظام
_def('NOTIFICATION_TYPE_ACCOUNT', 'account');              // حساب
_def('NOTIFICATION_TYPE_SUPPORT', 'support');              // دعم فني

// ===========================================
// 1️⃣9️⃣ حالات التذكرة (Ticket Status)
// ===========================================
_def('TICKET_STATUS_OPEN', 'open');                // مفتوحة
_def('TICKET_STATUS_IN_PROGRESS', 'in_progress');  // قيد المعالجة
_def('TICKET_STATUS_WAITING', 'waiting');          // في انتظار الرد
_def('TICKET_STATUS_RESOLVED', 'resolved');        // محلولة
_def('TICKET_STATUS_CLOSED', 'closed');            // مغلقة
_def('TICKET_STATUS_REOPENED', 'reopened');        // أُعيد فتحها

// ===========================================
// 2️⃣0️⃣ أولويات التذكرة (Ticket Priority)
// ===========================================
_def('TICKET_PRIORITY_LOW', 'low');                // منخفضة
_def('TICKET_PRIORITY_NORMAL', 'normal');          // عادية
_def('TICKET_PRIORITY_HIGH', 'high');              // عالية
_def('TICKET_PRIORITY_URGENT', 'urgent');          // عاجلة

// ===========================================
// 2️⃣1️⃣ أنواع الخدمات (Service Types)
// ===========================================
_def('SERVICE_TYPE_ONE_TIME', 'one_time');         // لمرة واحدة
_def('SERVICE_TYPE_RECURRING', 'recurring');       // متكررة
_def('SERVICE_TYPE_SUBSCRIPTION', 'subscription'); // اشتراك
_def('SERVICE_TYPE_EMERGENCY', 'emergency');       // طوارئ

// ===========================================
// 2️⃣2️⃣ أنواع التسعير (Pricing Types)
// ===========================================
_def('PRICING_TYPE_FIXED', 'fixed');               // سعر ثابت
_def('PRICING_TYPE_HOURLY', 'hourly');             // بالساعة
_def('PRICING_TYPE_QUOTE_BASED', 'quote_based');   // حسب العرض

// ===========================================
// 2️⃣3️⃣ حالات حجز الخدمة (Service Booking Status)
// ===========================================
_def('BOOKING_STATUS_PENDING', 'pending');             // في الانتظار
_def('BOOKING_STATUS_CONFIRMED', 'confirmed');         // مؤكد
_def('BOOKING_STATUS_IN_PROGRESS', 'in_progress');     // قيد التنفيذ
_def('BOOKING_STATUS_COMPLETED', 'completed');         // مكتمل
_def('BOOKING_STATUS_CANCELLED', 'cancelled');         // ملغي
_def('BOOKING_STATUS_NO_SHOW', 'no_show');             // لم يحضر
_def('BOOKING_STATUS_REFUNDED', 'refunded');           // مسترد

// ===========================================
// 2️⃣4️⃣ أنواع حجز الخدمة (Booking Types)
// ===========================================
_def('BOOKING_TYPE_INSTANT', 'instant');           // فوري
_def('BOOKING_TYPE_SCHEDULED', 'scheduled');       // مجدول
_def('BOOKING_TYPE_EMERGENCY', 'emergency');       // طوارئ

// ===========================================
// 2️⃣5️⃣ أنواع معاملات المحفظة (Wallet Transaction Types)
// ===========================================
_def('WALLET_TRANSACTION_CREDIT', 'credit');       // إيداع
_def('WALLET_TRANSACTION_DEBIT', 'debit');         // سحب
_def('WALLET_TRANSACTION_REFUND', 'refund');       // استرداد
_def('WALLET_TRANSACTION_BONUS', 'bonus');         // مكافأة
_def('WALLET_TRANSACTION_COMMISSION', 'commission'); // عمولة

// ===========================================
// 2️⃣6️⃣ أنواع المستندات (Document Types)
// ===========================================
_def('DOCUMENT_TYPE_COMMERCIAL_REGISTER', 'commercial_register'); // سجل تجاري
_def('DOCUMENT_TYPE_LICENSE', 'license');                         // رخصة
_def('DOCUMENT_TYPE_ID_CARD', 'id_card');                         // بطاقة هوية
_def('DOCUMENT_TYPE_TAX_CERTIFICATE', 'tax_certificate');         // شهادة ضريبية
_def('DOCUMENT_TYPE_BANK_ACCOUNT', 'bank_account');               // حساب بنكي
_def('DOCUMENT_TYPE_OTHER', 'other');                             // أخرى

// ===========================================
// 2️⃣7️⃣ حالات المستند (Document Status)
// ===========================================
_def('DOCUMENT_STATUS_PENDING', 'pending');        // في انتظار المراجعة
_def('DOCUMENT_STATUS_APPROVED', 'approved');      // موافق عليه
_def('DOCUMENT_STATUS_REJECTED', 'rejected');      // مرفوض
_def('DOCUMENT_STATUS_EXPIRED', 'expired');        // منتهي

// ===========================================
// 2️⃣8️⃣ مواضع البنر (Banner Positions)
// ===========================================
_def('BANNER_POSITION_HOMEPAGE_MAIN', 'homepage_main');           // الصفحة الرئيسية - رئيسي
_def('BANNER_POSITION_HOMEPAGE_SECONDARY', 'homepage_secondary'); // الصفحة الرئيسية - ثانوي
_def('BANNER_POSITION_CATEGORY', 'category');                     // صفحة التصنيف
_def('BANNER_POSITION_PRODUCT', 'product');                       // صفحة المنتج
_def('BANNER_POSITION_CART', 'cart');                             // صفحة السلة
_def('BANNER_POSITION_CHECKOUT', 'checkout');                     // صفحة الدفع
_def('BANNER_POSITION_SIDEBAR', 'sidebar');                       // الشريط الجانبي

// ===========================================
// 2️⃣9️⃣ أيام الأسبوع (Days of Week)
// ===========================================
_def('DAY_SUNDAY', 0);
_def('DAY_MONDAY', 1);
_def('DAY_TUESDAY', 2);
_def('DAY_WEDNESDAY', 3);
_def('DAY_THURSDAY', 4);
_def('DAY_FRIDAY', 5);
_def('DAY_SATURDAY', 6);

// ===========================================
// 3️⃣0️⃣ أنواع الضرائب (Tax Types)
// ===========================================
_def('TAX_TYPE_VAT', 'vat');                       // ضريبة القيمة المضافة
_def('TAX_TYPE_GST', 'gst');                       // ضريبة السلع والخدمات
_def('TAX_TYPE_SALES_TAX', 'sales_tax');           // ضريبة المبيعات
_def('TAX_TYPE_CUSTOMS', 'customs');               // ضريبة جمركية
_def('TAX_TYPE_EXCISE', 'excise');                 // ضريبة انتقائية

// ===========================================
// 3️⃣1️⃣ رموز الخطأ (Error Codes)
// ===========================================
_def('ERROR_CODE_VALIDATION', 1001);               // خطأ في التحقق من البيانات
_def('ERROR_CODE_AUTHENTICATION', 1002);           // خطأ في المصادقة
_def('ERROR_CODE_AUTHORIZATION', 1003);            // خطأ في الصلاحيات
_def('ERROR_CODE_NOT_FOUND', 1004);                // غير موجود
_def('ERROR_CODE_DATABASE', 1005);                 // خطأ في قاعدة البيانات
_def('ERROR_CODE_SERVER', 1006);                   // خطأ في السيرفر
_def('ERROR_CODE_PAYMENT', 1007);                  // خطأ في الدفع
_def('ERROR_CODE_INSUFFICIENT_STOCK', 1008);       // مخزون غير كافٍ
_def('ERROR_CODE_INVALID_COUPON', 1009);           // كوبون غير صالح
_def('ERROR_CODE_FILE_UPLOAD', 1010);              // خطأ في رفع الملف

// ===========================================
// 3️⃣2️⃣ رموز HTTP (HTTP Status Codes)
// ===========================================
_def('HTTP_OK', 200);
_def('HTTP_CREATED', 201);
_def('HTTP_NO_CONTENT', 204);
_def('HTTP_BAD_REQUEST', 400);
_def('HTTP_UNAUTHORIZED', 401);
_def('HTTP_FORBIDDEN', 403);
_def('HTTP_NOT_FOUND', 404);
_def('HTTP_METHOD_NOT_ALLOWED', 405);
_def('HTTP_CONFLICT', 409);
_def('HTTP_UNPROCESSABLE_ENTITY', 422);
_def('HTTP_TOO_MANY_REQUESTS', 429);
_def('HTTP_INTERNAL_SERVER_ERROR', 500);
_def('HTTP_SERVICE_UNAVAILABLE', 503);

// ===========================================
// 3️⃣3️⃣ رسائل النجاح (Success Messages)
// ===========================================
_def('MSG_SUCCESS_CREATED', 'تم الإنشاء بنجاح');
_def('MSG_SUCCESS_UPDATED', 'تم التحديث بنجاح');
_def('MSG_SUCCESS_DELETED', 'تم الحذف بنجاح');
_def('MSG_SUCCESS_LOGIN', 'تم تسجيل الدخول بنجاح');
_def('MSG_SUCCESS_LOGOUT', 'تم تسجيل الخروج بنجاح');
_def('MSG_SUCCESS_REGISTERED', 'تم التسجيل بنجاح');
_def('MSG_SUCCESS_VERIFIED', 'تم التحقق بنجاح');

// ===========================================
// 3️⃣4️⃣ رسائل الخطأ (Error Messages)
// ===========================================
_def('MSG_ERROR_INVALID_CREDENTIALS', 'بيانات الدخول غير صحيحة');
_def('MSG_ERROR_UNAUTHORIZED', 'غير مصرح لك بالوصول');
_def('MSG_ERROR_NOT_FOUND', 'العنصر غير موجود');
_def('MSG_ERROR_SERVER', 'حدث خطأ في السيرفر');
_def('MSG_ERROR_VALIDATION', 'خطأ في البيانات المدخلة');
_def('MSG_ERROR_DATABASE', 'خطأ في قاعدة البيانات');
_def('MSG_ERROR_EMAIL_EXISTS', 'البريد الإلكتروني مستخدم مسبقاً');
_def('MSG_ERROR_PHONE_EXISTS', 'رقم الجوال مستخدم مسبقاً');

// ===========================================
// 3️⃣5️⃣ Regex Patterns (مصوّبة)
// ===========================================
_def('REGEX_EMAIL', '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/');
_def('REGEX_PHONE_INTERNATIONAL', '/^\+?[1-9]\d{1,14}$/'); // E.164
_def('REGEX_PASSWORD_STRONG', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/');
_def('REGEX_SLUG', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
_def('REGEX_USERNAME', '/^[a-zA-Z0-9_-]{3,20}$/');
_def('REGEX_POSTAL_CODE', '/^[0-9]{5}$/');

// ===========================================
// ✅ تم تحميل الثوابت بنجاح
// ===========================================