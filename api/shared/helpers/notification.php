<?php
// htdocs/api/helpers/notification.php
// ملف دوال الإشعارات (Notification Helper)
// يدعم Email, SMS, Push Notifications, Database
// تم التعديل لدعم PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/sms.php';

// ===========================================
// Notification Class
// ===========================================

class Notification {
    
    private static ?PDO $pdo = null;
    
    /**
     * تعيين PDO instance
     * 
     * @param PDO $pdo
     */
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    // ===========================================
    // 1️⃣ إرسال إشعار (Send Notification)
    // ===========================================
    
    /**
     * إرسال إشعار متعدد القنوات
     * 
     * @param int $userId معرف المستخدم
     * @param string $type نوع الإشعار
     * @param string $title العنوان
     * @param string $message الرسالة
     * @param array $data بيانات إضافية
     * @param array $channels القنوات ['email', 'sms', 'push', 'database']
     * @return array
     */
    public static function send($userId, $type, $title, $message, $data = [], $channels = ['database']) {
        if (!self::$pdo) {
            return [
                'success' => false,
                'message' => 'PDO not set'
            ];
        }
        
        $results = [
            'user_id' => $userId,
            'type' => $type,
            'channels' => []
        ];
        
        try {
            // جلب بيانات المستخدم
            $user = self::getUserData($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // جلب إعدادات الإشعارات للمستخدم
            $settings = self::getUserNotificationSettings($userId, $type);
            
            // إرسال حسب القنوات المطلوبة
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'database':
                        $results['channels']['database'] = self::saveToDatabase(
                            $userId,
                            $type,
                            $title,
                            $message,
                            $data
                        );
                        break;
                        
                    case 'email': 
                        if ($settings['email_enabled']) {
                            $results['channels']['email'] = self::sendEmail(
                                $user['email'],
                                $user['username'],
                                $title,
                                $message,
                                $type
                            );
                        } else {
                            $results['channels']['email'] = [
                                'success' => false,
                                'message' => 'Email notifications disabled by user'
                            ];
                        }
                        break;
                        
                    case 'sms':
                        if ($settings['sms_enabled'] && ! empty($user['phone'])) {
                            $results['channels']['sms'] = self::sendSMS(
                                $user['phone'],
                                $message
                            );
                        } else {
                            $results['channels']['sms'] = [
                                'success' => false,
                                'message' => 'SMS notifications disabled or no phone'
                            ];
                        }
                        break;
                        
                    case 'push': 
                        if ($settings['push_enabled']) {
                            $results['channels']['push'] = self::sendPushNotification(
                                $userId,
                                $title,
                                $message,
                                $data
                            );
                        } else {
                            $results['channels']['push'] = [
                                'success' => false,
                                'message' => 'Push notifications disabled by user'
                            ];
                        }
                        break;
                }
            }
            
            $results['success'] = true;
            
        } catch (Exception $e) {
            self::logError('Notification send failed: ' . $e->getMessage());
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    // ===========================================
    // 2️⃣ حفظ الإشعار في قاعدة البيانات
    // ===========================================
    
    /**
     * حفظ الإشعار في جدول notifications
     * 
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @return array
     */
    private static function saveToDatabase($userId, $type, $title, $message, $data = []) {
        if (!self::$pdo) return ['success' => false, 'message' => 'PDO not set'];
        
        $dataJson = ! empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
        
        try {
            $stmt = self::$pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, data, is_read, created_at) 
                    VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$userId, $type, $title, $message, $dataJson]);
            $notificationId = self::$pdo->lastInsertId();
            
            self::logNotification('database', $userId, $type, 'saved');
            
