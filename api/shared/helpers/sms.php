<?php
// htdocs/api/helpers/sms.php
// ملف دوال إرسال الرسائل النصية SMS (SMS Helper)
// يدعم Unifonic, Twilio, Nexmo، مع تخزين السجلات في DB عبر PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// SMS Class
// ===========================================

class SMS {
    
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
    // 1️⃣ إرسال رسالة نصية (Send SMS)
    // ===========================================
    
    /**
     * إرسال رسالة نصية
     * 
     * @param string $phone رقم الجوال (مع كود الدولة)
     * @param string $message نص الرسالة
     * @param string $lang لغة الرسالة (ar, en, etc.)
     * @return array ['success' => bool, 'message' => string, 'message_id' => string]
     */
    public static function send($phone, $message, $lang = 'ar') {
        // التحقق من تفعيل SMS
        if (!SMS_ENABLED) {
            self::logSMS('disabled', $phone, $message);
            self::saveSMSLog($phone, $message, 'disabled', null, $lang);
            return [
                'success' => true,
                'message' => 'SMS disabled in config',
                'message_id' => null
            ];
        }
        
        // تنظيف رقم الجوال
        $phone = self::formatPhoneNumber($phone);
        
        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Invalid phone number format',
                'message_id' => null
            ];
        }
        
        try {
            // اختيار المزود حسب الإعدادات
            switch (SMS_PROVIDER) {
                case 'unifonic':
                    $result = self::sendWithUnifonicCURL($phone, $message);
                    break;
                    
                case 'twilio':
                    $result = self::sendWithTwilio($phone, $message);
                    break;
                    
                case 'nexmo':
                    $result = self::sendWithNexmo($phone, $message);
                    break;
                    
                default:
                    $result = [
                        'success' => false,
                        'message' => 'Invalid SMS provider:  ' . SMS_PROVIDER,
                        'message_id' => null
                    ];
            }
            
            // تخزين السجل في DB
            self::saveSMSLog($phone, $message, $result['success'] ? 'sent' : 'failed', $result['message_id'], $lang);
            
            return $result;
            
        } catch (Exception $e) {
            self::logError('SMS send failed: ' . $e->getMessage());
            self::saveSMSLog($phone, $message, 'error', null, $lang);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'message_id' => null
            ];
        }
    }
    
    // ===========================================
    // 2️⃣ إرسال عبر Unifonic (CURL)
    // ===========================================
    
    /**
     * إرسال SMS عبر Unifonic باستخدام CURL
     * 
     * @param string $phone
     * @param string $message
     * @return array
     */
    private static function sendWithUnifonicCURL($phone, $message) {
        $url = SMS_API_URL;
        
        $data = [
            'AppSid' => UNIFONIC_APP_SID,
            'SenderID' => SMS_SENDER_ID,
            'Recipient' => $phone,
            'Body' => $message
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            self::logError('Unifonic CURL Error: ' . $error);
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error,
                'message_id' => null
            ];
        }
        
        $result = json_decode($response, true);
        
        if (DEBUG_MODE) {
            self:: logSMS('unifonic_response', $phone, json_encode($result));
        }
        
        // التحقق من نجاح الإرسال
        if ($httpCode == 200 && isset($result['success']) && $result['success'] === true) {
            self::logSMS('sent', $phone, $message);
            
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $result['data']['MessageID'] ?? null
            ];
        } else {
            $errorMessage = $result['message'] ?? 'Unknown error';
            self::logError('Unifonic Error: ' . $errorMessage);
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'message_id' => null
            ];
        }
    }
    
    // ===========================================
    // 3️⃣ إرسال عبر Twilio
    // ===========================================
    
    /**
     * إرسال SMS عبر Twilio
     * 
     * @param string $phone
     * @param string $message
     * @return array
     */
    private static function sendWithTwilio($phone, $message) {
        // يتطلب Twilio SDK
        // composer require twilio/sdk
        
        if (!class_exists('Twilio\Rest\Client')) {
            return [
                'success' => false,
                'message' => 'Twilio SDK not installed',
                'message_id' => null
            ];
        }
        
        try {
            $accountSid = getenv('TWILIO_ACCOUNT_SID');
            $authToken = getenv('TWILIO_AUTH_TOKEN');
            $fromNumber = getenv('TWILIO_PHONE_NUMBER');
            
            $client = new Twilio\Rest\Client($accountSid, $authToken);
            
            $result = $client->messages->create(
                $phone,
                [
                    'from' => $fromNumber,
                    'body' => $message
                ]
            );
            
            self::logSMS('sent', $phone, $message);
            
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $result->sid
            ];
            
        } catch (Exception $e) {
            self::logError('Twilio Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'message_id' => null
            ];
        }
    }
    
    // ===========================================
    // 4️⃣ إرسال عبر Nexmo (Vonage)
    // ===========================================
    
    /**
     * إرسال SMS عبر Nexmo
     * 
     * @param string $phone
     * @param string $message
     * @return array
     */
    private static function sendWithNexmo($phone, $message) {
        $apiKey = getenv('NEXMO_API_KEY');
        $apiSecret = getenv('NEXMO_API_SECRET');
        $from = SMS_SENDER_ID;
        
        $url = 'https://rest.nexmo.com/sms/json';
        
        $data = [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'from' => $from,
            'to' => $phone,
            'text' => $message
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            self::logError('Nexmo CURL Error:  ' . $error);
            return [
                'success' => false,
                'message' => 'Connection error',
                'message_id' => null
            ];
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['messages'][0]['status']) && $result['messages'][0]['status'] == '0') {
            self::logSMS('sent', $phone, $message);
            
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $result['messages'][0]['message-id']
            ];
        } else {
            $errorMessage = $result['messages'][0]['error-text'] ?? 'Unknown error';
            self:: logError('Nexmo Error:  ' . $errorMessage);
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'message_id' => null
            ];
        }
    }
    
    // ===========================================
    // 🔧 دوال قاعدة البيانات (Database Functions)
    // ===========================================
    
    /**
     * حفظ سجل SMS في قاعدة البيانات
     * 
     * @param string $phone
     * @param string $message
     * @param string $status
     * @param string|null $messageId
     * @param string $lang
     * @return bool
     */
    private static function saveSMSLog($phone, $message, $status, $messageId = null, $lang = 'ar') {
        if (!self::$pdo) return false;
        
        try {
            $stmt = self::$pdo->prepare("INSERT INTO sms_logs (phone, message, status, message_id, language, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$phone, $message, $status, $messageId, $lang]);
            return true;
        } catch (PDOException $e) {
            self::logError('Failed to save SMS log: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===========================================
    // 5️⃣ إرسال OTP (رمز التحقق)
    // ===========================================
    
    /**
     * إرسال رمز تحقق OTP
     * 
     * @param string $phone
     * @param string $otp
     * @param string $lang اللغة (ar أو en)
     * @return array
     */
    public static function sendOTP($phone, $otp, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "رمز التحقق الخاص بك في " . APP_NAME . " هو: " . $otp;
            $message .= "\nلا تشارك هذا الرمز مع أي شخص. ";
        } else {
            $message = "Your verification code for " . APP_NAME . " is: " . $otp;
            $message .= "\nDo not share this code with anyone.";
        }
        
        return self::send($phone, $message, $lang);
    }
    
    // ===========================================
    // 6️⃣ إرسال إشعار طلب جديد
    // ===========================================
    
    /**
     * إرسال إشعار بطلب جديد
     * 
     * @param string $phone
     * @param string $orderNumber
     * @param float $total
     * @param string $lang
     * @return array
     */
    public static function sendOrderNotification($phone, $orderNumber, $total, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "تم استلام طلبك #" . $orderNumber;
            $message .= "\nالمبلغ:  " . $total . " " . DEFAULT_CURRENCY_SYMBOL;
            $message .= "\nشكراً لك - " . APP_NAME;
        } else {
            $message = "Your order #" . $orderNumber .  " has been received";
            $message .= "\nTotal: " . DEFAULT_CURRENCY_SYMBOL . $total;
            $message .= "\nThank you - " . APP_NAME;
        }
        
        return self:: send($phone, $message, $lang);
    }
    
    // ===========================================
    // 7️⃣ إرسال إشعار شحن الطلب
    // ===========================================
    
    /**
     * إرسال إشعار بشحن الطلب
     * 
     * @param string $phone
     * @param string $orderNumber
     * @param string $trackingNumber
     * @param string $lang
     * @return array
     */
    public static function sendShipmentNotification($phone, $orderNumber, $trackingNumber, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "تم شحن طلبك #" . $orderNumber;
            if ($trackingNumber) {
                $message .= "\nرقم التتبع: " . $trackingNumber;
            }
            $message .= "\n" . APP_NAME;
        } else {
            $message = "Your order #" .  $orderNumber . " has been shipped";
            if ($trackingNumber) {
                $message .= "\nTracking:  " . $trackingNumber;
            }
            $message .= "\n" . APP_NAME;
        }
        
        return self::send($phone, $message, $lang);
    }
    
    // ===========================================
    // 8️⃣ إرسال إشعار توصيل الطلب
    // ===========================================
    
    /**
     * إرسال إشعار بتوصيل الطلب
     * 
     * @param string $phone
     * @param string $orderNumber
     * @param string $lang
     * @return array
     */
    public static function sendDeliveryNotification($phone, $orderNumber, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "تم توصيل طلبك #" . $orderNumber .  " بنجاح";
            $message .= "\nنتمنى أن تكون راضياً عن خدمتنا";
            $message .= "\n" . APP_NAME;
        } else {
            $message = "Your order #" . $orderNumber . " has been delivered successfully";
            $message .= "\nWe hope you're satisfied with our service";
            $message .= "\n" . APP_NAME;
        }
        
        return self::send($phone, $message, $lang);
    }
    
    // ===========================================
    // 9️⃣ إرسال رسالة ترحيبية
    // ===========================================
    
    /**
     * إرسال رسالة ترحيبية للمستخدم الجديد
     * 
     * @param string $phone
     * @param string $name
     * @param string $lang
     * @return array
     */
    public static function sendWelcomeSMS($phone, $name, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "مرحباً " . $name . "! ";
            $message .= "\nشكراً لانضمامك إلى " . APP_NAME;
            $message .= "\nابدأ التسوق الآن:  " . APP_URL;
        } else {
            $message = "Welcome " . $name . "!";
            $message .= "\nThank you for joining " . APP_NAME;
            $message .= "\nStart shopping:  " . APP_URL;
        }
        
        return self:: send($phone, $message, $lang);
    }
    
    // ===========================================
    // 🔟 إرسال تذكير بسلة مهجورة
    // ===========================================
    
    /**
     * إرسال تذكير بسلة تسوق مهجورة
     * 
     * @param string $phone
     * @param int $itemsCount
     * @param string $lang
     * @return array
     */
    public static function sendAbandonedCartReminder($phone, $itemsCount, $lang = 'ar') {
        if ($lang === 'ar') {
            $message = "لديك " . $itemsCount .  " منتج في سلة التسوق";
            $message .= "\nأكمل طلبك الآن واحصل على خصم 10%";
            $message .= "\n" . APP_URL;
        } else {
            $message = "You have " .  $itemsCount . " item(s) in your cart";
            $message .= "\nComplete your order now and get 10% off";
            $message .= "\n" . APP_URL;
        }
        
        return self::send($phone, $message, $lang);
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * تنسيق رقم الجوال
     * 
     * @param string $phone
     * @return string|false
     */
    private static function formatPhoneNumber($phone) {
        // إزالة المسافات والرموز
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // دعم أرقام دولية عامة (للنظام العالمي)
        if (preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return $phone;
        }
        
        // إذا كان الرقم يبدأ بـ 05 (السعودية)
        if (preg_match('/^05\d{8}$/', $phone)) {
            return '+966' . substr($phone, 1);
        }
        // إذا كان يبدأ بـ 5 فقط
        elseif (preg_match('/^5\d{8}$/', $phone)) {
            return '+966' . $phone;
        }
        // إذا كان يبدأ بـ 9665
        elseif (preg_match('/^9665\d{8}$/', $phone)) {
            return '+' . $phone;
        }
        
        // دعم أرقام دول أخرى (مثال: مصر 01، الإمارات 05)
        // يمكن توسيع هذا حسب الحاجة
        
        return false;
    }
    
    /**
     * التحقق من صحة رقم الجوال السعودي
     * 
     * @param string $phone
     * @return bool
     */
    public static function isValidSaudiPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^(05|5)(5|0|3|6|4|9|1|8|7)([0-9]{7})$/', $phone) === 1;
    }
    
    /**
     * التحقق من صحة رقم جوال دولي
     * 
     * @param string $phone
     * @return bool
     */
    public static function isValidInternationalPhone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return preg_match('/^\+?[1-9]\d{1,14}$/', $phone) === 1;
    }
    
    /**
     * حساب عدد الرسائل المطلوبة (حسب الطول)
     * 
     * @param string $message
     * @return int
     */
    public static function calculateMessageCount($message) {
        $length = mb_strlen($message, 'UTF-8');
        
        // رسالة واحدة:  160 حرف (لاتيني) أو 70 حرف (عربي/يونيكود)
        $perMessage = 70; // نفترض يونيكود
        
        if ($length <= $perMessage) {
            return 1;
        }
        
        // الرسائل المتعددة: 67 حرف لكل رسالة
        $perMessage = 67;
        return ceil($length / $perMessage);
    }
    
    /**
     * حساب تكلفة الرسالة
     * 
     * @param string $message
     * @param float $pricePerMessage السعر لكل رسالة
     * @return float
     */
    public static function calculateCost($message, $pricePerMessage = 0.10) {
        $count = self::calculateMessageCount($message);
        return $count * $pricePerMessage;
    }
    
    /**
     * تسجيل عملية إرسال SMS
     * 
     * @param string $status
     * @param string $phone
     * @param string $message
     */
    private static function logSMS($status, $phone, $message) {
        if (LOG_ENABLED) {
            $logMessage = sprintf(
                "[%s] SMS %s: To=%s, Message=%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $phone,
                substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
            );
            
            error_log($logMessage, 3, LOG_FILE_API);
        }
    }
    
    /**
     * تسجيل خطأ
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[SMS Error] " . $message, 3, LOG_FILE_ERROR);
        }
        
        if (DEBUG_MODE) {
            error_log("[SMS Debug] " . $message);
        }
    }
    
    /**
     * إرسال SMS لعدة أرقام (Bulk SMS)
     * 
     * @param array $phones مصفوفة أرقام الجوالات
     * @param string $message
     * @param string $lang
     * @return array ['success_count' => int, 'fail_count' => int, 'results' => array]
     */
    public static function sendBulk($phones, $message, $lang = 'ar') {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($phones as $phone) {
            $result = self::send($phone, $message, $lang);
            $results[] = [
                'phone' => $phone,
                'result' => $result
            ];
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
            
            // تأخير صغير لتجنب Rate Limiting
            usleep(100000); // 0.1 ثانية
        }
        
        return [
            'total' => count($phones),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }
}

// ===========================================
// ✅ تم تحميل SMS Helper بنجاح
// ===========================================

?>