            return [
                'success' => true,
                'notification_id' => $notificationId
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => $e->errorInfo()[2]
            ];
        }
    }
    
    // ===========================================
    // 3️⃣ إرسال بريد إلكتروني
    // ===========================================
    
    /**
     * إرسال إشعار عبر البريد الإلكتروني
     * 
     * @param string $email
     * @param string $name
     * @param string $title
     * @param string $message
     * @param string $type
     * @return array
     */
    private static function sendEmail($email, $name, $title, $message, $type) {
        $sent = Mail::send($email, $title, $message);
        
        self::logNotification('email', $email, $type, $sent ? 'sent' : 'failed');
        
        return [
            'success' => $sent,
            'message' => $sent ? 'Email sent' : 'Email failed'
        ];
    }
    
    // ===========================================
    // 4️⃣ إرسال رسالة نصية
    // ===========================================
    
    /**
     * إرسال إشعار عبر SMS
     * 
     * @param string $phone
     * @param string $message
     * @return array
     */
    private static function sendSMS($phone, $message) {
        $result = SMS::send($phone, $message);
        
        self:: logNotification('sms', $phone, 'sms', $result['success'] ? 'sent' :  'failed');
        
        return $result;
    }
    
    // ===========================================
    // 5️⃣ إرسال Push Notification
    // ===========================================
    
    /**
     * إرسال Push Notification (Firebase FCM)
     * 
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param array $data
     * @return array
     */
    private static function sendPushNotification($userId, $title, $message, $data = []) {
        // TODO: تنفيذ Firebase Cloud Messaging
        // يحتاج إلى: 
        // 1. Firebase Server Key
        // 2. Device tokens من جدول user_devices
        
        self::logNotification('push', $userId, 'push', 'not_implemented');
        
        return [
            'success' => false,
            'message' => 'Push notifications not implemented yet'
        ];
    }
    
    // ===========================================
    // 6️⃣ إشعارات خاصة بالطلبات
    // ===========================================
    
    /**
     * إشعار تأكيد طلب جديد
     * 
     * @param int $userId
     * @param array $order
     * @return array
     */
    public static function orderCreated($userId, $order) {
        $title = 'تأكيد الطلب - Order Confirmation';
        $message = "تم استلام طلبك #{$order['order_number']} بنجاح.  المبلغ: {$order['grand_total']} " .  DEFAULT_CURRENCY_SYMBOL;
        
        $data = [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'total' => $order['grand_total']
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_ORDER,
            $title,
            $message,
            $data,
            ['database', 'email', 'sms']
        );
    }
    
    /**
     * إشعار تغيير حالة الطلب
     * 
     * @param int $userId
     * @param string $orderNumber
     * @param string $status
     * @return array
     */
    public static function orderStatusChanged($userId, $orderNumber, $status) {
        $statusTexts = [
            ORDER_STATUS_CONFIRMED => 'تم تأكيد طلبك - Order Confirmed',
            ORDER_STATUS_PROCESSING => 'جاري تجهيز طلبك - Order Processing',
            ORDER_STATUS_SHIPPED => 'تم شحن طلبك - Order Shipped',
            ORDER_STATUS_DELIVERED => 'تم توصيل طلبك - Order Delivered',
            ORDER_STATUS_CANCELLED => 'تم إلغاء طلبك - Order Cancelled'
        ];
        
        $title = $statusTexts[$status] ?? 'تحديث الطلب - Order Update';
        $message = "طلبك #{$orderNumber}:  {$title}";
        
        $data = [
            'order_number' => $orderNumber,
            'status' => $status
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_ORDER,
            $title,
            $message,
            $data,
            ['database', 'sms']
        );
    }
    
    /**
     * إشعار شحن الطلب
     * 
     * @param int $userId
     * @param string $orderNumber
     * @param string $trackingNumber
     * @return array
     */
    public static function orderShipped($userId, $orderNumber, $trackingNumber) {
        $title = 'تم شحن طلبك - Order Shipped';
        $message = "طلبك #{$orderNumber} في الطريق إليك. رقم التتبع: {$trackingNumber}";
        
        $data = [
            'order_number' => $orderNumber,
            'tracking_number' => $trackingNumber
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_SHIPMENT,
            $title,
            $message,
            $data,
            ['database', 'email', 'sms']
        );
    }
    
    /**
     * إشعار توصيل الطلب
     * 
     * @param int $userId
     * @param string $orderNumber
     * @return array
     */
    public static function orderDelivered($userId, $orderNumber) {
        $title = 'تم توصيل طلبك - Order Delivered';
        $message = "تم توصيل طلبك #{$orderNumber} بنجاح. نتمنى أن تكون راضياً عن خدمتنا! ";
        
        $data = [
            'order_number' => $orderNumber
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_SHIPMENT,
            $title,
            $message,
            $data,
            ['database', 'sms']
        );
    }
    
    // ===========================================
    // 7️⃣ إشعارات الدفع
    // ===========================================
    
    /**
     * إشعار دفع ناجح
     * 
     * @param int $userId
     * @param string $orderNumber
     * @param float $amount
     * @return array
     */
    public static function paymentSuccess($userId, $orderNumber, $amount) {
        $title = 'دفع ناجح - Payment Success';
        $message = "تم استلام دفعتك بنجاح. المبلغ: {$amount} " . DEFAULT_CURRENCY_SYMBOL .  " للطلب #{$orderNumber}";
        
        $data = [
            'order_number' => $orderNumber,
            'amount' => $amount
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_PAYMENT,
            $title,
            $message,
            $data,
            ['database', 'email']
        );
    }
    
    /**
     * إشعار فشل الدفع
     * 
     * @param int $userId
     * @param string $orderNumber
     * @param string $reason
     * @return array
     */
    public static function paymentFailed($userId, $orderNumber, $reason) {
        $title = 'فشل الدفع - Payment Failed';
        $message = "فشلت عملية الدفع للطلب #{$orderNumber}. السبب: {$reason}";
        
        $data = [
            'order_number' => $orderNumber,
            'reason' => $reason
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_PAYMENT,
            $title,
            $message,
            $data,
            ['database', 'email', 'sms']
        );
    }
    
    // ===========================================
    // 8️⃣ إشعارات المرتجعات
    // ===========================================
    
    /**
     * إشعار طلب إرجاع جديد
     * 
     * @param int $userId
     * @param string $returnNumber
     * @return array
     */
    public static function returnRequested($userId, $returnNumber) {
        $title = 'طلب إرجاع - Return Request';
        $message = "تم استلام طلب الإرجاع #{$returnNumber}. سيتم مراجعته خلال 24 ساعة.";
        
        $data = [
            'return_number' => $returnNumber
        ];
        
        return self:: send(
            $userId,
            NOTIFICATION_TYPE_RETURN,
            $title,
            $message,
            $data,
            ['database', 'email']
        );
    }
    
    /**
     * إشعار موافقة على الإرجاع
     * 
     * @param int $userId
     * @param string $returnNumber
     * @return array
     */
    public static function returnApproved($userId, $returnNumber) {
        $title = 'تمت الموافقة على الإرجاع - Return Approved';
        $message = "تمت الموافقة على طلب الإرجاع #{$returnNumber}. يرجى إرسال المنتج خلال 7 أيام.";
        
        $data = [
            'return_number' => $returnNumber
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_RETURN,
            $title,
            $message,
            $data,
            ['database', 'email', 'sms']
        );
    }
    
    // ===========================================
    // 9️⃣ إشعارات التقييم
    // ===========================================
    
    /**
     * تذكير بتقييم المنتج
     * 
     * @param int $userId
     * @param string $productName
     * @param int $productId
     * @return array
     */
    public static function reviewReminder($userId, $productName, $productId) {
        $title = 'قيّم منتجك - Rate Your Product';
        $message = "ما رأيك في {$productName}؟ شارك تجربتك مع الآخرين! ";
        
        $data = [
            'product_id' => $productId,
            'product_name' => $productName
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_REVIEW,
            $title,
            $message,
            $data,
            ['database']
        );
    }
    
    // ===========================================
    // 🔟 إشعارات العروض والتسويق
    // ===========================================
    
    /**
     * إشعار عرض خاص
     * 
     * @param int $userId
     * @param string $offerTitle
     * @param string $offerDescription
     * @return array
     */
    public static function specialOffer($userId, $offerTitle, $offerDescription) {
        $title = 'عرض خاص - Special Offer';
        $message = "{$offerTitle}:  {$offerDescription}";
        
        $data = [
            'offer_title' => $offerTitle
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_PROMOTION,
            $title,
            $message,
            $data,
            ['database', 'email']
        );
    }
    
    /**
     * إشعار سلة مهجورة
     * 
     * @param int $userId
     * @param int $itemsCount
     * @return array
     */
    public static function abandonedCart($userId, $itemsCount) {
        $title = 'أكمل طلبك - Complete Your Order';
        $message = "لديك {$itemsCount} منتج في سلة التسوق.  أكمل طلبك الآن واحصل على خصم 10%!";
        
        $data = [
            'items_count' => $itemsCount
        ];
        
        return self:: send(
            $userId,
            NOTIFICATION_TYPE_PROMOTION,
            $title,
            $message,
            $data,
            ['database', 'email', 'sms']
        );
    }
    
    // ===========================================
    // 1️⃣1️⃣ إشعارات الحساب
    // ===========================================
    
    /**
     * إشعار تسجيل دخول من جهاز جديد
     * 
     * @param int $userId
     * @param string $device
     * @param string $location
     * @return array
     */
    public static function newDeviceLogin($userId, $device, $location) {
        $title = 'تسجيل دخول جديد - New Login';
        $message = "تم تسجيل دخول إلى حسابك من جهاز جديد:  {$device} في {$location}";
        
        $data = [
            'device' => $device,
            'location' => $location
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_ACCOUNT,
            $title,
            $message,
            $data,
            ['database', 'email']
        );
    }
    
    /**
     * إشعار تغيير كلمة المرور
     * 
     * @param int $userId
     * @return array
     */
    public static function passwordChanged($userId) {
        $title = 'تم تغيير كلمة المرور - Password Changed';
        $message = "تم تغيير كلمة المرور لحسابك بنجاح. إذا لم تقم بذلك، يرجى التواصل معنا فوراً.";
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_ACCOUNT,
            $title,
            $message,
            [],
            ['database', 'email', 'sms']
        );
    }
    
    // ===========================================
    // 1️⃣2️⃣ إشعارات الدعم الفني
    // ===========================================
    
    /**
     * إشعار رد على تذكرة دعم
     * 
     * @param int $userId
     * @param string $ticketNumber
     * @return array
     */
    public static function supportTicketReply($userId, $ticketNumber) {
        $title = 'رد على تذكرتك - Ticket Reply';
        $message = "تم الرد على تذكرة الدعم #{$ticketNumber}. تحقق من الردود الجديدة. ";
        
        $data = [
            'ticket_number' => $ticketNumber
        ];
        
        return self::send(
            $userId,
            NOTIFICATION_TYPE_SUPPORT,
            $title,
            $message,
            $data,
            ['database', 'email']
        );
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * جلب بيانات المستخدم
     * 
     * @param int $userId
     * @return array|null
     */
    private static function getUserData($userId) {
        if (!self::$pdo) return null;
        
        try {
            $stmt = self::$pdo->prepare("SELECT id, username, email, phone FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * جلب إعدادات الإشعارات للمستخدم
     * 
     * @param int $userId
     * @param string $type
     * @return array
     */
    private static function getUserNotificationSettings($userId, $type) {
        if (!self::$pdo) {
            return [
                'email_enabled' => NOTIFICATION_EMAIL_ENABLED,
                'sms_enabled' => NOTIFICATION_SMS_ENABLED,
                'push_enabled' => NOTIFICATION_PUSH_ENABLED
            ];
        }
        
        try {
            $stmt = self::$pdo->prepare("SELECT email_enabled, sms_enabled, push_enabled 
                    FROM user_notification_settings 
                    WHERE user_id = ? AND notification_type = ?");
            $stmt->execute([$userId, $type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                return $row;
            }
        } catch (PDOException $e) {
            // fallback to defaults
        }
        
        // الإعدادات الافتراضية
        return [
            'email_enabled' => NOTIFICATION_EMAIL_ENABLED,
            'sms_enabled' => NOTIFICATION_SMS_ENABLED,
            'push_enabled' => NOTIFICATION_PUSH_ENABLED
        ];
    }
    
    /**
     * تسجيل عملية إشعار
     * 
     * @param string $channel
     * @param mixed $recipient
     * @param string $type
     * @param string $status
     */
    private static function logNotification($channel, $recipient, $type, $status) {
        if (LOG_ENABLED) {
            $message = sprintf(
                "[%s] Notification %s via %s:  Recipient=%s, Type=%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $channel,
                $recipient,
                $type
            );
            
            error_log($message, 3, LOG_FILE_API);
        }
    }
    
    /**
     * تسجيل خطأ
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[Notification Error] " . $message, 3, LOG_FILE_ERROR);
        }
    }
    
    /**
     * إرسال إشعار جماعي
     * 
     * @param array $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @param array $channels
     * @return array
     */
    public static function sendBulk($userIds, $type, $title, $message, $data = [], $channels = ['database']) {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($userIds as $userId) {
            $result = self::send($userId, $type, $title, $message, $data, $channels);
            
            $results[] = [
                'user_id' => $userId,
                'result' => $result
            ];
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        return [
            'total' => count($userIds),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }
}

// ===========================================
// ✅ تم تحميل Notification Helper بنجاح
// ===========================================

?>